<?php

namespace app\common\repositories\merchant;

use app\common\dao\merchant\MerchantMemberDao;
use app\common\dao\store\order\StoreGroupOrderDao;
use app\common\dao\store\order\StoreRefundOrderDao;
use app\common\dao\user\UserShoppingCreditsDao;
use app\common\dao\user\UserShoppingCreditsRecordDao;
use app\common\model\store\order\StoreGroupOrder;
use app\common\model\store\order\StoreOrder;
use app\common\model\user\User;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 购物金商城订单支付与商户结算处理。
 */
class ShoppingCreditsSettlementRepository extends BaseRepository
{
    private MerchantMemberDao $memberDao;
    private UserShoppingCreditsDao $shoppingCreditsDao;
    private UserShoppingCreditsRecordDao $shoppingCreditsRecordDao;
    private StoreGroupOrderDao $groupOrderDao;
    private StoreRefundOrderDao $storeRefundOrderDao;

    public function __construct(
        MerchantMemberDao $memberDao,
        UserShoppingCreditsDao $shoppingCreditsDao,
        UserShoppingCreditsRecordDao $shoppingCreditsRecordDao,
        StoreGroupOrderDao $groupOrderDao,
        StoreRefundOrderDao $storeRefundOrderDao
    ) {
        $this->memberDao = $memberDao;
        $this->shoppingCreditsDao = $shoppingCreditsDao;
        $this->shoppingCreditsRecordDao = $shoppingCreditsRecordDao;
        $this->groupOrderDao = $groupOrderDao;
        $this->storeRefundOrderDao = $storeRefundOrderDao;
    }
    public function payStoreOrder(User $user, StoreGroupOrder $groupOrder, StoreOrderRepository $orderRepository): array
    {
        $result = [];
        Db::transaction(function () use ($user, $groupOrder, $orderRepository, &$result) {
            $groupOrder = $this->groupOrderDao->query([])
                ->where('group_order_id', $groupOrder->group_order_id)
                ->where('uid', $user->uid)
                ->lock(true)
                ->find();
            if (!$groupOrder) {
                throw new ValidateException('订单不存在');
            }
            if ((int)$groupOrder->paid) {
                $shoppingCreditsPayType = array_search(ShoppingCreditsRepository::PAY_TYPE, StoreOrderRepository::PAY_TYPE, true);
                if ((int)$groupOrder->pay_type !== $shoppingCreditsPayType) {
                    throw new ValidateException('订单已支付');
                }
                $result = ['order_id' => $groupOrder->group_order_id, 'pay_price' => $groupOrder->pay_price];
                return;
            }

            $groupOrder->append(['orderList']);
            if (count($groupOrder->orderList) !== 1) {
                throw new ValidateException('购物金仅支持单店铺订单');
            }
            $order = $groupOrder->orderList[0];
            $member = $this->memberDao->search()
                ->where('uid', $user->uid)
                ->where('mer_id', $order->mer_id)
                ->where('is_logoff', 0)
                ->find();
            if (!$member) {
                throw new ValidateException('请先加入该店铺会员');
            }
            if (!(int)$member->financial_status) {
                throw new ValidateException('购物金已冻结，请联系商家');
            }

            $asset = $this->shoppingCreditsDao->search()
                ->where('uid', $user->uid)
                ->where('mer_id', $order->mer_id)
                ->lock(true)
                ->find();
            $rechargeBalance = (string)($asset->recharge_amount ?? '0.00');
            $giftBalance = (string)($asset->gift_amount ?? '0.00');
            $balance = bcadd($rechargeBalance, $giftBalance, 2);
            if (bccomp($balance, (string)$groupOrder->pay_price, 2) < 0) {
                throw new ValidateException('购物金余额不足');
            }
            $recharge = bccomp($rechargeBalance, (string)$groupOrder->pay_price, 2) >= 0
                ? (string)$groupOrder->pay_price : $rechargeBalance;
            $gift = bcsub((string)$groupOrder->pay_price, $recharge, 2);

            $this->creditsRepository()->changeBalance(
                (int)$user->uid,
                (int)$order->mer_id,
                $recharge,
                $gift,
                2,
                (string)$order->order_sn,
                'order',
                '购物金支付扣除'
            );
            $groupOrder->pay_type = array_search(ShoppingCreditsRepository::PAY_TYPE, StoreOrderRepository::PAY_TYPE, true);
            $groupOrder->save();
            foreach ($groupOrder->orderList as $item) {
                $item->pay_type = $groupOrder->pay_type;
                $item->save();
            }
            $orderRepository->paySuccess($groupOrder);
            $result = ['order_id' => $groupOrder->group_order_id, 'pay_price' => $groupOrder->pay_price];
        });
        return $result;
    }

    /**
     * 购物金充值款已经在充值成功时进入商户余额，商品支付只结算补贴和费用。
     */
    public function settleStoreOrderMerchant(StoreOrder $order, string $commissionAmount): void
    {
        $brokerageAmount = bcadd((string)$order->extension_one, (string)$order->extension_two, 2);
        $feeAmount = bcadd($brokerageAmount, $commissionAmount, 2);
        $adjustment = bcsub((string)$order->platform_coupon_price, $feeAmount, 2);
        $this->creditsRepository()->changeMerchantMoney(
            (int)$order->mer_id,
            $adjustment,
            (string)$order->order_sn,
            'order',
            '购物金商品订单补贴及费用结算'
        );
    }

