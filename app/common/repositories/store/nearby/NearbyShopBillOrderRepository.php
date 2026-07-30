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

namespace app\common\repositories\store\nearby;

use app\common\dao\store\nearby\NearbyShopBillOrderDao;
use app\common\model\store\nearby\NearbyShopBillOrder;
use app\common\model\store\order\StoreGroupOrder;
use app\common\model\store\order\StoreOrder;
use app\common\model\store\order\StoreOrderProduct;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use think\facade\Db;
use think\facade\Log;

class NearbyShopBillOrderRepository extends BaseRepository
{
    public function __construct(NearbyShopBillOrderDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建买单订单
     */
    public function createBillOrder(array $data)
    {
        $orderData = [
            'order_sn' => $this->dao->makeOrderSn(),
            'uid' => (int)($data['uid'] ?? 0),
            'mer_id' => $data['mer_id'],
            'pay_price' => $data['pay_price'],
            'order_price' => $data['pay_price'],
            'pay_type' => $data['pay_type'],
            'status' => 0,
            'paid' => 0,
        ];

        if (!empty($data['coupon_id'])) {
            $orderData['coupon_id'] = $data['coupon_id'];
        }

        return $this->dao->create($orderData);
    }

    /**
     * 支付成功回调处理
     */
    public function paySuccess($orderSn, $payType = 'weixin', $transactionId = '')
    {
        $order = $this->dao->getWhere(['order_sn' => $orderSn]);
        if (!$order || $order['paid'] == 1) {
            return false;
        }

        $updateData = [
            'paid' => 1,
            'status' => 1,
            'pay_time' => time(),
            'pay_type' => $payType,
        ];

        // 幂等保护：WHERE paid=0 防止并发回调重复处理
        $affected = NearbyShopBillOrder::getDB()
            ->where('id', $order['id'])
            ->where('paid', 0)
            ->update($updateData);

        if (!$affected) {
            Log::info('NearbyBill paySuccess: already paid ' . $orderSn);
            return $order;
        }

        $order = $this->dao->getWhere(['order_sn' => $orderSn]);

        try {
            $this->recordFinancial($order, $updateData);
        } catch (\Exception $e) {
            Log::error('NearbyBill paySuccess financial record failed: ' . $e->getMessage());
        }

        // Demo：同步写入商城订单，便于用户订单列表展示
        try {
            $this->syncStoreOrder($order, $payType);
        } catch (\Exception $e) {
            Log::error('NearbyBill syncStoreOrder failed: ' . $e->getMessage());
        }

        return $order;
    }

    /**
     * 记录财务流水：买单金额进入商户账户
     */
    protected function recordFinancial($order, $updateData)
    {
        $merId = (int)($order['mer_id'] ?? 0);
        $amount = (float)($order['pay_price'] ?? 0);
        if ($merId <= 0 || $amount <= 0) {
            return;
        }

        $payType = (string)($updateData['pay_type'] ?? $order['pay_type'] ?? 'weixin');
        $payTypeIndex = array_search($payType, StoreOrderRepository::PAY_TYPE, true);
        if ($payTypeIndex === false) {
            $payTypeIndex = 10;
        }

        // 商户入账（冻结期走 lock，否则直接余额）
        app()->make(MerchantRepository::class)->addLockMoney(
            $merId,
            'order',
            (int)$order['id'],
            $amount
        );

        $userInfo = 'uid:' . (int)($order['uid'] ?? 0);
        try {
            if (!empty($order['uid'])) {
                $nick = \app\common\model\user\User::getDB()->where('uid', (int)$order['uid'])->value('nickname');
                if ($nick) $userInfo = (string)$nick;
            }
        } catch (\Throwable $e) {}

        app()->make(\app\common\dao\system\merchant\FinancialRecordDao::class)->inc([
            'order_id' => (int)$order['id'],
            'order_sn' => (string)$order['order_sn'],
            'user_info' => $userInfo,
            'user_id' => (int)($order['uid'] ?? 0),
            'financial_type' => 'nearby_bill',
            'type' => 1,
            'number' => $amount,
            'pay_type' => (int)$payTypeIndex,
        ], $merId);

        Log::info('NearbyBill financial in: sn=' . $order['order_sn'] . ' mer_id=' . $merId . ' amount=' . $amount . ' pay_type=' . $payType);
    }

    /**
     * 买单成功后写入 store_order，合并进用户订单列表
     */
    protected function syncStoreOrder($billOrder, $payType = 'weixin')
    {
        $uid = (int)($billOrder['uid'] ?? 0);
        if ($uid <= 0) {
            // 未登录买单：无法挂到用户订单列表
            return null;
        }

        $exists = StoreOrder::getDB()
            ->where('uid', $uid)
            ->where('mark', '扫码买单:' . ($billOrder['order_sn'] ?? ''))
            ->find();
        if ($exists) {
            return $exists;
        }

        $orderRepository = app()->make(StoreOrderRepository::class);
        $merchant = app()->make(MerchantRepository::class)->get((int)$billOrder['mer_id']);
        $amount = (string)$billOrder['pay_price'];
        $payTypeIndex = array_search($payType, StoreOrderRepository::PAY_TYPE, true);
        if ($payTypeIndex === false) {
            $payTypeIndex = array_search('routine', StoreOrderRepository::PAY_TYPE, true);
        }
        if ($payTypeIndex === false) {
            $payTypeIndex = 2;
        }

        $image = $merchant['mer_avatar'] ?? ($merchant['mer_banner'] ?? '');
        $storeName = ($merchant['mer_name'] ?? '商家') . ' · 扫码买单';
        $payTime = is_numeric($billOrder['pay_time'] ?? null)
            ? (int)$billOrder['pay_time']
            : time();

        $payTimeStr = date('Y-m-d H:i:s', $payTime);

        return Db::transaction(function () use (
            $orderRepository,
            $billOrder,
            $uid,
            $amount,
            $payTypeIndex,
            $image,
            $storeName,
            $payTimeStr
        ) {
            $groupOrderSn = $orderRepository->getNewOrderId(StoreOrderRepository::TYPE_SN_ORDER) . '0';
            $orderSn = $orderRepository->getNewOrderId(StoreOrderRepository::TYPE_SN_ORDER) . '1';

            $group = StoreGroupOrder::create([
                'uid' => $uid,
                'group_order_sn' => $groupOrderSn,
                'total_postage' => 0,
                'total_price' => $amount,
                'total_num' => 1,
                'pay_price' => $amount,
                'coupon_price' => 0,
                'pay_postage' => 0,
                'cost' => 0,
                'real_name' => '扫码顾客',
                'user_phone' => '',
                'user_address' => '',
                'paid' => 1,
                'pay_time' => $payTimeStr,
                'pay_type' => $payTypeIndex,
                'activity_type' => 0,
                'is_remind' => 0,
                'is_del' => 0,
            ]);

            $order = StoreOrder::create([
                'order_sn' => $orderSn,
                'uid' => $uid,
                'spread_uid' => 0,
                'group_order_id' => $group['group_order_id'],
                'mer_id' => (int)$billOrder['mer_id'],
                'real_name' => '扫码顾客',
                'user_phone' => '',
                'user_address' => '',
                'cart_id' => '',
                'total_num' => 1,
                'total_price' => $amount,
                'total_postage' => 0,
                'pay_price' => $amount,
                'pay_postage' => 0,
                'commission_rate' => 0,
                'extension_one' => 0,
                'extension_two' => 0,
                'coupon_id' => '',
                'coupon_price' => 0,
                'platform_coupon_price' => 0,
                'svip_discount' => 0,
                'cost' => 0,
                'integral' => 0,
                'integral_price' => 0,
                'give_integral' => 0,
                'pay_type' => $payTypeIndex,
                'paid' => 1,
                'pay_time' => $payTimeStr,
                'status' => 3,
                'order_type' => 1,
                'is_virtual' => 1,
                'activity_type' => 0,
                'is_del' => 0,
                'is_system_del' => 0,
                'mark' => '扫码买单:' . ($billOrder['order_sn'] ?? ''),
                'remark' => '扫码买单',
                'task_id' => '',
                'kuaidi_order_id' => '',
                'merchant_take_info' => '',
            ]);

            StoreOrderProduct::create([
                'order_id' => $order['order_id'],
                'uid' => $uid,
                'cart_id' => 0,
                'product_id' => 0,
                'product_sku' => 'scan_pay',
                'is_refund' => 0,
                'product_num' => 1,
                'product_type' => 0,
                'activity_id' => 0,
                'refund_num' => 1,
                'is_reply' => 0,
                'total_price' => $amount,
                'product_price' => $amount,
                'extension_one' => 0,
                'extension_two' => 0,
                'coupon_price' => 0,
                'platform_coupon_price' => 0,
                'postage_price' => 0,
                'svip_discount' => 0,
                'cost' => 0,
                'integral' => 0,
                'integral_price' => 0,
                'integral_total' => 0,
                'reservation_time_part' => '',
                'settlement_price' => 0,
                'cart_info' => json_encode([
                    'product' => [
                        'store_name' => $storeName,
                        'image' => $image,
                        'product_id' => 0,
                    ],
                    'productAttr' => [
                        'price' => $amount,
                        'image' => $image,
                        'sku' => '扫码买单',
                    ],
                    'product_type' => 0,
                    'cart_num' => 1,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            return $order;
        });
    }
}
