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

use app\common\dao\animal_rescue\AnimalRescuePostDao;
use app\common\dao\animal_rescue\AnimalRescueOrderDao;
use app\common\dao\animal_rescue\AnimalRescueParticipantDao;
use app\common\dao\animal_rescue\CloudAdoptionOrderDao;
use app\common\model\animal_rescue\AnimalRescuePost;
use app\common\model\animal_rescue\AnimalRescueOrder;
use app\common\model\animal_rescue\AdoptionDeposit;
use app\common\repositories\BaseRepository;
use app\common\dao\animal_rescue\AdoptionApplicationDao;
use app\common\dao\animal_rescue\AdoptionDepositDao;
use think\facade\Db;
use think\facade\Log;

/**
 * 流浪动物救助 - 核心业务仓库
 * Class AnimalRescueRepository
 * @package app\common\repositories\animal_rescue
 */
class AnimalRescueRepository extends BaseRepository
{
    /**
     * @var AnimalRescuePostDao
     */
    protected $dao;

    /**
     * 公开帖子条件（列表含进行中+已完成，便于展示已领养状态）
     */
    const IS_SHOW_WHERE = [
        'is_show' => 1,
        'status' => [1, 2],
        'is_del' => 0,
    ];

    /** 发帖类型 */
    const TYPE_RESCUE = 1;
    const TYPE_ADOPTION = 2;
    const TYPE_CLOUD = 3;

