<?php

namespace app\common\repositories\merchant;

use app\common\dao\merchant\ShoppingCreditsOrderDao;
use app\common\dao\merchant\ShoppingCreditsRefundOrderDao;
use app\common\dao\user\UserShoppingCreditsDao;
use app\common\repositories\BaseRepository;
use crmeb\services\AlipayService;
use crmeb\services\LockService;
use crmeb\services\pay\dto\PayRefundDto;
use crmeb\services\pay\PayAttach;
use crmeb\services\pay\PayServices;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 购物金充值退款处理。
 */
class ShoppingCreditsRefundRepository extends BaseRepository
{
    private ShoppingCreditsRefundOrderDao $refundOrderDao;
    private UserShoppingCreditsDao $shoppingCreditsDao;

    public function __construct(
        ShoppingCreditsOrderDao $dao,
        ShoppingCreditsRefundOrderDao $refundOrderDao,
        UserShoppingCreditsDao $shoppingCreditsDao
    ) {
        $this->dao = $dao;
        $this->refundOrderDao = $refundOrderDao;
        $this->shoppingCreditsDao = $shoppingCreditsDao;
    }
    public function applyRefund(int $uid, string $orderNo, string $reason): string
    {
        $refundNo = '';
        Db::transaction(function () use ($uid, $orderNo, $reason, &$refundNo) {
            $order = $this->dao->search()->where('order_no', $orderNo)->where('uid', $uid)->lock(true)->find();
            if (!$order || !(int)$order->paid) {
                throw new ValidateException('充值订单不存在或未支付');
            }
            if ((int)$order->refund_status !== 0) {
                throw new ValidateException('该订单正在退款或已退款');
            }
            $balance = $this->shoppingCreditsDao->search()
                ->where('uid', $uid)->where('mer_id', $order->mer_id)->lock(true)->find();
            if (!$balance || bccomp((string)$balance->recharge_amount, '0.00', 2) <= 0) {
                throw new ValidateException('购物金本金余额为0，无法退款');
            }
            $refundAmount = bccomp((string)$balance->recharge_amount, (string)$order->recharge_amount, 2) >= 0
                ? (string)$order->recharge_amount : (string)$balance->recharge_amount;
            $refundGift = bccomp((string)$balance->gift_amount, (string)$order->gift_amount, 2) >= 0
                ? (string)$order->gift_amount : (string)$balance->gift_amount;
            $refundNo = $this->makeOrderNo('SCR', $uid);
            $this->refundOrderDao->create([
                'refund_order_no' => $refundNo,
                'order_no' => $orderNo,
                'mer_id' => $order->mer_id,
                'uid' => $uid,
                'refund_reason' => $reason,
                'is_all' => bccomp($refundAmount, (string)$order->recharge_amount, 2) === 0
                    && bccomp($refundGift, (string)$order->gift_amount, 2) === 0 ? 1 : 0,
                'audit_type' => 0,
                'refund_amount' => $refundAmount,
                'refund_gift_amount' => $refundGift,
                'refund_pay_type' => $order->pay_type,
            ]);
            $order->refund_status = 1;
            $order->save();
            $this->creditsRepository()->changeBalance($uid, (int)$order->mer_id, $refundAmount, $refundGift, 2, $refundNo, 'refund', '购物金退款申请');
        });
        return $refundNo;
    }

