<?php

namespace app\common\repositories\animal_rescue;

use app\common\dao\animal_rescue\AnimalRescueOrderDao;
use app\common\dao\animal_rescue\AnimalRescueParticipantDao;
use app\common\dao\animal_rescue\AnimalRescuePostDao;
use app\common\dao\animal_rescue\PostFundAuditDao;
use app\common\dao\animal_rescue\SettlementRecordDao;
use app\common\dao\animal_rescue\CloudAdoptionOrderDao;
use app\common\model\animal_rescue\AnimalRescuePost;
use app\common\model\animal_rescue\PostFundAudit;
use app\common\model\animal_rescue\SettlementRecord;
use app\common\model\store\service\StoreService;
use app\common\model\system\merchant\Merchant;
use app\common\model\system\merchant\MerchantType;
use app\common\model\user\User;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\user\UserBillRepository;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;

/**
 * v2.1 拨款审核 + 救助站辅助 + 月捐结算
 */
class FundAuditRepository extends BaseRepository
{
    /** fund_status */
    const FUND_NONE = 0;
    const FUND_RAISING = 1;
    const FUND_WAIT_VOUCHER = 2;
    const FUND_AUDITING = 3;
    const FUND_WAIT_PAY = 4;
    const FUND_PAID = 5;
    const FUND_REFUNDED = 6;
    const FUND_REJECTED = -1;

    protected $dao;

