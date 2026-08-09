<?php

namespace app\common\repositories\store\order;

use app\validate\api\{UserAddressValidate};
use crmeb\jobs\SendSmsJob;
use think\facade\{Db, Cache, Queue};
use crmeb\services\SwooleTaskService;
use think\exception\ValidateException;
use app\common\repositories\system\RecordRepository;
use app\common\repositories\system\form\FormRepository;
use app\common\repositories\store\StoreCategoryRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\delivery\DeliveryConfigRepository;
use app\common\repositories\delivery\DeliveryStationRepository;
use app\common\repositories\store\coupon\{StoreCouponRepository, StoreCouponUserRepository};
use app\common\repositories\user\{
    UserRepository,
    UserBillRepository,
    UserAddressRepository,
    UserMerchantRepository,
    MemberinterestsRepository
};
use app\common\repositories\store\product\{
    ProductReservationRepository,
    ProductAttrValueReservationRepository,
    ProductRepository,
    StoreDiscountRepository,
    ProductGroupSkuRepository,
    ProductGroupUserRepository,
    ProductAttrValueRepository,
    ProductAssistSkuRepository,
    ProductPresellSkuRepository
};

class StoreOrderCreateRepository extends StoreOrderRepository
{
    protected $seckill_stock_key = [];
    //获取商户的配置
    public $configFiled = ['mer_integral_status', 'mer_integral_rate', 'mer_store_stock', 'svip_coupon_merge','enable_assigned','enable_tostore_assigned'];
    //商户的字段
    public $merchantField = ['mer_id,category_id,mer_name,mer_state,mer_avatar,is_trader,type_id,delivery_way,commission_rate,commission_switch,is_blindbox'];

    const DEFAULT_DELIVERY = ['mer_delivery','mer_take','mer_city_takes'];

