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

use think\Model;
use think\exception\ValidateException;

trait MerchantOrderTrait
{
    /**
     * 计算整单改价后的价格
     *
     * @param array $data
     * @return float
     */
    private function getNewPayPrice(array $data) : array
    {
        $updatePriceFlag = 0;
        // 判断是否改价了，如果没有改价，则返回默认值
        if(!isset($data['change_fee_type']) || $data['change_fee_type'] === '') {
            return [0, 1, $updatePriceFlag];
        }

        $newPayPrice = $data['new_pay_price'] ?? 0;
        if($data['change_fee_type'] == 1){ // 立减
            $newPayPrice = bcsub($data['old_pay_price'], $data['reduce_price'], 2);
        }
        if($data['change_fee_type'] == 2){ // 折扣
            $newPayPrice = bcmul($data['old_pay_price'], $data['discount_rate']/100, 2);
        }
        $changeFeeRate = bcdiv($newPayPrice, $data['old_pay_price'], 2);
        $updatePriceFlag = 1;

        return [(float)$newPayPrice, (float)$changeFeeRate, $updatePriceFlag];
    }
    /**
     * 运费模版
     *
     * @param array $template
     * @return array
     */
    private function template(array $template): array
    {
        $cartProductShippingFeeTemplate = [];
        foreach ($template as $temp) {
            $cartProductShippingFeeTemplate[$temp['shipping_template_id']] = $temp;
        }

        return $cartProductShippingFeeTemplate;
    }
    /**
     * 计算积分
     *
     * @param array $result
     * @param $cartList
     * @param integer $merId
     * @param integer $useIntegral
     * @param integer $userIntegral
     * @return array
     */
    private function calculateIntegral(array $result, $cartList, int $merId, int $useIntegral, int $userIntegral): array
    {
        $usedNum = 0;
        $config = $this->getMerchantConfig($merId);
        $sysIntegralConfig = systemConfig(['integral_money', 'integral_status', 'integral_order_rate']);
        $integralFlag = $sysIntegralConfig['integral_status'] &&
            $sysIntegralConfig['integral_money'] > 0 &&
            $config['config']['mer_integral_status'];

        if ($integralFlag && $useIntegral) {
            $result['totalPaymentPrice'] = 0;
            $result['discountDetail']['integralDiscountAmount'] = 0;
            foreach ($cartList as $value) {
                // 积分抵扣比例，优先取商品设置的积分抵扣比例，若无则取商户默认设置的积分抵扣比例
                $integralRate = $value['product']['integral_rate'];
                if ($integralRate < 0) {
                    $integralRate = $config['config']['mer_integral_rate'];
                } else if ($integralRate > 0) {
                    $integralRate = min(bcdiv($integralRate, 100, 4), 1);
                }
                // 根据比例计算商品积分抵扣金额
                $productIntegralPrice = min((float)bcmul($value['paymentPrice'], $integralRate, 2), $value['paymentPrice']);

                if ($productIntegralPrice > 0) {
                    // 计算商品积分数，若商品积分数大于用户积分数，则取用户积分数，否则取商品积分数
                    $productIntegral = ceil(bcdiv($productIntegralPrice, $sysIntegralConfig['integral_money'], 2));
                    if ($productIntegral > $userIntegral) {
                        $use = $userIntegral;
                        $useIntegralPrice = bcmul($userIntegral, $sysIntegralConfig['integral_money'], 0);
                        $userIntegral = 0;
                    } else {
                        $use = $productIntegral;
                        $useIntegralPrice = $productIntegralPrice;
                        $userIntegral = (float)bcsub($userIntegral, $productIntegral, 0);
                    }
                    // 各商品积分使用详情
                    $value['integral'] = ['use' => $use, 'price' => $useIntegralPrice, 'userIntegral' => $userIntegral];
                    // 更新商品实付金额
                    $value['paymentPrice'] = max((float)bcsub($value['paymentPrice'], $useIntegralPrice, 2), 0);
                    // 更新总支付金额和积分抵扣金额
                    $result['totalPaymentPrice'] += $value['paymentPrice'];
                    $result['discountDetail']['integralDiscountAmount'] += $value['integral']['price'];
                    $usedNum += $use;
                }

            }
        }

        $result['commission_rate'] = $config['commission_rate'];
        $result['commission_switch'] = $config['commission_switch'];
        $result['integral']['flag'] = $integralFlag;
        $result['integral']['usedNum'] = $usedNum;
        $result['integral']['userIntegral'] = $userIntegral;
        $result['integral']['deductionAmount'] = (float)bcmul($userIntegral, $sysIntegralConfig['integral_money'], 2);

        return $result;
    }
    /**
     * 获取商户配置信息
     *
     * @param integer $merId
     * @return array
     */
    private function getMerchantConfig(int $merId): array
    {
        $configFiled = [
            'mer_integral_status',      // 商户积分开关
            'mer_integral_rate',        // 商户积分比例
            'mer_store_stock',          // 库存警戒
            'mer_take_status',          // 自提开关
            'mer_take_name',            // 自提门店名称
            'mer_take_phone',           // 自提电话
            'mer_take_address',         // 自提地址
            'mer_take_location',        // 自提店铺经纬度
            'mer_take_day',             // 自提营业日期
            'svip_coupon_merge',        // svip合并优惠券
            'mer_take_time',             // 自提时间段
        ];
        $merchantInfoData = $this->getMerchantRepository()->getSearch([])->where('mer_id', $merId)
            ->with([
                'config' => function ($query) use ($configFiled) {
                    $query->whereIn('config_key', $configFiled);
                }
            ])->field('mer_id,category_id,mer_name,mer_state,mer_avatar,is_trader,type_id,delivery_way,commission_rate,commission_switch')
            ->find()
            ->append(['openReceipt'])
            ->toArray();

        foreach ($merchantInfoData['config'] as $key => $config) {
            $merchantInfoData['config'][$config['config_key']] = $config['value'];
            unset($merchantInfoData['config'][$key]);
        }

        return $merchantInfoData;
    }
    /**
     * 计算优惠券优惠金额，并更新实付总金额和优惠详情中的优惠券优惠金额字段
     *
     * @param array $result
     * @param $cartList
     * @param $userCoupons
     * @param integer $totalPaymentPrice
     * @return array
     */
    private function calculateCoupon(array $result, $cartList, $userCoupons, int $merId, array $useCouponData, $user): array
    {
        if (empty($userCoupons) || empty($user)) {
            return $result;
        }

        // 会员和优惠券是否叠加
        $svipCouponMerge = merchantConfig($merId, 'svip_coupon_merge');
        $usePlatformCoupons = (isset($useCouponData[0]) && !empty($useCouponData[0])) ? [end($useCouponData[0])] : [];
        $useMerchantCoupons = (isset($useCouponData[1]) && !empty($useCouponData[1])) ? [end($useCouponData[1])] : [];
        $useProductCoupons = (isset($useCouponData[2]) && !empty($useCouponData[2])) ? $useCouponData[2] : [];
        $useCoupon = array_unique(array_merge($usePlatformCoupons, $useMerchantCoupons, $useProductCoupons));

        // 校验优惠券信息,校验优惠券是否可用
        $result['couponList'] = $this->getStoreCouponUserRepository()->validCoupon($userCoupons->toArray(), $useCoupon, $cartList, $result['totalPaymentPrice'], $merId);

        $usedCoupons = [];
        $couponRemainingPrice = [];
        $oldTotalPrice = $result['totalPaymentPrice'];
        $result['totalPaymentPrice'] = 0;
        $result['discountDetail']['couponsDiscountAmount'] = 0;
        foreach ($cartList as $key => $cart) {
            $defaultMerCoupon = 0;
            $defaultProCoupon = 0;
            $defaultPlaCoupon = 0;
            $paymentPrice = $cart['paymentPrice'];
            // 判断优惠券和会员是否可叠加使用
            $isNotOverlay = ($svipCouponMerge == 0 && $cart['useSvip']);
            foreach ($result['couponList'] as &$coupon) {
                if($cart['paymentPrice'] > 0 && !$coupon['disabled']) {
                    $coupon['checked'] = false;
                    if($useCoupon) {
                        if ($isNotOverlay) {
                            $result['totalPaymentPrice'] = $oldTotalPrice;
                            throw new ValidateException('优惠券和会员不可叠加使用');
                            continue;
                        }
                        if (!in_array($coupon['coupon_user_id'], $useCoupon)) {
                            continue;
                        }
                        $coupon['checked'] = true;
                    }

                    // 平台券
                    if(in_array($coupon['coupon']['type'], [10, 11, 12]) && !$defaultPlaCoupon && !isset($useCouponData[0])) {
                        $coupon['checked'] = true;
                        $defaultPlaCoupon = $coupon['coupon_user_id'];
                    }
                    // 店铺券
                    if($coupon['coupon']['type'] == 0 && !$defaultMerCoupon && !isset($useCouponData[1])) {
                        $coupon['checked'] = true;
                        $defaultMerCoupon = $coupon['coupon_user_id'];
                    }
                    // 商品券
                    if($coupon['coupon']['type'] == 1 && !$defaultProCoupon && !isset($useCouponData[2])) {
                        $coupon['checked'] = true;
                        $defaultProCoupon = $coupon['coupon_user_id'];
                    }

                    // 计算选择的优惠券金额
                    if($coupon['checked']) {
                        // 商品券
                        if (!$coupon['product'] && $coupon['coupon']['type'] == 1) {
                            // 如果该优惠券已经被使用，则跳过当前循环
                            $usedProductCoupons = isset($usedCoupons['product']) ? array_column($usedCoupons['product'], 'id') : [];
                            if (in_array($coupon['coupon_user_id'], $usedProductCoupons)) {
                                continue;
                            }
                            // 商品券，只对指定商品进行优惠
                            $coupon = $coupon->toArray();
                            $couponProductIds = array_column($coupon['product'], 'product_id');
                            if (in_array($cart['product_id'], $couponProductIds)) {
                                $fee = max((float)bcsub($cart['paymentPrice'], $coupon['coupon_price'], 2), 0);
                                $productCouponPrice = ($fee == 0) ? $cart['paymentPrice'] : $coupon['coupon_price'];
                                $usedCoupons = $this->recodeUseCoupon($usedCoupons, $coupon['coupon_user_id'], $productCouponPrice, $coupon['coupon']['type'], $cart);
                                $cart['paymentPrice'] = $fee;
                            }
                            continue;
                        }
                        // 平台券和店铺券，对所有商品进行优惠，按比例分配优惠金额
                        $rate = bcdiv($coupon['coupon_price'], $oldTotalPrice, 4);
                        if ($rate > 1) {
                            $usedCoupons = $this->recodeUseCoupon($usedCoupons, $coupon['coupon_user_id'], $cart['paymentPrice'], $coupon['coupon']['type'], $cart);
                            $cart['paymentPrice'] = 0;
                            continue;
                        }
                        // 是否为最后一个商品，则将剩余的优惠券优惠金额分配给最后一个商品
                        if (count($cartList) >1 && (count($cartList) == $key + 1) && isset($couponRemainingPrice[$coupon['coupon_id']])) {
                            $cart['paymentPrice'] = (float)bcsub($cart['paymentPrice'], $couponRemainingPrice[$coupon['coupon_id']], 2);
                            $usedCoupons = $this->recodeUseCoupon($usedCoupons, $coupon['coupon_user_id'], $couponRemainingPrice[$coupon['coupon_id']], $coupon['coupon']['type'], $cart);
                            continue;
                        }
                        $couponPrice = (count($cartList) == 1) ? $coupon['coupon_price'] : bcmul($cart['paymentPrice'], $rate, 2);
                        $cart['paymentPrice'] = (float)bcsub($cart['paymentPrice'], $couponPrice, 2);
                        // 计算剩余优惠金额，用于下一个商品的优惠券分配
                        $couponRemainingPrice[$coupon['coupon_id']] = (float)bcsub($couponRemainingPrice[$coupon['coupon_id']] ?? $coupon['coupon_price'], $couponPrice, 2);
                        $usedCoupons = $this->recodeUseCoupon($usedCoupons, $coupon['coupon_user_id'], $couponPrice, $coupon['coupon']['type'], $cart);
                    }
                }
            }
            $cart['couponsDiscount'] = (float)bcsub($paymentPrice, $cart['paymentPrice'], 2);

            $result['totalPaymentPrice'] += $cart['paymentPrice']; // 实付总金额
            $result['discountDetail']['couponsDiscountAmount'] += $cart['couponsDiscount']; // 优惠券优惠金额
            $result['usedCoupons'] = $usedCoupons;
        }

        if(!$usedCoupons) {
            // 如果使用了优惠券则再次校验优惠券信息
            $result['couponList'] = $this->getStoreCouponUserRepository()->validCoupon($result['couponList'], $useCoupon, $cartList, $result['totalPaymentPrice'], $merId);
        }

        return $result;
    }
    /**
     * 记录使用的优惠券信息，并区分店铺券、商品券和平台券
     *
     * @param array $usedCoupons
     * @param integer $couponId
     * @param float $couponPrice
     * @param integer $type
     * @param $cart
     * @return void
     */
    private function recodeUseCoupon(array $usedCoupons, int $couponId, float $couponPrice, int $type, $cart)
    {
        switch ($type) {
            case 0: // 店铺券
                $key = 'merchant';
                break;
            case 1: // 商品券
                $key = 'product';
                break;
            default: // 平台券
                $key = 'platform';
                break;
        }
        $usedCoupons[$key][] = [
            'id' => $couponId,
            'cartId' => $cart['cart_id'],
            'price' => $couponPrice
        ];

        $cart["{$key}CouponDiscountAmount"] += $couponPrice;

        return $usedCoupons;
    }
    /**
     * 根据运费模板计算运费金额
     * $regionRule 运费规则数组
     * $aggregate 商品规格属性总量，type为0时，表示总数量；type为2时，表示总重量，type为3时，表示总体积。
     *
     * @param array $cartProductShippingFeeTemplate 运费模板数组
     * @return string
     */
    private function calculateShippingCost(array $cartProductShippingFeeTemplate): string
    {
        $fee = 0;
        foreach ($cartProductShippingFeeTemplate as $value) {
            if (empty($value['aggregate'])) {
                continue;
            }
            $value = $value->toArray();
            $region = $value['region'] ?? null;
            if (!$region) {
                continue;
            }
            $regionRule = array_shift($region);
            $aggregate = $value['aggregate'];
            $fee += $aggregate > 0 ? bcadd($regionRule['first_price'], bcmul(bcdiv(bcsub($aggregate, $regionRule['first'], 2), $regionRule['continue'], 2), $regionRule['continue_price'], 2), 2) : $regionRule['first_price'];
        }

        return $fee;
    }
    /**
     * 验证用户信息是否有效
     *
     * @param integer $uid
     * @return Model|null
     */
    private function validUser(int $uid): ?Model
    {
        if (!$uid) {
            return null;
        }

        $user = $this->getUserRepository()->userInfo($uid);
        if (empty($user)) {
            throw new ValidateException('用户不存在');
        }

        return $user;
    }
    /**
     * 验证地址信息是否有效
     *
     * @param [type] $addressId
     * @param [type] $uid
     * @return Model|null
     */
    private function validAddress($addressId, int $uid): ?Model
    {
        if (empty($addressId)) {
            return null;
        }
        $where['address_id'] = $addressId;
        if ($uid) {
            $where['uid'] = $uid;
        }

        $address = $this->getUserAddressRepository()->getWhere($where);
        if (empty($address)) {
            throw new ValidateException('地址不存在');
        }

        return $address;
    }
    /**
     * 验证购物车信息是否有效
     * 若存在失效商品则抛出异常
     * 若超出限购数也抛出异常
     * 若存在非正常商品也抛出
     *
     * @param $user
     * @param integer $merId
     * @param array $cartIds
     * @param $address
     * @return array
     */
    private function validCartList($user, int $merId, array $cartIds, $address): ?array
    {
        $res = $this->getStoreCartRepository()->getMerchantList($user, $merId, $cartIds, $address);
        if (count($res['fail'])) {
            throw new ValidateException('存在已失效商品，请检查');
        }

        return $res['list'];
    }
    /**
     * 获取优惠券信息
     *
     * @param integer $uid
     * @param integer $merId
     * @param array $useCoupon
     * @return void
     */
    private function fetchUserCoupons(int $uid, int $merId)
    {
        return $this->getStoreCouponUserRepository()->validUserCoupon($uid, $merId);
    }
}