    /**
     * 分页查询用户在某店铺的购物金退款单
     * @param int $uid 用户ID
     * @param int $merId 商户ID
     * @param int $page 页码
     * @param int $limit 每页数量
     * @param string $date 申请时间范围
     * @return array 含count与list的分页结果
     */
    public function userRefundOrders(int $uid, int $merId, int $page, int $limit, string $date = ''): array
    {
        $query = $this->refundOrderDao->search()
            ->where('uid', $uid)
            ->where('mer_id', $merId)
            ->when($date !== '', function ($query) use ($date) {
                getModelTime($query, $date, 'create_time');
            })
            ->order('id DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();
        return compact('count', 'list');
    }

    /**
     * 判断用户在指定店铺是否存在处理中的购物金退款。
     */
    public function hasPendingRefund(int $uid, int $merId, bool $lock = false): bool
    {
        $query = $this->refundOrderDao->search()
            ->where('uid', $uid)
            ->where('mer_id', $merId)
            ->whereIn('audit_type', [0, 1])
            ->whereNull('refund_time');
        if ($lock) {
            $query->lock(true);
        }

        return (bool)$query->find();
    }

    /**
     * 商户分页查询购物金充值订单，支持订单号、用户、支付方式、时间等多条件筛选
     * @param int $merId 商户ID
     * @param array $where 筛选条件（order_no/keyword/uid/nickname/phone/pay_type/date）
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array 含count与list的分页结果
     */
    public function merchantOrders(int $merId, array $where, int $page, int $limit): array
    {
        $query = $this->dao->search()->alias('o')
            ->join('user u', 'u.uid = o.uid')
            ->where('o.mer_id', $merId)
            ->where('o.paid', 1)
            ->when(($where['order_no'] ?? '') !== '', fn($q) => $q->whereLike('o.order_no', '%' . $where['order_no'] . '%'))
            ->when(($where['keyword'] ?? '') !== '', fn($q) => $q->whereLike('u.uid|u.phone|u.nickname', '%' . $where['keyword'] . '%'))
            ->when(($where['uid'] ?? '') !== '', fn($q) => $q->where('o.uid', (int)$where['uid']))
            ->when(($where['nickname'] ?? '') !== '', fn($q) => $q->whereLike('u.nickname', '%' . $where['nickname'] . '%'))
            ->when(($where['phone'] ?? '') !== '', fn($q) => $q->whereLike('u.phone', '%' . $where['phone'] . '%'))
            ->when(($where['pay_type'] ?? '') !== '', fn($q) => $q->where('o.pay_type', $where['pay_type']))
            ->when(($where['date'] ?? '') !== '', function ($q) use ($where) {
                getModelTime($q, $where['date'], 'o.pay_time');
            });
        $rechargeAmount = (string)((clone $query)->sum('o.recharge_amount') ?: '0.00');
        $giftAmount = (string)((clone $query)->sum('o.gift_amount') ?: '0.00');
        $count = $query->count();
        $list = $query->field('o.*,u.nickname,u.avatar,u.phone')->page($page, $limit)->order('o.id DESC')->select()->toArray();
        return [
            'count' => $count,
            'list' => $list,
            'statistics' => [
                'recharge_amount' => bcadd($rechargeAmount, '0', 2),
                'gift_amount' => bcadd($giftAmount, '0', 2),
                'total_amount' => bcadd($rechargeAmount, $giftAmount, 2),
            ],
        ];
    }

    /**
     * 商户分页查询购物金退款单，支持审核结果、订单号、用户、时间等条件筛选
     * @param int $merId 商户ID
     * @param array $where 筛选条件（audit_type/order_no/uid/date）
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array 含count与list的分页结果
     */
    public function merchantRefundOrders(int $merId, array $where, int $page, int $limit): array
    {
        $query = $this->refundOrderDao->search()->alias('r')
            ->join('user u', 'u.uid = r.uid')
            ->join('shopping_credits_order o', 'o.order_no = r.order_no')
            ->leftJoin('merchant_admin a', 'a.merchant_admin_id = r.audit_id')
            ->where('r.mer_id', $merId)
            ->when(($where['audit_type'] ?? '') !== '', fn($q) => $q->where('r.audit_type', (int)$where['audit_type']))
            ->when(($where['order_no'] ?? '') !== '', fn($q) => $q->whereLike('r.order_no|r.refund_order_no', '%' . $where['order_no'] . '%'))
            ->when(($where['uid'] ?? '') !== '', fn($q) => $q->where('r.uid', (int)$where['uid']))
            ->when(($where['date'] ?? '') !== '', function ($q) use ($where) {
                getModelTime($q, $where['date'], 'r.create_time');
            });
        $count = $query->count();
        $list = $query->field([
            'r.*', 'u.nickname', 'u.avatar', 'u.phone',
            'o.recharge_amount', 'o.gift_amount', 'o.pay_time', 'o.pay_type',
            'a.real_name audit_name',
        ])->page($page, $limit)->order('r.id DESC')->select()->toArray();
        return compact('count', 'list');
    }

    /**
     * 获取当前商户购物金退款审核统计。
     * @param int $merId 商户ID
     * @return array
     */
    public function refundStatistics(int $merId): array
    {
        $rows = $this->refundOrderDao->search()
            ->where('mer_id', $merId)
            ->field('audit_type, COUNT(*) AS count')
            ->group('audit_type')
            ->select()
            ->toArray();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int)$row['audit_type']] = (int)$row['count'];
        }

        return [
            ['type' => 0, 'name' => '全部', 'count' => array_sum($counts)],
            ['type' => 1, 'name' => '已退款', 'count' => $counts[1] ?? 0],
            ['type' => -1, 'name' => '已拒绝', 'count' => $counts[-1] ?? 0],
            ['type' => 2, 'name' => '待审核', 'count' => $counts[0] ?? 0],
        ];
    }

    /**
     * 获取购物金退款单详情，退款单不存在时抛出异常
     * @param int $merId 商户ID
     * @param string $refundNo 退款单号
     * @return array 退款单详情
     */
    public function refundDetail(int $merId, string $refundNo): array
    {
        $detail = $this->refundOrderDao->search()->alias('r')
            ->join('user u', 'u.uid = r.uid')
            ->join('shopping_credits_order o', 'o.order_no = r.order_no')
            ->leftJoin('merchant_admin a', 'a.merchant_admin_id = r.audit_id')
            ->where('r.mer_id', $merId)
            ->where('r.refund_order_no', $refundNo)
            ->field([
                'r.*', 'u.nickname', 'u.avatar', 'u.phone',
                'o.recharge_amount', 'o.gift_amount', 'o.pay_time', 'o.pay_type', 'o.out_trade_no',
                'a.real_name audit_name',
            ])->find();
        if (!$detail) {
            throw new ValidateException('退款订单不存在');
        }
        return $detail->toArray();
    }

    /**
     * 商户审核购物金退款申请，通过分布式锁串行执行，防止重复审核
     * @param int $merId 商户ID
     * @param int $id 退款记录ID
     * @param bool $approve 是否同意退款
     * @param string $reason 审核备注/拒绝原因
     * @param int $adminId 审核管理员ID
     */
    public function auditRefund(int $merId, int $id, bool $approve, string $reason, int $adminId): void
    {
        app()->make(LockService::class)->exec('merchant.shopping.credits.refund.audit.' . $id,
            function () use ($merId, $id, $approve, $reason, $adminId) {
                $this->auditRefundLocked($merId, $id, $approve, $reason, $adminId);
            },
            30
        );
    }

    /**
     * 购物金退款审核核心逻辑（需在锁内调用）：事务内更新审核状态，拒绝时退回冻结资金，同意时提交支付渠道退款
     * @param int $merId 商户ID
     * @param int $id 退款记录ID
     * @param bool $approve 是否同意退款
     * @param string $reason 审核备注/拒绝原因
     * @param int $adminId 审核管理员ID
     */
    private function auditRefundLocked(int $merId, int $id, bool $approve, string $reason, int $adminId): void
    {
        $refundData = Db::transaction(function () use ($merId, $id, $approve, $reason, $adminId) {
            $refund = $this->refundOrderDao->search()
                ->where('mer_id', $merId)
                ->where('id', $id)
                ->lock(true)
                ->find();
            if (!$refund || (int)$refund->audit_type !== 0) {
                throw new ValidateException('退款订单状态异常');
            }

            $order = $this->dao->search()
                ->where('order_no', $refund->order_no)
                ->lock(true)
                ->find();
            if (!$order) {
                throw new ValidateException('充值订单不存在');
            }

            if (!$approve) {
                $this->rejectRefund($refund, $order, $reason, $adminId);
                return null;
            }

            return $this->approveRefund($refund, $order, $reason, $adminId);
        });

        if (!$approve || !$refundData) {
            return;
        }

        $refundAmount = (string)$refundData['refund_amount'];
        $isAlipay = str_starts_with(strtolower((string)$refundData['pay_type']), 'alipay');
        $this->submitRefundToChannel($refundData);

        if ($isAlipay || bccomp($refundAmount, '0.00', 2) <= 0) {
            $this->markRefundSuccess((string)$refundData['refund_order_no']);
        }
    }

    /**
     * 拒绝退款并返还申请时冻结的购物金。
     */
    private function rejectRefund($refund, $order, string $reason, int $adminId): void
    {
        $refund->refusing_refund_reason = $reason;
        $refund->audit_id = $adminId;
        $refund->audit_type = -1;
        $refund->audit_time = date('Y-m-d H:i:s');
        $refund->mer_remark = $reason;
        $refund->save();

        $order->refund_status = 0;
        $order->save();

        $this->creditsRepository()->changeBalance(
            (int)$refund->uid,
            (int)$refund->mer_id,
            (string)$refund->refund_amount,
            (string)$refund->refund_gift_amount,
            1,
            (string)$refund->refund_order_no,
            'refund_reject',
            '购物金退款被拒，资金退回'
        );
    }

    /**
     * 同意退款并生成提交支付渠道所需的数据。
     */
    private function approveRefund($refund, $order, string $reason, int $adminId): array
    {
        $refund->audit_id = $adminId;
        $refund->audit_type = 1;
        $refund->audit_time = date('Y-m-d H:i:s');
        $refund->mer_remark = $reason;
        $refund->save();

        $order->refund_status = 2;
        $order->save();

        return [
            'id' => (int)$refund->id,
            'refund_order_no' => (string)$refund->refund_order_no,
            'refund_amount' => (string)$refund->refund_amount,
            'refund_reason' => (string)$refund->refund_reason,
            'pay_type' => (string)$order->pay_type,
            'pay_channel' => (string)$order->pay_channel,
            'order_no' => (string)$order->order_no,
            'mer_id' => (int)$order->mer_id,
            'recharge_amount' => (string)$order->recharge_amount,
            'out_trade_no' => (string)$order->out_trade_no,
        ];
    }

    /**
     * Retry refund requests that remain in the processing state.
     *
     * Payment channels use the same refund number on every attempt, so a
     * transient timeout cannot create a second refund request.
     */
    public function retryProcessingRefunds(int $limit = 100): int
    {
        $refunds = $this->refundOrderDao->search()
            ->where('audit_type', 1)
            ->whereNull('refund_time')
            ->order('id ASC')
            ->limit(max(1, $limit))
            ->select();
        $successCount = 0;

        foreach ($refunds as $refund) {
            try {
                $result = app()->make(LockService::class)->exec(
                    'merchant.shopping.credits.refund.retry.' . $refund->refund_order_no,
                    function () use ($refund) {
                        $current = $this->refundOrderDao->search()
                            ->where('id', $refund->id)
                            ->where('audit_type', 1)
                            ->whereNull('refund_time')
                            ->find();
                        if (!$current) {
                            return false;
                        }

                        $order = $this->dao->search()
                            ->where('order_no', $current->order_no)
                            ->where('mer_id', $current->mer_id)
                            ->find();
                        if (!$order) {
                            return false;
                        }

                        $data = [
                            'refund_order_no' => (string)$current->refund_order_no,
                            'refund_amount' => (string)$current->refund_amount,
                            'refund_reason' => (string)$current->refund_reason,
                            'pay_type' => (string)$order->pay_type,
                            'pay_channel' => (string)$order->pay_channel,
                            'order_no' => (string)$order->order_no,
                            'recharge_amount' => (string)$order->recharge_amount,
                            'out_trade_no' => (string)$order->out_trade_no,
                        ];
                        $refundAmount = (string)$data['refund_amount'];
                        if (bccomp($refundAmount, '0.00', 2) <= 0) {
                            $this->markRefundSuccess($data['refund_order_no']);
                            return true;
                        }

                        if (str_starts_with(strtolower($data['pay_type']), 'alipay')) {
                            try {
                                $response = AlipayService::make()->queryRefund(
                                    $data['order_no'],
                                    $data['refund_order_no']
                                );
                                $status = is_object($response)
                                    ? (string)($response->refundStatus ?? $response->refund_status ?? '')
                                    : (string)($response['refund_status'] ?? '');
                                if (strtoupper($status) === 'REFUND_SUCCESS') {
                                    $this->markRefundSuccess($data['refund_order_no']);
                                    return true;
                                }
                            } catch (\Throwable $e) {
                                // Query failures are followed by an idempotent retry.
                            }
                        }

                        $this->submitRefundToChannel($data);
                        if (str_starts_with(strtolower($data['pay_type']), 'alipay')) {
                            $this->markRefundSuccess($data['refund_order_no']);
                        }
                        return false;
                    },
                    30
                );
                if ($result) {
                    $successCount++;
                }
            } catch (\Throwable $e) {
                // Keep this record in processing state and continue with the
                // remaining records; the next scheduled run can retry it.
            }
        }

        return $successCount;
    }

    /**
     * 将购物金退款请求提交到支付渠道（微信/支付宝），退款金额为0时直接跳过
     * @param array $refundData 退款数据（含pay_type/order_no/refund_order_no/refund_amount等）
     */
    private function submitRefundToChannel(array $refundData): void
    {
        if (bccomp((string)$refundData['refund_amount'], '0.00', 2) <= 0) {
            return;
        }

        $dto = PayRefundDto::fromPayType($refundData['pay_type'], $refundData['order_no'], [
            'attach' => PayAttach::MERCHANT_SHOPPING_CREDITS,
            'refund_id' => $refundData['refund_order_no'],
            'refund_no' => $refundData['refund_order_no'],
            'pay_price' => $refundData['recharge_amount'],
            'refund_price' => $refundData['refund_amount'],
            'transaction_id' => $refundData['out_trade_no'],
            'refund_message' => $refundData['refund_reason'],
            'payment_channel' => $refundData['pay_channel'],
        ]);
        app()->make(PayServices::class)->refund($dto);
    }

    /**
     * Query pending Alipay refunds. This is intended for a scheduled command
     * because Alipay does not provide the same refund callback path as WeChat.
     */
    public function queryAlipayRefunds(int $limit = 100): int
    {
        $refunds = $this->refundOrderDao->search()
            ->where('audit_type', 1)
            ->whereNull('refund_time')
            ->whereLike('refund_pay_type', 'alipay%')
            ->order('id ASC')
            ->limit(max(1, $limit))
            ->select();
        $successCount = 0;

        foreach ($refunds as $refund) {
            $order = $this->dao->search()
                ->where('order_no', $refund->order_no)
                ->where('mer_id', $refund->mer_id)
                ->find();
            if (!$order) {
                continue;
            }

            try {
                $response = AlipayService::make()->queryRefund(
                    (string)$order->order_no,
                    (string)$refund->refund_order_no
                );
                $status = is_object($response)
                    ? (string)($response->refundStatus ?? $response->refund_status ?? '')
                    : (string)($response['refund_status'] ?? '');
                if (strtoupper($status) === 'REFUND_SUCCESS') {
                    $this->markRefundSuccess((string)$refund->refund_order_no);
                    $successCount++;
                }
            } catch (\Throwable $e) {
                // Keep processing records for the next scheduled attempt.
            }
        }

        return $successCount;
    }

    /**
     * 处理支付渠道退款结果回调，退款状态为SUCCESS时标记退款成功
     * @param array $data 回调数据（含out_refund_no/refund_no及refund_status）
     */
    public function handleRefundNotify(array $data): void
    {
        $refundNo = (string)($data['out_refund_no']
            ?? $data['outRefundNo']
            ?? $data['refund_no']
            ?? $data['refundNo']
            ?? '');
        if ($refundNo === '') {
            return;
        }

        $refund = $this->refundOrderDao->search()
            ->where('refund_order_no', $refundNo)
            ->find();
        if (!$refund) {
            return;
        }

        $status = strtoupper((string)($data['refund_status']
            ?? $data['refundStatus']
            ?? $data['status']
            ?? ''));
        if ($status !== 'SUCCESS') {
            return;
        }

        $this->markRefundSuccess($refundNo);
    }

    /**
     * 标记购物金退款成功，事务内扣减商户余额、更新退款单及充值订单状态
     * @param string $refundNo 退款单号
     */
    private function markRefundSuccess(string $refundNo): void
    {
        Db::transaction(function () use ($refundNo) {
            $refund = $this->refundOrderDao->search()
                ->where('refund_order_no', $refundNo)
                ->lock(true)
                ->find();
            if (!$refund || (int)$refund->audit_type !== 1 || $refund->refund_time) {
                return;
            }

            $this->creditsRepository()->changeMerchantMoney(
                (int)$refund->mer_id,
                bcsub('0.00', (string)$refund->refund_amount, 2),
                (string)$refund->refund_order_no,
                'recharge_refund',
                '购物金充值原路退款'
            );
            $refund->refund_time = date('Y-m-d H:i:s');
            $refund->save();
            $this->dao->search()
                ->where('order_no', $refund->order_no)
                ->where('mer_id', $refund->mer_id)
                ->update(['refund_status' => 2]);
        });
    }


    private function creditsRepository(): ShoppingCreditsRepository
    {
        return app()->make(ShoppingCreditsRepository::class);
    }

    private function makeOrderNo(string $prefix, int $uid): string
    {
        return $prefix . date('YmdHis') . $uid . random_int(1000, 9999);
    }
}