    /**
     * 订单确认页面数据
     * @param $user
     * @param array $cartId
     * @param array|null $takes
     * @param array|null $useCoupon
     * @param bool $useIntegral
     * @param int|null $addressId
     * @param false $createOrder
     * @return array|bool[]
     */
    public function v2CartIdByOrderInfo($user, array $cartId, array $takes = null, array $useCoupon = null, bool
    $useIntegral = false, int $addressId = null, array $cityTakes = [])
    {
        $uid = $user->uid;
        $userIntegral = $user->integral;
        $key = md5(json_encode(compact('cartId', 'takes', 'useCoupon', 'useIntegral', 'addressId','cityTakes'))) . $uid;
        //验证地址
        $address = $this->validateAddress($addressId, $uid);

        $svip_status = $user->is_svip > 0 && systemConfig('svip_switch_status') == '1';
        $svip_integral_rate = $svip_status ? app()->make(MemberinterestsRepository::class)->getSvipInterestVal(MemberinterestsRepository::HAS_TYPE_PAY) : 0;

        //获取购物车信息
        [
            $order_type, //订单类型
            $order_model,//商品种类
            $mer_form_id,//商户表单id
            $merchantCartList //购物车数据
        ] = $this->validateCartList($cartId,$address,$user,$svip_status);

        $mer_form_info = new \stdClass();
        //虚拟订单自定义数据
        $order_extend = [];
        if ($mer_form_id > 0) {
            $mer_form_info = app()->make(FormRepository::class)->get($mer_form_id);
        }
        $order_price = 0;
        $total_true_price = 0;
        $order_total_price = 0;
        $order_coupon_price = 0;
        $noDeliver = false;
        $presellType = 0;
        $fn = [];
        $enabledPlatformCoupon = !$order_type;
        $order_total_postage = 0;
        $orderDeliveryStatus = true;
        $order_svip_discount = 0;
        // 循环计算每个店铺的订单数据
        foreach ($merchantCartList as &$merchantCart) {
            //运费计算规则
            $postageRule = [];
            // 当前商户 所有商品的 总金额
            $total_price = 0;
            // 当前商户 总商品数
            $total_num = 0;
            // 实际支付金额
            $valid_total_price = 0;
            // 邮费
            $postage_price = 0;
            // 商品金额
            $product_price = [];
            // 预售定金
            $final_price = 0;
            // 预售尾款金额
            $down_price = 0;
            // 合计优惠金额
            $total_svip_discount = 0;
            // 商品订单是否支持退款状态
            $order_refund_switch = 0;
            $merchantTakeId = 0;
            //用户选择自提的的商户中是否存在当前商户 [1=>1,2=>2]
            $isTake = false;
            // 预约到店判断
            if(isset($takes[0]) && $takes[0] == $merchantCart['mer_id']) {
                $isTake = true;
            }
            $merchant_take_info = [];
            // 普通自提判断
            if(isset($takes[$merchantCart['mer_id']]['take_id'])) {
                $isTake = true;
                $merchant_take_info = $takes;
                $merchantTakeId = $takes[$merchantCart['mer_id']]['take_id'];
                if(!$merchantTakeId) {
                    throw new ValidateException("【{$merchantCart['mer_name']}】商户暂未设置提货点，请切换其他配送方式");
                }
            }
            // 同城配送判断
            $isCityTake = false;
            if(isset($cityTakes[$merchantCart['mer_id']]['take_id'])) {
                $isCityTake = true;
                $merchant_take_info = $cityTakes;
                $merchantTakeId = $cityTakes[$merchantCart['mer_id']]['take_id'];
                if(!$merchantTakeId) {
                    throw new ValidateException("【{$merchantCart['mer_name']}】商户暂未设置提货点，请切换其他配送方式");
                }
            }
            $merchantCart['take_id'] = $merchantTakeId;
            [$merTake, $merDelivery, $merCityTakes] = $this->validateDelivery($merchantCart['delivery_way']);

            [$_merTake, $_merDelivery, $_merCityTakes] = [$merTake, $merDelivery,$merCityTakes];
            $product_cart = [];
            foreach ($merchantCart['list'] as $k => $cart) {
                $productTaks = $_merTake;
                $productDelivery = $_merDelivery;
                $productCityTakes = $_merCityTakes;

                //如果商品有配送方式信息
                if ($cart['product']['delivery_way']) {
                    //商品是否支持自提
                    [$productTaks, $productDelivery,$productCityTakes] = $this->validateDelivery($cart['product']['delivery_way']);
                    //这里根据每个商品的状态去判断商户所有的商品是否都应该支持自提
                    $merTake = $merTake && $productTaks;
                    $merDelivery = $merDelivery && $productDelivery;
                    $merCityTakes = $merCityTakes && $productCityTakes;
                }

                //默认根据商户选择的支持配送方式走
                $merchantCart['list'][$k]['allow_city_take'] = $productCityTakes;
                $merchantCart['list'][$k]['allow_take'] = $productTaks;
                $merchantCart['list'][$k]['allow_delivery'] = $productDelivery;
                //如果是预售订单 获取预售的订单的首款,尾款预售类型
                if ($order_type == 2) {
                    $final_price = bcadd($final_price, bcmul($cart['cart_num'], $cart['productPresellAttr']['final_price'], 2), 2);
                    $presellType = $cart['productPresell']['presell_type'];
                    if ($presellType == 2)
                        $down_price = bcadd($down_price, bcmul($cart['cart_num'], $cart['productPresellAttr']['down_price'], 2), 2);
                }
            }
            unset($cart);
            // 当前商户所有商品配送方式是否支持当前的选择
            $deliveryStatus = ($merDelivery || $merTake || $merCityTakes);
            if(!$deliveryStatus && count($merchantCart['list']) == 1) {
                throw new ValidateException("【{$merchantCart['mer_name']}】，配送方式有误，请检查");
            }
            if(!$deliveryStatus && count($merchantCart['list']) > 1) {
                throw new ValidateException("【{$merchantCart['mer_name']}】，存在多个商品配送方式不统一，请分开下单");
            }
            // //如果配送方式
            // if ($deliveryStatus && !$isTake && ($merDelivery || $merTake)) {
            //     //自提状态
            //     $isTake = $merDelivery ? 0 : 1;
            // }

            $enabledCoupon = !($order_type && $order_type != 2);
            //只有预售和普通商品可以用优惠券
            if (!$enabledCoupon) $merchantCart['coupon'] = [];
            $svip_coupon_merge = merchantConfig($merchantCart['mer_id'], 'svip_coupon_merge');
            $use_svip = 0;
            $city_take_postage = 0;
            if ($isCityTake && $merCityTakes && $address) {
                $checkCityTake = $this->checkCityTake($merchantCart, $address->toArray());
                $deliveryStatus = $checkCityTake && $deliveryStatus;
            }
            // 验证上门服务是否在商品支持范围内
            if($order_model == 4 && !$isTake && $address) {
                $checkHomeServiceRange = $this->checkHomeServiceRange($merchantCart['mer_id'], $address->toArray());
                $deliveryStatus = $checkHomeServiceRange;
            }
            //获取运费规则和统计商品数据
            foreach ($merchantCart['list'] as &$cart) {
                $svip_discount = 0;
                $price = bcmul($cart['cart_num'], $this->cartByPrice($cart), 2);
                $cart['total_price'] = $price;
                $cart['postage_price'] = 0;
                $cart['svip_discount'] = 0;
                $total_price = bcadd($total_price, $price, 2);
                $total_num += $cart['cart_num'];
                $_price = bcmul($cart['cart_num'], $this->cartByCouponPrice($cart), 2);
                $cart['svip_coupon_merge'] = 1;
                if ($cart['productAttr']['show_svip_price'] && !$cart['product_type']) {
                    $svip_discount = max(bcmul($cart['cart_num'], bcsub($cart['productAttr']['org_price'] ?? 0, $cart['productAttr']['price'], 2), 2), 0);
                    if ($svip_coupon_merge != '1') {
                        $_price = 0;
                        $cart['svip_coupon_merge'] = 0;
                    }
                    $use_svip = 1;
                }
                $valid_total_price = bcadd($valid_total_price, $_price, 2);
                $cart['allow_price'] = $_price;
                $cart['temp_number'] = 0;
                $total_svip_discount = bcadd($total_svip_discount, $svip_discount, 2);
                $cart['svip_discount'] = $svip_discount;
                $cart['refund_switch'] = $cart['product']['refund_switch'];
                if ($cart['refund_switch']) $order_refund_switch = 1;
                if (!isset($product_cart[$cart['product_id']]))
                    $product_cart[$cart['product_id']] = [$cart['cart_id']];
                else
                    $product_cart[$cart['product_id']][] = $cart['cart_id'];

                if ($_price > 0) {
                    $product_price[$cart['product_id']] = bcadd($product_price[$cart['product_id']] ?? 0, $_price, 2);
                }
                $temp1 = $cart['product']['temp'];
                if (!$temp1)  {
                    continue;
                }
                $number = $this->productByTempNumber($cart);
                $fn[] = function () use ($cart) {
                    unset($cart['product']['temp']);
                };
                // 模板不存在，数量为0，包邮，虚拟商品，套餐商品的包邮
                if (
                    $number <= 0 ||
                    $order_model ||
                    $cart['product']['delivery_free'] ||
                    ($cart['product_type'] == 10 && $cart['productDiscount']['free_shipping'])
                ) {
                    continue;
                }
                $cart['temp_number'] = $number;
                $free = $temp1['free'][0] ?? null;
                $region = $temp1['region'][0] ?? null;
                //undelivery 0不限制  1自定义 2仅支持添加的配送区域
                //不包邮 & 不自提
                if (
                    !$cart['product']['delivery_free'] &&
                    !$isTake &&
                    !$isCityTake &&
                    // 用户地址不存在 ｜｜ 仅配送选择的地区但没有配送地区也没有包邮地区
                    (!$address || ($temp1['undelivery'] == 2 && !$free && (!$region || !$region['city_id'])))
                ) {
                    $cart['undelivered'] = true;
                    $noDeliver = true;
                    continue;
                }
                $cart['undelivered'] = (!$isTake) && $temp1['undelivery'] == 1 && isset($temp1['undelives']);
                if ($cart['undelivered']) {
                    $noDeliver = true; continue;
                }
                $tempId = $cart['product']['temp_id'];
                if (!isset($postageRule[$tempId])) {
                    $postageRule[$tempId] = ['free' => null, 'region' => null, 'cart' => [], 'price' => 0];
                }

                $freeRule = $postageRule[$tempId]['free'];
                $regionRule = $postageRule[$tempId]['region'];

                $postageRule[$tempId]['cart'][] = $cart['cart_id'];
                $postageRule[$tempId]['price'] = bcadd($postageRule[$tempId]['price'], $cart['price'], 2);

                if ($temp1['appoint'] && $free) {
                    if (!isset($freeRule)) {
                        $freeRule = $free;
                        $freeRule['cart_price'] = 0;
                        $freeRule['cart_number'] = 0;
                    }
                    $freeRule['cart_number'] = bcadd($freeRule['cart_number'], $number, 2);
                    $freeRule['cart_price'] = bcadd($freeRule['cart_price'], $price, 2);
                }

                if ($region) {
                    if (!isset($regionRule)) {
                        $regionRule = $region;
                        $regionRule['cart_price'] = 0;
                        $regionRule['cart_number'] = 0;
                    }
                    $regionRule['cart_number'] = bcadd($regionRule['cart_number'], $number, 2);
                    $regionRule['cart_price'] = bcadd($regionRule['cart_price'], $price, 2);
                }
                $postageRule[$tempId]['free'] = $freeRule;
                $postageRule[$tempId]['region'] = $regionRule;
            }
            unset($cart);

            // 不是自提则计算运费
            if (!$isTake && !$isCityTake) {
                //计算运费
                foreach ($postageRule as $item) {
                    $freeRule = $item['free'];
                    if ($freeRule && $freeRule['cart_number'] >= $freeRule['number'] && $freeRule['cart_price'] >= $freeRule['price'])
                        continue;
                    if (!$item['region']) continue;
                    $regionRule = $item['region'];
                    $postage = $regionRule['first_price'];
                    if ($regionRule['first'] > 0 && $regionRule['cart_number'] > $regionRule['first']) {
                        $num = ceil(bcdiv(bcsub($regionRule['cart_number'], $regionRule['first'], 2), $regionRule['continue'], 2));
                        $postage = bcadd($postage, bcmul($num, $regionRule['continue_price'], 2), 2);
                    }
                    $postage_price = bcadd($postage_price, $postage, 2);
                    $cartNum = count($item['cart']);
                    //计算每个商品的运费比例
                    foreach ($merchantCart['list'] as &$cart) {
                        if (in_array($cart['cart_id'], $item['cart'])) {
                            if (--$cartNum) {
                                $cart['postage_price'] = bcmul($postage, bcdiv($cart['temp_number'], $regionRule['cart_number'], 3), 2);
                                $postage = bcsub($postage, $cart['postage_price'], 2);
                            } else {
                                $cart['postage_price'] = $postage;
                            }
                        }
                    }
                    unset($cart);
                }
                unset($item);
            }
            $coupon_price = 0;
            $use_coupon_product = [];
            $use_store_coupon = 0;
            $useCouponFlag = isset($useCoupon[$merchantCart['mer_id']]);
            $merCouponIds = (array)($useCoupon[$merchantCart['mer_id']] ?? []);
            $merCouponIds = array_reverse($merCouponIds);
            $sortIds = $merCouponIds;
            unset($defaultSort);
            $defaultSort = [];
            if (count($merCouponIds)) {
                foreach ($merchantCart['coupon'] as &$item) {
                    $defaultSort[] = &$item;
                    if (!in_array($item['coupon_user_id'], $sortIds, true)) {
                        $sortIds[] = $item['coupon_user_id'];
                    }
                }
                unset($item);
                usort($merchantCart['coupon'], function ($a, $b) use ($sortIds) {
                    return array_search($a['coupon_user_id'], $sortIds) > array_search($b['coupon_user_id'], $sortIds) ? 1 : -1;
                });
            }

            //过滤不可用店铺优惠券
            foreach ($merchantCart['coupon'] as &$coupon) {
                if (!$coupon['coupon']['type']) continue;
                $coupon['disabled'] = false;
                $coupon['checked'] = false;
                if (count(array_intersect(array_column($coupon['product'], 'product_id'), array_keys($product_price))) == 0) {
                    $coupon['disabled'] = true;
                    continue;
                }
                if ($svip_coupon_merge != '1' && $use_svip) {
                    $coupon['disabled'] = true;
                    continue;
                }
                $flag = false;
                foreach ($coupon['product'] as $_product) {
                    if (isset($product_price[$_product['product_id']]) && $product_price[$_product['product_id']] >= $coupon['use_min_price']) {
                        $flag = true;
                        break;
                    }
                }
                if (!$flag) {
                    $coupon['disabled'] = true;
                }
            }
            unset($coupon);
            //计算商品券金额
            foreach ($merchantCart['coupon'] as &$coupon) {
                if (!$coupon['coupon']['type']) continue;
                if ($coupon['disabled']) continue;

                foreach ($coupon['product'] as $_product) {
                    if (isset($product_price[$_product['product_id']]) && $product_price[$_product['product_id']] >= $coupon['use_min_price']) {
                        if ($useCouponFlag) {
                            if (!in_array($coupon['coupon_user_id'], $merCouponIds) || isset($use_coupon_product[$_product['product_id']])) {
                                continue;
                            }
                        } else if (isset($use_coupon_product[$_product['product_id']])) {
                            continue;
                        }
                        $coupon_price = bcadd($coupon_price, $coupon['coupon_price'], 2);
                        $use_coupon_product[$_product['product_id']] = $coupon;
                        $coupon['checked'] = true;
                        break;
                    }
                }
                unset($_product);
            }
            unset($coupon);
            $pay_price = max(bcsub($valid_total_price, $coupon_price, 2), 0);
            $_pay_price = $pay_price;
            //计算店铺券
            foreach ($merchantCart['coupon'] as &$coupon) {
                if ($coupon['coupon']['type']) continue;
                $coupon['checked'] = false;
                $coupon['disabled'] = $pay_price <= 0;
                if ($use_store_coupon || $pay_price <= 0) continue;
                if ($svip_coupon_merge != '1' && $use_svip) {
                    $coupon['disabled'] = true;
                    continue;
                }
                //店铺券
                if ($valid_total_price >= $coupon['use_min_price']) {
                    if ($useCouponFlag) {
                        if (!in_array($coupon['coupon_user_id'], $merCouponIds)) {
                            continue;
                        }
                    }
                    $use_store_coupon = $coupon;
                    $coupon_price = bcadd($coupon_price, $coupon['coupon_price'], 2);
                    $_pay_price = bcsub($_pay_price, $coupon['coupon_price'], 2);
                    $coupon['checked'] = true;
                } else {
                    $coupon['disabled'] = true;
                }
            }
            unset($coupon);
            if ($isCityTake && $merCityTakes && $address && $deliveryStatus) {
                $city_take_postage = $this->getDeliveryFee($total_price, $merchantCart, $address);
            }

            $productCouponRate = [];
            $storeCouponRate = null;
            $useCouponIds = [];
            //计算优惠占比
            foreach ($use_coupon_product as $productId => $coupon) {
                $productCouponRate[$productId] = [
                    'rate' => $product_price[$productId] > 0 ? bcdiv($coupon['coupon_price'], $product_price[$productId], 4) : 1,
                    'coupon_price' => $coupon['coupon_price'],
                    'price' => $product_price[$productId]
                ];
                $useCouponIds[] = $coupon['coupon_user_id'];
            }
            if ($use_store_coupon) {
                $storeCouponRate = [
                    'rate' => $pay_price > 0 ? bcdiv($use_store_coupon['coupon_price'], $pay_price, 4) : 1,
                    'coupon_price' => $use_store_coupon['coupon_price'],
                    'price' => $coupon_price
                ];
                $useCouponIds[] = $use_store_coupon['coupon_user_id'];
            }

            //计算单个商品实际支付金额
            foreach ($merchantCart['list'] as $_k => &$cart) {
                $cartTotalPrice = bcmul($this->cartByPrice($cart), $cart['cart_num'], 2);
                $_cartTotalPrice = $cartTotalPrice;
                if (!$cart['product_type'] && $cartTotalPrice > 0) {
                    if (isset($productCouponRate[$cart['product_id']])) {
                        //计算每个商品优惠金额(商品券)
                        if ($productCouponRate[$cart['product_id']]['rate'] >= 1) {
                            $cartTotalPrice = 0;
                        } else {
                            array_pop($product_cart);
                            if (!count($product_cart)) {
                                $cartTotalPrice = bcsub($cartTotalPrice, $productCouponRate[$cart['product_id']]['coupon_price'], 2);
                                $productCouponRate[$cart['product_id']]['coupon_price'] = 0;
                            } else {
                                $couponPrice = bcmul($cartTotalPrice, $productCouponRate[$cart['product_id']]['rate'], 2);
                                $cartTotalPrice = bcsub($cartTotalPrice, $couponPrice, 2);
                                $productCouponRate[$cart['product_id']]['coupon_price'] = bcsub($productCouponRate[$cart['product_id']]['coupon_price'], $couponPrice, 2);
                            }
                        }
                    }
                    //(店铺券)
                    if ($storeCouponRate && $cartTotalPrice > 0) {
                        if ($storeCouponRate['rate'] >= 1) {
                            $cartTotalPrice = 0;
                        } else {
                            if (count($merchantCart['list']) == $_k + 1) {
                                $cartTotalPrice = bcsub($cartTotalPrice, $storeCouponRate['coupon_price'], 2);
                            } else {
                                $couponPrice = bcmul($cartTotalPrice, $storeCouponRate['rate'], 2);
                                $cartTotalPrice = bcsub($cartTotalPrice, $couponPrice, 2);
                                $storeCouponRate['coupon_price'] = bcsub($storeCouponRate['coupon_price'], $couponPrice, 2);
                            }
                        }
                    }
                }

                //单个商品实际支付金额
                $cart['coupon_price'] = bcsub($_cartTotalPrice, $cartTotalPrice, 2);
                $cart['true_price'] = $cartTotalPrice;
            }
            unset($cart, $_k);
            $total_true_price = bcadd($_pay_price, $total_true_price, 2);
            if (count($merchantCartList) > 1 || count($merchantCart['list']) > 1) {
                $orderDeliveryStatus = $orderDeliveryStatus && $deliveryStatus;
            }
            $merchantCart['order'] = [
                'true_price' => $_pay_price,
                'city_take_postage' => $city_take_postage,
                'platform_coupon_price' => 0,
                'valid_total_price' => $valid_total_price,
                'total_price' => $total_price,
                'final_price' => $final_price,
                'down_price' => $down_price,
                'coupon_price' => $coupon_price,
                'svip_coupon_merge' => $svip_coupon_merge,
                'postage_price' => $postage_price,
                'isTake' => $isTake,
                'total_num' => $total_num,
                'enabledCoupon' => $enabledCoupon,
                'useCouponIds' => $useCouponIds,
                'allow_take' => $merTake,
                'allow_delivery' => $merDelivery,
                'allow_city_take' => $merCityTakes,
                'isCityTake' => $isCityTake,
                'delivery_status' => $deliveryStatus,
                'svip_discount' => $total_svip_discount,
                'use_svip' => $use_svip,
                'order_refund_switch' => $order_refund_switch,
                'take_id' => $merchantTakeId,
                'city_take' => $merchant_take_info,
                'default_delivery' => $order_model == 4 ? '' : self::DEFAULT_DELIVERY[array_search(true, [$merDelivery, $merTake, $merCityTakes], true)],
            ];
            $order_total_postage = bcadd($order_total_postage, $postage_price, 2);
            $order_svip_discount = bcadd($total_svip_discount, $order_svip_discount, 2);
            if (count($defaultSort)) {
                $merchantCart['coupon'] = &$defaultSort;
            }
        }

        unset($merchantCart);
//--------------- 到这里是 循环商户的 -----------------
        $usePlatformCouponId = $useCoupon[0] ?? 0;
        $usePlatformCouponId = is_array($usePlatformCouponId) ? array_pop($usePlatformCouponId) : $usePlatformCouponId;
        $usePlatformCouponFlag = isset($useCoupon[0]);

        foreach ($merchantCartList as &$merchantCart) {
            if (!$merchantCart['order']['use_svip'])
                continue;
            $totalMergePrice = 0;
            foreach ($merchantCart['list'] as &$cart) {
                if (!$cart['svip_coupon_merge']) {
                    $totalMergePrice = bcadd($totalMergePrice, $cart['true_price'], 2);
                    $cart['allow_price'] = $cart['true_price'];
                }
            }
            unset($cart);
            if ($totalMergePrice > 0) {
                $total_true_price = bcadd($total_true_price, $totalMergePrice, 2);
                $merchantCart['order']['valid_total_price'] = bcadd($merchantCart['order']['valid_total_price'], $totalMergePrice, 2);
                $merchantCart['order']['true_price'] = $merchantCart['order']['valid_total_price'];
            }
        }
        unset($merchantCart);

        //计算平台券优惠金额
//        if ($total_true_price > 0) {
        $StoreCouponUser = app()->make(StoreCouponUserRepository::class);
        $platformCoupon = $StoreCouponUser->validUserPlatformCoupon($uid);
        if ($enabledPlatformCoupon && count($platformCoupon)) {
            $catePriceLst = [];
            $storePriceLst = [];
            $_cartNum = 0;

            foreach ($merchantCartList as &$merchantCart) {
                if ($merchantCart['order']['true_price'] <= 0) continue;
                foreach ($merchantCart['list'] as &$cart) {
                    $_cartNum++;
                    if ($cart['product']['cate_id']) {
                        if (!isset($catePriceLst[$cart['product']['cate_id']])) {
                            $catePriceLst[$cart['product']['cate_id']] = ['price' => 0, 'cart' => []];
                        }
                        $catePriceLst[$cart['product']['cate_id']]['price'] = bcadd($catePriceLst[$cart['product']['cate_id']]['price'], $cart['true_price']);
                        $catePriceLst[$cart['product']['cate_id']]['cart'][] = &$cart;
                    }
                }
                unset($cart);
                $storePriceLst[$merchantCart['mer_id']] = [
                    'price' => $merchantCart['order']['true_price'],
                    'num' => count($merchantCart['list'])
                ];
            }
            unset($merchantCart);
            $flag = false;
            $platformCouponRate = null;

            foreach ($platformCoupon as &$coupon) {
                $coupon['checked'] = false;
                //通用券
                if ($coupon['coupon']['type'] === StoreCouponRepository::TYPE_PLATFORM_ALL) {
                    $coupon['disabled'] = $total_true_price <= 0 || $coupon['use_min_price'] > $total_true_price;
                    if (!$platformCouponRate && !$coupon['disabled'] && !$flag && ((!$usePlatformCouponId && !$usePlatformCouponFlag) || $usePlatformCouponId == $coupon['coupon_user_id'])) {
                        $platformCouponRate = [
                            'id' => $coupon['coupon_user_id'],
                            'type' => $coupon['coupon']['type'],
                            'price' => $total_true_price,
                            'coupon_price' => $coupon['coupon_price'],
                            'use_count' => $_cartNum,
                            'check' => function ($cart) {
                                return true;
                            }
                        ];
                        $coupon['checked'] = true;
                        $flag = true;
                    }
                    //品类券
                } else if ($coupon['coupon']['type'] === StoreCouponRepository::TYPE_PLATFORM_CATE) {
                    $_price = 0;
                    $_use_count = 0;
                    $cateIds = $coupon['product']->column('product_id');
                    $allCateIds = array_unique(array_merge(app()->make(StoreCategoryRepository::class)->allChildren($cateIds), $cateIds));
                    $flag2 = true;
                    foreach ($allCateIds as $cateId) {
                        if (isset($catePriceLst[$cateId])) {
                            $_price = bcadd($catePriceLst[$cateId]['price'], $_price, 2);
                            $_use_count += count($catePriceLst[$cateId]['cart']);
                            $flag2 = false;
                        }
                    }
                    $coupon['disabled'] = $flag2 || $coupon['use_min_price'] > $_price;
                    //品类券可用
                    if (!$platformCouponRate && !$coupon['disabled'] && !$flag && !$flag2 && ((!$usePlatformCouponId && !$usePlatformCouponFlag) || $usePlatformCouponId == $coupon['coupon_user_id'])) {
                        $platformCouponRate = [
                            'id' => $coupon['coupon_user_id'],
                            'type' => $coupon['coupon']['type'],
                            'price' => $_price,
                            'use_cate' => $allCateIds,
                            'coupon_price' => $coupon['coupon_price'],
                            'use_count' => $_use_count,
                            'check' => function ($cart) use ($allCateIds) {
                                return in_array($cart['product']['cate_id'], $allCateIds);
                            }
                        ];
                        $coupon['checked'] = true;
                        $flag = true;
                    }
                    //跨店券
                } else if ($coupon['coupon']['type'] === StoreCouponRepository::TYPE_PLATFORM_STORE) {
                    $_price = 0;
                    $_use_count = 0;
                    $flag2 = true;
                    foreach ($coupon['product'] as $item) {
                        $merId = $item['product_id'];
                        if (isset($storePriceLst[$merId])) {
                            $_price = bcadd($storePriceLst[$merId]['price'], $_price, 2);
                            $_use_count += $storePriceLst[$merId]['num'];
                            $flag2 = false;
                        }
                    }
                    $coupon['disabled'] = $flag2 || $coupon['use_min_price'] > $_price;
                    //店铺券可用
                    if (!$platformCouponRate && !$coupon['disabled'] && !$flag && !$flag2 && ((!$usePlatformCouponId && !$usePlatformCouponFlag) || $usePlatformCouponId == $coupon['coupon_user_id'])) {
                        $_merIds = $coupon['product']->column('product_id');
                        $platformCouponRate = [
                            'id' => $coupon['coupon_user_id'],
                            'type' => $coupon['coupon']['type'],
                            'price' => $_price,
                            'use_store' => $_merIds,
                            'coupon_price' => $coupon['coupon_price'],
                            'use_count' => $_use_count,
                            'check' => function ($cart) use ($_merIds) {
                                return in_array($cart['mer_id'], $_merIds);
                            }
                        ];
                        $coupon['checked'] = true;
                        $flag = true;
                    }
                }
            }
            unset($coupon);
        }
//        }

        $usePlatformCouponId = 0;
        $total_platform_coupon_price = 0;
        //计算平台优惠券
        if (isset($platformCouponRate)) {
            $_coupon_price = $platformCouponRate['coupon_price'];
            foreach ($merchantCartList as &$merchantCart) {
                $_ids = array_column($merchantCart['list'], 'cart_id');
                usort($merchantCart['list'], function ($a, $b) {
                    return $a['true_price'] > $b['true_price'] ? 1 : -1;
                });
                $_price = 0;
                foreach ($merchantCart['list'] as &$cart) {
                    if ($cart['true_price'] <= 0 || !$platformCouponRate['check']($cart)) continue;

                    if ($platformCouponRate['use_count'] === 1) {
                        $couponPrice = min($platformCouponRate['coupon_price'], $cart['true_price']);
                    } else {
                        $couponPrice = min(max(0.01, bcmul($_coupon_price, bcdiv($cart['true_price'], $platformCouponRate['price'], 6), 2)), $cart['true_price']);
                    }
                    $platformCouponRate['coupon_price'] = bcsub($platformCouponRate['coupon_price'], $couponPrice, 2);
                    $cart['true_price'] = bcsub($cart['true_price'], $couponPrice, 2);
                    $cart['platform_coupon_price'] = $couponPrice;
                    $platformCouponRate['use_count']--;
                    $_price = bcadd($couponPrice, $_price, 2);
                }
                unset($cart);
                $merchantCart['order']['platform_coupon_price'] = $_price;
                $merchantCart['order']['true_price'] = bcsub($merchantCart['order']['true_price'], $_price, 2);
                $total_platform_coupon_price = bcadd($total_platform_coupon_price, $_price, 2);
                usort($merchantCart['list'], function ($a, $b) use ($_ids) {
                    return array_search($a['cart_id'], $_ids) > array_search($b['cart_id'], $_ids) ? 1 : -1;
                });
            }
            $usePlatformCouponId = $platformCouponRate['id'];
            unset($merchantCart);
        }

        //积分配置
        $sysIntegralConfig = systemConfig(['integral_money', 'integral_status', 'integral_order_rate']);
        $merIntegralFlag = false;
        $order_total_integral = 0;
        $order_total_integral_price = 0;
        $order_total_give_integral = 0;
        $allow_no_address = true;

        foreach ($merchantCartList as &$merchantCart) {
            $_cartHasBlindbox = !empty($merchantCart['is_blindbox']);
            if (!$_cartHasBlindbox) {
                foreach ($merchantCart['list'] as $_c) {
                    if (!empty($_c['product']['is_blindbox_exclusive'])
                        || ((int)($_c['product_id'] ?? 0) > 0 && (int)Db::name('store_product')->where('product_id', (int)$_c['product_id'])->value('is_blindbox_exclusive') === 1)
                    ) { $_cartHasBlindbox = true; break; }
                }
            }
            $allow_no_address = $allow_no_address && ($merchantCart['order']['isTake'] || $_cartHasBlindbox);
            $merIntegralConfig = $merchantCart['take'];
            $merIntegralConfig['mer_integral_rate'] = min(1, $merIntegralConfig['mer_integral_rate'] > 0 ? bcdiv($merIntegralConfig['mer_integral_rate'], 100, 4) : $merIntegralConfig['mer_integral_rate']);
            $total_integral = 0;
            $total_integral_price = 0;
            $merIntegralFlag = $merIntegralFlag || ((bool)$merIntegralConfig['mer_integral_status']);
            $integralFlag = $useIntegral && $sysIntegralConfig['integral_status'] && $sysIntegralConfig['integral_money'] > 0 && $merIntegralConfig['mer_integral_status'];

            //计算积分抵扣
            foreach ($merchantCart['list'] as &$cart) {
                //只有普通商品可以抵扣
                if ($cart['product_type'] == 0 && $integralFlag && $userIntegral > 0 && $merchantCart['order']['true_price'] > 0) {
                    $integralRate = $cart['product']['integral_rate'];
                    if ($integralRate < 0) {
                        $integralRate = $merIntegralConfig['mer_integral_rate'];
                    } else if ($integralRate > 0) {
                        $integralRate = min(bcdiv($integralRate, 100, 4), 1);
                    }
                    if ($integralRate > 0) {
                        $productIntegralPrice = min(bcmul(bcmul($this->cartByPrice($cart), $cart['cart_num'], 2), $integralRate, 2), $cart['true_price']);
                        if ($productIntegralPrice > 0) {
                            $productIntegral = ceil(bcdiv($productIntegralPrice, $sysIntegralConfig['integral_money'], 3));
                            if ($productIntegral <= $userIntegral) {
                                $userIntegral = bcsub($userIntegral, $productIntegral, 0);
                                //使用多少积分抵扣了多少金额
                                $cart['integral'] = [
                                    'use' => $productIntegral,
                                    'price' => $productIntegralPrice
                                ];
                            } else {
                                $productIntegralPrice = bcmul($userIntegral, $sysIntegralConfig['integral_money'], 2);
                                //使用多少积分抵扣了多少金额
                                $cart['integral'] = [
                                    'use' => $userIntegral,
                                    'price' => $productIntegralPrice
                                ];
                                $userIntegral = 0;
                            }

                            $cart['true_price'] = bcsub($cart['true_price'], $cart['integral']['price'], 2);
                            $merchantCart['order']['true_price'] = bcsub($merchantCart['order']['true_price'], $cart['integral']['price'], 2);

                            $total_integral_price = bcadd($total_integral_price, $cart['integral']['price'], 2);
                            $total_integral = bcadd($total_integral, $cart['integral']['use'], 0);
                            continue;
                        }
                    }
                }
                $cart['integral'] = null;
            }
            unset($cart);
            $order_total_integral = bcadd($order_total_integral, $total_integral, 0);
            $order_total_integral_price = bcadd($order_total_integral_price, $total_integral_price, 2);

            $_pay_price = $merchantCart['order']['true_price'];
            $valid_total_price = $merchantCart['order']['valid_total_price'];
            $total_price = $merchantCart['order']['total_price'];
            $final_price = $merchantCart['order']['final_price'];
            $down_price = $merchantCart['order']['down_price'];
            $coupon_price = $merchantCart['order']['coupon_price'];
            $postage_price = $merchantCart['order']['postage_price'];
            $city_take_postage = $merchantCart['order']['city_take_postage'];
            //计算订单商品金额
            $org_price = bcadd(bcsub($total_price, $valid_total_price, 2), max($_pay_price, 0), 2);
            if ($presellType == 2) {
                $org_price = max(bcsub($org_price, $final_price, 2), $down_price);
            }

            //获取可优惠金额
            $coupon_price = min($coupon_price, bcsub($total_price, $down_price, 2));
            $order_coupon_price = bcadd($coupon_price, $order_coupon_price, 2);

            //计算订单金额
            if ($order_type != 2 || $presellType != 2) {
                $endPostage = $city_take_postage ? $city_take_postage : $postage_price;
                $pay_price = bcadd($endPostage, $org_price, 2);
            } else {
                $pay_price = $org_price;
            }

            $giveIntegralFlag = $sysIntegralConfig['integral_status'] && $sysIntegralConfig['integral_order_rate'] > 0;
            $total_give_integral = 0;
            //计算赠送积分, 只有普通商品赠送积分
            if ($giveIntegralFlag && !$order_type && $pay_price > 0) {
                $total_give_integral = floor(bcmul($pay_price, $sysIntegralConfig['integral_order_rate'], 0));
                if ($total_give_integral > 0 && $svip_status && $svip_integral_rate > 0) {
                    $total_give_integral = bcmul($svip_integral_rate, $total_give_integral, 0);
                }
            }
            $order_total_give_integral = bcadd($total_give_integral, $order_total_give_integral, 0);

            foreach ($fn as $callback) {
                $callback();
            }

            $merchantCart['order']['order_type'] = $order_type;
            $merchantCart['order']['total_give_integral'] = $total_give_integral;
            $merchantCart['order']['total_integral_price'] = $total_integral_price;
            $merchantCart['order']['total_integral'] = $total_integral;
            $merchantCart['order']['org_price'] = $org_price;
            $merchantCart['order']['pay_price'] = $pay_price;
            $merchantCart['order']['coupon_price'] = $coupon_price;
            $order_price = bcadd($order_price, $pay_price, 2);

            $order_total_price = bcadd($order_total_price, $total_price, 2);
        }
        unset($merchantCart);

        if ($order_model) {
            $allow_no_address = false;
        }

        foreach ($merchantCartList as &$merchantCart) {
            foreach ($merchantCart['list'] as &$cart) {
                $cart['total_price'] = bcadd($cart['total_price'], $cart['svip_discount'], 2);
            }
            unset($cart);
            $merchantCart['order']['total_price'] = bcadd($merchantCart['order']['total_price'], $merchantCart['order']['svip_discount'], 2);
            $order_total_price = bcadd($order_total_price, $merchantCart['order']['svip_discount'], 2);
        }
        unset($merchantCart);

        $status = ($address || $order_model || $allow_no_address) ? ($noDeliver ? 'noDeliver' : 'finish') : 'noAddress';
        $order = $merchantCartList;
        $total_price = $order_total_price;

        $openIntegral = $merIntegralFlag && !$order_type && $sysIntegralConfig['integral_status'] && $sysIntegralConfig['integral_money'] > 0;
        $total_coupon = bcadd($order_svip_discount, bcadd(bcadd($total_platform_coupon_price, $order_coupon_price, 2), $order_total_integral_price, 2), 2);

        $data = compact(
                'order_type',
                'order_model',
                'order_extend',
                'mer_form_id',
                'mer_form_info',
                'order_total_postage',
                'order_price',
                'total_price',
                'platformCoupon',
                'enabledPlatformCoupon',
                'usePlatformCouponId',
                'order_total_integral',
                'order_total_integral_price',
                'order_total_give_integral',
                'order_svip_discount',
                'total_platform_coupon_price',
                'total_coupon',
                'order_coupon_price',
                'status',
                'address',
                'openIntegral',
                'useIntegral',
                'key',
                'order_refund_switch',
                'order'
            ) + ['allow_address' => !$allow_no_address, 'order_delivery_status' => $orderDeliveryStatus];

        // 盲盒确认页脱敏：不暴露真实款式名称/SKU图（创建订单时仍会按权重随机并写入真实 SKU）
        foreach ($data['order'] as &$merchantCart) {
            if (empty($merchantCart['is_blindbox'])) {
                continue;
            }
            foreach ($merchantCart['list'] as &$cart) {
                if (!empty($cart['productAttr']) && is_array($cart['productAttr'])) {
                    $cart['productAttr']['sku'] = '惊喜开盒 · 款式随机揭晓';
                    if (!empty($cart['product']['image'])) {
                        $cart['productAttr']['image'] = $cart['product']['image'];
                    }
                }
            }
            unset($cart);
        }
        unset($merchantCart);

        Cache::set('order_create_cache' . $uid . '_' . $key, $data, 600);
        return $data;
    }

