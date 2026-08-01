<?php

namespace crmeb\listens\pay;

use app\common\repositories\store\nearby\WelfareFreeOrderRepository;
use app\common\repositories\store\order\StoreGroupOrderRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\interfaces\ListenerInterface;
use think\facade\Log;

/**
 * 公益网购享免单：商品订单支付成功后分账
 */
class WelfareFreePaySuccessListen implements ListenerInterface
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
                $groupOrder = app()->make(StoreGroupOrderRepository::class)->getWhere(['group_order_sn' => $orderSn]);
            }
            if (!$groupOrder || (int)$groupOrder->paid !== 1) {
                return;
            }

            $orderList = app()->make(StoreOrderRepository::class)->search([
                'group_order_id' => $groupOrder->group_order_id,
            ])->select();

            $repo = app()->make(WelfareFreeOrderRepository::class);
            $hit = 0;
            foreach ($orderList as $order) {
                if (empty($order['is_welfare_order'])) {
                    continue;
                }
                $hit++;
                Log::info('WelfareFreePaySuccessListen settle order_id=' . ($order['order_id'] ?? 0)
                    . ' scan_mer=' . ($order['welfare_scan_mer_id'] ?? 0)
                    . ' bill_amount=' . ($order['welfare_bill_amount'] ?? 0));
                $repo->settleOnPaySuccess($order);
            }
            if (!$hit) {
                Log::info('WelfareFreePaySuccessListen: no welfare order in group '
                    . ($groupOrder->group_order_id ?? 0));
            }
        } catch (\Throwable $e) {
            Log::error('WelfareFreePaySuccessListen: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }
}
