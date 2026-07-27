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

use app\common\model\store\product\ProductAttrValue;
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
        try {
            // 兼容两种来源：
            // 1) order.paySuccess => ['groupOrder' => ...]（含模拟支付 / paySuccess 内抛出）
            // 2) pay_success_order => ['order_sn' => ...]（微信回调）
            $groupOrder = $data['groupOrder'] ?? null;
            if (!$groupOrder) {
                $orderSn = $data['order_sn'] ?? '';
                if (!$orderSn) {
                    return;
                }
                $groupOrder = app()->make(StoreGroupOrderRepository::class)->getWhere(['group_order_sn' => $orderSn]);
            }
            if (!$groupOrder || $groupOrder->paid != 1) {
                return;
            }

            $orderList = app()->make(StoreOrderRepository::class)->search([
                'group_order_id' => $groupOrder->group_order_id,
            ])->with(['orderProduct'])->select();

            foreach ($orderList as $order) {
                if (empty($order['is_blindbox_order'])) {
                    continue;
                }

                foreach ($order->orderProduct as $product) {
                    $cartInfo = $product['cart_info'] ?? [];
                    if (is_string($cartInfo)) {
                        $cartInfo = json_decode($cartInfo, true) ?: [];
                    }
                    if (!is_array($cartInfo) || empty($cartInfo['productAttr'])) {
                        Log::error('盲盒入柜跳过：cart_info.productAttr 缺失', [
                            'order_id' => $order['order_id'],
                            'product_id' => $product['product_id'] ?? 0,
                        ]);
                        continue;
                    }

                    $attr = $cartInfo['productAttr'];
                    // 订单商品表存的是 product_sku(=unique)，没有 product_sku_id
                    $skuId = (int)($attr['value_id'] ?? 0);
                    $skuUnique = (string)($attr['unique'] ?? ($product['product_sku'] ?? ''));
                    if ($skuId <= 0 && $skuUnique !== '') {
                        $skuId = (int)ProductAttrValue::where('unique', $skuUnique)->value('value_id');
                    }
                    if ($skuId <= 0) {
                        Log::error('盲盒入柜跳过：无法解析 attr_value_id', [
                            'order_id' => $order['order_id'],
                            'product_id' => $product['product_id'] ?? 0,
                            'product_sku' => $skuUnique,
                        ]);
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
                        'sku_unique' => $skuUnique,
                        'order_id' => $order['order_id'],
                        'quantity' => $product['product_num'] ?? 1,
                        'random_seed' => uniqid('bb_', true),
                    ];

                    $cabinetRepo->addToCabinet($cabinetData);

                    Log::info('盲盒入柜成功：uid=' . $order['uid'] . ', product_id=' . $product['product_id'] . ', attr_value_id=' . $skuId);

                    $this->checkCollectionAchievement((int)$order['uid'], (int)$product['product_id'], $cabinetRepo);
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