    /**
     * 订单创建
     * @param $key
     * @param int $pay_type
     * @param $user
     * @param array $cartId
     * @param array $extend
     * @param array $mark
     * @param array $receipt_data
     * @param array|null $takes
     * @param array|null $useCoupon
     * @param bool $useIntegral
     * @param int|null $addressId
     * @param array $post
     * @return mixed
     * @throws \think\db\exception\DbException
     */
    public function v2CreateOrder($key, int $pay_type, $user, array $cartId, array $extend, array $mark, array
    $receipt_data, array $takes = null, array $useCoupon = null, bool $useIntegral = false, int $addressId = null, array $post = [])
    {

        $uid = $user->uid;
        $cache_key = 'order_create_cache' . $uid . '_' . $key;
        $orderInfo = Cache::get($cache_key);
        if (!$orderInfo) {
            throw new ValidateException('订单操作超时,请刷新页面');
        }

        if ($pay_type == 7 && count($orderInfo['order']) > 1) {
            throw new ValidateException('线下支付每次仅能购买一个店铺商品');
        }
        $order_model = $orderInfo['order_model'];
//        $order_extend = $orderInfo['order_extend'];
        if (!$orderInfo['order_delivery_status']) {
            throw new ValidateException('部分商品配送方式不一致,请单独下单');
        }

        if ($orderInfo['order_price'] > 1000000)throw new ValidateException('支付金额超出最大限制');
        $order_extend = [];
        // 验证商品表单是否必填
        if ($orderInfo['mer_form_id'] > 0) {
            $order_extend = $this->getFormInfo($orderInfo['mer_form_id'],$extend,$order_model);
        }
        if ($orderInfo['status'] == 'noDeliver') throw new ValidateException('部分商品不支持该区域');
        if ($orderInfo['status'] == 'noAddress') throw new ValidateException('请选择地址');
        if (!$order_model && $orderInfo['allow_address']) {
            if (!$orderInfo['address']) throw new ValidateException('请选择正确的收货地址');
            if (!$orderInfo['address']['province_id']) throw new ValidateException('请完善收货地址信息');
        }
        $orderType = $orderInfo['order_type'];
        if ($orderType && (count($orderInfo['order']) > 1 || ($orderType != 10 && count($orderInfo['order'][0]['list']) > 1))) {
            throw new ValidateException('活动商品请单独购买');
        }

        $merchantCartList = $orderInfo['order'];
        $cartSpread = 0;
        $hasTake = false;

        foreach ($merchantCartList as $merchantCart) {
            if ($merchantCart['order']['isTake']) {
                $hasTake = true;
            }
            //检查发票状态
            if (isset($receipt_data[$merchantCart['mer_id']]) && !$merchantCart['openReceipt'])
                throw new ValidateException('该店铺不支持开发票');

            foreach ($merchantCart['list'] as $cart) {
                if (!$cartSpread && $cart['spread_id']) {
                    $cartSpread = $cart['spread_id'];
                }
            }
            if($merchantCart['order']['isCityTake'] && !$merchantCart['order']['delivery_status']) {
                throw new ValidateException("商户：【{$merchantCart['mer_name']}】的订单，不在配送范围内，请检查");
            }
        }
        if ($hasTake) {
            app()->make(UserAddressValidate::class)->scene('take')->check($post);
        }

        // 分享者 X / 邀请人 B：先记下原邀请链，再处理商品分享绑定
        $inviteBUid = (int)($user->spread_uid ?? 0);
        $cartShareMerId = 0;
        foreach ($merchantCartList as $_mc) {
            foreach ($_mc['list'] as $_c) {
                if (!$cartShareMerId && !empty($_c['share_mer_id'])) {
                    $cartShareMerId = (int)$_c['share_mer_id'];
                }
            }
        }
        if (!$cartShareMerId) {
            $cartShareMerId = app()->make(\app\common\repositories\store\BlindBoxShareRepository::class)
                ->getBound((int)$uid);
        }

        if ($cartSpread) {
            app()->make(UserRepository::class)->bindSpread($user, $cartSpread);
        }
        $ex = systemConfig('extension_status');
        $spreadUser = null;
        $topUser = null;
        $get_extension_info = get_extension_info($user);
        $isSelfBuy = $get_extension_info['isSelfBuy'];
        if ($ex) {
            if ($isSelfBuy) {
                $spreadUser = $user;
                $topUser = $user->valid_spread;
            } else {
                $spreadUser = $user->valid_spread;
                $topUser = $user->valid_top;
            }
        }
        $spreadUid = $spreadUser->uid ?? 0;
        $topUid = $topUser->uid ?? 0;

        // 全站三级分销：有分享者 X 时，一级=X，二级=B(原邀请人)；无 X 保持原逻辑
        $shareXUid = (int)$cartSpread;
        if ($shareXUid <= 0 && $cartShareMerId > 0) {
            $shareXUid = app()->make(\app\common\repositories\store\BlindBoxShareRepository::class)
                ->resolveShareUid($cartShareMerId);
        }
        if ($ex && $shareXUid > 0) {
            $spreadUid = $shareXUid;
            $topUid = $inviteBUid > 0 && $inviteBUid !== $shareXUid ? $inviteBUid : 0;
            $userRepositoryTmp = app()->make(UserRepository::class);
            $spreadUser = $userRepositoryTmp->get($spreadUid) ?: $spreadUser;
            $topUser = $topUid > 0 ? ($userRepositoryTmp->get($topUid) ?: null) : null;
        }
        $giveCouponIds = [];
        $address = $orderInfo['address'];
        $allUseCoupon = $orderInfo['usePlatformCouponId'] ? [$orderInfo['usePlatformCouponId']] : [];
        $totalNum = 0;
        $totalPostage = 0;
        $totalCost = 0;
        $cartIds = [];
        $orderList = [];

        // ===== 盲盒模块：SKU随机选择逻辑（平台盲盒店 + 商家专属盲盒）=====
        $merchantRepository = app()->make(MerchantRepository::class);
        $productAttrValueRepository = app()->make(ProductAttrValueRepository::class);
        foreach ($merchantCartList as &$_merchantCart) {
            $_merchant = $merchantRepository->get($_merchantCart['mer_id']);
            $_isBlindboxShop = $_merchant && !empty($_merchant['is_blindbox']);
            foreach ($_merchantCart['list'] as &$_cart) {
                $_isExclusiveProduct = !empty($_cart['product']['is_blindbox_exclusive'])
                    || (int)Db::name('store_product')->where('product_id', (int)$_cart['product_id'])->value('is_blindbox_exclusive') === 1;
                if (!$_isBlindboxShop && !$_isExclusiveProduct) {
                    continue;
                }
                    $_attrValues = $productAttrValueRepository->search(['product_id' => $_cart['product_id']])
                        ->where('stock', '>', 0)
                        ->select();

                    if ($_attrValues->isEmpty()) {
                        throw new ValidateException('盲盒商品「' . ($_cart['product']['store_name'] ?? '') . '」所有款式已售罄');
                    }

                    $cartNum = (int)$_cart['cart_num'];
                    $retryCount = 0;
                    $maxRetries = count($_attrValues);
                    $excludedIds = [];
                    $_selectedSku = null;

                    while ($retryCount < $maxRetries) {
                        $candidateValues = $excludedIds
                            ? $_attrValues->filter(fn($v) => !in_array($v['value_id'], $excludedIds))
                            : $_attrValues;
                        if ($candidateValues->isEmpty()) {
                            throw new ValidateException('盲盒商品「' . ($_cart['product']['store_name'] ?? '') . '」所有款式已售罄');
                        }

                        $_selectedSku = $this->weightedRandomSelect($candidateValues);
                        if (!$_selectedSku) {
                            throw new ValidateException('盲盒款式选择失败');
                        }

                        $currentStock = Db::name('store_product_attr_value')
                            ->where('value_id', $_selectedSku['value_id'])
                            ->value('stock');

                        if ($currentStock >= $cartNum) {
                            break;
                        }

                        $excludedIds[] = $_selectedSku['value_id'];
                        $retryCount++;
                        $_selectedSku = null;
                    }

                    if (!$_selectedSku) {
                        throw new ValidateException('盲盒商品「' . ($_cart['product']['store_name'] ?? '') . '」库存不足');
                    }

                $_attrArr = is_object($_selectedSku) ? $_selectedSku->toArray() : (array)$_selectedSku;
                // 随机换 SKU 后 toArray 不含 bc_extension_*，需按商品分销设置补齐，避免下单 Undefined array key
                $_cartArr = is_array($_cart) ? $_cart : (method_exists($_cart, 'toArray') ? $_cart->toArray() : (array)$_cart);
                $_attrArr['bc_extension_one'] = $this->calcBlindboxBcExtension($_cartArr, $_attrArr, 1);
                $_attrArr['bc_extension_two'] = $this->calcBlindboxBcExtension($_cartArr, $_attrArr, 2);
                $_cart['productAttr'] = $_attrArr;
                $_cart['productAttrUnique'] = $_selectedSku['unique'];
                if ($_selectedSku['price'] > 0) {
                    $_cart['true_price'] = $_selectedSku['price'];
                }
            }
            unset($_cart);
        }
        unset($_merchantCart);
        // ===== 盲盒模块结束 =====

        //Cache::delete($cache_key);
        //订单支付以后是否支持取消， 目前仅用于预约商品
        $is_cancel = 0;
        foreach ($merchantCartList as $k => $merchantCart) {
            $cost = 0;
            $total_extension_one = 0;
            $total_extension_two = 0;
            //计算佣金和赠送的优惠券
            foreach ($merchantCart['list'] as &$cart) {
                if($order_model == 4) {
                   $is_cancel = $cart['reservation']['is_cancel_reservation'];
                }
                $cartIds[] = $cart['cart_id'];
                $giveCouponIds = array_merge($giveCouponIds, $cart['product']['give_coupon_ids'] ?: []);
                $cart['cost'] = $cart['productAttr']['cost'];
                $cost = bcadd(bcmul($cart['cost'], $cart['cart_num'], 2), $cost, 2);
                $extension_one = 0;
                $extension_two = 0;
                if ($ex || $orderType == 4) {
                    //预售订单
                    if ($orderType == 2) {
                        $_payPrice = $merchantCart['order']['pay_price'];
                        $rate = $cart['productPresell']['presell_type'] == 2 ? bcdiv($cart['productPresellAttr']['down_price'], $cart['productPresellAttr']['presell_price'], 3) : 1;
                        $one_price = $_payPrice > 0 ? bcdiv($_payPrice, $cart['cart_num'], 2) : 0;
                        if ($spreadUid && $cart['productPresellAttr']['bc_extension_one'] > 0) {
                            $org_extension = $cart['productPresellAttr']['bc_extension_one'];
                            if ($spreadUser->brokerage_level > 0 && $spreadUser->brokerage && $spreadUser->brokerage->extension_one_rate > 0) {
                                $org_extension = bcmul($org_extension, 1 + $spreadUser->brokerage->extension_one_rate, 2);
                            }
                            $_extension_one = bcmul($rate, $org_extension, 3);
                            $presell_extension_one = 0;
                            if ($cart['true_price'] > 0) {
                                $extension_one = bcmul(bcdiv($one_price, $cart['productPresellAttr']['down_price'], 3), $_extension_one, 2);
                            }
                            if ($rate < 1) {
                                $presell_extension_one = bcmul(1 - $rate, $org_extension, 2);
                            }
                            $cart['final_extension_one'] = bcmul($extension_one, $cart['cart_num'], 2);
                            $extension_one = bcadd($extension_one, $presell_extension_one, 2);
                            $cart['presell_extension_one'] = bcmul($presell_extension_one, $cart['cart_num'], 2);
                        }
                        if ($topUid && $cart['productPresellAttr']['bc_extension_two'] > 0) {
                            $org_extension = $cart['productPresellAttr']['bc_extension_two'];
                            if ($topUser->brokerage_level > 0 && $topUser->brokerage && $topUser->brokerage->extension_two_rate > 0) {
                                $org_extension = bcmul($org_extension, 1 + $topUser->brokerage->extension_two_rate, 2);
                            }
                            $_extension_two = bcmul($rate, $org_extension, 2);
                            $presell_extension_two = 0;
                            if ($cart['true_price'] > 0) {
                                $extension_two = bcmul(bcdiv($one_price, $cart['productPresellAttr']['down_price'], 3), $_extension_two, 2);
                            }
                            if ($rate < 1) {
                                $presell_extension_two = bcmul(1 - $rate, $org_extension, 2);
                            }
                            $cart['final_extension_two'] = bcmul($extension_two, $cart['cart_num'], 2);;
                            $extension_two = bcadd($extension_two, $presell_extension_two, 2);
                            $cart['presell_extension_two'] = bcmul($presell_extension_two, $cart['cart_num'], 2);
                        }
                    } else if ($orderType == 4) {
                        $extension_one = 0;
                        $extension_two = 0;
                        $topUid = 0;
                        $isSelfBuy = 0;
                        $spreadUid = 0;
                        if ($cart['product']['productGroup']['leader_extension']) {
                            $frate = $cart['product']['productGroup']['leader_rate'];
                            $productGroupUserRepository = app()->make(ProductGroupUserRepository::class);
                            $group_buying = $productGroupUserRepository->getWhere(['group_buying_id' => $cart['source_id'],'is_leader' => 1, 'is_del' => 0]);
                            if ($group_buying) {
                                $spreadUid = $group_buying['uid'] ?? 0;
                                $extension_one = $cart['true_price'] > 0 ? bcmul($cart['true_price'],bcdiv($frate, 100,3), 2) : 0;
                            }
                        }
                    }else if (!$orderType) {
                        $bcOne = (float)($cart['productAttr']['bc_extension_one'] ?? 0);
                        $bcTwo = (float)($cart['productAttr']['bc_extension_two'] ?? 0);
                        if ($spreadUid && $bcOne > 0) {
                            $org_extension = $bcOne;
                            if ($spreadUser && $spreadUser->brokerage_level > 0 && $spreadUser->brokerage && $spreadUser->brokerage->extension_one_rate > 0) {
                                $org_extension = bcmul($org_extension, 1 + $spreadUser->brokerage->extension_one_rate, 2);
                            }
                            $extension_one = $cart['true_price'] > 0 ? bcmul(bcdiv($cart['true_price'], $cart['total_price'], 3), $org_extension, 2) : 0;
                        }
                        if ($topUid && $bcTwo > 0) {
                            $org_extension = $bcTwo;
                            if ($topUser && $topUser->brokerage_level > 0 && $topUser->brokerage && $topUser->brokerage->extension_two_rate > 0) {
                                $org_extension = bcmul($org_extension, 1 + $topUser->brokerage->extension_two_rate, 2);
                            }
                            $extension_two = $cart['true_price'] > 0 ? bcmul(bcdiv($cart['true_price'], $cart['total_price'], 3), $org_extension, 2) : 0;
                        }
                    }
                }
                $cart['extension_one'] = $extension_one;
                $cart['extension_two'] = $extension_two;
                $total_extension_one = bcadd($total_extension_one, bcmul($extension_one, $cart['cart_num'], 2), 2);
                $total_extension_two = bcadd($total_extension_two, bcmul($extension_two, $cart['cart_num'], 2), 2);
            }
            unset($cart);

            $rate = 0;
            if ($merchantCart['commission_switch']) {
                $rate = $merchantCart['commission_rate'];
            } else if (
                isset($merchantCart['merchantCategory']['commission_rate']) && $merchantCart['merchantCategory']['commission_rate'] > 0
            )
            {
                $rate = bcmul($merchantCart['merchantCategory']['commission_rate'], 100, 4);
            }

            $user_address = isset($address) ? ($address['province'] . $address['city'] . $address['district'] . $address['street'] . $address['detail']) : '';
            $_merchantCheck = $merchantRepository->get($merchantCart['mer_id']);
            $_isBlindboxShop = $_merchantCheck && !empty($_merchantCheck['is_blindbox']);
            $_isExclusiveOrder = 0;
            foreach ($merchantCart['list'] as $_exCart) {
                $pid = (int)($_exCart['product_id'] ?? 0);
                $flag = !empty($_exCart['product']['is_blindbox_exclusive'])
                    || ($pid > 0 && (int)Db::name('store_product')->where('product_id', $pid)->value('is_blindbox_exclusive') === 1);
                if ($flag) {
                    $_isExclusiveOrder = 1;
                    break;
                }
            }
            $_isBlindbox = $_isBlindboxShop || $_isExclusiveOrder;
            // 盲盒订单：写入分享来源店铺
            $_shareMerId = 0;
            if ($_isBlindbox) {
                foreach ($merchantCart['list'] as $_shareCart) {
                    if (!empty($_shareCart['share_mer_id'])) {
                        $_shareMerId = (int)$_shareCart['share_mer_id'];
                        break;
                    }
                }
                if (!$_shareMerId) {
                    $_shareMerId = $cartShareMerId ?: app()->make(\app\common\repositories\store\BlindBoxShareRepository::class)
                        ->getBound((int)$uid);
                }
            }
            // 专属盲盒整单不分销
            $_orderSpreadUid = $spreadUid;
            $_orderTopUid = $topUid;
            $_orderExtOne = $total_extension_one;
            $_orderExtTwo = $total_extension_two;
            if ($_isExclusiveOrder) {
                $_orderSpreadUid = 0;
                $_orderTopUid = 0;
                $_orderExtOne = 0;
                $_orderExtTwo = 0;
            }
            // 公益网购享免单上下文
            $_welfareCtx = app()->make(\app\common\repositories\store\nearby\WelfareFreeOrderRepository::class)->getContext((int)$uid);
            $_isWelfare = 0;
            $_welfareBillAmount = 0;
            $_welfareScanMerId = 0;
            $_welfareCommission = 0;
            if ($_welfareCtx) {
                foreach ($merchantCart['list'] as $_wCart) {
                    if ((int)($_wCart['product_id'] ?? 0) === (int)$_welfareCtx['product_id']) {
                        $_isWelfare = 1;
                        $_welfareBillAmount = (float)$_welfareCtx['bill_amount'];
                        $_welfareScanMerId = (int)$_welfareCtx['scan_mer_id'];
                        $_welfareCommission = (float)$_welfareCtx['welfare_commission'];
                        break;
                    }
                }
            }

            //整理订单数据
            $_order = [
                'cartInfo' => $merchantCart,
                'activity_type' => $orderInfo['order_type'],
                'is_blindbox_order' => (int)$_isBlindbox,
                'is_blindbox_exclusive' => (int)$_isExclusiveOrder,
                'is_welfare_order' => $_isWelfare,
                'welfare_bill_amount' => $_welfareBillAmount,
                'welfare_scan_mer_id' => $_welfareScanMerId,
                'welfare_commission' => $_welfareCommission,
                'welfare_bill_sn' => '',
                'share_mer_id' => $_shareMerId,
                'commission_rate' => (float)$rate,
                'order_type' => $merchantCart['order']['isTake'] ? 1 : ($merchantCart['order']['isCityTake'] ? 2 : 0),
                'is_virtual' => $order_model,
                'extension_one' => $_orderExtOne,
                'extension_two' => $_orderExtTwo,
                'order_sn' => $this->getNewOrderId(StoreOrderRepository::TYPE_SN_ORDER) . ($k + 1),
                'uid' => $uid,
                'spread_uid' => $_orderSpreadUid,
                'top_uid' => $_orderTopUid,
                'is_selfbuy' => $isSelfBuy,
                'real_name' => $merchantCart['order']['isTake'] ? $post['real_name'] : ($address['real_name'] ?? ''),
                'user_phone' => $merchantCart['order']['isTake'] ? $post['phone'] : ($address['phone'] ?? ''),
                'user_address' => $user_address,
                'cart_id' => implode(',', array_column($merchantCart['list'], 'cart_id')),
                'total_num' => $merchantCart['order']['total_num'],
                'total_price' => $merchantCart['order']['total_price'],
                'total_postage' => $merchantCart['order']['isCityTake'] ? $merchantCart['order']['city_take_postage'] : $merchantCart['order']['postage_price'],
                'pay_postage' => $merchantCart['order']['isCityTake'] ? $merchantCart['order']['city_take_postage'] : $merchantCart['order']['postage_price'],
                'svip_discount' => $merchantCart['order']['svip_discount'],
                'pay_price' => $merchantCart['order']['pay_price'],
                'integral' => $merchantCart['order']['total_integral'],
                'integral_price' => $merchantCart['order']['total_integral_price'],
                'give_integral' => $merchantCart['order']['total_give_integral'],
                'mer_id' => $merchantCart['mer_id'],
                'cost' => $cost,
                'order_extend' => count($order_extend) ? json_encode($order_extend, JSON_UNESCAPED_UNICODE) : '',
                'coupon_id' => implode(',', $merchantCart['order']['useCouponIds']),
                'mark' => $mark[$merchantCart['mer_id']] ?? '',
                'coupon_price' => bcadd($merchantCart['order']['coupon_price'], $merchantCart['order']['platform_coupon_price'], 2),
                'platform_coupon_price' => $merchantCart['order']['platform_coupon_price'],
                'pay_type' => $pay_type,
                'refund_switch' => $merchantCart['order']['order_refund_switch'],
                'is_cancel' => $is_cancel,
                'enable_assigned' =>  $merchantCart['order']['isTake'] ? $merchantCart['take']['enable_tostore_assigned']
            :$merchantCart['take']['enable_assigned'],
                'merchant_take_id' => $merchantCart['order']['take_id'],
                'merchant_take_info' => json_encode($merchantCart['order']['city_take']),
            ];
            $allUseCoupon = array_merge($allUseCoupon, $merchantCart['order']['useCouponIds']);
            $orderList[] = $_order;
            $totalPostage = bcadd($totalPostage, $_order['total_postage'], 2);
            $totalCost = bcadd($totalCost, $cost, 2);
            $totalNum += $merchantCart['order']['total_num'];
        }
        $groupOrder = [
            'uid' => $uid,
            'group_order_sn' => count($orderList) === 1 ? $orderList[0]['order_sn'] : ($this->getNewOrderId(StoreOrderRepository::TYPE_SN_ORDER) . '0'),
            'total_postage' => $totalPostage,
            'total_price' => $orderInfo['total_price'],
            'total_num' => $totalNum,
            'real_name' => $address['real_name'] ?? '',
            'user_phone' => $address['phone'] ?? '',
            'user_address' => $user_address,
            'pay_price' => $orderInfo['order_price'],
            'coupon_price' => bcadd($orderInfo['total_platform_coupon_price'], $orderInfo['order_coupon_price'], 2),
            'pay_postage' => $totalPostage,
            'cost' => $totalCost,
            'coupon_id' => $orderInfo['usePlatformCouponId'] > 0 ? $orderInfo['usePlatformCouponId'] : '',
            'pay_type' => $pay_type,
            'give_coupon_ids' => $giveCouponIds,
            'integral' => $orderInfo['order_total_integral'],
            'integral_price' => $orderInfo['order_total_integral_price'],
            'give_integral' => $orderInfo['order_total_give_integral'],
            'activity_type' => $orderInfo['order_type'],
        ];
        event('order.create.before', compact('groupOrder', 'orderList'));

        try{
            $group = Db::transaction(function () use ($ex, $user, $topUid, $spreadUid, $uid, $receipt_data, $cartIds,
                $allUseCoupon, $groupOrder, $orderList, $orderInfo,$order_model) {
                $storeGroupOrderRepository = app()->make(StoreGroupOrderRepository::class);
                $storeCartRepository = app()->make(StoreCartRepository::class);
                $attrValueRepository = app()->make(ProductAttrValueRepository::class);
                $productRepository = app()->make(ProductRepository::class);
                $storeOrderProductRepository = app()->make(StoreOrderProductRepository::class);
                $couponUserRepository = app()->make(StoreCouponUserRepository::class);
                //订单记录
                $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
                $userMerchantRepository = app()->make(UserMerchantRepository::class);

                //减库存
                foreach ($orderList as $order) {
                    foreach ($order['cartInfo']['list'] as $cart) {
                        if (!isset($uniqueList[$cart['productAttr']['product_id'] . $cart['productAttr']['unique']]))
                            $uniqueList[$cart['productAttr']['product_id'] . $cart['productAttr']['unique']] = true;
                        else
                            throw new ValidateException('购物车商品信息重复');
                        try {
                            if ($cart['product_type'] == '1') {
                                $seckillKey = $productRepository->getSeckillStockCache($cart['product'], $cart['productAttr']['unique']);
                                $pop = $productRepository->pop($seckillKey['key'], $cart['product_type'] ,$cart['cart_num']);
                                if ($pop === false) throw new ValidateException('库存不足');
                                $this->seckill_stock_key = [
                                    'key' => $seckillKey['key'],
                                    'num' => $cart['cart_num']
                                ];
                                $attrValueRepository->descSkuStock($cart['product']['old_product_id'], $cart['productAttr']['sku'], $cart['cart_num']);//减少sku库存
                                $productRepository->descStock($cart['product']['old_product_id'], $cart['cart_num']);
                                $productRepository->incSales($cart['product']['product_id'], $cart['cart_num']);
                                $attrValueRepository->incSales($cart['product']['product_id'], $cart['productAttr']['sku'],$cart['cart_num']);//增加秒杀sku的销量
                            } else if ($cart['product_type'] == '2') {
                                $productPresellSkuRepository = app()->make(ProductPresellSkuRepository::class);
                                $productPresellSkuRepository->descStock($cart['productPresellAttr']['product_presell_id'], $cart['productPresellAttr']['unique'], $cart['cart_num']);
                                $attrValueRepository->descStock($cart['productAttr']['product_id'], $cart['productAttr']['unique'], $cart['cart_num']);
                                $productRepository->descStock($cart['product']['product_id'], $cart['cart_num']);
                            } else if ($cart['product_type'] == '3') {
                                app()->make(ProductAssistSkuRepository::class)->descStock($cart['productAssistAttr']['product_assist_id'], $cart['productAssistAttr']['unique'], $cart['cart_num']);
                                $productRepository->descStock($cart['product']['old_product_id'], $cart['cart_num']);
                                $attrValueRepository->descStock($cart['product']['old_product_id'], $cart['productAttr']['unique'], $cart['cart_num']);
                            } else if ($cart['product_type'] == '4') {
                                app()->make(ProductGroupSkuRepository::class)->descStock($cart['activeSku']['product_group_id'], $cart['activeSku']['unique'], $cart['cart_num']);
                                $productRepository->descStock($cart['product']['old_product_id'], $cart['cart_num']);
                                $attrValueRepository->descStock($cart['product']['old_product_id'], $cart['productAttr']['unique'], $cart['cart_num']);
                            } else {
                                if ($order_model == 4) {
                                    if(!$this->validateReservation($cart)) {
                                        throw new ValidateException('当前时间已经预约满了～');
                                    }
                                    $productRepository->incSales($cart['product']['product_id'], $cart['cart_num']);
                                } else {
                                    $attrValueRepository->descStock($cart['productAttr']['product_id'], $cart['productAttr']['unique'], $cart['cart_num']);
                                    $productRepository->descStock($cart['product']['product_id'], $cart['cart_num']);
                                    if ($cart['integral'] && $cart['integral']['use'] > 0) {
                                        $productRepository->incIntegral($cart['product']['product_id'], $cart['integral']['use'], $cart['integral']['price']);
                                    }
                                }

                            }
                        } catch (\Exception $e) {
                            throw new ValidateException('库存不足');
                        }
                    }
                }

                if ($orderInfo['order_type'] == 10 && !app()->make(StoreDiscountRepository::class)->decStock($orderList[0]['cartInfo']['list'][0]['source_id'])) {
                    throw new ValidateException('套餐库不足');
                }

                //修改购物车状态
                $storeCartRepository->updates($cartIds, [
                    'is_pay' => 1
                ]);

                //使用优惠券
                if (count($allUseCoupon)) {
                    $couponUserRepository->updates($allUseCoupon, [
                        'use_time' => date('Y-m-d H:i:s'),
                        'status' => 1
                    ]);
                }

                //创建订单
                $groupOrder = $storeGroupOrderRepository->create($groupOrder);
                $bills = [];

                if ($groupOrder['integral'] > 0) {
                    $user->integral = bcsub($user->integral, $groupOrder['integral'], 0);
                    app()->make(UserBillRepository::class)->decBill($user['uid'], 'integral', 'deduction', [
                        'link_id' => $groupOrder['group_order_id'],
                        'status' => 1,
                        'title' => '购买商品',
                        'number' => $groupOrder['integral'],
                        'mark' => '购买商品使用积分抵扣' . floatval($groupOrder['integral_price']) . '元',
                        'balance' => $user->integral
                    ]);
                    $user->save();
                }

                foreach ($orderList as $k => $order) {
                    $orderList[$k]['group_order_id'] = $groupOrder->group_order_id;
                }

                $orderProduct = [];
                $orderStatus = [];
                foreach ($orderList as $order) {
                    $cartInfo = $order['cartInfo'];
                    unset($order['cartInfo']);
                    //创建子订单
                    $_order = $this->dao->create($order);

                    if ($order['integral'] > 0) {
                        $bills[] = [
                            'uid' => $uid,
                            'link_id' => $_order->order_id,
                            'pm' => 0,
                            'title' => '积分抵扣',
                            'category' => 'mer_integral',
                            'type' => 'deduction',
                            'number' => $order['integral'],
                            'balance' => $user->integral,
                            'mark' => '购买商品使用' . $order['integral'] . '积分抵扣' . floatval($order['integral_price']) . '元',
                            'mer_id' => $order['mer_id'],
                            'status' => 1
                        ];
                    }

                    //创建发票信息
                    if (isset($receipt_data[$_order['mer_id']])) {
                        app()->make(StoreOrderReceiptRepository::class)->add($receipt_data[$_order['mer_id']], $_order);
                    }

                    $orderStatus[] = [
                        'order_id' => $_order->order_id,
                        'order_sn' => $_order->order_sn,
                        'type' => $storeOrderStatusRepository::TYPE_ORDER,
                        'change_message' => '订单生成',
                        'change_type' => $storeOrderStatusRepository::ORDER_STATUS_CREATE,
                        'uid' => $user->uid,
                        'nickname' => $user->nickname,
                        'user_type' => $storeOrderStatusRepository::U_TYPE_USER,
                    ];

                    foreach ($cartInfo['list'] as $cart) {

                        $productPrice = $cart['true_price'];
                        $extension_one = $cart['extension_one'];
                        $extension_two = $cart['extension_two'];

                        //计算预售订单尾款
                        if ($cartInfo['order']['order_type'] == 2) {
                            $finalPrice = max(bcsub($cartInfo['order']['final_price'], $cartInfo['order']['coupon_price'], 2), 0);
                            $allFinalPrice = $order['order_type'] ? $finalPrice : bcadd($finalPrice, $order['pay_postage'], 2);
                            if ($cart['productPresell']['presell_type'] == 1) {
                                $productPrice = bcsub($cartInfo['order']['pay_price'], $order['pay_postage'], 2);
                            } else {
                                $productPrice = bcadd($cartInfo['order']['pay_price'], $finalPrice, 2);
                            }
                            //生成尾款订单
                            if ($cart['productPresell']['presell_type'] == 2) {
                                $presellOrderRepository = app()->make(PresellOrderRepository::class);
                                $presellOrderRepository->create([
                                    'uid' => $uid,
                                    'order_id' => $_order->order_id,
                                    'mer_id' => $_order->mer_id,
                                    'final_start_time' => $cart['productPresell']['final_start_time'],
                                    'final_end_time' => $cart['productPresell']['final_end_time'],
                                    'pay_price' => $allFinalPrice,
                                    'presell_order_sn' => $this->getNewOrderId(StoreOrderRepository::TYPE_SN_PRESELL)
                                ]);
                            }
                            app()->make(ProductPresellSkuRepository::class)->incCount($cart['source_id'], $cart['productAttr']['unique'], 'one_take');
                        }

                        $order_cart = [
                            'product' => $cart['product'],
                            'productAttr' => $cart['productAttr'],
                            'reservation' => $cart['reservation'] ?? [],
                            'product_type' => $cart['product_type'],
                        ];

                        if ($cart['product_type'] == '2') {
                            $order_cart['productPresell'] = $cart['productPresell'];
                            $order_cart['productPresellAttr'] = $cart['productPresellAttr'];
                            $order_cart['final_extension_one'] = $cart['final_extension_one'] ?? 0;
                            $order_cart['final_extension_two'] = $cart['final_extension_two'] ?? 0;
                            $order_cart['presell_extension_one'] = $cart['presell_extension_one'] ?? 0;
                            $order_cart['presell_extension_two'] = $cart['presell_extension_two'] ?? 0;
                        } else if ($cart['product_type'] == '3') {
                            $order_cart['productAssistAttr'] = $cart['productAssistAttr'];
                            $order_cart['productAssistSet'] = $cart['productAssistSet'];
                        } else if ($cart['product_type'] == '4') {
                            $order_cart['activeSku'] = $cart['activeSku'];
                        } else if ($cart['product_type'] == '10') {
                            $order_cart['active'] = $cart['productDiscount'];
                            $order_cart['activeSku'] = $cart['productDiscountAttr'];
                        }

                        $orderProduct[] = [
                            'order_id' => $_order->order_id,
                            'cart_id' => $cart['cart_id'],
                            'uid' => $uid,
                            'product_id' => $cart['product_id'],
                            'activity_id' => $cart['source'] >= 1 ? $cart['source_id'] : $cart['product_id'],
                            'total_price' => $cart['total_price'],
                            'product_price' => $productPrice,
                            'extension_one' => $extension_one,
                            'extension_two' => $extension_two,
                            'postage_price' => $cart['postage_price'],
                            'svip_discount' => $cart['svip_discount'],
                            'cost' => $cart['cost'],
                            'coupon_price' => $cart['coupon_price'],
                            'platform_coupon_price' => $cart['platform_coupon_price'],
                            'product_sku' => $cart['productAttr']['unique'],
                            'settlement_price' => bcmul($cart['productAttr']['settlement_price'], $cart['cart_num'], 2),
                            'product_num' => $cart['cart_num'],
                            'refund_num' => $cart['cart_num'],
                            'integral_price' => $cart['integral']['price'] ?? 0,
                            'integral' => $cart['integral'] ? bcdiv($cart['integral']['use'], $cart['cart_num'], 0) : 0,
                            'integral_total' => $cart['integral'] ? $cart['integral']['use'] : 0,
                            'product_type' => $cart['product_type'],
                            'cart_info' => json_encode($order_cart),
                            'refund_switch' => $cart['refund_switch'],
                            'reservation_date' => $cart['reservation_date'],
                            'reservation_id' => $cart['reservation_id'],
                            'reservation_time_part' => ($cart['reservationAttr'] ?? '') && $cart['reservationAttr']  ?
                        $cart['reservationAttr']['start_time'] .'-'.$cart['reservationAttr']['end_time'] : '',
                        ];
                    }
                    $userMerchantRepository->getInfo($uid, $order['mer_id']);
                    app()->make(MerchantRepository::class)->incSales($order['mer_id'], $order['total_num']);
                }

                if (count($bills) > 0) {
                    app()->make(UserBillRepository::class)->insertAll($bills);
                }
                $storeOrderStatusRepository->batchCreateLog($orderStatus);
                $storeOrderProductRepository->insertAll($orderProduct);
                event('order.create', compact('groupOrder'));
                return $groupOrder;
            });
        } catch (\Exception $e) {
            if (!empty($this->seckill_stock_key)) {
                app()->make(ProductRepository::class)->push($this->seckill_stock_key['key'],1,
                    $this->seckill_stock_key['num'],true);
            }
            throw new ValidateException($e->getMessage());
        }
        foreach ($merchantCartList as $merchantCart) {
            foreach ($merchantCart['list'] as $cart) {
                if (($cart['productAttr']['stock'] - $cart['cart_num']) < (int)merchantConfig($merchantCart['mer_id'], 'mer_store_stock')) {
                    SwooleTaskService::merchant('notice', [
                        'type' => 'min_stock',
                        'data' => [
                            'title' => '库存不足',
                            'message' => $cart['product']['store_name'] . '(' . $cart['productAttr']['sku'] . ')库存不足',
                            'id' => $cart['product']['product_id']
                        ]
                    ], $merchantCart['mer_id']);
                }
            }
        }
        Queue::push(SendSmsJob::class, ['tempId' => 'ORDER_CREATE', 'id' => $group->group_order_id]);
        if ($addressId)
            app()->make(RecordRepository::class)->addRecord(RecordRepository::TYPE_ADDRESS_RECORD,['address_id' => $addressId,'num' => count($orderList)]);
        Cache::delete($key);
        return $group;
    }