    /**
     * 商城商品退款时退回购物金（优先退赠送金再退本金），幂等处理防止重复入账，校验累计退款不超过支付流水
     * @param int $uid 用户ID
     * @param int $merId 商户ID
     * @param string $orderSn 商城订单号
     * @param string $refundPrice 退款金额
     * @param string $refundNo 退款单号
     */
    public function refundStoreOrder(int $uid, int $merId, string $orderSn, string $refundPrice, string $refundNo): void
    {
        Db::transaction(function () use ($uid, $merId, $orderSn, $refundPrice, $refundNo) {
            $asset = $this->shoppingCreditsDao->search()
                ->where('uid', $uid)
                ->where('mer_id', $merId)
                ->lock(true)
                ->find();
            if (!$asset) {
                throw new ValidateException('购物金账户不存在');
            }

            $payRecord = $this->shoppingCreditsRecordDao->search()
                ->where('uid', $uid)->where('mer_id', $merId)
                ->where('link_no', $orderSn)->where('link_type', 'order')->where('type', 2)->find();
            if (!$payRecord) {
                throw new ValidateException('购物金支付流水不存在');
            }

            // 同一购物金账户的退款会被余额行锁串行化，重试只会入账一次。
            $refundRecord = $this->shoppingCreditsRecordDao->search()
                ->where('uid', $uid)
                ->where('mer_id', $merId)
                ->where('link_no', $refundNo)
                ->where('link_type', 'order_refund')
                ->where('type', 1)
                ->find();
            if ($refundRecord) {
                return;
            }

            $previousRefundNos = $this->storeRefundOrderDao->query([])->alias('r')
                ->join('store_order o', 'o.order_id = r.order_id')
                ->where('o.order_sn', $orderSn)
                ->where('r.status', 3)
                ->where('r.refund_order_sn', '<>', $refundNo)
                ->column('r.refund_order_sn');
            $refundedRecharge = '0.00';
            $refundedGift = '0.00';
            if ($previousRefundNos) {
                $refundQuery = $this->shoppingCreditsRecordDao->search()
                    ->where('uid', $uid)
                    ->where('mer_id', $merId)
                    ->where('link_type', 'order_refund')
                    ->where('type', 1)
                    ->whereIn('link_no', $previousRefundNos);
                $refundedRecharge = (string)((clone $refundQuery)->sum('recharge_amount') ?: '0.00');
                $refundedGift = (string)((clone $refundQuery)->sum('gift_amount') ?: '0.00');
            }

            $remainingRecharge = max(bcsub((string)$payRecord->recharge_amount, $refundedRecharge, 2), '0.00');
            $remainingGift = max(bcsub((string)$payRecord->gift_amount, $refundedGift, 2), '0.00');
            if (bccomp(bcadd($remainingRecharge, $remainingGift, 2), $refundPrice, 2) < 0) {
                throw new ValidateException('购物金可退款金额不足');
            }

            // 与 Java 版保持一致：退款时优先退赠送金，再退充值本金。
            $gift = bccomp($remainingGift, $refundPrice, 2) >= 0 ? $refundPrice : $remainingGift;
            $recharge = bcsub($refundPrice, $gift, 2);
            $this->creditsRepository()->changeBalance($uid, $merId, $recharge, $gift, 1, $refundNo, 'order_refund', '商品订单购物金退款');
        });
    }

    /**
     * 购物金商品退款时返还商户抽佣及分销费用（退款金额与商户退款金额之差，不为负数）
     * @param int $merId 商户ID
     * @param string $refundNo 退款单号
     * @param string $refundPrice 商品退款总金额
     * @param string $merchantRefundPrice 商户实际退款金额
     */
    public function refundStoreOrderMerchantFees(
        int $merId,
        string $refundNo,
        string $refundPrice,
        string $merchantRefundPrice
    ): void {
        $feeAmount = bcsub($refundPrice, $merchantRefundPrice, 2);
        if (bccomp($feeAmount, '0.00', 2) < 0) {
            $feeAmount = '0.00';
        }
        $this->creditsRepository()->changeMerchantMoney(
            $merId,
            $feeAmount,
            $refundNo,
            'order_refund_fee',
            '购物金商品退款返还抽佣及分销费用'
        );
    }

    /**
     * 购物金商品退款时撤回平台券补贴金额（从商户余额扣减）
     * @param int $merId 商户ID
     * @param string $refundNo 退款单号
     * @param string $platformRefundPrice 平台券补贴退款金额
     */
    public function refundStoreOrderPlatformCoupon(
        int $merId,
        string $refundNo,
        string $platformRefundPrice
    ): void {
        $this->creditsRepository()->changeMerchantMoney(
            $merId,
            bcsub('0.00', $platformRefundPrice, 2),
            $refundNo,
            'order_refund_platform_coupon',
            '购物金商品退款撤回平台券补贴'
        );
    }


    private function creditsRepository(): ShoppingCreditsRepository
    {
        return app()->make(ShoppingCreditsRepository::class);
    }
}
