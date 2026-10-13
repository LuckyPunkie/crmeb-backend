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
namespace app\common\repositories\store\order;

use think\facade\Db;
use think\facade\Cache;
use think\facade\Queue;
use crmeb\jobs\SendSmsJob;
use crmeb\services\PayStatusService;
use crmeb\services\SwooleTaskService;
use think\exception\ValidateException;
use app\common\repositories\system\RecordRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\store\coupon\StoreCouponUserRepository;
use app\common\repositories\store\product\{
    ProductAttrValueRepository,
    ProductRepository
};
use app\common\repositories\user\{
    UserAddressRepository,
    UserBillRepository,
    UserRepository
};

class MerchantOrderCreateRepository extends StoreOrderRepository
{
    use MerchantOrderTrait;

    const CACHE_KEY = 'order_create_cache';

    private function getUserRepository(): UserRepository
    {
        return app()->make(UserRepository::class);
    }
    private function getUserAddressRepository(): UserAddressRepository
    {
        return app()->make(UserAddressRepository::class);
    }
    private function getStoreCartRepository(): StoreCartRepository
    {
        return app()->make(StoreCartRepository::class);
    }
    private function getStoreCouponUserRepository(): StoreCouponUserRepository
    {
        return app()->make(StoreCouponUserRepository::class);
    }
    private function getMerchantRepository(): MerchantRepository
    {
        return app()->make(MerchantRepository::class);
    }
    private function getProductRepository(): ProductRepository
    {
        return app()->make(ProductRepository::class);
    }
    private function getProductAttrValueRepository(): ProductAttrValueRepository
    {
        return app()->make(ProductAttrValueRepository::class);
    }
    private function getStoreGroupOrderRepository(): StoreGroupOrderRepository
    {
        return app()->make(StoreGroupOrderRepository::class);
    }
    private function getUserBillRepository(): UserBillRepository
    {
        return app()->make(UserBillRepository::class);
    }
    private function getStoreOrderStatusRepository(): StoreOrderStatusRepository
    {
        return app()->make(StoreOrderStatusRepository::class);
    }
    private function getStoreOrderProductRepository(): StoreOrderProductRepository
    {
        return app()->make(StoreOrderProductRepository::class);
    }
    private function getRecordRepository(): RecordRepository
    {
        return app()->make(RecordRepository::class);
    }
    public function merchantInfo($merId)
    {
        $merchant = $this->getMerchantRepository()->get($merId);
        if (!$merchant) {
            throw new ValidateException('商家不存在');
        }
        return $merchant;
    }
    /**
     * 检查订单
     *
     * @return array
     */
    public function checkOrder(array $params): ?array
    {
        $result = [];
        $uid = $params['uid'];
        $key = md5(json_encode($params)) . $uid;
        // 校验用户信息
        $user = $this->validUser($uid);
        $svipStatus = (isset($user) && $user['is_svip'] > 0 && systemConfig('svip_switch_status') == '1');
        $userIntegral = $user['integral'] ?? 0;
        // 校验地址信息
        $address = $this->validAddress($params['address_id'], $uid);
        // 校验购物车信息
        $cartList = $this->validCartList($user, $params['merId'], $params['cart_ids'], $address);
        // 获取优惠券信息
        $userCoupons = $this->fetchUserCoupons($uid, $params['merId']);
        // 处理运费模板
        $cartProductShippingFeeTemplate = $this->template(array_column(array_column($cartList, 'product'), 'temp'));

        $result['totalCartNum'] = 0;
        $result['totalPaymentPrice'] = 0;
        $result['totalOriginalPrice'] = 0;
        $result['updatedPriceFlag'] = 0;
        $result['discountDetail']['updatedDiscountAmount'] = 0;
        $result['discountDetail']['vipDiscountAmount'] = 0;
        foreach ($cartList as &$value) {
            $cartNum = $value['cart_num'];
            $value['productAttr']['org_price'] = $value['productAttr']['price'];
            $value['productAttr']['show_svip_price'] = false;
            // 原价
            $value['totalAmount'] = (float)bcmul($value['productAttr']['price'], $cartNum, 2);
            // 改价后金额
            $updatedPriceFlag = (isset($value['productAttr']['new_price']) || isset($value['updatedTotalAmount'])) ? 1 : 0;
            if($updatedPriceFlag) {
                $value['updatedTotalAmount'] = $value['is_batch'] ? (float)$value['updatedTotalAmount'] : (float)bcmul($value['productAttr']['new_price'], $cartNum, 2);
            }
            // 会员权益后的金额
            $value['vipTotalAmount'] = ($svipStatus && !$updatedPriceFlag) ? (float)bcmul($value['productAttr']['svip_price'], $cartNum, 2) : 0;
            $value['productAttr']['show_svip_price'] = ($svipStatus && !$updatedPriceFlag) ? true : false;
            // 是否使用会员权益
            $value['useSvip'] = ($value['vipTotalAmount'] != 0) ? 1 : 0;
            // 实付价格：优先取改价后金额，若无改价后金额则取会员权益后的总金额，若无会员权益后的总金额则取原价
            $value['paymentPrice'] = ($updatedPriceFlag) ? $value['updatedTotalAmount'] : current(array_filter([$value['updatedTotalAmount'], $value['vipTotalAmount'], $value['totalAmount']]));
            // 计算优惠金额
            $value['updatedDiscountAmount'] = ($updatedPriceFlag) ? (float)bcsub($value['totalAmount'], $value['updatedTotalAmount'], 2) : 0;
            $value['vipDiscountAmount'] = (!$updatedPriceFlag && $value['vipTotalAmount'] != 0) ? (float)bcsub($value['totalAmount'], $value['vipTotalAmount'], 2) : 0;
            // 不免运费且为快递配送，则计算该商品的运费。【is_free_shipping : 0 不免运费，1 免运费】【delivery_way : 0 快递，1 自提】
            if ($params['is_free_shipping'] == 0 && $params['delivery_way'] == 0) {
                $shippingFeeTemplate = $value['product']['temp'] ? $value['product']['temp']->toArray() : null;
                // 判断是否符合免运费条件,free为空说明不免费，则计算运费
                if ($shippingFeeTemplate && empty($shippingFeeTemplate['free'])) {
                    $cartProductShippingFeeTemplate[$value['product']['temp_id']]['aggregate'] += $this->productByTempNumber($value);
                }
            }

            $value['productAttr']['price'] = current(array_filter([$value['productAttr']['new_price'], $value['productAttr']['new_batch_price'], $value['productAttr']['svip_price'], $value['productAttr']['price']]));
            $result['totalCartNum'] += $cartNum;
            // 原价总金额
            $result['totalOriginalPrice'] += $value['totalAmount'];
            $result['totalPaymentPrice'] += $value['paymentPrice'];
            $result['discountDetail']['updatedDiscountAmount'] += $value['updatedDiscountAmount'];
            $result['discountDetail']['vipDiscountAmount'] += $value['vipDiscountAmount'];
        }

        // 判断改价，单商品改价标记为1，整单改价标记为2
        $cartUpdatePrice = array_column($cartList, 'is_batch');
        if($cartUpdatePrice) {
            $result['updatedPriceFlag'] = (in_array(0, $cartUpdatePrice)) ? 1 : 2;
        }
        $result['totalPrice'] = $result['totalPaymentPrice'];
        // 计算优惠券
        $result = $this->calculateCoupon($result, $cartList, $userCoupons, $params['merId'], $params['use_coupon'], $user);
        // 计算积分
        $result = $this->calculateIntegral($result, $cartList, $params['merId'], $params['use_integral'], $userIntegral);
        // 计算运费
        $result['totalShippingAmount'] = $address ? (float)$this->calculateShippingCost($cartProductShippingFeeTemplate) : 0;
        // 总优惠金额
        $result['totalDiscountAmount'] = array_sum($result['discountDetail']);
        // 支付总金额：实付总金额 + 运费
        $result['totalPaymentPrice'] = (float)bcadd($result['totalPaymentPrice'], $result['totalShippingAmount'], 2);
        $result['key'] = $key;
        $result['carts'] = $cartList;
        $result['address'] = $address;

        Cache::set(self::CACHE_KEY . $uid . '_' . $key, $result, 600);
        return $result;
    }
    /**
     * 创建订单
     *
     * @param array $params
     * @return void
     */
    public function createOrder(array $params)
    {
        $uid = $params['uid'];
        $cacheKey = self::CACHE_KEY . $uid . '_' . $params['key'];
        $orderInfo = Cache::get($cacheKey);
        if (!$orderInfo) {
            throw new ValidateException('订单操作超时,请刷新页面');
        }
        // 验证价格
        if ($params['old_pay_price'] != $orderInfo['totalPaymentPrice']) {
            throw new ValidateException('支付金额异常，参数信息与缓存不一致');
        }
        if ($orderInfo['totalPaymentPrice'] > 1000000) {
            throw new ValidateException('支付金额超出最大限制');
        }
        // 组装优惠ids, 验证use_coupon
        $usedCouponIds = [];
        $usedCoupons = isset($orderInfo['usedCoupons']) ? $orderInfo['usedCoupons'] : [];
        foreach ($usedCoupons as $value) {
            $usedCouponIds = array_unique(array_merge($usedCouponIds, array_column($value, 'id')));
        }
        // 验证address
        $address = $orderInfo['address'];
        if(!$address && $params['address_id']) {
            $address = $this->validAddress($params['address_id'], $uid);
        }
        $userAddress = isset($address) ? ($address['province'] . $address['city'] . $address['district'] . $address['street'] . $address['detail']) : '';
        // 计算成本金额
        $cost = 0;
        foreach ($orderInfo['carts'] as $cart) {
            $cost += bcmul($cart['productAttr']['cost'], $cart['cart_num']);
        }
        // 订单数据
        $groupOrder = [
            'uid' => $uid,
            'group_order_sn' => ($this->getNewOrderId(StoreOrderRepository::TYPE_SN_ORDER) . '0'),
            'total_postage' => $orderInfo['totalShippingAmount'],
            'total_price' => $orderInfo['totalPrice'],
            'total_num' => $orderInfo['totalCartNum'],
            'real_name' => $address['real_name'] ?? '',
            'user_phone' => $address['phone'] ?? '',
            'user_address' => $userAddress,
            'pay_price' => $orderInfo['totalPaymentPrice'],
            'coupon_price' => $orderInfo['discountDetail']['couponsDiscountAmount'] ?? 0,
            'pay_postage' => $orderInfo['totalShippingAmount'],
            'cost' => $cost,
            'coupon_id' => (isset($orderInfo['usedCoupons']['platform'][0]['id']) ? $orderInfo['usedCoupons']['platform'][0]['id'] : 0),
            'pay_type' => array_search($params['pay_type'], MerchantOrderCreateRepository::PAY_TYPE),
            'integral' => $orderInfo['integral']['usedNum'] ?? 0,
            'integral_price' => $orderInfo['discountDetail']['integralDiscountAmount'] ?? 0,
            'is_behalf' => 1 // 代客下单订单标识
        ];
        // 子订单数据
        $order = [
            'order_sn' => ($this->getNewOrderId(StoreOrderRepository::TYPE_SN_ORDER) . '1'),
            'uid' => $uid,
            'real_name' => $address['real_name'] ?? '',
            'user_phone' => $address['phone'] ?? '',
            'user_address' => $userAddress,
            'cart_id' => implode(',', array_column($orderInfo['carts'], 'cart_id')),
            'total_num' => $orderInfo['totalCartNum'],
            'total_price' => $orderInfo['totalPrice'],
            'total_postage' => $orderInfo['totalShippingAmount'],
            'pay_price' => $orderInfo['totalPaymentPrice'],
            'pay_postage' => $orderInfo['totalShippingAmount'],
            'commission_rate' => $orderInfo['commission_switch'] ? $orderInfo['commission_rate'] : 0,
            'integral' => $orderInfo['integral']['usedNum'] ?? 0,
            'integral_price' => $orderInfo['discountDetail']['integralDiscountAmount'] ?? 0,
            'coupon_id' => implode(',', $usedCouponIds),
            'coupon_price' => $orderInfo['discountDetail']['couponsDiscountAmount'] ?? 0,
            'platform_coupon_price' => array_sum(array_column(isset($orderInfo['usedCoupons']['platform']) ? $orderInfo['usedCoupons']['platform'] : [], 'price')) ?? 0,
            'svip_discount' => $orderInfo['discountDetail']['vipDiscountAmount'] ?? 0,
            'order_type' => $params['delivery_way'] == 4 ? 1 : $params['delivery_way'], // 0:快递 1:自提 4:无需核销（前端融合在一个字段里，在此处拆分开）
            'behalf_no_verify' => $params['delivery_way'] == 4 ? 1: 0, // 代客下单订单是否无需核销
            'is_virtual' => 0,
            'mer_id' => $params['merId'],
            'mark' => $params['mark'],
            'cost' => $cost,
            'pay_type' => array_search($params['pay_type'], MerchantOrderCreateRepository::PAY_TYPE),
            'is_behalf' => 1 // 代客下单订单标识
        ];

        $carts = $orderInfo['carts'];
        $user = $this->validUser($uid);
        event('order.create.before', compact('groupOrder', 'order'));
        try {
            [$group, $storeOrder] = Db::transaction(function () use ($user, $params, $order, $groupOrder, $carts) {
                $orderProduct = [];
                $productRepository = $this->getProductRepository();
                $productAttrValueRepository = $this->getProductAttrValueRepository();
                // 1. 减库存,加销量,加使用积分抵扣金额总数 product_attr, attr_value,  product
                $remainingPrice = $groupOrder['pay_price'];
                foreach ($carts as $key => $cart) {
                    $productAttrValueRepository->descStock($cart['productAttr']['product_id'], $cart['productAttr']['unique'], $cart['cart_num']);
                    $productRepository->descStock($cart['product']['product_id'], $cart['cart_num']);
                    if ($cart['integral'] && $cart['integral']['use'] > 0) {
                        $productRepository->incIntegral($cart['product']['product_id'], $cart['integral']['use'], $cart['integral']['price']);
                    }
                    $orderProduct[] = [
                        'cart_id' => $cart['cart_id'],
                        'uid' => $user->uid ?? 0,
                        'product_id' => $cart['product_id'],
                        'total_price' => $cart['totalAmount'],
                        'product_price' => $cart['paymentPrice'],
                        'svip_discount' => $cart['vipTotalAmount'],
                        'cost' => $cart['productAttr']['cost'],
                        'coupon_price' => $cart['couponsDiscount'],
                        'platform_coupon_price' => $cart['platformCouponDiscountAmount'],
                        'product_sku' => $cart['productAttr']['unique'],
                        'product_num' => $cart['cart_num'],
                        'refund_num' => $cart['cart_num'],
                        'integral_price' => $cart['integral']['price'] ?? 0,
                        'integral' => $cart['integral']['use'] ?? 0,
                        'integral_total' => $cart['integral']['price'] ?? 0,
                        'product_type' => $cart['product_type'],
                        'cart_info' => json_encode($cart),
                        'refund_switch' => $cart['product']['refund_switch'],
                    ];
                }
                // 2. 修改购物车状态 store_cart
                $cartIds = array_column($carts, 'cart_id');
                $this->getStoreCartRepository()->updates($cartIds, ['is_pay' => 1]);
                // 3. 使用优惠券 user_coupon
                if (!empty($order['coupon_id'])) {
                    $this->getStoreCouponUserRepository()->updates(explode(',', $order['coupon_id']), [
                        'use_time' => date('Y-m-d H:i:s'),
                        'status' => 1
                    ]);
                }
                // 4. 主订单表 group_order
                $groupOrder = $this->getStoreGroupOrderRepository()->create($groupOrder);
                // 5. 生成账单记录，减积分 user, user_bill
                if ($groupOrder['integral'] > 0) {
                    // 生成用户账单记录
                    $this->getUserBillRepository()->decBill($user['uid'], 'integral', 'deduction', [
                        'link_id' => $groupOrder['group_order_id'],
                        'status' => 1,
                        'title' => '购买商品',
                        'number' => $groupOrder['integral'],
                        'mark' => '购买商品使用积分抵扣' . floatval($groupOrder['integral_price']) . '元',
                        'balance' => $user->integral
                    ]);
                    // 减用户积分
                    $user->integral = bcsub($user->integral, $groupOrder['integral'], 0);
                    $user->save();
                }
                // 6. 子订单表 order
                $order['group_order_id'] = $groupOrder->group_order_id;
                $storeOrder = $this->dao->create($order);
                // 7. 订单操作记录 order_status
                $orderStatus[] = [
                    'order_id' => $storeOrder->order_id,
                    'order_sn' => $storeOrder->order_sn,
                    'type' => StoreOrderStatusRepository::TYPE_ORDER,
                    'change_message' => '订单生成',
                    'change_type' => StoreOrderStatusRepository::ORDER_STATUS_CREATE,
                    'uid' => $user->uid ?? 0,
                    'nickname' => $user->nickname ?? '游客',
                    'user_type' => StoreOrderStatusRepository::U_TYPE_USER,
                ];
                $this->getStoreOrderStatusRepository()->batchCreateLog($orderStatus);
                // 8. 订单商品记录 order_product
                foreach ($orderProduct as $key => &$value) {
                    $value['order_id'] = $storeOrder->order_id;
                    $value['postage_price'] = $key == 0 ? $groupOrder['total_postage'] : 0;
                }
                $this->getStoreOrderProductRepository()->insertAll($orderProduct);
                // 9. 记录地址使用次数
                if ($params['address_id']) {
                    $this->getRecordRepository()->addRecord(
                        RecordRepository::TYPE_ADDRESS_RECORD,
                        [
                            'address_id' => $params['address_id'],
                            'num' => 1
                        ]
                    );
                }

                event('order.create', compact('groupOrder'));
                return [$groupOrder, $storeOrder];
            });
        } catch (\Exception $e) {
            throw new ValidateException($e->getMessage());
        };
        // 库存不足提醒商家
        foreach ($carts as $cart) {
            if (($cart['productAttr']['stock'] - $cart['cart_num']) < (int)merchantConfig($params['merId'], 'mer_store_stock')) {
                SwooleTaskService::merchant(
                    'notice',
                    [
                        'type' => 'min_stock',
                        'data' => [
                            'title' => '库存不足',
                            'message' => $cart['product']['store_name'] . '(' . $cart['productAttr']['sku'] . ')库存不足',
                            'id' => $cart['product']['product_id']
                        ]
                    ],
                    $params['merId']
                );
            }
        }
        // 通知
        Queue::push(SendSmsJob::class, ['tempId' => 'ORDER_CREATE', 'id' => $group->group_order_id]);
        // 自动打印订单
        $this->autoPrinter($storeOrder->order_id, $params['merId'], 2);
        Cache::delete($cacheKey);

        return $group;
    }
    /**
     * 支付
     * alipayQr 支付宝二维码支付
     * weixinQr 微信二维码支付
     * balance 余额支付
     * weixinBarCode 微信扫码枪支付
     * alipayBarCode 支付宝扫码枪支付
     *
     * @return void
     */
    public function merchantPay(array $params, $user, $groupOrder)
    {
        $authCode = isset($params['auth_code']) && !empty($params['auth_code']) ? $params['auth_code'] : '';
        //$combine = $params['pay_type'] == 'weixinQr' ? true : false;
        //halt($params,$authCode,$combine);
        return $this->pay($params['pay_type'], $user, $groupOrder, '', false, null, $authCode);
    }
    /**
     * 支付配置信息
     *
     * @param $uid
     * @return void
     */
    public function merchantPayConfig($uid, $merId)
    {
        $user = app()->make(UserRepository::class)->get($uid);
        $config = systemConfig(['pay_wechat_type', 'recharge_switch', 'yue_pay_status', 'pay_weixin_open', 'alipay_open', 'offline_switch', 'auto_close_order_timer', 'balance_func_status']);
        $merchant = $this->merchantInfo($merId);
        $timer = (int)($config['auto_close_order_timer'] ?: 15);
        $data = [
            'is_wechat_v3' => $config['pay_wechat_type'] ? true : false,
            'offline_switch' => $config['offline_switch'] && $merchant['offline_switch'] ? 1 : 0,
            'now_money' => $user['now_money'] ?? 0,
            'pay_weixin_open' => $config['pay_weixin_open'],
            'alipay_open' => $config['alipay_open'],
            'yue_pay_status' => ($config['yue_pay_status'] && $config['balance_func_status'] && $uid) ? 1 : 0,
            'invalid_time' => time() + $timer * 60,
        ];
        return $data;
    }
    /**
     * 支付状态查询
     *
     * @param array $params
     * @return void
     */
    public function payStatus(array $params)
    {
        $groupOrder = $this->getStoreGroupOrderRepository()->status($params['uid'], intval($params['id']));
        if (!$groupOrder) {
            throw new ValidateException('订单不存在');
        }

        // 返回订单信息
        return $groupOrder;
    }
}