    protected function getFormInfo($mer_form_id, $extends)
    {
        $params = app()->make(FormRepository::class)->getFormKeys($mer_form_id);
        $order_extend = [];
        try {
            foreach ($extends as $key => $extend) {
                foreach ($extend as $k => $v) {
                    if ($params['val'][$k]['val'] && !$v)
                        throw new ValidateException($params['val'][$k]['label'] . '为必填项');
                    if ($params['val'][$k]['type'] == 'uploadPicture') {
                        $order_extend[$key][$params['val'][$k]['label']] = $v;
                    } else {
                        $order_extend[$key][$params['val'][$k]['label']] = is_array($v) ? implode(',',$v) : $v;
                    }
                }
            }
        } catch (\Exception $e) {

        }

        return $order_extend;
    }

    /**
     * 获取用户的收获地址信息
     * @param $addressId
     * @param $uid
     * @return array|\think\Model|null
     * @author Qinii
     */
    protected function validateAddress($addressId, $uid)
    {
        $address = null;
        //验证地址
        if ($addressId) {
            $addressRepository = app()->make(UserAddressRepository::class);
            $address = $addressRepository->getWhere(['uid' => $uid, 'address_id' => $addressId]);
        }
        return $address;
    }

    protected function validateDelivery($way)
    {
        $takes = false;
        $city_takes = false;
        $delivery = true;
        if ($way) {
            $delivery_way = is_array($way) ? $way : explode(',', $way);
            $takes = in_array('1', $delivery_way, true);
            $delivery = in_array('2', $delivery_way, true);
            $city_takes = in_array('3', $delivery_way, true);
        }
        return [$takes,$delivery,$city_takes];
    }

