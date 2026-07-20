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

namespace crmeb\listens\pay;

use app\common\repositories\store\order\StoreGroupOrderRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\user\UserBlindboxCabinetRepository;
use crmeb\interfaces\ListenerInterface;
use think\facade\Log;

/**
 * 盲盒订单支付成功入柜监听器
 * 在支付成功后，将盲盒订单中抽中的款式写入用户盒柜
 */
class BlindBoxPaySuccessListen implements ListenerInterface
{

    public function handle($data): void
    {
        $orderSn = $data['order_sn'] ?? '';
        if (!$orderSn) {
            return;
        }

        try {
            $groupOrder = app()->make(StoreGroupOrderRepository::class)->getWhere(['group_order_sn' => $orderSn]);
            if (!$groupOrder || $groupOrder->paid != 1) return;

            $orderList = app()->make(StoreOrderRepository::class)->search([
                'group_order_id' => $groupOrder->group_order_id,
            ])->select();

            foreach ($orderList as $order) {
                if (empty($order['is_blindbox_order'])) {
                    continue;
                }

                foreach ($order->orderProduct as $product) {
                    $skuId = $product['product_sku_id'] ?? 0;
                    if ($skuId <= 0) {
                        Log::error('盲盒入柜跳过：product_sku_id 无效', ['order_id' => $order['order_id'], 'product_id' => $product['product_id'] ?? 0]);
                        continue;
                    }

                    $cartInfo = json_decode($product['cart_info'], true);
                    if (!$cartInfo || !isset($cartInfo['productAttr'])) {
                        continue;
                    }

                    $cabinetRepo = app()->make(UserBlindboxCabinetRepository::class);

                    $existing = $cabinetRepo->getWhere([
                        'uid' => $order['uid'],
                        'order_id' => $order['order_id'],
                        'attr_value_id' => $skuId,
                    ]);

                    if ($existing) {
                        Log::info('盲盒入柜跳过（已存在）：uid=' . $order['uid'] . ', order_id=' . $order['order_id'] . ', sku_id=' . $skuId);
                        continue;
                    }

                    $cabinetData = [
                        'uid' => $order['uid'],
                        'product_id' => $product['product_id'],
                        'attr_value_id' => $skuId,
                        'sku_unique' => $cartInfo['productAttr']['unique'] ?? '',
                        'order_id' => $order['order_id'],
                        'quantity' => $product['product_num'] ?? 1,
                        'random_seed' => uniqid('bb_', true),
                    ];

                    $cabinetRepo->addToCabinet($cabinetData);

                    Log::info('盲盒入柜成功：uid=' . $order['uid'] . ', product_id=' . $product['product_id'] . ', attr_value_id=' . $product['product_sku_id']);

                    $this->checkCollectionAchievement($order['uid'], $product['product_id'], $cabinetRepo);
                }
            }
        } catch (\Exception $e) {
            Log::error('盲盒入柜失败：' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
        }
    }

    /**
     * 检查集齐成就
     */
    protected function checkCollectionAchievement(int $uid, int $productId, UserBlindboxCabinetRepository $cabinetRepo): void
    {
        $stats = $cabinetRepo->getCabinetStats($uid, $productId);

        if ($stats['totalCount'] > 0 && $stats['collectedCount'] >= $stats['totalCount']) {
            Log::info('用户' . $uid . '已集齐盲盒商品' . $productId . '的全部' . $stats['totalCount'] . '款！');
        }
    }
}
