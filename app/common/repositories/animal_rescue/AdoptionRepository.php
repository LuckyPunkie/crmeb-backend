<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace app\common\repositories\animal_rescue;

use app\common\dao\animal_rescue\AdoptionApplicationDao;
use app\common\dao\animal_rescue\AdoptionDepositDao;
use app\common\dao\animal_rescue\AnimalRescueParticipantDao;
use app\common\dao\animal_rescue\AnimalRescuePostDao;
use app\common\model\animal_rescue\AdoptionApplication;
use app\common\repositories\BaseRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserRepository;
use think\facade\Db;
use think\facade\Log;

/**
 * 领养业务仓库
 * Class AdoptionRepository
 * @package app\common\repositories\animal_rescue
 */
class AdoptionRepository extends BaseRepository
{
    /**
     * @var AdoptionApplicationDao
     */
    protected $dao;

    /**
     * @param AdoptionApplicationDao $dao
     */
    public function __construct(AdoptionApplicationDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 提交领养申请
     * @param int $uid 申请人UID
     * @param int $postId 帖子ID
     * @param array $data 申请信息
     * @return int application_id
     */
    public function apply(int $uid, int $postId, array $data): int
    {
        // 检查帖子是否存在且为领养类型
        $postDao = app()->make(AnimalRescuePostDao::class);
        $post = $postDao->getWhere([
            'post_id' => $postId,
            'is_del' => 0,
            'status' => 1,
            'type' => 2, // 领养类型
        ]);
        if (!$post) {
            throw new \think\exception\ValidateException('该帖子不存在或不可领养');
        }
        // 禁止申请自己发布的领养帖子
        if ($post->uid == $uid) {
            throw new \think\exception\ValidateException('不能申请自己发布的领养');
        }
        // 检查是否已有进行中的申请
        $exist = $this->dao->getWhereCount([
            'uid' => $uid,
            'post_id' => $postId,
            'status' => [1, 2], // 审核中或审核通过
        ]);
        if ($exist > 0) {
            throw new \think\exception\ValidateException('您已提交过领养申请，请勿重复申请');
        }
        $data['uid'] = $uid;
        $data['post_id'] = $postId;
        $data['status'] = 1; // 审核中
        unset($data['agreed']);
        $applicationId = (int)$this->dao->create($data)->application_id;

        $title = AnimalRescueNotify::postTitle($post);
        AnimalRescueNotify::send(
            (int)$post->uid,
            '收到新的领养申请',
            '「' . $title . '」有新的领养申请，请尽快审核。',
            (int)$postId,
            2,
            '/pages/animal_rescue/applications/index?post_id=' . $postId
        );

        return $applicationId;
    }

    /**
     * 缴纳保证金下单
     * @param int $uid 领养人UID
     * @param int $applicationId 申请ID
     * @param array $data 支付参数
     * @return array [order_id, deposit_id]
     */
    public function payDeposit(int $uid, int $applicationId, array $data): array
    {
        $application = $this->dao->get($applicationId);
        if (!$application || $application->uid != $uid) {
            throw new \think\exception\ValidateException('申请不存在');
        }
        if ($application->status != 2) { // 非审核通过状态
            throw new \think\exception\ValidateException('请等待审核通过后再缴纳保证金');
        }
        // 获取帖子保证金信息
        $post = app()->make(AnimalRescuePostDao::class)->get($application->post_id);
        if (!$post) {
            throw new \think\exception\ValidateException('帖子不存在');
        }
        $depositDao = app()->make(AdoptionDepositDao::class);
        // 复用未实际支付的保证金订单，避免重复创建导致列表误判已缴纳
        $existUnpaid = \app\common\model\animal_rescue\AdoptionDeposit::getDB()
            ->where('application_id', $applicationId)
            ->where('uid', $uid)
            ->whereNull('pay_time')
            ->order('deposit_id', 'desc')
            ->find();
        if ($existUnpaid) {
            return [
                'deposit_id' => $existUnpaid['deposit_id'] ?? $existUnpaid->deposit_id,
                'order_sn' => $existUnpaid['order_sn'] ?? $existUnpaid->order_sn,
                'amount' => $existUnpaid['amount'] ?? $existUnpaid->amount,
                'thaw_time' => $existUnpaid['thaw_time'] ?? $existUnpaid->thaw_time,
            ];
        }
        // 生成唯一订单号
        $orderSn = 'AD' . date('YmdHis') . rand(1000, 9999);
        // 计算解冻时间
        $thawMonths = $post->deposit_thaw_months ?: 6;
        $thawTime = date('Y-m-d H:i:s', strtotime("+{$thawMonths} months"));
        // 写入保证金记录
        $deposit = $depositDao->create([
            'uid' => $uid,
            'application_id' => $applicationId,
            'post_id' => $application->post_id,
            'amount' => $post->deposit_amount,
            'thaw_months' => $thawMonths,
            'thaw_time' => $thawTime,
            'order_sn' => $orderSn,
            'status' => 1, // 冻结中（真正支付成功以 pay_time 为准）
        ]);
        return [
            'deposit_id' => $deposit->deposit_id,
            'order_sn' => $orderSn,
            'amount' => $post->deposit_amount,
            'thaw_time' => $thawTime,
        ];
    }

    /**
     * 保证金自动解冻（定时任务调用）
     * @return int 解冻数量
     */
    public function autoThawDeposits(): int
    {
        $depositDao = app()->make(AdoptionDepositDao::class);
        $deposits = $depositDao->getThawList();
        $count = 0;
        foreach ($deposits as $deposit) {
            try {
                Db::transaction(function () use ($deposit, $depositDao) {
                    // 退款到用户钱包
                    $userRepo = app()->make(UserRepository::class);
                    $user = $userRepo->get($deposit->uid);
                    if ($user) {
                        $user->now_money = bcadd($user->now_money, $deposit->amount, 2);
                        $user->save();
                    }
                    // 写入账单流水
                    $billRepo = app()->make(UserBillRepository::class);
                    $billRepo->incBill(
                        $deposit->uid,
                        'now_money',
                        'adoption_deposit_refund',
                        [
                            'number' => $deposit->amount,
                            'title' => '领养保证金返还',
                            'link_id' => $deposit->deposit_id,
                            'balance' => $user->now_money ?? 0,
                        ]
                    );
                    // 更新保证金状态
                    $depositDao->update($deposit->deposit_id, ['status' => 2]); // 已解冻
                    // 更新参与记录状态
                    $participant = app()->make(AnimalRescueParticipantDao::class)->getWhere(
                        ['uid' => $deposit->uid, 'post_id' => $deposit->post_id, 'type' => 2]
                    );
                    if ($participant) {
                        app()->make(AnimalRescueParticipantDao::class)->update(
                            $participant->participant_id, ['status' => 3]
                        );
                    }
                    // 更新领养申请状态
                    app()->make(AdoptionApplicationDao::class)->update(
                        $deposit->application_id,
                        ['status' => 4] // 已完成
                    );
                });

                $post = app()->make(AnimalRescuePostDao::class)->get($deposit->post_id);
                $title = AnimalRescueNotify::postTitle($post);
                AnimalRescueNotify::send(
                    (int)$deposit->uid,
                    '领养保证金已返还',
                    '「' . $title . '」领养保证金 ¥' . $deposit->amount . ' 已解冻并返还至平台钱包，可在钱包中查看。',
                    (int)$deposit->post_id,
                    2,
                    '/pages/animal_rescue/my_records/index'
                );
                $count++;
            } catch (\Exception $e) {
                // 单条失败不影响其他记录处理
                \think\facade\Log::error('保证金解冻失败: ' . $e->getMessage() . ' deposit_id=' . $deposit->deposit_id);
            }
        }
        return $count;
    }

    /**
     * 保证金即将到期提醒（默认 7 天内）
     */
    public function remindDepositExpiring(int $days = 7): int
    {
        $depositDao = app()->make(AdoptionDepositDao::class);
        $list = $depositDao->getExpiringSoonList($days);
        $count = 0;
        foreach ($list as $deposit) {
            $cacheKey = 'animal_rescue:deposit_remind:' . $deposit->deposit_id;
            if (\think\facade\Cache::get($cacheKey)) {
                continue;
            }
            $post = app()->make(AnimalRescuePostDao::class)->get($deposit->post_id);
            $title = AnimalRescueNotify::postTitle($post);
            $thawTime = (string)$deposit->thaw_time;
            AnimalRescueNotify::send(
                (int)$deposit->uid,
                '领养保证金即将到期',
                '「' . $title . '」领养保证金 ¥' . $deposit->amount . ' 将于 ' . $thawTime . ' 解冻返还，请留意钱包到账。',
                (int)$deposit->post_id,
                2,
                '/pages/animal_rescue/my_records/index'
            );
            \think\facade\Cache::set($cacheKey, 1, 86400 * max(8, $days + 1));
            $count++;
        }
        return $count;
    }

    /**
     * 后台领养申请列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getAdminAdoptionList(array $where, int $page, int $limit): array
    {
        $query = $this->dao->search($where)->with([
            'user' => function ($query) {
                $query->field('uid,nickname,phone');
            },
            'post' => function ($query) {
                $query->field('post_id,title,type,deposit_amount,deposit_thaw_months');
            },
            'deposit' => function ($query) {
                $query->field('deposit_id,application_id,amount,status,thaw_time,pay_time,order_sn');
            },
        ]);
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
    }

    /**
     * 后台审核领养申请
     * @param int $id
     * @param int $status
     * @param string $remark
     */
    public function auditAdoption(int $id, int $status, string $remark = ''): void
    {
        $application = $this->dao->get($id);
        $this->dao->update($id, [
            'status' => $status,
            'remark' => $remark,
        ]);
        if ($application) {
            $this->notifyApplicantAuditResult($application, (int)$status, $remark);
        }
    }

    /**
     * 保证金支付成功回调
     * 入账：保证金冻结 + 参与记录 + 申请已领养 + 平台财务流水（托管至解冻返还）
     * @param string $orderSn AD前缀订单号
     * @param string $payType
     */
    public function depositPaySuccess(string $orderSn, string $payType = 'mock'): void
    {
        $depositDao = app()->make(AdoptionDepositDao::class);
        $deposit = $depositDao->getWhere(['order_sn' => $orderSn]);
        if (!$deposit) return;

        // 防止重复处理
        $participantDao = app()->make(AnimalRescueParticipantDao::class);
        $exist = $participantDao->getWhereCount([
            'uid' => $deposit->uid,
            'post_id' => $deposit->post_id,
            'type' => 2,
            'order_id' => $deposit->deposit_id,
        ]);
        if ($exist > 0) return;

        Db::transaction(function () use ($deposit, $depositDao, $participantDao, $payType) {
            $payType = $payType ?: 'mock';
            $txId = ($payType === 'mock' ? 'MOCK' : 'PAY') . date('YmdHis') . $deposit->deposit_id;
            // 冻结中保持 status=1（autoThawDeposits 依赖此状态），pay_time 标记已支付
            $depositDao->update($deposit->deposit_id, [
                'status' => 1,
                'order_id' => $deposit->deposit_id,
                'pay_type' => $payType,
                'transaction_id' => $txId,
                'pay_time' => date('Y-m-d H:i:s'),
            ]);
            // 创建参与记录（状态=2 进行中，解冻后更新为3）
            $participantDao->create([
                'uid' => $deposit->uid,
                'post_id' => $deposit->post_id,
                'type' => 2,
                'amount' => $deposit->amount,
                'order_id' => $deposit->deposit_id,
                'status' => 2,
            ]);
            // 更新领养申请状态为 3=已领养
            $applicationDao = app()->make(AdoptionApplicationDao::class);
            $applicationDao->update($deposit->application_id, ['status' => 3]);

            // 帖子标记为已完成，首页展示「已领养」
            app()->make(AnimalRescuePostDao::class)->update((int)$deposit->post_id, [
                'status' => \app\common\model\animal_rescue\AnimalRescuePost::STATUS_COMPLETED,
                'status_time' => date('Y-m-d H:i:s'),
            ]);

            // 平台托管流水
            $payTypeIndex = array_search($payType, \app\common\repositories\store\order\StoreOrderRepository::PAY_TYPE, true);
            if ($payTypeIndex === false) $payTypeIndex = 10;
            $userInfo = '';
            try {
                $userInfo = (string)(\app\common\model\user\User::getDB()->where('uid', (int)$deposit->uid)->value('nickname') ?: '');
            } catch (\Throwable $e) {}
            app()->make(\app\common\dao\system\merchant\FinancialRecordDao::class)->inc([
                'order_id' => (int)$deposit->deposit_id,
                'order_sn' => (string)$deposit->order_sn,
                'user_info' => $userInfo ?: ('uid:' . (int)$deposit->uid),
                'user_id' => (int)$deposit->uid,
                'financial_type' => 'animal_rescue_deposit',
                'type' => 2,
                'number' => (float)$deposit->amount,
                'pay_type' => (int)$payTypeIndex,
            ], 0);

            Log::info('animal_rescue depositPaySuccess: application_id=' . $deposit->application_id
                . ' amount=' . $deposit->amount . ' pay_type=' . $payType);
        });
    }

    /**
     * 筹款到期检查（定时任务调用）
     * 将到期的救助/云养帖子状态更新为已完成
     * @return int 更新数量
     */
    public function checkExpiredPosts(): int
    {
        $postDao = app()->make(AnimalRescuePostDao::class);
        $posts = $postDao->getModel()::getDB()
            ->whereIn('type', [1, 3])
            ->where('status', 1)
            ->where('end_time', '<=', date('Y-m-d H:i:s'))
            ->select();
        $count = 0;
        foreach ($posts as $post) {
            $postDao->update($post->post_id, [
                'status' => 2, // 已完成
                'status_time' => date('Y-m-d H:i:s'),
            ]);
            $title = AnimalRescueNotify::postTitle($post);
            AnimalRescueNotify::send(
                (int)$post->uid,
                '筹款已到期',
                '「' . $title . '」筹款已到期并结束，可在「我的发布」查看详情。',
                (int)$post->post_id,
                AnimalRescueNotify::postType($post)
            );
            $count++;
        }
        return $count;
    }

    /**
     * 发布者查看自己帖子下的领养申请列表
     * @param int $postId 帖子ID
     * @param int $publisherUid 发布者UID
     * @param array $where 筛选条件
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getPostApplications(int $postId, int $publisherUid, array $where, int $page, int $limit): array
    {
        // 验证帖子归属
        $post = app()->make(AnimalRescuePostDao::class)->get($postId);
        if (!$post || $post->uid != $publisherUid) {
            throw new \think\exception\ValidateException('帖子不存在或无权查看');
        }
        $where['post_id'] = $postId;
        $query = $this->dao->search($where)->with([
            'user' => function ($query) {
                $query->field('uid,nickname,phone,avatar');
            },
            'deposit' => function ($query) {
                $query->field('deposit_id,application_id,amount,status,thaw_time');
            },
        ])->order('create_time', 'desc');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
    }

    /**
     * 查看申请详情（发布者或申请人可查看）
     * @param int $applicationId
     * @param int $uid
     * @return object
     */
    public function getApplicationDetail(int $applicationId, int $uid): object
    {
        $application = AdoptionApplication::getInstance()->with([
            'user' => function ($query) {
                $query->field('uid,nickname,phone,avatar');
            },
            'post' => function ($query) {
                $query->field('post_id,title,animal_name,animal_type,deposit_amount,deposit_thaw_months,images,uid');
            },
            'deposit' => function ($query) {
                $query->field('deposit_id,application_id,amount,status,thaw_time,order_sn');
            },
        ])->find($applicationId);

        if (!$application) {
            throw new \think\exception\ValidateException('申请不存在');
        }
        // 权限验证：申请人和帖子发布者均可查看
        $post = $application->post;
        if (!$post) {
            $post = app()->make(AnimalRescuePostDao::class)->get($application->post_id);
        }
        if ($application->uid != $uid && (!$post || $post->uid != $uid)) {
            throw new \think\exception\ValidateException('无权查看此申请');
        }
        return $application;
    }

    /**
     * 发布者审核领养申请
     * @param int $applicationId 申请ID
     * @param int $publisherUid 发布者UID
     * @param int $status 审核结果：2=通过, -1=拒绝
     * @param string $remark 审核备注
     */
    public function auditByPublisher(int $applicationId, int $publisherUid, int $status, string $remark = ''): void
    {
        $application = $this->dao->get($applicationId);
        if (!$application) {
            throw new \think\exception\ValidateException('申请不存在');
        }
        if ($application->status != 1) {
            throw new \think\exception\ValidateException('该申请已审核，不可重复操作');
        }
        // 验证帖子归属
        $post = app()->make(AnimalRescuePostDao::class)->get($application->post_id);
        if (!$post || $post->uid != $publisherUid) {
            throw new \think\exception\ValidateException('无权审核此申请');
        }
        // 检查帖子是否已删除
        if ($post->is_del == 1) {
            throw new \think\exception\ValidateException('帖子已删除，无法审核');
        }
        // 禁止审核自己的申请
        if ($application->uid == $publisherUid) {
            throw new \think\exception\ValidateException('不能审核自己的申请');
        }
        if ($status == -1 && empty($remark)) {
            throw new \think\exception\ValidateException('拒绝申请请填写原因');
        }

        Db::transaction(function () use ($applicationId, $status, $remark) {
            $this->dao->update($applicationId, [
                'status' => $status,
                'remark' => $remark,
            ]);
        });

        $this->notifyApplicantAuditResult($application, (int)$status, $remark);

        Log::info('animal_rescue auditByPublisher: application_id=' . $applicationId . ' status=' . $status . ' publisher_uid=' . $publisherUid);
    }

    /**
     * 通知申请人领养审核结果
     */
    protected function notifyApplicantAuditResult($application, int $status, string $remark = ''): void
    {
        $post = app()->make(AnimalRescuePostDao::class)->get($application->post_id);
        $title = AnimalRescueNotify::postTitle($post);
        $uid = (int)$application->uid;
        if ($status === 2) {
            AnimalRescueNotify::send(
                $uid,
                '领养申请已通过',
                '「' . $title . '」领养申请已通过，请尽快缴纳保证金完成领养。',
                (int)$application->post_id,
                2,
                '/pages/animal_rescue/pay_deposit/index?application_id=' . (int)$application->application_id
            );
        } elseif ($status === -1) {
            $reason = $remark !== '' ? ('原因：' . mb_substr($remark, 0, 80)) : '请查看详情';
            AnimalRescueNotify::send(
                $uid,
                '领养申请未通过',
                '「' . $title . '」领养申请未通过。' . $reason,
                (int)$application->post_id,
                2,
                '/pages/animal_rescue/my_records/index'
            );
        }
    }

    /**
     * 后台保证金托管列表
     */
    public function getAdminDepositList(array $where, int $page, int $limit): array
    {
        $depositDao = app()->make(AdoptionDepositDao::class);
        $query = $depositDao->search($where)->with([
            'user' => function ($query) {
                $query->field('uid,nickname,phone,avatar');
            },
            'post' => function ($query) {
                $query->field('post_id,title,animal_name,uid,deposit_amount,deposit_thaw_months');
            },
        ]);

        // 仅已支付（有支付时间）；传 paid=0 可看未支付下单
        if (!isset($where['paid']) || $where['paid'] === '' || $where['paid'] === null) {
            $query->whereNotNull('pay_time')->where('pay_time', '<>', '');
        } elseif ((int)$where['paid'] === 1) {
            $query->whereNotNull('pay_time')->where('pay_time', '<>', '');
        } elseif ((int)$where['paid'] === 0) {
            $query->where(function ($q) {
                $q->whereNull('pay_time')->whereOr('pay_time', '');
            });
        }

        $count = (clone $query)->count();
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
    }

    /**
     * 保证金汇总
     */
    public function getDepositStatistics(): array
    {
        $model = app()->make(AdoptionDepositDao::class)->getModel();
        $base = function () use ($model) {
            return $model::getDB()->whereNotNull('pay_time')->where('pay_time', '<>', '');
        };
        return [
            'frozen_amount' => (float)($base()->where('status', 1)->sum('amount') ?: 0),
            'frozen_count' => (int)$base()->where('status', 1)->count(),
            'thawed_amount' => (float)($base()->where('status', 2)->sum('amount') ?: 0),
            'thawed_count' => (int)$base()->where('status', 2)->count(),
            'deducted_amount' => (float)($base()->where('status', 3)->sum('amount') ?: 0),
            'deducted_count' => (int)$base()->where('status', 3)->count(),
        ];
    }

    /**
     * 申请人查看自己的领养申请列表
     * @param int $applicantUid 申请人UID
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getMyApplications(int $applicantUid, array $where, int $page, int $limit): array
    {
        $where['uid'] = $applicantUid;
        $query = $this->dao->search($where)->with([
            'post' => function ($query) {
                $query->field('post_id,title,animal_name,animal_type,images,deposit_amount');
            },
            'deposit' => function ($query) {
                $query->field('deposit_id,application_id,amount,status,thaw_time,pay_time');
            },
        ])->order('create_time', 'desc');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
    }
}