    protected function validateCartList($cartId,$address,$user, $svip_status)
    {
        $storeCartRepository = app()->make(storeCartRepository::class);
        $cartData = $storeCartRepository->cartIbByData($cartId, $user->uid, $address);
        //获取各种ID合集
        $data = $this->getIdsData($cartData);
        //验证购物车信息 - 异常全部处理掉
        [$order_type, $order_model, $mer_form_id] = $this->validateCartBasic($data, $cartData);
        //去掉过期的优惠券信息
        if (in_array($order_type,[0,2])) {
            app()->make(StoreCouponUserRepository::class)->failCoupon($user->uid);
        }
        //获取商户的基本信息
        $merchantData = $this->getMerchantData($data['merIds'], $user, $order_type);
        //获取以商户为单位的购物车信心列表
        $merchantCartList = $this->getMerchantCartList($cartData, $merchantData, $order_type,$svip_status);
        return [$order_type, $order_model, $mer_form_id, $merchantCartList];
    }

    protected function getIdsData($res)
    {
        // 购物车所有商户的信息
        $toArray = $res->toArray();
        $product_type = array_unique(array_column($toArray, 'product_type'));
        $is_pays = array_unique(array_column($toArray,'is_pay'));
        $merIds = array_unique(array_column($toArray,'mer_id'));
        if (count($merIds) > 1 && systemConfig('sys_switch_combine') == 0) {
            throw new ValidateException('多个商户无法合并支付，请分开下单');
        }
        // 获取每个商品的ID数组
        $product_ids = array_column($toArray,'product_id');
        $num_data = array_column($toArray,'cart_num');

        // 获取每个商品ID出现的次数及购买数量，作为验证限购数量使用
        foreach ($product_ids as $key => $value) {
            if (!isset($productNum[$value])) {
                $productNum[$value] = 0;
            }
            //每个商品出现的次数 ['id' => 'num']
            $productNum[$value] += $num_data[$key];
        }
        return compact('product_type','is_pays','productNum','merIds');
    }