    /**
     * @param AnimalRescuePostDao $dao
     */
    public function __construct(AnimalRescuePostDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取API帖子列表（移动端）
     * @param array $where 查询条件
     * @param int $page 页码
     * @param int $limit 每页数量
     * @param int $uid 当前用户（0=未登录）
     * @return array
     */
    public function getApiList(array $where, int $page, int $limit, int $uid = 0): array
    {
        $query = $this->dao->search($where)->with([
            'author' => function ($query) {
                $query->field('uid,avatar,nickname');
            },
            'city' => function ($query) {
                $query->field('id,name');
            },
            'merchant' => function ($query) {
                $query->field('mer_id,mer_name,mer_avatar,shelter_status');
            },
        ]);
        $count = $query->count();
        $list = $query->page($page, $limit)->field([
            'post_id', 'type', 'title', 'animal_name', 'animal_type',
            'city_id', 'target_amount', 'raised_amount', 'deposit_amount',
            'images', 'participant_count', 'status', 'fund_status', 'end_time',
            'create_time', 'uid', 'mer_id', 'animal_age', 'animal_health'
        ])->select()->toArray();

        // 领养帖：补充是否已领养、当前用户申请状态
        $adoptionIds = [];
        foreach ($list as $item) {
            if ((int)$item['type'] === self::TYPE_ADOPTION) {
                $adoptionIds[] = (int)$item['post_id'];
            }
        }
        $myApplyMap = [];
        if ($uid > 0 && !empty($adoptionIds)) {
            $apps = \think\facade\Db::name('adoption_application')
                ->where('uid', $uid)
                ->whereIn('post_id', $adoptionIds)
                ->whereIn('status', [1, 2, 3, 4, -1])
                ->field('post_id,status,application_id')
                ->order('application_id', 'desc')
                ->select()->toArray();
            foreach ($apps as $app) {
                $pid = (int)$app['post_id'];
                // 同一帖子只保留最新一条申请
                if (!isset($myApplyMap[$pid])) {
                    $myApplyMap[$pid] = [
                        'status' => (int)$app['status'],
                        'application_id' => (int)$app['application_id'],
                    ];
                }
            }
        }

        $fundRepo = app()->make(FundAuditRepository::class);
        foreach ($list as &$item) {
            $shelter = $fundRepo->buildShelterInfo((int)($item['mer_id'] ?? 0));
            $item['is_certified_shelter'] = (bool)$shelter;
            $item['shelter'] = $shelter;
            $this->appendPublisherDisplay($item);
            $item['is_adopted'] = ((int)$item['type'] === self::TYPE_ADOPTION && (int)$item['status'] === AnimalRescuePost::STATUS_COMPLETED);
            $my = $myApplyMap[(int)$item['post_id']] ?? null;
            $item['my_apply_status'] = $my ? $my['status'] : 0;
            $item['my_application_id'] = $my ? $my['application_id'] : 0;
            // 兼容：帖子尚未标记完成，但本人已领养
            if (!$item['is_adopted'] && in_array((int)$item['my_apply_status'], [3, 4], true)) {
                $item['is_adopted'] = true;
            }
        }
        unset($item);

        return compact('count', 'list');
    }

    /**
     * 获取帖子详情
     * @param int $id 帖子ID
     * @param int $uid 当前用户（0=未登录）
     * @return array|\think\Model|null
     */
    public function getDetail(int $id, int $uid = 0)
    {
        $info = $this->dao->search(['post_id' => $id, 'is_del' => 0])->with([
            'author' => function ($query) {
                $query->field('uid,avatar,nickname');
            },
            'city' => function ($query) {
                $query->field('id,name');
            },
            'merchant' => function ($query) {
                $query->field('mer_id,mer_name,mer_avatar,mer_info,shelter_status,shelter_certified_at');
            },
            'fundAudit',
        ])->find();
        if ($info) {
            $fundRepo = app()->make(FundAuditRepository::class);
            $shelter = $fundRepo->buildShelterInfo((int)($info['mer_id'] ?? 0));
            $info['is_certified_shelter'] = (bool)$shelter;
            $info['shelter'] = $shelter;
            $arr = is_object($info) && method_exists($info, 'toArray') ? $info->toArray() : (array)$info;
            $this->appendPublisherDisplay($arr);
            $info['publisher_display'] = $arr['publisher_display'] ?? '';
            $info['publisher_staff_name'] = $arr['publisher_staff_name'] ?? '';
            $info['is_staff_publish'] = !empty($arr['is_staff_publish']);
            // 月捐：本月剩余天数
            if ((int)$info['type'] === self::TYPE_CLOUD) {
                $info['month_remain_days'] = (int)date('t') - (int)date('j') + 1;
            }

            // 领养状态：是否已领养 + 当前用户申请状态
            $info['is_adopted'] = ((int)$info['type'] === self::TYPE_ADOPTION
                && (int)$info['status'] === AnimalRescuePost::STATUS_COMPLETED);
            $info['my_apply_status'] = 0;
            $info['my_application_id'] = 0;
            if ((int)$info['type'] === self::TYPE_ADOPTION && $uid > 0) {
                $app = Db::name('adoption_application')
                    ->where('uid', $uid)
                    ->where('post_id', $id)
                    ->whereIn('status', [1, 2, 3, 4, -1])
                    ->field('application_id,status')
                    ->order('application_id', 'desc')
                    ->find();
                if ($app) {
                    $info['my_apply_status'] = (int)$app['status'];
                    $info['my_application_id'] = (int)$app['application_id'];
                    if (in_array((int)$app['status'], [3, 4], true)) {
                        $info['is_adopted'] = true;
                    }
                }
            }
        }
        return $info;
    }

    /**
     * PRD：员工发布 → 发布者显示「救助站名称 · 员工昵称」
     */
    protected function appendPublisherDisplay(array &$item): void
    {
        $merId = (int)($item['mer_id'] ?? 0);
        $uid = (int)($item['uid'] ?? 0);
        $merName = (string)($item['shelter']['mer_name']
            ?? ($item['merchant']['mer_name'] ?? ''));
        $authorName = (string)($item['author']['nickname'] ?? '');

        $staffName = '';
        $isStaff = false;
        if ($merId > 0 && $uid > 0) {
            $staff = Db::name('store_service')
                ->where('mer_id', $merId)
                ->where('uid', $uid)
                ->where('is_del', 0)
                ->field('nickname,account')
                ->order('service_id', 'desc')
                ->find();
            if ($staff) {
                $isStaff = true;
                $staffName = trim((string)($staff['nickname'] ?: ''));
                if ($staffName === '') {
                    $staffName = $authorName;
                }
            }
        }

        if ($isStaff && $merName !== '' && $staffName !== '') {
            $display = $merName . ' · ' . $staffName;
        } elseif ($merName !== '') {
            $display = $merName;
        } else {
            $display = $authorName !== '' ? $authorName : '爱心用户';
        }

        $item['is_staff_publish'] = $isStaff;
        $item['publisher_staff_name'] = $staffName;
        $item['publisher_display'] = $display;
    }

    /**
     * 发布帖子
     * @param array $data
     * @param int $uid
     * @return int post_id
     */
    public function createPost(array $data, int $uid): int
    {
        $data['uid'] = $uid;
        $type = (int)($data['type'] ?? 0);
        Log::info('animal_rescue createPost: uid=' . $uid . ' type=' . $type);

        $fundRepo = app()->make(FundAuditRepository::class);
        $merId = (int)($data['mer_id'] ?? 0);
        if ($merId <= 0) {
            $merId = $fundRepo->resolveShelterMerIdByUid($uid);
        }
        // 月捐仅认证救助站可发
        if ($type === self::TYPE_CLOUD) {
            if ($merId <= 0 || !$fundRepo->isShelterMerchant($merId)) {
                throw new \think\exception\ValidateException('仅认证救助站可发布月捐');
            }
            $data['mer_id'] = $merId;
            // 月捐按自然月重置，截止到本月末
            if (empty($data['end_time'])) {
                $data['end_time'] = date('Y-m-t 23:59:59');
            }
        } elseif ($merId > 0 && $fundRepo->isShelterMerchant($merId)) {
            $data['mer_id'] = $merId;
        } else {
            $data['mer_id'] = 0;
        }

        // 自动审核机制：根据系统配置决定是否直接通过
        $needAudit = systemConfig('animal_rescue_audit') == 1;
        if (!$needAudit) {
            $data['status'] = 1; // 进行中
            $data['is_show'] = 1;
            $data['status_time'] = date('Y-m-d H:i:s');
        } else {
            $data['status'] = 0; // 审核中
        }
        // 救助帖初始化拨款状态
        if ($type === self::TYPE_RESCUE) {
            $data['fund_status'] = FundAuditRepository::FUND_RAISING;
        } else {
            $data['fund_status'] = FundAuditRepository::FUND_NONE;
        }
        // 设置筹款截止时间（救助/月捐类型）
        if (in_array($type, [self::TYPE_RESCUE, self::TYPE_CLOUD], true)) {
            $data['end_time'] = $this->normalizeEndTime($data['end_time'] ?? '');
        } else {
            unset($data['end_time']);
        }
        // 处理图片（数组转逗号分隔字符串）
        if (isset($data['images']) && is_array($data['images'])) {
            $data['images'] = implode(',', $data['images']);
        }
        return $this->dao->create($data)->post_id;
    }

    /**
     * 编辑帖子
     * @param int $id 帖子ID
     * @param int $uid 用户ID
     * @param array $data
     * @return bool
     */
    public function updatePost(int $id, int $uid, array $data): bool
    {
        if (!$this->dao->uidExists($id, $uid)) {
            return false;
        }
        if (isset($data['images']) && is_array($data['images'])) {
            $data['images'] = implode(',', $data['images']);
        }
        if (array_key_exists('end_time', $data)) {
            if ($data['end_time'] === '' || $data['end_time'] === null) {
                unset($data['end_time']);
            } else {
                $data['end_time'] = $this->normalizeEndTime($data['end_time']);
            }
        }
        $this->dao->update($id, $data);
        return true;
    }

    /**
     * 规范化筹款截止时间：至少明天，最多一年；未传则默认 +30 天
     */
    protected function normalizeEndTime($endTime): string
    {
        $ts = $endTime ? strtotime((string)$endTime) : false;
        if (!$ts) {
            return date('Y-m-d 23:59:59', strtotime('+30 days'));
        }
        $min = strtotime('tomorrow');
        $max = strtotime('+365 days');
        if ($ts < $min) {
            $ts = $min;
        }
        if ($ts > $max) {
            $ts = $max;
        }
        return date('Y-m-d 23:59:59', $ts);
    }

    /**
     * 删除帖子（软删除 + 级联处理关联数据）
     * @param int $id
     * @param int $uid
     * @return bool
     */
    public function deletePost(int $id, int $uid): bool
    {
        if (!$this->dao->uidExists($id, $uid)) {
            return false;
        }

        Db::transaction(function () use ($id, $uid) {
            // 1. 软删除帖子
            $this->dao->update($id, ['is_del' => 1]);

            // 2. 取消关联的救助捐款订单（未支付订单）
            $orderDao = app()->make(AnimalRescueOrderDao::class);
            $orderDao->getModel()::getDB()
                ->where('post_id', $id)
                ->where('paid', 0)
                ->update(['paid' => -1]);

            // 3. 取消关联的云养订单（未支付订单）
            $cloudDao = app()->make(CloudAdoptionOrderDao::class);
            $cloudDao->getModel()::getDB()
                ->where('post_id', $id)
                ->where('paid', 0)
                ->update(['paid' => -1]);

            // 4. 将进行中的领养申请标记为已取消
            app()->make(AdoptionApplicationDao::class)->getModel()::getDB()
                ->where('post_id', $id)
                ->whereIn('status', [1, 2])
                ->update(['status' => -2]);

            // 5. 将冻结中的保证金标记为已失效
            app()->make(AdoptionDepositDao::class)->getModel()::getDB()
                ->where('post_id', $id)
                ->where('status', 1)
                ->update(['status' => -1]);

            // 6. 将进行中的参与记录标记为已取消
            app()->make(AnimalRescueParticipantDao::class)->getModel()::getDB()
                ->where('post_id', $id)
                ->where('status', 2)
                ->update(['status' => -1]);
        });

        Log::info('animal_rescue deletePost: post_id=' . $id . ' uid=' . $uid);
        return true;
    }

    /**
     * 捐款下单
     * @param int $uid 捐款人UID
     * @param int $postId 帖子ID
     * @param array $data 订单数据
     * @return array [order_id, order_sn]
     */
    public function donateOrder(int $uid, int $postId, array $data): array
    {
        $post = $this->dao->get($postId);
        if (!$post || (int)$post['is_del'] === 1) {
            throw new \think\exception\ValidateException('帖子不存在');
        }
        if ((int)$post['type'] !== self::TYPE_RESCUE || (int)$post['status'] !== 1) {
            throw new \think\exception\ValidateException('当前帖子不可捐款');
        }
        $fundStatus = (int)($post['fund_status'] ?? 0);
        if ($fundStatus > FundAuditRepository::FUND_RAISING) {
            throw new \think\exception\ValidateException('该救助已满额，正在拨款审核中');
        }

        $orderSn = $this->generateOrderSn('DN');
        $orderData = [
            'order_sn' => $orderSn,
            'uid' => $uid,
            'post_id' => $postId,
            'amount' => $data['amount'],
            'pay_type' => $data['pay_type'] ?? 'weixin',
            'is_anonymous' => $data['is_anonymous'] ?? 0,
            'message' => $data['message'] ?? '',
            'paid' => 0,
        ];
        $orderId = app()->make(AnimalRescueOrderDao::class)->create($orderData)->order_id;
        return ['order_id' => $orderId, 'order_sn' => $orderSn];
    }

    /**
     * 云养下单
     * @param int $uid
     * @param int $postId
     * @param array $data
     * @return array [cloud_order_id, order_sn]
     */
    public function cloudOrder(int $uid, int $postId, array $data): array
    {
        $post = $this->dao->get($postId);
        if (!$post || (int)$post['is_del'] === 1 || (int)$post['type'] !== self::TYPE_CLOUD) {
            throw new \think\exception\ValidateException('月捐帖子不存在');
        }
        if ((int)$post['status'] !== 1) {
            throw new \think\exception\ValidateException('当前月捐不可捐赠');
        }
        // 达目标后仍可继续捐
        $orderSn = $this->generateOrderSn('CL');
        $orderData = [
            'order_sn' => $orderSn,
            'uid' => $uid,
            'post_id' => $postId,
            'amount' => $data['amount'],
            'pay_type' => $data['pay_type'] ?? 'weixin',
            'is_subscribe' => $data['is_subscribe'] ?? 0,
            'is_anonymous' => $data['is_anonymous'] ?? 0,
            'settlement_month' => date('Y-m'),
            'paid' => 0,
        ];
        $cloudOrderId = app()->make(CloudAdoptionOrderDao::class)->create($orderData)->cloud_order_id;
        return ['cloud_order_id' => $cloudOrderId, 'order_sn' => $orderSn];
    }

    /**
     * 救助捐款支付成功回调处理
     * 入账：订单已付 + 帖子已筹 + 参与记录 + 平台财务流水（托管，待拨款审核）
     * @param string $orderSn 订单编号
     * @param string $payType 支付方式 weixin|alipay|mock|...
     */
    public function donatePaySuccess(string $orderSn, string $payType = ''): void
    {
        $orderDao = app()->make(AnimalRescueOrderDao::class);
        $order = $orderDao->getWhere(['order_sn' => $orderSn]);
        if (!$order || $order->paid == 1) return;

        Db::transaction(function () use ($order, $orderDao, $payType) {
            $payType = $payType ?: ($order->pay_type ?: 'weixin');
            $txId = ($payType === 'mock' ? 'MOCK' : 'PAY') . date('YmdHis') . $order->order_id;
            // 更新订单状态
            $orderDao->update($order->order_id, [
                'paid' => 1,
                'pay_type' => $payType,
                'pay_time' => date('Y-m-d H:i:s'),
                'transaction_id' => $txId,
            ]);
            // 更新帖子已筹金额；参与人数按用户去重（同一人多次捐款只计 1 人）
            $this->dao->incField($order->post_id, 'raised_amount', $order->amount);
            $participantDao = app()->make(AnimalRescueParticipantDao::class);
            $alreadyJoined = $participantDao->search([
                'uid' => $order->uid,
                'post_id' => $order->post_id,
                'type' => 1,
            ])->count() > 0;
            // 写入参与记录
            $participantDao->create([
                'uid' => $order->uid,
                'post_id' => $order->post_id,
                'type' => 1,
                'amount' => $order->amount,
                'order_id' => $order->order_id,
                'status' => 1, // 已完成
                'is_refunded' => 0,
            ]);
            if (!$alreadyJoined) {
                $this->dao->incField($order->post_id, 'participant_count', 1);
            }
            // 平台托管流水（审核拨付前资金在平台账上）
            $this->recordPlatformFund([
                'order_id' => $order->order_id,
                'order_sn' => $order->order_sn,
                'user_id' => $order->uid,
                'amount' => $order->amount,
                'pay_type' => $payType,
                'financial_type' => 'animal_rescue_donate',
                'title' => '救助捐款入账(平台托管)',
            ]);
            // 满额 → 待提交凭证
            app()->make(FundAuditRepository::class)->maybeMarkWaitVoucher((int)$order->post_id);
        });
    }

    /**
     * 云养支付成功回调处理
     * 入账：订单已付 + 帖子已筹 + 参与记录 + 平台财务流水（待月结转入商家钱包）
     * @param string $orderSn
     * @param string $payType
     */
    public function cloudPaySuccess(string $orderSn, string $payType = ''): void
    {
        $cloudDao = app()->make(CloudAdoptionOrderDao::class);
        $order = $cloudDao->getWhere(['order_sn' => $orderSn]);
        if (!$order || $order->paid == 1) return;

        Db::transaction(function () use ($order, $cloudDao, $payType) {
            $payType = $payType ?: ($order->pay_type ?: 'weixin');
            $month = date('Y-m');
            $txId = ($payType === 'mock' ? 'MOCK' : 'PAY') . date('YmdHis') . $order->cloud_order_id;
            $update = [
                'paid' => 1,
                'pay_time' => date('Y-m-d H:i:s'),
                'settlement_month' => $month,
                'pay_type' => $payType,
                'transaction_id' => $txId,
            ];
            $cloudDao->update($order->cloud_order_id, $update);
            $this->dao->incField($order->post_id, 'raised_amount', $order->amount);

            $participantDao = app()->make(AnimalRescueParticipantDao::class);
            // 「人参与月捐」按当月去重人数，同一用户多次月捐只计 1 人
            $alreadyThisMonth = $participantDao->search([
                'uid' => $order->uid,
                'post_id' => $order->post_id,
                'type' => 3,
            ])->where('settlement_month', $month)->count() > 0;

            $participantDao->create([
                'uid' => $order->uid,
                'post_id' => $order->post_id,
                'type' => 3, // 救助站月捐
                'amount' => $order->amount,
                'order_id' => $order->cloud_order_id,
                'status' => 1,
                'settlement_month' => $month,
            ]);
            if (!$alreadyThisMonth) {
                $this->dao->incField($order->post_id, 'participant_count', 1);
            }
            // 平台流水：月捐待结算入商家钱包
            $this->recordPlatformFund([
                'order_id' => $order->cloud_order_id,
                'order_sn' => $order->order_sn,
                'user_id' => $order->uid,
                'amount' => $order->amount,
                'pay_type' => $payType,
                'financial_type' => 'animal_rescue_cloud',
                'title' => '救助站月捐入账(待结算)',
            ]);
        });
    }

    /**
     * 写入平台财务流水（救助相关资金进系统账）
     */
    protected function recordPlatformFund(array $data): void
    {
        $amount = (float)($data['amount'] ?? 0);
        if ($amount <= 0) return;

        $payType = (string)($data['pay_type'] ?? 'mock');
        // financial_record.pay_type 存 StoreOrder PAY_TYPE 下标；mock=10
        $payTypeIndex = array_search($payType, \app\common\repositories\store\order\StoreOrderRepository::PAY_TYPE, true);
        if ($payTypeIndex === false) {
            $payTypeIndex = array_search('mock', \app\common\repositories\store\order\StoreOrderRepository::PAY_TYPE, true);
        }
        if ($payTypeIndex === false) {
            $payTypeIndex = 10;
        }

        $userInfo = '';
        try {
            $userInfo = (string)(\app\common\model\user\User::getDB()->where('uid', (int)$data['user_id'])->value('nickname') ?: '');
        } catch (\Throwable $e) {}

        app()->make(\app\common\dao\system\merchant\FinancialRecordDao::class)->inc([
            'order_id' => (int)$data['order_id'],
            'order_sn' => (string)$data['order_sn'],
            'user_info' => $userInfo ?: ('uid:' . (int)$data['user_id']),
            'user_id' => (int)$data['user_id'],
            'financial_type' => (string)$data['financial_type'],
            'type' => 2, // 平台侧流水
            'number' => $amount,
            'pay_type' => (int)$payTypeIndex,
            // 备注写入 user_info 过长时不够，依赖 financial_type 区分
        ], 0);

        Log::info('animal_rescue platform fund in: type=' . $data['financial_type']
            . ' sn=' . $data['order_sn'] . ' amount=' . $amount . ' pay_type=' . $payType);
    }

    /**
     * 获取我的参与记录
     * @param int $uid
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getMyRecords(int $uid, array $where, int $page, int $limit): array
    {
        $participantDao = app()->make(AnimalRescueParticipantDao::class);
        $where['uid'] = $uid;
        $query = $participantDao->search($where)->with([
            'post' => function ($query) {
                $query->field('post_id,title,type,images,animal_type,uid');
            }
        ]);
        // 汇总统计：救助/云养按参与记录；领养按申请（含审核中/已通过/已领养/已完成），避免仅支付后才计入
        $rescue = $participantDao->getWhereCount(['uid' => $uid, 'type' => 1]);
        $cloud = $participantDao->getWhereCount(['uid' => $uid, 'type' => 3]);
        $adoptionPaid = $participantDao->getWhereCount(['uid' => $uid, 'type' => 2]);
        $adoptionApp = app()->make(AdoptionApplicationDao::class)->getWhereCount([
            'uid' => $uid,
            'status' => [1, 2, 3, 4],
        ]);
        // 申请与支付后的参与记录可能同时存在，取较大值避免漏计或简单相加重复
        $adoption = max($adoptionApp, $adoptionPaid);
        $summary = [
            'total' => $rescue + $adoption + $cloud,
            'rescue' => $rescue,
            'adoption' => $adoption,
            'cloud' => $cloud,
        ];
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        return ['summary' => $summary, 'count' => $count, 'list' => $list];
    }

    /**
     * 获取我的发布列表
     * @param int $uid
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getMyPosts(int $uid, array $where, int $page, int $limit): array
    {
        $where['uid'] = $uid;
        $where['is_del'] = 0;
        if (!isset($where['type']) || $where['type'] === '' || $where['type'] === null) {
            unset($where['type']);
        }
        $query = $this->dao->search($where);
        $count = $query->count();
        $list = $query->page($page, $limit)->field([
            'post_id', 'type', 'title', 'animal_name', 'animal_type', 'images',
            'status', 'fund_status', 'mer_id', 'raised_amount', 'target_amount', 'create_time'
        ])->order('post_id DESC')->select()->toArray();

        if (!empty($list)) {
            $adoptionIds = array_column(array_filter($list, function($p) { return $p['type'] == 2; }), 'post_id');
            $countMap = [];
            if (!empty($adoptionIds)) {
                $rows = \think\facade\Db::name('adoption_application')
                    ->whereIn('post_id', $adoptionIds)
                    ->field('post_id, count(*) as cnt')
                    ->group('post_id')
                    ->select()->toArray();
                foreach ($rows as $row) {
                    $countMap[$row['post_id']] = (int)$row['cnt'];
                }
            }
            foreach ($list as &$item) {
                $item['application_count'] = $item['type'] == 2 ? ($countMap[$item['post_id']] ?? 0) : null;
            }
            unset($item);
        }

        return compact('count', 'list');
    }

    /**
     * 后台帖子列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getAdminList(array $where, int $page, int $limit): array
    {
        $query = $this->dao->search($where)->with([
            'author' => function ($query) {
                $query->field('uid,nickname');
            },
            'merchant' => function ($query) {
                $query->field('mer_id,mer_name,shelter_status');
            },
        ]);
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
    }

    /**
     * 后台审核帖子
     * @param int $id
     * @param int $status
     * @param string $remark
     */
    public function auditPost(int $id, int $status, string $remark = ''): void
    {
        $post = $this->dao->get($id);
        $updateData = [
            'status' => $status,
            'status_time' => date('Y-m-d H:i:s'),
        ];
        if ($status == 1) {
            $updateData['is_show'] = 1;
        }
        $this->dao->update($id, $updateData);

        if ($post) {
            $title = AnimalRescueNotify::postTitle($post);
            $postType = AnimalRescueNotify::postType($post);
            if ((int)$status === 1) {
                AnimalRescueNotify::send(
                    (int)$post['uid'],
                    '救助帖子审核通过',
                    '「' . $title . '」已审核通过并上线，爱心人士可以参与了。',
                    (int)$id,
                    $postType
                );
            } elseif ((int)$status === -1) {
                $reason = $remark !== '' ? ('原因：' . mb_substr($remark, 0, 80)) : '请修改后重新提交';
                AnimalRescueNotify::send(
                    (int)$post['uid'],
                    '救助帖子审核未通过',
                    '「' . $title . '」未通过审核。' . $reason,
                    (int)$id,
                    $postType,
                    '/pages/animal_rescue/my_posts/index'
                );
            }
        }
    }

    /**
     * 管理员删除帖子（软删除）
     * @param int $id
     */
    public function adminDelete(int $id): void
    {
        $this->dao->update($id, ['is_del' => 1]);
    }

    /**
     * 模块数据统计
     * @return array
     */
    public function getStatistics(): array
    {
        $postModel = app()->make(AnimalRescuePostDao::class);
        $orderModel = app()->make(AnimalRescueOrderDao::class);
        $depositModel = app()->make(AdoptionDepositDao::class);

        $auditModel = \app\common\model\animal_rescue\PostFundAudit::getDB();

        $pendingFundAudit = (int)$auditModel->alias('a')
            ->leftJoin('animal_rescue_post p', 'a.post_id = p.post_id')
            ->where('a.status', \app\common\model\animal_rescue\PostFundAudit::STATUS_PENDING)
            ->where('p.fund_status', FundAuditRepository::FUND_AUDITING)
            ->count();

        $totalFundAudit = (int)\app\common\model\animal_rescue\PostFundAudit::getDB()->count();

        $rejectedFundAudit = (int)\app\common\model\animal_rescue\PostFundAudit::getDB()
            ->where('status', \app\common\model\animal_rescue\PostFundAudit::STATUS_REJECTED)
            ->count();

        return [
            'total_posts' => AnimalRescuePost::getDB()->where('is_del', 0)->count(),
            'total_raised' => AnimalRescueOrder::getDB()->where('paid', 1)->sum('amount') ?: 0,
            'total_participants' => AnimalRescuePost::getDB()->where('is_del', 0)->sum('participant_count') ?: 0,
            'total_deposit' => AdoptionDeposit::getDB()->where('status', 1)->whereNotNull('pay_time')->where('pay_time', '<>', '')->sum('amount') ?: 0,
            'pending_fund_audit' => $pendingFundAudit,
            'total_fund_audit' => $totalFundAudit,
            'rejected_fund_audit' => $rejectedFundAudit,
        ];
    }

    /**
     * 分类帖子计数
     * @return array
     */
    public function getCategoryCount(): array
    {
        return $this->dao->getCategoryCount();
    }

    /**
     * 生成订单编号
     * @param string $prefix 前缀
     * @return string
     */
    private function generateOrderSn(string $prefix = 'AR'): string
    {
        return $prefix . date('YmdHis') . rand(1000, 9999);
    }
}