    public function __construct(PostFundAuditDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取救助站店铺类型 ID
     */
    public function getShelterTypeId(): int
    {
        $id = (int)MerchantType::getDB()
            ->where(function ($q) {
                $q->where('type_name', '救助站')->whereOr('mark', 'shelter');
            })
            ->value('mer_type_id');
        return $id;
    }

    /**
     * 商户是否为认证救助站
     */
    public function isShelterMerchant(int $merId): bool
    {
        if ($merId <= 0) return false;
        $mer = Merchant::getDB()->where('mer_id', $merId)->field('mer_id,type_id,shelter_status')->find();
        if (!$mer) return false;
        if ((int)$mer['shelter_status'] === 1) return true;
        $typeId = $this->getShelterTypeId();
        return $typeId > 0 && (int)$mer['type_id'] === $typeId;
    }

    /**
     * 根据 C 端 uid 解析所属救助站 mer_id（客服员工绑定 / 手机号匹配）
     */
    public function resolveShelterMerIdByUid(int $uid): int
    {
        if ($uid <= 0) return 0;
        $serviceMerId = (int)StoreService::getDB()
            ->where('uid', $uid)
            ->where('mer_id', '>', 0)
            ->where('is_open', 1)
            ->order('service_id DESC')
            ->value('mer_id');
        if ($serviceMerId > 0 && $this->isShelterMerchant($serviceMerId)) {
            return $serviceMerId;
        }
        $phone = (string)User::getDB()->where('uid', $uid)->value('phone');
        if ($phone === '') return 0;
        $merId = (int)Merchant::getDB()
            ->where('mer_phone', $phone)
            ->where(function ($q) {
                $q->where('shelter_status', 1);
                $typeId = $this->getShelterTypeId();
                if ($typeId > 0) {
                    $q->whereOr('type_id', $typeId);
                }
            })
            ->value('mer_id');
        return $merId > 0 && $this->isShelterMerchant($merId) ? $merId : 0;
    }

    /**
     * 商户改为救助站时同步冗余字段（后台改类型后调用）
     */
    public function syncMerchantShelterFlag(int $merId, int $typeId): void
    {
        $shelterTypeId = $this->getShelterTypeId();
        $isShelter = $shelterTypeId > 0 && $typeId === $shelterTypeId;
        $update = [
            'shelter_status' => $isShelter ? 1 : 0,
        ];
        if ($isShelter) {
            $update['shelter_certified_at'] = date('Y-m-d H:i:s');
        }
        Merchant::getDB()->where('mer_id', $merId)->update($update);
        // 改为救助站时，尽量把店主 C 端账号绑上 mer_id，便于「我的」展示认证标识
        if ($isShelter) {
            $mer = Merchant::getDB()->where('mer_id', $merId)->field('mer_id,mer_phone')->find();
            $phone = (string)($mer['mer_phone'] ?? '');
            if ($phone !== '') {
                \think\facade\Db::execute(
                    'UPDATE `eb_user` SET `mer_id`=? WHERE `phone`=? AND (`mer_id` IS NULL OR `mer_id`=0 OR `mer_id`=?)',
                    [$merId, $phone, $merId]
                );
                $uid = (int)\think\facade\Db::name('user')->where('phone', $phone)->value('uid');
                if ($uid > 0) {
                    app()->make(\app\common\repositories\circle\CircleAgentProvisionRepository::class)
                        ->ensureUserMerchantLink($uid, $merId);
                }
            }
        }
    }

    /**
     * 组装认证救助站展示信息
     */
    public function buildShelterInfo(int $merId): ?array
    {
        if ($merId <= 0 || !$this->isShelterMerchant($merId)) {
            return null;
        }
        $mer = Merchant::getDB()->where('mer_id', $merId)
            ->field('mer_id,mer_name,mer_avatar,mer_info,shelter_status,shelter_certified_at')
            ->find();
        if (!$mer) return null;
        return [
            'mer_id' => (int)$mer['mer_id'],
            'mer_name' => $mer['mer_name'],
            'mer_avatar' => $mer['mer_avatar'] ?? '',
            'mer_info' => $mer['mer_info'] ?? '',
            'is_certified_shelter' => true,
            'shelter_certified_at' => $mer['shelter_certified_at'],
        ];
    }

    /**
     * 捐款满额后切到待提交凭证
     */
    public function maybeMarkWaitVoucher(int $postId): void
    {
        $post = app()->make(AnimalRescuePostDao::class)->get($postId);
        if (!$post) return;
        if ((int)$post['type'] !== AnimalRescuePost::TYPE_RESCUE) return;
        if ((int)$post['status'] !== AnimalRescuePost::STATUS_ACTIVE) return;
        $fundStatus = (int)($post['fund_status'] ?? 0);
        if ($fundStatus !== self::FUND_RAISING && $fundStatus !== self::FUND_NONE) return;
        $raised = (float)$post['raised_amount'];
        $target = (float)$post['target_amount'];
        if ($target <= 0 || $raised < $target) return;

        app()->make(AnimalRescuePostDao::class)->update($postId, [
            'fund_status' => self::FUND_WAIT_VOUCHER,
            'status_time' => date('Y-m-d H:i:s'),
        ]);
        Log::info('animal_rescue fund wait voucher: post_id=' . $postId);

        $title = AnimalRescueNotify::postTitle($post);
        AnimalRescueNotify::send(
            (int)$post['uid'],
            '捐款已满额，请提交拨款凭证',
            '「' . $title . '」已达到目标金额，请尽快提交费用清单与票据，审核通过后拨款。',
            $postId,
            1,
            '/pages/animal_rescue/fund_audit/index?post_id=' . $postId
        );
    }

    /**
     * 发布人提交/更新凭证
     */
    public function submitVoucher(int $postId, int $uid, array $data): int
    {
        $postDao = app()->make(AnimalRescuePostDao::class);
        $post = $postDao->get($postId);
        if (!$post || (int)$post['is_del'] === 1) {
            throw new ValidateException('帖子不存在');
        }
        if ((int)$post['uid'] !== $uid) {
            throw new ValidateException('仅发布人可提交凭证');
        }
        if ((int)$post['type'] !== AnimalRescuePost::TYPE_RESCUE) {
            throw new ValidateException('仅救助帖子需要提交拨款凭证');
        }
        $fundStatus = (int)$post['fund_status'];
        if (!in_array($fundStatus, [self::FUND_WAIT_VOUCHER, self::FUND_REJECTED], true)) {
            throw new ValidateException('当前状态不可提交凭证');
        }
        $costList = trim((string)($data['cost_list'] ?? ''));
        $invoiceImages = $data['invoice_images'] ?? [];
        if ($costList === '') {
            throw new ValidateException('请填写费用清单');
        }
        if (!is_array($invoiceImages) || count($invoiceImages) < 1) {
            throw new ValidateException('请上传医疗票据');
        }
        if (count($invoiceImages) > 9) {
            throw new ValidateException('票据最多上传9张');
        }
        $otherFiles = $data['other_files'] ?? [];
        if (!is_array($otherFiles)) $otherFiles = [];
        if (count($otherFiles) > 9) {
            throw new ValidateException('其他材料最多9张');
        }

        return Db::transaction(function () use ($post, $postDao, $uid, $costList, $invoiceImages, $otherFiles, $data, $fundStatus) {
            $payload = [
                'post_id' => (int)$post['post_id'],
                'uid' => $uid,
                'submitted_at' => date('Y-m-d H:i:s'),
                'cost_list' => $costList,
                'invoice_images' => $invoiceImages,
                'other_files' => $otherFiles,
                'remark' => mb_substr((string)($data['remark'] ?? ''), 0, 200),
                'status' => PostFundAudit::STATUS_PENDING,
                'reject_reason' => '',
                'auditor' => 0,
                'audited_at' => null,
                'actual_amount' => 0,
                'refund_amount' => 0,
            ];
            $auditId = (int)$post['audit_id'];
            if ($auditId > 0) {
                $this->dao->update($auditId, $payload);
            } else {
                $auditId = (int)$this->dao->create($payload)->audit_id;
            }
            $postDao->update((int)$post['post_id'], [
                'fund_status' => self::FUND_AUDITING,
                'audit_id' => $auditId,
                'status_time' => date('Y-m-d H:i:s'),
            ]);
            return $auditId;
        });
    }

    /**
     * 审核前编辑凭证（status=2 待提交时可保存不进入审核）
     */
    public function saveVoucherDraft(int $postId, int $uid, array $data): int
    {
        $postDao = app()->make(AnimalRescuePostDao::class);
        $post = $postDao->get($postId);
        if (!$post || (int)$post['uid'] !== $uid) {
            throw new ValidateException('无权操作');
        }
        if ((int)$post['fund_status'] !== self::FUND_WAIT_VOUCHER) {
            throw new ValidateException('当前状态不可编辑凭证');
        }
        $payload = [
            'post_id' => (int)$post['post_id'],
            'uid' => $uid,
            'cost_list' => (string)($data['cost_list'] ?? ''),
            'invoice_images' => $data['invoice_images'] ?? [],
            'other_files' => $data['other_files'] ?? [],
            'remark' => mb_substr((string)($data['remark'] ?? ''), 0, 200),
            'status' => PostFundAudit::STATUS_PENDING,
        ];
        $auditId = (int)$post['audit_id'];
        if ($auditId > 0) {
            $this->dao->update($auditId, $payload);
            return $auditId;
        }
        $auditId = (int)$this->dao->create($payload)->audit_id;
        $postDao->update((int)$post['post_id'], ['audit_id' => $auditId]);
        return $auditId;
    }

    /**
     * 后台拨款审核列表
     */
    public function getAdminAuditList(array $where, int $page, int $limit): array
    {
        $query = $this->dao->search($where)->with([
            'post' => function ($q) {
                $q->field('post_id,title,type,target_amount,raised_amount,uid,fund_status,mer_id');
            },
            'author' => function ($q) {
                $q->field('uid,nickname,phone');
            },
        ]);

        // 待审核仅展示「审核中」帖子，避免退回待提交后仍出现在待审列表
        if (isset($where['status']) && $where['status'] !== '' && (int)$where['status'] === PostFundAudit::STATUS_PENDING) {
            $query->whereIn('post_id', function ($q) {
                $q->name('animal_rescue_post')
                    ->where('fund_status', self::FUND_AUDITING)
                    ->field('post_id');
            });
        }

        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
    }

    public function getAuditDetail(int $auditId)
    {
        $detail = PostFundAudit::getDB()->where('audit_id', $auditId)->with([
            'post' => function ($q) {
                $q->field('post_id,title,content,type,target_amount,raised_amount,uid,fund_status,mer_id,images');
            },
            'author' => function ($q) {
                $q->field('uid,nickname,phone,avatar');
            },
        ])->find();
        if (!$detail) {
            return null;
        }
        $arr = $detail->toArray();
        $merId = (int)($arr['post']['mer_id'] ?? 0);
        $arr['shelter'] = $this->buildShelterInfo($merId);
        return $arr;
    }

    /**
     * 平台审核通过并拨款
     */
    public function approveAndAllocate(int $auditId, float $actualAmount, int $adminId): void
    {
        if ($actualAmount < 0) {
            throw new ValidateException('实际消费金额不能为负');
        }
        Db::transaction(function () use ($auditId, $actualAmount, $adminId) {
            $audit = PostFundAudit::getDB()->where('audit_id', $auditId)->lock(true)->find();
            if (!$audit || (int)$audit['status'] !== PostFundAudit::STATUS_PENDING) {
                throw new ValidateException('审核记录不存在或已处理');
            }
            $postDao = app()->make(AnimalRescuePostDao::class);
            $post = $postDao->get((int)$audit['post_id']);
            if (!$post || (int)$post['fund_status'] !== self::FUND_AUDITING) {
                throw new ValidateException('帖子拨款状态异常');
            }
            $total = (float)$post['raised_amount'];
            if ($actualAmount - $total > 0.00001) {
                throw new ValidateException('实际消费金额不能超过捐款总额');
            }
            $refundTotal = round($total - $actualAmount, 2);
            if ($actualAmount + $refundTotal - $total > 0.00001) {
                throw new ValidateException('实际消费金额与退款金额之和不能超过捐款总金额，请重新输入。');
            }

            // 拨给发布人用户钱包
            if ($actualAmount > 0) {
                $this->creditUserWallet((int)$post['uid'], $actualAmount, 'animal_rescue_fund', '救助拨款', (int)$post['post_id']);
            }

            $this->dao->update($auditId, [
                'status' => PostFundAudit::STATUS_PASSED,
                'actual_amount' => $actualAmount,
                'refund_amount' => $refundTotal,
                'auditor' => $adminId,
                'audited_at' => date('Y-m-d H:i:s'),
            ]);

            $postDao->update((int)$post['post_id'], [
                'fund_status' => self::FUND_PAID,
                'status_time' => date('Y-m-d H:i:s'),
            ]);

            if ($refundTotal > 0) {
                $this->refundDonorsProportionally((int)$post['post_id'], $total, $refundTotal);
                $postDao->update((int)$post['post_id'], [
                    'fund_status' => self::FUND_REFUNDED,
                    'status_time' => date('Y-m-d H:i:s'),
                ]);
            }
        });

        // 事务外通知（失败不影响拨款结果）
        try {
            $post = app()->make(AnimalRescuePostDao::class)->get(
                (int)PostFundAudit::getDB()->where('audit_id', $auditId)->value('post_id')
            );
            if ($post) {
                $title = AnimalRescueNotify::postTitle($post);
                $msg = '「' . $title . '」拨款审核已通过，实际消费 ¥' . number_format($actualAmount, 2, '.', '')
                    . ' 已拨入您的平台钱包。';
                AnimalRescueNotify::send(
                    (int)$post['uid'],
                    '拨款审核通过',
                    $msg,
                    (int)$post['post_id'],
                    1
                );
            }
        } catch (\Throwable $e) {
            Log::error('animal_rescue approve notify fail: ' . $e->getMessage());
        }
    }

    /**
     * 审核拒绝
     */
    public function rejectAudit(int $auditId, string $reason, int $adminId): void
    {
        $reason = mb_substr(trim($reason), 0, 200);
        if ($reason === '') {
            throw new ValidateException('请填写拒绝原因');
        }
        Db::transaction(function () use ($auditId, $reason, $adminId) {
            $audit = PostFundAudit::getDB()->where('audit_id', $auditId)->lock(true)->find();
            if (!$audit || (int)$audit['status'] !== PostFundAudit::STATUS_PENDING) {
                throw new ValidateException('审核记录不存在或已处理');
            }
            $this->dao->update($auditId, [
                'status' => PostFundAudit::STATUS_REJECTED,
                'reject_reason' => $reason,
                'auditor' => $adminId,
                'audited_at' => date('Y-m-d H:i:s'),
            ]);
            app()->make(AnimalRescuePostDao::class)->update((int)$audit['post_id'], [
                'fund_status' => self::FUND_REJECTED,
                'status_time' => date('Y-m-d H:i:s'),
            ]);
        });

        try {
            $post = app()->make(AnimalRescuePostDao::class)->get((int)(
                PostFundAudit::getDB()->where('audit_id', $auditId)->value('post_id')
            ));
            if ($post) {
                $title = AnimalRescueNotify::postTitle($post);
                AnimalRescueNotify::send(
                    (int)$post['uid'],
                    '拨款审核未通过',
                    '「' . $title . '」拨款凭证审核未通过。原因：' . mb_substr($reason, 0, 80) . '，请补充材料后重新提交。',
                    (int)$post['post_id'],
                    1,
                    '/pages/animal_rescue/fund_audit/index?post_id=' . (int)$post['post_id']
                );
            }
        } catch (\Throwable $e) {
            Log::error('animal_rescue reject notify fail: ' . $e->getMessage());
        }
    }

    /**
     * 平台退回至待提交凭证（审核中可退回编辑）
     */
    public function rollbackToWaitVoucher(int $postId): void
    {
        $postDao = app()->make(AnimalRescuePostDao::class);
        $post = $postDao->get($postId);
        if (!$post || (int)$post['fund_status'] !== self::FUND_AUDITING) {
            throw new ValidateException('仅审核中状态可退回');
        }
        $postDao->update($postId, [
            'fund_status' => self::FUND_WAIT_VOUCHER,
            'status_time' => date('Y-m-d H:i:s'),
        ]);
        if ((int)$post['audit_id'] > 0) {
            $this->dao->update((int)$post['audit_id'], ['status' => PostFundAudit::STATUS_PENDING]);
        }

        $title = AnimalRescueNotify::postTitle($post);
        AnimalRescueNotify::send(
            (int)$post['uid'],
            '拨款凭证已退回',
            '「' . $title . '」拨款凭证已被平台退回，请修改后重新提交。',
            $postId,
            1,
            '/pages/animal_rescue/fund_audit/index?post_id=' . $postId
        );
    }

    /**
     * 月捐结算记录（平台只读）
     */
    public function getAdminSettlementList(array $where, int $page, int $limit): array
    {
        $dao = app()->make(SettlementRecordDao::class);
        $query = $dao->search($where)->with([
            'post' => function ($q) {
                $q->field('post_id,title,type,mer_id,uid');
            },
            'merchant' => function ($q) {
                $q->field('mer_id,mer_name,shelter_status');
            },
        ]);
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
    }

    /**
     * 按占比退款（四舍五不入：只取整数部分）
     */
    protected function refundDonorsProportionally(int $postId, float $totalRaised, float $refundTotal): void
    {
        if ($refundTotal <= 0 || $totalRaised <= 0) return;
        $participantDao = app()->make(AnimalRescueParticipantDao::class);
        $list = $participantDao->search([
            'post_id' => $postId,
            'type' => 1,
        ])->where('is_refunded', 0)->select();

        $refundedSum = 0;
        foreach ($list as $item) {
            $ratio = (float)$item['amount'] / $totalRaised;
            // 四舍五不入：只保留整数部分
            $amount = (int)floor($refundTotal * $ratio);
            if ($amount <= 0) continue;
            $this->creditUserWallet((int)$item['uid'], (float)$amount, 'animal_rescue_refund', '救助剩余退款', $postId);
            $participantDao->update((int)$item['participant_id'], ['is_refunded' => 1]);
            $refundedSum += $amount;

            AnimalRescueNotify::send(
                (int)$item['uid'],
                '救助剩余款项已退回',
                '您参与的救助项目有剩余款项 ¥' . $amount . ' 已退回平台钱包。',
                $postId,
                1
            );
        }
        // 零头留在平台（不入发布人账户），仅记日志
        $remain = $refundTotal - $refundedSum;
        if ($remain > 0) {
            Log::info('animal_rescue refund fraction to platform fund: post_id=' . $postId . ' amount=' . $remain);
        }
    }

    protected function creditUserWallet(int $uid, float $amount, string $type, string $title, int $linkId): void
    {
        if ($uid <= 0 || $amount <= 0) return;
        $user = User::getDB()->where('uid', $uid)->lock(true)->find();
        if (!$user) {
            throw new ValidateException('用户不存在 uid=' . $uid);
        }
        $balance = bcadd((string)$user['now_money'], (string)$amount, 2);
        User::getDB()->where('uid', $uid)->update(['now_money' => $balance]);
        app()->make(UserBillRepository::class)->incBill($uid, 'now_money', 'sys_inc_money', [
            'link_id' => $linkId,
            'status' => 1,
            'title' => $title,
            'number' => $amount,
            'mark' => $title . ' ¥' . $amount,
            'balance' => $balance,
        ]);
    }

    /**
     * 月捐结算：结算指定月份（默认上个月）
     */
    public function settleMonthly(?string $month = null): int
    {
        $month = $month ?: date('Y-m', strtotime('first day of last month'));
        $postDao = app()->make(AnimalRescuePostDao::class);
        $cloudDao = app()->make(CloudAdoptionOrderDao::class);
        $settlementDao = app()->make(SettlementRecordDao::class);
        $merchantRepo = app()->make(MerchantRepository::class);

        $posts = $postDao->search([
            'type' => AnimalRescuePost::TYPE_CLOUD,
            'is_del' => 0,
        ])->where('mer_id', '>', 0)->select();

        $count = 0;
        foreach ($posts as $post) {
            $postId = (int)$post['post_id'];
            $merId = (int)$post['mer_id'];
            if (!$this->isShelterMerchant($merId)) continue;

            $exists = SettlementRecord::getDB()
                ->where('post_id', $postId)
                ->where('settlement_month', $month)
                ->find();
            if ($exists) continue;

            $start = $month . '-01 00:00:00';
            $end = date('Y-m-d H:i:s', strtotime($start . ' +1 month'));
            $total = (float)\app\common\model\animal_rescue\CloudAdoptionOrder::getDB()
                ->where('post_id', $postId)
                ->where('paid', 1)
                ->where('pay_time', '>=', $start)
                ->where('pay_time', '<', $end)
                ->sum('amount');

            Db::transaction(function () use ($settlementDao, $merchantRepo, $postDao, $postId, $merId, $month, $total, &$count) {
                $settlementDao->create([
                    'post_id' => $postId,
                    'merchant_id' => $merId,
                    'settlement_month' => $month,
                    'total_amount' => $total,
                    'transferred_at' => date('Y-m-d H:i:s'),
                    'status' => 1,
                ]);
                if ($total > 0) {
                    $merchantRepo->addMoney($merId, $total);
                }
                // 重置当月进度（结算后进入新月）
                $postDao->update($postId, [
                    'raised_amount' => 0,
                    'participant_count' => 0,
                    'status' => AnimalRescuePost::STATUS_ACTIVE,
                    'is_show' => 1,
                    'status_time' => date('Y-m-d H:i:s'),
                ]);
                $count++;
            });
        }
        return $count;
    }
}