    protected function validateCartBasic($data, $res)
    {
        // 购物车所有商户的信息
        if (count($data['product_type']) > 1)throw new ValidateException('存在多类型商品');
        if (count($data['is_pays']) > 1 || $data['is_pays'][0] == 1) throw new ValidateException('存在已购买商品请重新添加购物车');

        $order_type = $data['product_type'][0];
        $mer_form_id = 0;
        $order_model = 0;
        $productNum = $data['productNum'];

        $storeOrderRepository = app()->make(StoreOrderRepository::class);
        foreach ($res as $k => $cart) {
            if (!$cart['checkCartProduct']) $this->returnFail($cart);
            if ($cart['is_pay'])throw new ValidateException('存在以支付购物车信息');
            if ($cart['cart_num'] <= 0) throw new ValidateException('购买商品数必须大于0');
            if (!$mer_form_id) $mer_form_id = $cart['product']['mer_form_id'];
            if (!$order_model) $order_model = $cart['product']['type'];
            if ($mer_form_id && $k) throw new ValidateException('存在表单信息商品，请单独购买');
            if ($order_model && $k) throw new ValidateException('虚拟商品必须单独购买');
            if ($order_model == 4 && !$this->validateReservation($cart))
                throw new ValidateException('当前时间已经预约满了～');
            if ($order_type == 0 ) {
                if ($cart['product']['pay_limit'] == 1) {
                    if ($cart['product']['once_max_count'] < $cart['cart_num']) {
                        throw new ValidateException('[超出单次限购数：' . $cart['product']['once_max_count'] . ']' . mb_substr($cart['product']['store_name'], 0, 10) . '...');
                    }
                } else if ($cart['product']['pay_limit'] == 2) {
                    $count = $productNum[$cart['product_id']];
                    if ($count > $cart['product']['once_max_count']) {
                        throw new ValidateException('[超出限购总数：' . $cart['product']['once_max_count'] . ']' . mb_substr($cart['product']['store_name'], 0, 10) . '...');
                    }
                    $pay_count = $storeOrderRepository->getMaxCountNumber($cart['uid'], $cart['product_id']);
                    if (($count + $pay_count) > $cart['product']['once_max_count']) {
                        throw new ValidateException('[超出限购总数：' . $cart['product']['once_max_count'] . ']' . mb_substr($cart['product']['store_name'], 0, 10) . '...');
                    }
                } else {
                    if ($cart['product']['once_min_count'] > 0 && $cart['product']['once_min_count'] > $cart['cart_num']) {
                        throw new ValidateException('[低于起购数:' . $cart['product']['once_min_count'] . ']' . mb_substr($cart['product']['store_name'], 0, 10) . '...');
                    }
                }
            } else {
                if (($order_type != 10 && $k != 0) || (count($data['merIds']) != 1) ) {
                    throw new ValidateException('活动商品必须单独购买');
                }
            }
        }
        return [$order_type, $order_model, $mer_form_id];
    }

