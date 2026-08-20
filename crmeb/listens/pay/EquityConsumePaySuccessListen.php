<?php

namespace crmeb\listens\pay;

use app\common\repositories\store\equity\EquityGrantRepository;
use app\common\repositories\store\order\StoreGroupOrderRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\interfaces\ListenerInterface;
use think\facade\Log;

/**
 * 商城/扫码点餐订单支付成功 → 消费送股
 */
class EquityConsumePaySuccessListen implements ListenerInterface
{
    public function handle($data): void
    {
        try {
            $groupOrder = $data['groupOrder'] ?? null;
            if (!$groupOrder) {
                $orderSn = $data['order_sn'] ?? '';
                if (!$orderSn) {
                    return;
                }
                // 充值入股单跳过
                if (strpos($orderSn, 'EQ') === 0 || strpos($orderSn, 'NB') === 0) {
                    return;
                }
                $groupOrder = app()->make(StoreGroupOrderRepository::class)->getWhere(['group_order_sn' => $orderSn]);
            }
            if (!$groupOrder || (int)$groupOrder->paid !== 1) {
                return;
            }

            $orderList = app()->make(StoreOrderRepository::class)->search([
                'group_order_id' => $groupOrder->group_order_id,
            ])->select();

            $grant = app()->make(EquityGrantRepository::class);
            foreach ($orderList as $order) {
                $uid = (int)($order['uid'] ?? 0);
                $merId = (int)($order['mer_id'] ?? 0);
                $payPrice = $order['pay_price'] ?? 0;
                if ($uid <= 0 || $merId <= 0) {
                    continue;
                }
                $grant->grantOnConsume(
                    $merId,
                    $uid,
                    $payPrice,
                    (string)($order['order_id'] ?? ''),
                    'order'
                );
            }
        } catch (\Throwable $e) {
            Log::error('EquityConsumePaySuccessListen: ' . $e->getMessage());
        }
    }
}