    /**
     *  存在失效商品就抛出异常
     * @param $fail
     * @return mixed
     * @author Qinii
     */
    protected function returnFail($item)
    {
        $fail['product'] = app()->make(ProductRepository::class)->getFailProduct($item['product_id']);
        if ($item['is_fail']) {
            throw new ValidateException('[已失效]' . mb_substr($fail['product']['store_name'], 0, 10) . '...');
        }
        if (in_array($item['product_type'], [1, 2, 3]) && !$item['userPayCount']) {
            throw new ValidateException('[超出限购数]' . mb_substr($fail['product']['store_name'], 0, 10) . '...');
        }
        throw new ValidateException('[已失效]' . mb_substr($fail['product']['store_name'], 0, 10) . '...');
    }

    /**
     * 盲盒随机 SKU 后补齐佣金字段（对齐 ProductAttrValue::getBcExtension*Attr）
     * @param array $cart
     * @param array $attrArr
     * @param int $level 1|2
     * @return float
     */
    protected function calcBlindboxBcExtension(array $cart, array $attrArr, int $level): float
    {
        if (!intval(systemConfig('extension_status'))) {
            return 0;
        }
        $extType = (int)($cart['product']['extension_type'] ?? 0);
        if ($extType === 1) {
            $key = $level === 1 ? 'extension_one' : 'extension_two';
            return floatval($attrArr[$key] ?? 0);
        }
        $rateKey = $level === 1 ? 'extension_one_rate' : 'extension_two_rate';
        $price = $attrArr['org_price'] ?? ($attrArr['price'] ?? 0);
        return floatval(round(bcmul((string)systemConfig($rateKey), (string)$price, 3), 2));
    }

    /**
     * 盲盒模块：加权随机选择
     * @param $items
     * @return mixed|null
     */
    protected function weightedRandomSelect($items)
    {
        if (empty($items)) return null;

        $itemsArr = is_array($items) ? $items : $items->toArray();
        if (empty($itemsArr)) return null;

        $cumulative = [];
        $totalWeight = 0;
        foreach ($itemsArr as $item) {
            $weight = intval($item['probability_weight'] ?? 10);
            if ($weight > 0) {
                $totalWeight += $weight;
                $cumulative[] = ['weight' => $totalWeight, 'item' => $item];
            }
        }

        if ($totalWeight <= 0) {
            return $itemsArr[array_rand($itemsArr)];
        }

        $rand = mt_rand(1, $totalWeight);

        $low = 0;
        $high = count($cumulative) - 1;
        while ($low < $high) {
            $mid = $low + (($high - $low) >> 1);
            if ($cumulative[$mid]['weight'] < $rand) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }

        return $cumulative[$low]['item'] ?? reset($itemsArr);
    }

    protected function getMerchantData($merIds, $user, $order_type)
    {
        $uid = $user->uid;
        // 商户分类关联查询
        $merWith = ['merchantCategory'];
        // 商户优惠券关联查询
        if (in_array($order_type, [0,2]))
            $merWith['coupon'] = function ($query) use ($uid) {$query->where('uid', $uid);};

        $merchantInfoData = app()->make(MerchantRepository::class)->getSearch([])
            ->whereIn('mer_id',$merIds)
            ->with($merWith)->field($this->merchantField)
            ->select()->append(['openReceipt'])->toArray();

        // 整理为 ['商户ID' => [ 商户信息]] 的格式
        $keyArray = array_column($merchantInfoData,'mer_id');
        $merchantInfoData = array_combine($keyArray,$merchantInfoData);

        return $merchantInfoData;
    }

    /**
     *  验证购物车信息
     * @param $res
     * @return array
     * @author Qinii
     */
    protected function getMerchantCartList($res, $merchantInfoData, $order_type, $svip_status)
    {
        $arr = [];
        foreach ($res as $item) {
            //商户信息
            if (!isset($arr[$item['mer_id']])) {
                $merchantData = $merchantInfoData[$item['mer_id']] ?? ['mer_id' => 0];
                $merchantData['take'] = merchantConfig($item['mer_id'], $this->configFiled);
                $arr[$item['mer_id']] = $merchantData;
            }
            if (!$order_type && $svip_status && $item['product']['show_svip_price']) {
                $item['productAttr']['show_svip_price'] = true;
                $item['productAttr']['org_price'] = $item['productAttr']['price'];
                $item['productAttr']['price'] = $item['productAttr']['svip_price'];
            } else {
                $item['productAttr']['show_svip_price'] = false;
            }
            switch($order_type)
            {
                case 2:
                    $item->append(['productPresell', 'productPresellAttr']);
                    break;
                case 3:
                    $item->append(['productAssistAttr']);
                    break;
                case 4:
                    $item->append(['activeSku']);
                    break;
                case 10:
                    $item->append(['productDiscount', 'productDiscountAttr']);
                    $item['productAttr']['price'] = $item['productDiscountAttr']['active_price'];
                    $item['productAttr']['show_svip_price'] = false;
                    break;
                default:
                    break;
            }
            $arr[$item['mer_id']]['list'][] = $item;
        }
        $list = array_values($arr);
        return $list;
    }

    public function validateReservation($cart)
    {
        $valueReservationRepository = app()->make(ProductAttrValueReservationRepository::class);
        $val = $valueReservationRepository->validateStock($cart['product_id'], $cart['reservation_id'], $cart['reservation_date'], $cart['cart_num']);
        return $val === false ? false : true;
    }

    const RANGE_TYPE_METHOD = [
        1 => 'checkRadius',
        2 => 'checkRegion',
        3 => 'checkFence',
    ];
    /**
     * 检查同城配送范围
     *
     * @param array $merchantCart
     * @param array $address
     * @return boolean
     */
    protected function checkCityTake(array $merchantCart, array $address) : bool
    {
        $deliveryStationRepository = app()->make(DeliveryStationRepository::class);
        $deliveryStation = $deliveryStationRepository->deliveryStationInfo($merchantCart['take_id'], $merchantCart['mer_id']);

        $checkMethod = self::RANGE_TYPE_METHOD[$deliveryStation['range_type']];
        return $deliveryStationRepository->$checkMethod($address, $deliveryStation);
    }
    /**
     * 同城配送费用计算
     *
     * @param float $totalPrice
     * @param array $merchantCart
     * @param array $address
     * @return float
     */
    protected function getDeliveryFee(float $totalPrice, $merchantCart, $address) : float
    {
        // 计算重量
        $totalWeight = 0;
        foreach($merchantCart['list'] as $item) {
            $totalWeight += $item['productAttr']['weight'] * $item['cart_num'];
        }
        $deliveryConfigRepository = app()->make(DeliveryConfigRepository::class);
        $deliverySettings = $deliveryConfigRepository->getDeliveryConfig($merchantCart['mer_id']);
        if (!$deliverySettings) {
            throw new ValidateException('商户：【' . $deliverySettings['merchant']['mer_name'] . '】，未设置配送费用信息，无法计算配送费，请切换其他配送方式');
        }
        // 是否到达起送价
        if ($totalPrice < $deliverySettings['min_delivery_amount']) {
            throw new ValidateException('商户：【' . $deliverySettings['merchant']['mer_name'] . '】，订单金额不能低于起送价，起送价：¥' . $deliverySettings['min_delivery_amount']);
        }
        // 是否到达免配送费金额
        if ($totalPrice >= $deliverySettings['free_shipping_amount']) {
            return 0;
        }
        return $deliveryConfigRepository->deliveryFee(
            $merchantCart['take_id'],
            $totalWeight,
            $deliverySettings->toArray(),
            $address->toArray()
        );
    }
    /**
     * 检查上门服务是否在商品支持范围内
     *
     * @param int $merId
     * @param array $address
     * @return boolean
     */
    protected function checkHomeServiceRange(int $merId, array $address) : bool
    {
        return app()->make(ProductReservationRepository::class)->checkRangeByType($merId, $address);
    }
}


