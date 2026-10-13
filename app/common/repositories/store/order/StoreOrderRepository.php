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

use think\facade\Cache;
use crmeb\services\pay\Pay;
use app\controller\admin\order\Order;
use app\common\dao\store\order\StoreOrderDao;
use app\common\model\store\order\StoreGroupOrder;
use app\common\model\store\order\StoreOrder;
use app\common\model\user\User;
use app\common\repositories\BaseRepository;
use app\common\repositories\delivery\DeliveryOrderRepository;
use app\common\repositories\delivery\DeliveryServiceRepository;
use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\store\coupon\StoreCouponUserRepository;
use app\common\repositories\store\product\CdkeyLibraryRepository;
use app\common\repositories\store\product\ProductAssistSetRepository;
use app\common\repositories\store\product\ProductAttrValueRepository;
use app\common\repositories\store\product\ProductCdkeyRepository;
use app\common\repositories\store\product\ProductCopyRepository;
use app\common\repositories\store\product\ProductGroupBuyingRepository;
use app\common\repositories\store\product\ProductPresellSkuRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\product\StoreDiscountRepository;
use app\common\repositories\store\shipping\ExpressRepository;
use app\common\repositories\store\StorePrinterRepository;
use app\common\repositories\system\attachment\AttachmentRepository;
use app\common\repositories\system\merchant\FinancialRecordRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserBrokerageRepository;
use app\common\repositories\user\UserMerchantRepository;
use app\common\repositories\user\UserRepository;
use crmeb\jobs\PayGiveCouponJob;
use crmeb\jobs\SendSmsJob;
use crmeb\jobs\UserBrokerageLevelJob;
use crmeb\services\CrmebServeServices;
use crmeb\services\ExpressService;
use crmeb\services\printer\Printer;
use crmeb\services\QrcodeService;
use crmeb\services\SwooleTaskService;
use Exception;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\exception\ValidateException;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use think\facade\Route;
use app\common\repositories\user\MemberinterestsRepository;
use app\common\repositories\store\staff\StaffsRepository;
use app\common\repositories\store\product\ProductAttrValueReservationRepository;
use app\common\repositories\circle\CircleFinancialRecordRepository;
use app\common\repositories\wechat\WechatUserRepository;
use EasyWeChat\Support\Arr;

/**
 * 订单
 */
class StoreOrderRepository extends BaseRepository
{
    /**
     * 支付类型 0余额 1 微信 2 小程序 3 微信 4 支付宝 5 支付宝 6 微信 7 线下支付 8 微信扫码枪支付 9 支付宝扫码枪支付
     * PAY_TYPE 里的数据位置不可随意变动，否则会影响到支付方式对应关系
     */
    const PAY_TYPE = ['balance', 'weixin', 'routine', 'h5', 'alipay', 'alipayQr', 'weixinQr', 'offline', 'weixinBarCode', 'alipayBarCode'];
    const PAY_TYPE_FILTEER = [0 => 0, 1 => '1,2,3,6,8', 2 => '4,5,9', 3 => 7];
    const TYPE_SN_ORDER = 'wxo';
    const TYPE_SN_PRESELL = 'wxp';
    const TYPE_SN_USER_ORDER = 'wxs';
    const TYPE_SN_USER_RECHARGE = 'wxu';
    const TYPE_SN_SERVER_ORDER = 'cs';
    const TYPE_SN_REFUND = 'rwx';


    /**
     * StoreOrderRepository constructor.
     * @param StoreOrderDao $dao
     */
    public function __construct(StoreOrderDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 处理支付请求。
     *
     * 根据支付类型和用户信息，生成支付配置，支持多种支付方式包括余额支付、微信支付、支付宝支付等。
     * 支持组合支付功能，以及移动端支付的特殊配置。在支付前会触发一个事件，允许其他功能模块对支付行为进行干预或附加操作。
     *
     * @param string $type 支付方式类型，包括'balance'（余额支付）、'weixin'（微信支付）、'alipay'（支付宝支付）等。
     * @param User $user 进行支付的用户对象。
     * @param StoreGroupOrder $groupOrder 需要支付的订单对象。
     * @param string $return_url 支付成功后返回的URL，主要用于支付宝支付。
     * @param bool $isApp 是否为APP支付，影响支付方式的后缀命名。
     * @param null|string $combine 是否使用组合支付，如果为null，则从系统配置中获取。
     * @return array 返回包含支付配置和订单ID的信息。
     */
    public function pay(string $type, ?User $user, StoreGroupOrder $groupOrder, $return_url = '', $isApp = false, $combine = null, $authCode = '')
    {
        // 如果支付方式为余额支付，则直接调用余额支付方法
        if ($type === 'balance') {
            return $this->payBalance($user, $groupOrder);
        }
        $openid = '';
        if (in_array($type, ['alipayQr', 'alipay'], true)) {
            $order = $groupOrder->getPayParams($return_url, $authCode);
            $store = 'alipay';
        } else {
            // 判断是否使用组合支付，如果未指定，则从系统配置中获取默认值
            if (is_null($combine)) {
                $config = systemConfig(['open_wx_combine','wechat_service_tool']);
                $combine = $config['open_wx_combine'];
                if (
                    $combine &&
                    in_array($type, ['weixin', 'weixinApp', 'routine', 'h5', 'weixinQr','weixinBarCode'])
                ) {
                    if ($config['wechat_service_tool']) {
                        if (
                            $groupOrder['activity_type'] != 20 &&
                            count($groupOrder['orderList']->toArray()) == 1 &&
                            $groupOrder->orderList[0]->merchant->applyment_id && $groupOrder->orderList[0]->merchant->applyment_switch
                        ) { $combine = true; }else{ $combine = false; }
                    }
                } else {
                    $combine = false;
                }
            }
            $wechatUserRepository = app()->make(WechatUserRepository::class);
            if ($type == 'routine') {
                $openid = $wechatUserRepository->idByRoutineId($user['wechat_user_id']);
            } else {
                $openid = $wechatUserRepository->idByOpenId($user['wechat_user_id']);
            }
            // 在支付前触发一个事件，允许其他功能模块对支付行为进行干预或附加操作
            event('order.pay.before', compact('groupOrder', 'type', 'isApp'));

            // $combine = 1;
            if ($combine) {
                if ($config['wechat_service_tool']) {
                    $order = $groupOrder->getTransactionsPayParams($authCode);
                    $store = 'partner';
                } else {
                    $order = $groupOrder->getCombinePayParams();
                    $store = 'combine';
                }
            } else {
                $order = $groupOrder->getPayParams($return_url, $authCode);
                $store = 'weixin';
            }
        }
        // 如果是APP支付，且支付方式为微信或支付宝，则在支付方式后添加App后缀
        if (in_array($type, ['weixin', 'alipay'], true) && $isApp) {
            $type .= 'App';
        }
        $service = new Pay($store);
        // 生成支付配置
        $config = $service->pay($type, $order, $openid);
        if($authCode && $config['paid'] == 1) {
            $orders['transaction_id'] = $config['transaction_id'];
            $this->paySuccess($groupOrder, 0, $orders);
        }
        // 返回支付配置和订单ID，支付配置中包含支付方式的状态信息
        return app('json')->status($type, $config + ['order_id' => $groupOrder['group_order_id'], 'pay_price' => $groupOrder['pay_price']]);
    }

    /**
     * 使用余额支付用户订单
     *
     * 该方法用于处理用户使用余额支付订单的逻辑。它首先检查余额支付是否已开启，然后确保用户余额充足，
     * 接着在事务中更新用户余额并记录消费记录，最后标记订单支付成功。
     *
     * @param User $user 当前进行支付的用户对象
     * @param StoreGroupOrder $groupOrder 待支付的订单对象
     * @return json 返回支付成功的响应信息，包括订单ID
     * @throws ValidateException 如果支付方式未开启或用户余额不足，将抛出验证异常
     */
    public function payBalance(User $user, StoreGroupOrder $groupOrder)
    {

        // 检查余额支付是否开启，如果没有开启则抛出异常
        if (!systemConfig('yue_pay_status'))
            throw new ValidateException('未开启余额支付');
        $user = app()->make(UserRepository::class)->get($user->uid);
        // 检查用户余额是否足够支付订单，如果不足则抛出异常
        if ($user['now_money'] < $groupOrder['pay_price'])
            throw new ValidateException('余额不足，请更换支付方式');
        // 使用数据库事务来确保支付过程的原子性
        Db::transaction(function () use ($user, $groupOrder) {
            // 更新用户余额，这里使用bcsub确保金额精确减法
            $user->now_money = bcsub($user->now_money, $groupOrder['pay_price'], 2);
            $user->save();

            // 创建用户账单记录，记录用户的消费行为
            $userBillRepository = app()->make(UserBillRepository::class);
            $userBillRepository->decBill($user['uid'], 'now_money', 'pay_product', [
                'link_id' => $groupOrder['group_order_id'],
                'status' => 1,
                'title' => '购买商品',
                'number' => $groupOrder['pay_price'],
                'mark' => '余额支付支付' . floatval($groupOrder['pay_price']) . '元购买商品',
                'balance' => $user->now_money
            ]);
            // 标记订单支付成功，这可能包括更新订单状态等操作
            $this->paySuccess($groupOrder);
        });

        // 返回支付成功的响应信息，包括订单ID
        return app('json')->status('success', '余额支付成功', ['order_id' => $groupOrder['group_order_id']]);
    }

    /**
     * 修改订单支付方式
     *
     * 本函数用于更改订单组的支付方式。通过使用数据库事务，确保在更改订单组的支付类型时，订单组内所有订单的支付方式都能一致地更新。
     * 这样可以维护数据的一致性和完整性，避免因单个订单更新失败而导致整个订单组的数据不一致。
     *
     * @param StoreGroupOrder $groupOrder 订单组对象，包含需要更改支付方式的所有订单。
     * @param int $pay_type 新的支付方式标识，用于更新订单的支付方式。
     */
    public function changePayType(StoreGroupOrder $groupOrder, int $pay_type)
    {
        // 开启数据库事务处理
        Db::transaction(function () use ($groupOrder, $pay_type) {
            // 直接更新订单组的支付方式
            $groupOrder->pay_type = $pay_type;

            // 遍历订单组中的每个订单，逐一更新支付方式
            foreach ($groupOrder->orderList as $order) {
                $order->pay_type = $pay_type;
                // 保存每个订单的更改，确保每个订单的支付方式都被更新
                $order->save();
            }

            // 保存订单组的更改，完成支付方式的更新
            $groupOrder->save();
        });
    }

    /**
     * 生成并验证验证码
     *
     * 本函数用于生成一个唯一的验证码，并检查这个验证码是否已经存在。
     * 如果验证码已存在，将重新生成并检查，确保返回的验证码是唯一的。
     * 这种方法可以避免验证码被重复使用，提高系统的安全性。
     *
     * @return string 唯一的验证码
     */
    public function verifyCode()
    {
        // 生成一个唯一的验证码字符串
        $code = substr(uniqid('', true), 15) . substr(microtime(), 2, 8);

        // 检查生成的验证码是否已存在于数据库中
        if ($this->dao->existsWhere(['verify_code' => $code])) {
            // 如果验证码已存在，则重新生成验证码
            return $this->verifyCode();
        } else {
            // 如果验证码不存在，则返回该验证码
            return $code;
        }
    }


    /**
     *  支付成功后
     *
     * @param StoreGroupOrder $groupOrder
     * @author xaboy
     * @day 2020/6/9
     */
    public function paySuccess(StoreGroupOrder $groupOrder, $is_combine = 0, $subOrders = [], $isListen = 0)
    {
        $groupOrder->append(['user']);
        //修改订单状态
        $res = Db::transaction(function () use ($subOrders, $is_combine, $groupOrder, $isListen) {
            $time = date('Y-m-d H:i:s');
            $groupOrder->paid = 1;
            $groupOrder->pay_time = $time;
            $groupOrder->is_combine = $is_combine;
            $orderStatus = [];
            $groupOrder->append(['orderList.orderProduct']);
            $flag = true;
            $finance = [];
            $svipDiscount = 0;
            $isPoints = false;
            $profitsharing = [];
            $uid = $groupOrder->uid;

            $financialRecordRepository = app()->make(FinancialRecordRepository::class);
            $financeSn = $financialRecordRepository->getSn();
            $userMerchantRepository = app()->make(UserMerchantRepository::class);
            $storeOrderProfitsharingRepository = app()->make(StoreOrderProfitsharingRepository::class);
            $storeGroupOrderRepository = app()->make(StoreGroupOrderRepository::class);
            $isVipCoupon = $storeGroupOrderRepository->isVipCoupon($groupOrder);
            //验证是不是该用户的第一个订单
            $groupOrder->is_first = $storeGroupOrderRepository->validateOrderIsFirst((int)$groupOrder->uid);
            $i = 1;

            //订单记录
            $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
            $userRepository = app()->make(UserRepository::class);
            $merchantRepository = app()->make(MerchantRepository::class);
            $productAssistSetRepository = app()->make(ProductAssistSetRepository::class);
            $productPresellSkuRepository = app()->make(ProductPresellSkuRepository::class);
            $productGroupBuyingRepository = app()->make(ProductGroupBuyingRepository::class);
            $circleFinancialRecordRepository = app()->make(CircleFinancialRecordRepository::class);

            // 循环开始
            foreach ($groupOrder->orderList as $_k => $order) {
                $order->paid = 1;
                $order->pay_time = $time;
                $svipDiscount = bcadd($order->svip_discount, $svipDiscount, 2);
                if (isset($subOrders['sub_orders']) && isset($subOrders['sub_orders'][$order->order_sn])) {
                    $order->transaction_id = $subOrders['sub_orders'][$order->order_sn]['transaction_id'];
                } else if (isset($subOrders['transaction_id'])) {
                    $order->transaction_id = $subOrders['transaction_id'];
                }
                $presell = false;
                // 等待付尾款
                if ($order->activity_type == 2) {
                    if ($order->orderProduct[0]['cart_info']['productPresell']['presell_type'] == 2) {
                        $order->status = 10;
                        $presell = true;
                    } else {
                        $productPresellSkuRepository->incCount($order->orderProduct[0]['activity_id'], $order->orderProduct[0]['product_sku'], 'two_pay');
                    }
                    $productPresellSkuRepository->incCount($order->orderProduct[0]['activity_id'], $order->orderProduct[0]['product_sku'], 'one_pay');
                } else if ($order->activity_type == 4) {
                    $order->status = 9;
                    $order->save();

                    $group_buying_id = $productGroupBuyingRepository->create(
                        $groupOrder->user,
                        $order->orderProduct[0]['cart_info']['activeSku']['product_group_id'],
                        $order->orderProduct[0]['activity_id'],
                        $order->order_id
                    );
                    $order->orderProduct[0]->activity_id = $group_buying_id;
                    $order->orderProduct[0]->save();
                } else if ($order->activity_type == 3) {
                    //更新助力状态
                    $productAssistSetRepository->changStatus($order->orderProduct[0]['activity_id']);
                }
                if (($order->order_type == 1 && $order->status != 10) || $order->is_virtual == 4)
                    $order->verify_code = $this->verifyCode();
                // 代客下单无需核销类型直接修改订单状态为待评价
                if ($order->behalf_no_verify && $order->is_behalf && $order->status == 0 && $order->order_type == 1) {
                    $order->status = 2;
                }
                $cdk_type = $order->orderProduct[0]->product->type;
                if (in_array($cdk_type, [ProductRepository::DEFINE_TYPE_CLOUD, ProductRepository::DEFINE_TYPE_CARD])) {
                    $msg = $this->sendCdkey($order, $cdk_type);
                    $order->status = 2;
                    $order->delivery_type = 6;
                    $order->delivery_name = '自动发货';
                    $order->delivery_id = $msg;
                    $order->verify_time = date('Y-m-d H:i:s', time());
                    $isPoints = true;
                }
                // 判断同城配送是否自动给第三方下单
                if($order->order_type == 2 && $order->take->type){
                    $this->cityDelivery($order, $order->mer_id, $order->orderProduct->toArray());
                }
                $order->save();
                if ($isPoints) $this->takeAfter($order, $groupOrder->user);
                $orderStatus[] = [
                    'order_id' => $order->order_id,
                    'order_sn' => $order->order_sn,
                    'type' => $storeOrderStatusRepository::TYPE_ORDER,
                    'change_message' => '订单支付成功',
                    'change_type' => $storeOrderStatusRepository::ORDER_STATUS_PAY_SUCCCESS,
                    'uid' => $order->uid,
                    'nickname' => $order->user->nickname ?? '游客',
                    'user_type' => $storeOrderStatusRepository::U_TYPE_USER,
                ];

                // 成为推广员
                foreach ($order->orderProduct as $product) {
                    if ($flag && $product['cart_info']['product']['is_gift_bag']) {
                        $userRepository->promoter($order->uid);
                        $flag = false;
                    }
                }

                $finance[] = [
                    'order_id' => $order->order_id,
                    'order_sn' => $order->order_sn,
                    'user_info' => $groupOrder->user->nickname ?? '游客',
                    'user_id' => $uid,
                    'financial_type' => $presell ? 'order_presell' : 'order',
                    'financial_pm' => 1,
                    'type' => $presell ? 2 : 1,
                    'number' => $order->pay_price,
                    'mer_id' => $order->mer_id,
                    'financial_record_sn' => $financeSn . ($i++),
                    'pay_type' => $order->pay_type,
                ];

                $_payPrice = bcsub($order->pay_price, bcadd($order['extension_one'], $order['extension_two'], 3), 2);
                if ($presell) {
                    if (isset($order->orderProduct[0]['cart_info']['presell_extension_one']) && $order->orderProduct[0]['cart_info']['presell_extension_one'] > 0) {
                        $_payPrice = bcadd($_payPrice, $order->orderProduct[0]['cart_info']['presell_extension_one'], 2);
                    }
                    if (isset($order->orderProduct[0]['cart_info']['presell_extension_two']) && $order->orderProduct[0]['cart_info']['presell_extension_two'] > 0) {
                        $_payPrice = bcadd($_payPrice, $order->orderProduct[0]['cart_info']['presell_extension_two'], 2);
                    }
                }

                $_order_rate = 0;
                if ($order['commission_rate'] > 0) {
                    $commission_rate = bcdiv((string)$order['commission_rate'], '100', 6);
                    $_order_rate = bcmul($_payPrice, (string)$commission_rate, 2);
                    $_payPrice = bcsub($_payPrice, $_order_rate, 2);
                }
                if (!$presell) {
                    if ($order['extension_one'] > 0) {
                        $finance[] = [
                            'order_id' => $order->order_id,
                            'order_sn' => $order->order_sn,
                            'user_info' => $groupOrder->user->nickname ?? '游客',
                            'user_id' => $uid,
                            'financial_type' => 'brokerage_one',
                            'financial_pm' => 0,
                            'type' => 1,
                            'number' => $order['extension_one'],
                            'mer_id' => $order->mer_id,
                            'financial_record_sn' => $financeSn . ($i++),
                            'pay_type' => $groupOrder->pay_type
                        ];
                    }
                    if ($order['extension_two'] > 0) {
                        $finance[] = [
                            'order_id' => $order->order_id,
                            'order_sn' => $order->order_sn,
                            'user_info' => $groupOrder->user->nickename ?? '游客',
                            'user_id' => $uid,
                            'financial_type' => 'brokerage_two',
                            'financial_pm' => 0,
                            'type' => 1,
                            'number' => $order['extension_two'],
                            'mer_id' => $order->mer_id,
                            'financial_record_sn' => $financeSn . ($i++),
                            'pay_type' => $order->pay_type,
                        ];
                    }
                    if ($order['commission_rate'] > 0) {
                        $finance[] = [
                            'order_id' => $order->order_id,
                            'order_sn' => $order->order_sn,
                            'user_info' => $groupOrder->user->nickname ?? '游客',
                            'user_id' => $uid,
                            'financial_type' => $financialRecordRepository::FINANCIA_TYPE_ORDER_CHARGE,
                            'financial_pm' => 0,
                            'type' => 1,
                            'number' => $_order_rate,
                            'mer_id' => $order->mer_id,
                            'financial_record_sn' => $financeSn . ($i++),
                            'pay_type' => $groupOrder->pay_type
                        ];
                    }
                    $finance[] = [
                        'order_id' => $order->order_id,
                        'order_sn' => $order->order_sn,
                        'user_info' => $groupOrder->user->nickname ?? '游客',
                        'user_id' => $uid,
                        'financial_type' => $order['activity_type'] == 20 ? 'points_order_true' : 'order_true',
                        'financial_pm' => 0,
                        'type' => 2,
                        'number' => $_payPrice,
                        'mer_id' => $order->mer_id,
                        'financial_record_sn' => $financeSn . ($i++),
                        'pay_type' => $groupOrder->pay_type
                    ];
                    if ($order->platform_coupon_price > 0) {
                        $finance[] = [
                            'order_id' => $order->order_id,
                            'order_sn' => $order->order_sn,
                            'user_info' => $groupOrder->user->nickname ?? '游客',
                            'user_id' => $uid,
                            'financial_type' => $isVipCoupon ? 'order_svip_coupon' : 'order_platform_coupon',
                            'financial_pm' => 0,
                            'type' => 1,
                            'number' => $order->platform_coupon_price,
                            'mer_id' => $order->mer_id,
                            'financial_record_sn' => $financeSn . ($i++),
                            'pay_type' => $groupOrder->pay_type
                        ];
                        $_payPrice = bcadd($_payPrice, $order->platform_coupon_price, 2);
                    }

                    if (!$is_combine && $order->pay_type != 7) {
                        $merchantRepository->addLockMoney($order->mer_id, 'order', $order->order_id, $_payPrice);
                    }
                }
                if ($is_combine) {
                    $profitsharing[] = [
                        'profitsharing_sn' => $storeOrderProfitsharingRepository->getOrderSn(),
                        'order_id' => $order->order_id,
                        'transaction_id' => $order->transaction_id ?? '',
                        'mer_id' => $order->mer_id,
                        'profitsharing_price' => $order->pay_price,
                        'profitsharing_mer_price' => $_payPrice,
                        'type' => $storeOrderProfitsharingRepository::PROFITSHARING_TYPE_ORDER,
                        'is_combine' => $is_combine,
                    ];
                }
                $userMerchantRepository->updatePayTime($uid, $order->mer_id, $order->pay_price);
                // 如果不是定时任务通知，则推送消息通知商家有新订单
                if(!$isListen) {
                    SwooleTaskService::merchant('notice', [
                        'type' => 'new_order',
                        'data' => [
                            'title' => '新订单',
                            'message' => '您有一个新的订单',
                            'id' => $order->order_id
                        ]
                    ], $order->mer_id);
                }
                //自动打印订单
                $this->autoPrinter($order->order_id, $order->mer_id, 1);
            }

            // 循环结束
            if ($groupOrder->user && $groupOrder->user->spread_uid) {
                Queue::push(UserBrokerageLevelJob::class, ['uid' => $groupOrder->user->spread_uid, 'type' => 'spread_pay_num', 'inc' => 1]);
                Queue::push(UserBrokerageLevelJob::class, ['uid' => $groupOrder->user->spread_uid, 'type' => 'spread_money', 'inc' => $groupOrder->pay_price]);
            }

            $userRepository->update($groupOrder->uid, [
                'pay_count' => Db::raw('pay_count+' . count($groupOrder->orderList)),
                'pay_price' => Db::raw('pay_price+' . $groupOrder->pay_price),
                'svip_save_money' => Db::raw('svip_save_money+' . $svipDiscount),
            ]);
            $this->giveIntegral($groupOrder);
            if (count($profitsharing)) {
                $storeOrderProfitsharingRepository->insertAll($profitsharing);
            }
            $financialRecordRepository->insertAll($finance);
            // 商圈提成流水
            $circleFinancialRecordRepository->insertAll($order);
            $storeOrderStatusRepository->batchCreateLog($orderStatus);
            if (count($groupOrder['give_coupon_ids']) > 0)
                $groupOrder['give_coupon_ids'] = app()->make(StoreCouponRepository::class)->getGiveCoupon($groupOrder['give_coupon_ids'])->column('coupon_id');
            $groupOrder->save();

            // 配送订单通知商家:配送订单商家配送且开启了配送员抢单则通知相关配送员有新订单
            if($order->order_type == 2 && $order->take->type == 0) {
                $deliveryOrderStatus = merchantConfig($order->mer_id, 'mer_delivery_order_status');
                if($deliveryOrderStatus) {
                    Queue::push(SendSmsJob::class, ['tempId' => 'DELIVERY_ORDER_NOTICE', 'id' => $order->order_id]);
                }
            }
            // 预约订单通知商家:上门服务订单且需要员工抢单则通知相关员工有新订单
            if($order->order_type == 0 && $order->is_virtual == 4 && $order->enable_assigned == 0) {
                Queue::push(SendSmsJob::class, ['tempId' => 'RESERVATION_ORDER_NOTICE', 'id' => $order->order_id]);
            }
        });
        //满额分销
        if ($groupOrder->user && !$groupOrder->user->is_promoter && systemConfig('promoter_type') == 3) {
            app()->make(UserRepository::class)->meetWithPromoter($groupOrder->user->uid);
        }

        if (count($groupOrder['give_coupon_ids']) > 0) {
            try {
                Queue::push(PayGiveCouponJob::class, ['ids' => $groupOrder['give_coupon_ids'], 'uid' => $groupOrder['uid']]);
            } catch (Exception $e) {
            }
        }

        Queue::push(SendSmsJob::class, ['tempId' => 'ORDER_PAY_SUCCESS', 'id' => $groupOrder->group_order_id]);
        Queue::push(SendSmsJob::class, ['tempId' => 'ADMIN_PAY_SUCCESS_CODE', 'id' => $groupOrder->group_order_id]);

        if($groupOrder->uid) {
            Queue::push(UserBrokerageLevelJob::class, ['uid' => $groupOrder->uid, 'type' => 'pay_money', 'inc' => $groupOrder->pay_price]);
            Queue::push(UserBrokerageLevelJob::class, ['uid' => $groupOrder->uid, 'type' => 'pay_num', 'inc' => 1]);
            $userBrokerageRepository = app()->make(UserBrokerageRepository::class);
            $userBrokerageRepository->incMemberValue($groupOrder->uid, 'member_pay_num', $groupOrder->group_order_id, $groupOrder->pay_price);
        }

        event('order.paySuccess', compact('groupOrder'));
        event('data.screen.send', []);
    }

    /**
     *  自动发货卡密商品
     * @param $order
     * @return mixed|string
     * @author Qinii
     * @day 2023/5/4
     */
    public function sendCdkey($order, $type)
    {
        $msg = '未获得卡密，请联系客服';
        if ($type == ProductRepository::DEFINE_TYPE_CARD) {
            $productCdkeyRepository = app()->make(ProductCdkeyRepository::class);
            $library_id = $order->orderProduct[0]['cart_info']['productAttr']['library_id'];
            $where = ['library_id' => $library_id, 'status' => 1];
            $cdkey = $productCdkeyRepository->search($where)->whereNotNull('key')->find();
            if ($cdkey) {
                $cdkey->status = -1;
                $cdkey->product_id = $order->orderProduct[0]['cart_info']['product']['product_id'];
                $cdkey->value_id = $order->orderProduct[0]['cart_info']['productAttr']['value_id'];
                $cdkey->save();
                $msg = '卡号：' . $cdkey['key'] . ' 卡密：' . $cdkey['pwd'];
            }
            app()->make(CdkeyLibraryRepository::class)->incUsedNum($library_id);
        } else {
            $productAttrValueRepository = app()->make(ProductAttrValueRepository::class);
            if (isset($order->orderProduct[0]['cart_info']['productAttr']['value_id'])) {
                $where['value_id'] = $order->orderProduct[0]['cart_info']['productAttr']['value_id'];
            } else {
                $where['unique'] = $order->orderProduct[0]['cart_info']['productAttr']['unique'];
            }
            $rest = $productAttrValueRepository->getWhere($where, '*', ['productCdkey' => function ($query) {
                $query->where('status', 1)->order('cdkey_id ASC')->withLimit(1);
            }]);
            $cdkey = $rest['productCdkey'];
            $msg = $cdkey['key'];
        }
        return $msg;
    }

    /**
     * 根据订单和商家ID自动打印小票。
     *
     * 本函数主要用于检查特定商家是否启用了自动打印设置，如果启用了，则尝试批量打印订单小票。
     * 如果在打印过程中遇到异常，将会捕获异常并记录错误信息，确保打印过程的健壮性。
     * 对于未开启自动打印的商家，函数将记录相应的日志信息，提示自动打印功能未开启。
     *
     * @param int $orderId 订单ID，用于指定需要打印的小票。
     * @param int $merId 商家ID，用于确定订单所属的商家，并检查该商家是否启用了自动打印。
     */
    public function autoPrinter(int $orderId, int $merId, $print_type = 1)
    {
        // 检查商家是否启用了自动打印功能
        $config = merchantConfig($merId, ['printing_auto_status','printing_status']);
        if ($config['printing_auto_status'] && $config['printing_status']) {
            try {
                // 如果自动打印功能已启用，则尝试批量打印订单小票
                $this->batchPrinter($orderId, $merId, $print_type);
            } catch (Exception $exception) {
                // 如果在打印过程中发生异常，记录异常信息
                Log::info('自动打印小票报错：' . $exception);
            }
        } else {
            // 如果商家未启用自动打印功能，记录相应的日志信息
            Log::info('自动打印小票验证：商户ID【' . $merId . '】，自动打印状态未开启');
        }
    }

    /**
     * 生成新的订单ID
     *
     * 本函数旨在生成一个唯一的、含有时间戳和随机数的订单ID，以确保订单ID的唯一性和可追踪性。
     * 使用了微秒级时间戳和随机数来增加订单ID的唯一性，避免了在高并发场景下的重复问题。
     *
     * @param string $type 订单类型前缀。用于区分不同类型的订单，如商品订单、服务订单等。
     * @return string 返回新的订单ID。订单ID由类型前缀、微秒级时间戳和随机数组成。
     */
    public function getNewOrderId($type)
    {
        // 获取当前时间的微秒和秒部分
        list($msec, $sec) = explode(' ', microtime());

        // 将微秒和秒转换为毫秒，并去掉小数点，用于提高ID的唯一性
        $msectime = number_format((floatval($msec) + floatval($sec)) * 1000, 0, '', '');

        // 生成订单ID：类型前缀 + 毫秒时间戳 + 随机数
        // 随机数生成考虑了微秒时间戳的值，以避免在高并发情况下产生相同的随机数
        $orderId = $type . $msectime . mt_rand(10000, max(intval($msec * 10000) + 10000, 98369));

        return $orderId;
    }

    /**
     * 根据商品临时类型计算购物车中商品的总量。
     * 该方法主要用于根据商品的不同类型（如重量型或体积型），来计算购物车中商品的总量。
     * 对于重量型商品，总量是商品数量乘以重量；对于体积型商品，总量是商品数量乘以体积。
     *
     * @param array $cart 购物车数据，包含商品信息和数量。
     * @return mixed 返回计算得到的商品总量，如果商品没有临时类型，则直接返回商品数量。
     */
    public function productByTempNumber($cart)
    {
        // 尝试获取商品的临时类型
        $type = $cart['product']['temp']['type'] ?? null;
        // 获取购物车中商品的数量
        $cartNum = $cart['cart_num'];

        // 如果商品没有临时类型，则直接返回商品数量
        if (!$type) {
            return $cartNum;
        } else if ($type == 2) {
            // 如果商品是体积型，返回商品数量乘以体积
            return bcmul($cartNum, $cart['productAttr']['volume'], 2);
        } else {
            // 如果商品是重量型，返回商品数量乘以重量
            return bcmul($cartNum, $cart['productAttr']['weight'], 2);
        }
    }

    /**
     * 根据商品类型获取购物车中商品的价格
     *
     * 本函数用于根据商品的不同类型，从购物车项中提取对应的价格。
     * 商品类型包括预售价、辅助销售价格、活动价格和普通价格。
     *
     * @param array $cart 购物车项的数据数组，包含商品类型和相应的价格信息。
     * @return float 返回对应的商品价格。如果商品类型不匹配任何预定义类型，则返回默认商品价格。
     */
    public function cartByPrice($cart)
    {
        // 检查商品类型是否为预售价
        if ($cart['product_type'] == '2') {
            return $cart['productPresellAttr']['presell_price'];
            // 检查商品类型是否为辅助销售价格
        } else if ($cart['product_type'] == '3') {
            return $cart['productAssistAttr']['assist_price'];
            // 检查商品类型是否为活动价格
        } else if ($cart['product_type'] == '4') {
            return $cart['activeSku']['active_price'];
            // 默认情况下返回普通商品价格
        } else {
            return $cart['productAttr']['price'];
        }
    }

    /**
     * 根据优惠券计算购物车中商品的价格。
     *
     * 此函数用于根据商品的类型和优惠券规则，计算购物车中商品的实际价格。
     * 不同类型的商品可能有不同的价格计算逻辑，例如预售商品、普通商品等。
     *
     * @param array $cart 购物车中单个商品的信息，包含商品类型和相关属性价格等。
     * @return float 返回计算后的商品价格。商品类型为2时返回预售商品的最终价格；其他类型商品返回0或普通商品的属性价格。
     */
    public function cartByCouponPrice($cart)
    {
        // 如果商品类型为2（预售商品），返回预售商品的最终价格。
        if ($cart['product_type'] == '2') {
            return $cart['productPresellAttr']['final_price'];
            // 如果商品类型为1（普通商品），或者3、4（其他特殊商品类型），返回0，表示不适用优惠券。
        } else if ($cart['product_type'] == '1') {
            return 0;
        } else if ($cart['product_type'] == '3') {
            return 0;
        } else if ($cart['product_type'] == '4') {
            return 0;
            // 如果商品类型不属于以上任何一种，视为普通商品，返回商品的属性价格。
        } else {
            return $cart['productAttr']['price'];
        }
    }


    /**
     * 根据商品类型获取预订商品的预售价
     *
     * 此函数用于判断购物车中的商品是否为预订商品，并返回相应的预售价。
     * 如果商品类型为2，则表示该商品为预订商品，返回预售价；否则，返回0。
     *
     * @param array $cart 购物车中商品的信息数组
     * @return float|int 返回预订商品的预售价（如果商品是预订类型），否则返回0
     */
    public function cartByDownPrice($cart)
    {
        // 判断商品类型是否为预订类型（类型为2）
        if ($cart['product_type'] == '2') {
            // 返回预订商品的预售价
            return $cart['productPresellAttr']['down_price'];
        } else {
            // 如果商品不是预订类型，返回0
            return 0;
        }
    }

    /**
     * 用户订单统计
     *
     * 本函数用于根据用户ID统计用户的订单情况，包括未支付、未发货、未收货、未评价、已完成和退款中的订单数量。
     * 同时，也统计了用户的总订单数量。
     *
     * @param int $uid 用户ID
     * @return array 包含各种状态订单数量的数组
     */
    public function userOrderNumber(int $uid)
    {
        // 未支付的订单数量
        $noPay = app()->make(StoreGroupOrderRepository::class)->orderNumber($uid);

        // 未邮费的订单数量（可能需要进一步确认含义）
        $noPostage = $this->dao->search(['uid' => $uid, 'status' => 0, 'paid' => 1])->where('StoreOrder.is_del', 0)->count();

        // 所有订单的数量（可能包括已删除的订单）
        $all = $this->dao->search(['uid' => $uid, 'status' => -2])->where('StoreOrder.is_del', 0)->count();

        // 未发货的订单数量
        $noDeliver = $this->dao->search(['uid' => $uid, 'status' => 1, 'paid' => 1])->where('StoreOrder.is_del', 0)->count();

        // 未评价的订单数量
        $noComment = $this->dao->search(['uid' => $uid, 'status' => 2, 'paid' => 1])->where('StoreOrder.is_del', 0)->count();

        // 已完成的订单数量
        $done = $this->dao->search(['uid' => $uid, 'status' => 3, 'paid' => 1])->where('StoreOrder.is_del', 0)->count();

        // 退款中的订单数量
        $refund = app()->make(StoreRefundOrderRepository::class)->getWhereCount(['uid' => $uid, 'status' => [0, 1, 2]]);

        // 总订单数量（已支付）
        //$orderPrice = $this->dao->search(['uid' => $uid, 'paid' => 1])->sum('pay_price');
        $orderCount = $this->dao->search(['uid' => $uid, 'paid' => 1])->count();

        // 返回包含各种状态订单数量的数组
        return compact('noComment', 'done', 'refund', 'noDeliver', 'noPay', 'noPostage', 'orderCount', 'all');
    }

    /**
     * 根据订单ID和用户ID获取订单详情
     * 此函数主要用于查询订单的详细信息，包括订单的产品信息、商家信息、收货信息等。
     * 如果提供了用户ID，则查询该用户的订单；否则，查询订单的其他相关信息。
     *
     * @param integer $id 订单ID
     * @param integer|null $uid 用户ID，可选参数。如果提供此参数，将查询指定用户ID的订单。
     * @return null|\app\common\model\StoreOrder 返回订单对象，如果未找到订单则返回null。
     */
    public function getDetail($id, $uid = null)
    {
        // 初始化查询条件
        $where = [];
        // 定义需要关联查询的模型和字段
        $with = [
            'take',
            'staffs',
            'orderProduct', // 订单产品信息
            'merchant' => function ($query) {
                // 商家信息，包括商家ID、商家名称、服务电话，并附加服务类型信息
                return $query->field('mer_id,mer_name,service_phone,long,lat,mer_avatar,mer_address')->append(['services_type'], true);
            },
            'receipt' => function ($query) {
                // 收货信息，包括订单ID和收货单ID
                return $query->field('order_id,order_receipt_id');
            },
            'takeOrderList.orderProduct', // 提货单中的产品信息
            'deliveryOrder' => function ($query) {
                return $query->with(['deliveryService' => function ($query) {
                    return $query->field('service_id,name,phone,avatar');
                }]);
            },
            'presellOrder'
        ];

        // 根据是否提供用户ID，调整查询条件
        if ($uid) {
            $where['uid'] = $uid; // 如果提供用户ID，添加到查询条件中
        } else {
            // 如果不提供用户ID，关联查询用户信息
            $with['user'] = function ($query) {
                return $query->field('uid,nickname,avatar,is_svip'); // 用户ID和昵称
            };
        }

        // 执行订单查询，包括订单本身的信息以及关联信息
        $order = $this->dao->search($where)
            ->where('order_id', $id)
            ->where('StoreOrder.is_del', 0)
            ->with($with)
            ->append(['refund_status', 'open_receipt'],true)
            ->find();
        // 如果订单不存在，返回null
        if (!$order) {
            return null;
        }

        $config = merchantConfig($order['mer_id'],['mer_take_address','mer_take_day','mer_take_time','mer_take_phone']);
        $order['merchant']['mer_take_address'] = $config['mer_take_address'];
        $order['merchant']['mer_take_day'] = $config['mer_take_day'];
        $order['merchant']['mer_take_time'] = $config['mer_take_time'];
        $order['merchant']['mer_take_phone'] = $config['mer_take_phone'];

        // 处理预售订单的特殊情况，计算预售订单的最终价格
        if ($order->activity_type == 2) {
            if ($order->presellOrder) {
                $order->presellOrder->append(['activeStatus']); // 预售订单的状态
                $order->presell_price = bcadd($order->pay_price, $order->presellOrder->pay_price, 2); // 计算预售订单的最终支付价格
            } else {
                $order->presell_price = $order->pay_price; // 如果不是预售订单，则预售价格等于支付价格
            }
        }
        $order['cancel_status'] = false;
        if ($order->is_virtual == 4 && $order->is_cancel) {
            $reservation = $order['orderProduct'][0]['cart_info']['reservation'] ?? '';
            if ($reservation && $reservation['is_cancel_reservation'] == 1) {
                $star = explode('-',$order['orderProduct'][0]['reservation_time_part']);
                $cancelTime = strtotime($order['orderProduct'][0]['reservation_date'] . ' ' .$star[0]);
                $cancelTime = $cancelTime - time() >= $reservation['cancel_reservation_time'] * 3600;
                $order['cancel_status'] = $cancelTime && ($order->status !== 2 ? true : false);
            }
        }
        $order['auto_take_time'] = app()->make(StoreOrderStatusRepository::class)->getTakeStatus($order->order_id);
        // 返回订单详情
        return $order;
    }

    /**
     * 根据验证码和用户ID查询订单详情
     * 此函数用于通过验证码和可选的用户ID来检索订单详情。它首先构建查询条件，然后查询数据库以获取满足条件的订单数据。
     * 如果找不到数据或者订单已经全部核销，则抛出异常。否则，返回查询到的订单数据。
     *
     * @param string $code 验证码
     * @param int|null $uid 用户ID，可选参数，用于限定查询的订单属于哪个用户
     * @return array 查询到的订单数据
     * @throws ValidateException 如果数据不存在或者订单已全部核销，则抛出此异常
     */
    public function codeByDetail($code, $uid = null)
    {
        // 初始化查询条件
        $where = [];
        // 如果提供了用户ID，则加入查询条件
        if ($uid) $where['uid'] = $uid;

        // 执行查询，根据验证码、订单未删除状态，以及关联的数据（订单产品和商家）进行查询
        $data = $this->dao->search($where)
            ->where('verify_code', $code)
            ->where('StoreOrder.is_del', 0)
            ->with([
                'orderProduct', // 关联订单产品
                'merchant' => function ($query) {
                    // 关联商家，只获取商家ID和名称
                    return $query->field('mer_id,mer_name');
                }
            ])
            ->find();

        // 如果查询结果为空，则抛出异常提示数据不存在
        if (!$data)
            throw new ValidateException('数据不存在');

        // 如果订单状态为已核销，则抛出异常提示订单已全部核销
        if($data['product_type'] == 0 && $data['is_virtual'] == 4) {
            if(!in_array($data['status'], [0,1,20])) {
                throw new ValidateException('该订单已全部核销');
            }
        } else {
            if($data['status']) {
                throw new ValidateException('该订单已全部核销');
            }
        }

        // 返回查询到的订单数据
        return $data;
    }

    /**
     * 给用户赠送积分
     *
     * 当订单满足赠送积分条件时，本函数将执行积分赠送操作。它通过增加用户的积分账单来实现，
     * 积分的来源是订单的赠送积分字段。此功能旨在鼓励用户消费，增加用户黏性。
     *
     * @param object $groupOrder 订单对象，包含订单相关信息
     *
     * 注意：本函数假设订单对象中包含用户信息（例如$user->integral）以及订单的赠送积分信息（$groupOrder->give_integral）。
     */
    public function giveIntegral($groupOrder)
    {
        // 检查订单是否满足赠送积分的条件（积分大于0）
        if ($groupOrder->give_integral > 0) {
            // 使用依赖注入的方式创建用户账单仓库实例，并增加用户的积分账单
            app()->make(UserBillRepository::class)->incBill($groupOrder->uid, 'integral', 'lock', [
                'link_id' => $groupOrder['group_order_id'], // 订单ID，用于关联积分的来源
                'status' => 0, // 积分状态，这里假设0表示积分锁定，即还未完全发放
                'title' => '下单赠送积分', // 积分的描述，表明积分的来源是下单赠送
                'number' => $groupOrder->give_integral, // 赠送的积分数量
                'mark' => '成功消费' . floatval($groupOrder['pay_price']) . '元,赠送积分' . floatval($groupOrder->give_integral), // 积分的备注信息，记录消费金额和赠送的积分数量
                'balance' => $groupOrder->user->integral // 用户当前的积分余额，用于记录积分的变化
            ]);
        }
    }

    /**
     * 计算并处理用户的推广佣金。
     *
     * 此方法用于根据订单信息和用户信息，计算并处理用户的推广佣金。它涉及到一级和二级推广佣金的计算，
     * 根据订单的不同状态和用户的角色，决定佣金的分配和记录。
     *
     * @param StoreOrder $order 订单对象，包含订单的相关信息，如推广UID、一级佣金、二级佣金等。
     * @param User $user 用户对象，包含用户的信息，如UID、昵称等。
     */
    public function computed(StoreOrder $order, User $user)
    {
        // 实例化用户账单仓库，用于处理用户账单相关操作。
        $userBillRepository = app()->make(UserBillRepository::class);

        // 根据订单状态和用户角色，确定推广UID和顶级UID。
        if ($order->spread_uid) {
            $spreadUid = $order->spread_uid;
            $topUid = $order->top_uid;
        } else if ($order->is_selfbuy) {
            $spreadUid = $user->uid;
            $topUid = $user->spread_uid;
        } else {
            $spreadUid = $user->spread_uid;
            $topUid = $user->top_uid;
        }

        // 如果订单有一级佣金且推广UID存在，则处理一级佣金的增加和记录。
        // 添加冻结佣金
        $title = $order->activity_type == 4 ? '获得团长分佣' : '获得推广佣金';
        if ($order->extension_one > 0 && $spreadUid) {
            // 增加用户账单的一级佣金金额，并记录相关详细信息。
            $userBillRepository->incBill($spreadUid, 'brokerage', 'order_one', [
                'link_id' => $order['order_id'],
                'status' => 0,
                'title' => $title,
                'number' => $order->extension_one,
                'mark' => $user['nickname'] . '成功消费' . floatval($order['pay_price']) . '元,奖励' . $title . floatval($order->extension_one),
                'balance' => 0
            ]);
            // 增加用户的一级佣金总额。
            $userRepository = app()->make(UserRepository::class);
            $userRepository->incBrokerage($spreadUid, $order->extension_one);
            // 注释掉的代码部分原用于记录财务记录，现被省略。
        }

        // 如果订单有二级佣金且顶级UID存在，则处理二级佣金的增加和记录。
        if ($order->extension_two > 0 && $topUid) {
            // 增加用户账单的二级佣金金额，并记录相关详细信息。
            $userBillRepository->incBill($topUid, 'brokerage', 'order_two', [
                'link_id' => $order['order_id'],
                'status' => 0,
                'title' => '获得推广佣金',
                'number' => $order->extension_two,
                'mark' => $user['nickname'] . '成功消费' . floatval($order['pay_price']) . '元,奖励推广佣金' . floatval($order->extension_two),
                'balance' => 0
            ]);
            // 增加用户的二级佣金总额。
            $userRepository = app()->make(UserRepository::class);
            $userRepository->incBrokerage($topUid, $order->extension_two);
            // 注释掉的代码部分原用于记录财务记录，现被省略。
        }
    }

    /**
     * 处理订单领取操作。
     *
     * 本函数在数据库事务中执行一系列操作，以确保订单状态的原子性更新。
     * 主要包括：
     * 1. 如果提供了用户信息，则计算与用户相关的订单数据。
     * 2. 发送两条短信通知，分别通知用户和管理员关于订单的领取情况。
     * 3. 更新商家的锁定资金。
     * 4. 保存更新后的订单信息。
     *
     * @param StoreOrder $order 待处理的订单对象。
     * @param User $user 可选的用户对象，如果提供，将进行相关计算。
     */
    public function takeAfter(StoreOrder $order, ?User $user)
    {
        // 使用数据库事务来确保操作的原子性
        Db::transaction(function () use ($user, $order) {
            // 如果提供了用户信息，则计算与用户相关的订单数据
            if ($user) {
                $this->computed($order, $user);
            }
            // 保存更新后的订单信息到数据库
            $order->save();
        });
        // 发送短信通知用户订单已被领取
        Queue::push(SendSmsJob::class, ['tempId' => 'ORDER_TAKE_SUCCESS', 'id' => $order->order_id]);
        // 发送短信通知管理员订单领取情况
        Queue::push(SendSmsJob::class, ['tempId' => 'ADMIN_TAKE_DELIVERY_CODE', 'id' => $order->order_id]);

        $main_id =  $order->main_id ?: $order->order_id;
        $count = $this->dao->getSearch([])->where('status', 'NotIn', [
            OrderStatus::ORDER_STATUS_PENDING_REVIEW,
            OrderStatus::ORDER_STATUS_COMPLETED,
            OrderStatus::ORDER_STATUS_REFUND
        ])->where(function($query) use ($main_id) {
            $query->where('order_id', $main_id)->whereOr('main_id', $main_id);
        })->count();

        if (!$count) {
            // 根据主订单ID获取具体的订单信息
            if ($order->main_id) {
                $order = $this->dao->getWhere(['order_id' =>$main_id]);
            }
            // 更新商家的锁定资金状态
            app()->make(MerchantRepository::class)->computedLockMoney($order);
        }
    }


    /**
     * 处理订单的领取操作。
     * 此函数主要用于将订单状态更新为已领取，同时记录相应的日志。
     *
     * @param int $id 订单ID。
     * @param User|null $user 当前操作的用户对象，如果为null，则表示是系统操作。
     * @throws ValidateException 如果订单不存在或状态不正确，则抛出验证异常。
     */
    public function takeOrder($id, ?User $user = null, $isApi = false)
    {
        // 根据订单ID和用户ID（如果存在）查询订单，排除已删除的订单。
        $order = $this->dao->searchAll(!$user ? [] : ['uid' => $user->uid])->where('order_id', $id)->where('StoreOrder.is_del', '<>', 1)->find();
        // 如果订单不存在，则抛出验证异常。
        if (!$order)
            throw new ValidateException('订单不存在');
        // 如果订单状态不为待领取（1）或订单类型不为特定值（20），则抛出验证异常。
        if ($order['status'] != 1)
            throw new ValidateException('订单状态有误');
        if ($order['order_type'] && !in_array($order['order_type'], [2,20])) // 2:同城配送、20:积分订单
            throw new ValidateException('订单类型有误');
        // 确定日志记录函数的名字，如果没有用户，则使用系统日志记录函数。
        if (!$user) {
            $user = $order->user;
        }
        // 根据订单的活动类型，更新订单状态为待评价（2）或已完成（3）。
        // 订单状态（0：待发货；1：待收货；2：待评价；3：已完成； 9: 拼团中 10:  待付尾款 11:尾款超时未付 -1：已退款）
        $order->status = $order->activity_type == 20 ? 3 : 2;
        // 设置订单的确认收货时间。
        $order->verify_time = date('Y-m-d H:i:s');
        // 触发订单领取前的事件。
        event('order.take.before', compact('order'));
        // 创建订单状态更新对象。
        $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
        $orderStatus = [
            'order_id' => $order->order_id,
            'order_sn' => $order->order_sn,
            'type' => $storeOrderStatusRepository::TYPE_ORDER,
            'change_message' => '已收货',
            'change_type' => $storeOrderStatusRepository::ORDER_STATUS_TAKE,
        ];
        // 使用事务处理订单状态的更新和日志的记录。
        Db::transaction(function () use ($order, $user, $storeOrderStatusRepository, $orderStatus, $isApi) {
            // 执行领取后的处理逻辑。
            $this->takeAfter($order, $user);

            if($order['order_type'] == 2 && $order->deliveryOrder) {
                $order->deliveryOrder->status = 4;
                $order->deliveryOrder->save();
            }
            // 保存订单的更新。
            $order->save();
            // 调用相应的日志记录函数。
            if($isApi) {
                $storeOrderStatusRepository->createUserLog($user->uid, $orderStatus);
            }else{
                $storeOrderStatusRepository->createSysLog($orderStatus);
            }
        });
        // 触发订单领取后的事件。
        event('order.take', compact('order'));
    }


    /**
     *  获取订单列表头部统计数据
     * @Author:Qinii
     * @Date: 2020/9/12
     * @param int|null $merId
     * @param int|null $orderType
     * @return array
     */
    public function OrderTitleNumber(?int $merId, ?int $orderType, $searchWhere = [])
    {
        $where = [];
        $sysDel = $merId ? 0 : null;                    //商户删除
        if ($merId) $where['mer_id'] = $merId;          //商户订单
        if ($orderType === 0) $where['order_type'] = 0; //普通订单
        if ($orderType === 1) $where['take_order'] = 1; //已核销订单
        if ($orderType === 2) $where['is_spread'] = 1; //分销订单
        //1: 未支付 2: 未发货 3: 待收货 4: 待评价 5: 交易完成 6: 已退款 7: 已删除

        $whereData = array_merge($searchWhere, $where);
        $all = $this->dao->search($whereData, $sysDel)->where($this->getOrderType(0))->count();
        $statusAll = $all;
        $unpaid = $this->dao->search($whereData, $sysDel)->where($this->getOrderType(1))->count();
        $unshipped = $this->dao->search($whereData, $sysDel)->where($this->getOrderType(2))->count();
        $untake = $this->dao->search($whereData, $sysDel)->where($this->getOrderType(3))->count();
        $unevaluate = $this->dao->search($whereData, $sysDel)->where($this->getOrderType(4))->count();
        $complete = $this->dao->search($whereData, $sysDel)->where($this->getOrderType(5))->count();
        $refund = $this->dao->search($whereData, $sysDel)->where($this->getOrderType(6))->count();
        $del = $this->dao->search($whereData, $sysDel)->where($this->getOrderType(7))->count();

        return compact('all', 'statusAll', 'unpaid', 'unshipped', 'untake', 'unevaluate', 'complete', 'refund', 'del');
    }

    /**
     * 根据条件统计不同类型的订单数量。
     *
     * 本函数用于根据传入的条件数组，统计并返回不同类型的订单数量。
     * 订单类型包括：全部订单、普通订单、核销订单、虚拟商品订单、卡密商品订单。
     * 统计结果以键值对形式返回，每个键代表一种订单类型，值是一个包含订单数量、类型名称和类型标识的数组。
     *
     * @param array $where 统计条件数组，用于筛选订单。
     * @return array 包含不同订单类型统计结果的数组。
     */
    public function orderType(array $where)
    {
        return [
            // 统计全部订单的数量，类型标识为-1，表示全部订单。
            'type' => [
                'count' => $this->dao->search($where)->count(),
                'title' => '全部',
                'order_type' => -1,
            ],
            // 统计普通订单的数量，即非虚拟、非核销的订单。
            'type_0' => [
                'count' => $this->dao->search($where)->where('order_type', 0)->where('is_virtual', 0)->count(),
                'title' => '普通订单',
                'order_type' => 0,
            ],
            // 统计核销订单的数量。
            'type_1' => [
                'count' => $this->dao->search($where)->where('order_type', 1)->count(),
                'title' => '核销订单',
                'order_type' => 1,
            ],
            // 统计虚拟商品订单的数量，即订单中包含虚拟商品的订单。
            'type_2' => [
                'count' => $this->dao->search($where)->where('is_virtual', 1)->count(),
                'title' => '虚拟订单',
                'order_type' => 2,
            ],
            // 统计卡密商品订单的数量，卡密商品订单特指通过卡密进行兑换的商品订单。
            'type_3' => [
                'count' => $this->dao->search(array_merge($where, ['order_type' => 3]))->count(),
                'title' => '卡密订单',
                'order_type' => 3,
            ],
            'type_4' => [
                'count' => $this->dao->search($where)->where('is_virtual', 4)->count(),
                'title' => '预约服务订单',
                'order_type' => 4,
            ],
        ];
    }

    /**
     * 获取订单类型
     * @param $status
     * @return mixed
     * @author Qinii
     */
    public function getOrderType($status)
    {
        $param['StoreOrder.is_del'] = 0;
        switch ($status) {
            case 1:
                $param['StoreOrder.paid'] = 0;
                break; // 未支付
            case 2:
                $param['StoreOrder.paid'] = 1;
                $param['StoreOrder.status'] = 0;
                break;  // 待发货
            case 3:
                $param['StoreOrder.order_type'] = 0;
                $param['StoreOrder.status'] = 1;
                break;  // 待收货
            case 4:
                $param['StoreOrder.status'] = 2;
                break;  // 待评价
            case 5:
                $param['StoreOrder.status'] = 3;
                break;  // 交易完成
            case 6:
                $param['StoreOrder.status'] = -1;
                break;  // 已退款
            case 7:
                $param['StoreOrder.is_del'] = 1;
                break;  // 已删除
            case 8:
                $param['StoreOrder.order_type'] = 1;
                $param['StoreOrder.status'] = 0;
                break;  // 待核销
            default:
                unset($param['StoreOrder.is_del']);
                break;  //全部
        }
        return $param;
    }

    /**
     * 检查是否存在特定条件的商户订单。
     * 该方法用于确定是否有一个订单满足给定的ID、是否未删除、已支付，以及可选的商户ID和订单状态。
     * 主要用于商户侧的订单管理操作，例如查询是否存在某个商户的未处理订单。
     *
     * @param int $id 订单ID。这是要检查的订单的唯一标识符。
     * @param int|null $merId 商户ID。可选参数，用于限定查询的商户。
     * @param int|null $re 订单状态标志。如果为1，则进一步限定订单状态为未处理。
     * @return bool 返回布尔值，表示是否找到满足条件的订单。
     */
    public function merDeliveryExists(int $id, ?int $merId, ?int $re = 0)
    {
        // 初始化订单查询条件
        $where = ['order_id' => $id, 'is_del' => 0, 'paid' => 1];

        // 如果 $re 为1，添加订单状态条件为未处理
        if ($re) $where['status'] = 0;

        // 如果提供了 $merId，添加商户ID作为查询条件
        if ($merId) $where['mer_id'] = $merId;

        // 调用DAO层方法检查满足条件的订单是否存在
        return $this->dao->merFieldExists($where);
    }

    /**
     * 检查是否存在特定商家的已支付未发货订单
     *
     * 本函数用于查询是否存在特定商家ID的、特定订单ID的、已支付且未发货且未删除的订单。
     * 这对于确保订单状态的正确性和避免重复发货等问题非常重要。
     *
     * @param int $id 订单ID，用于精确查询特定订单。
     * @param int $merId 商家ID，可选参数，用于查询特定商家的订单。如果未提供，则查询所有商家的订单。
     * @return bool 返回true表示存在符合条件的订单，返回false表示不存在。
     */
    public function merGetDeliveryExists(int $id, ?int $merId)
    {
        // 定义查询条件，包括订单ID、订单未删除、已支付、且状态为有效
        $where = ['order_id' => $id, 'is_del' => 0, 'paid' => 1, 'status' => 1];

        // 如果提供了商家ID，则将商家ID添加到查询条件中
        if ($merId) {
            $where['mer_id'] = $merId;
        }

        // 调用dao层的方法，检查是否存在符合条件的订单
        return $this->dao->merFieldExists($where);
    }

    /**
     * 检查订单状态是否存在
     *
     * 本函数用于确定是否存在指定订单ID和商家ID的订单，该订单满足未删除、未支付、未完成的状态。
     * 这对于处理订单状态查询和更新操作时的条件检查非常有用，可以避免对已删除、已支付或已完成的订单进行错误的操作。
     *
     * @param int $id 订单ID，用于唯一标识订单。
     * @param int $merId 商家ID，可选参数，用于指定特定商家的订单。如果未提供，则检查所有商家的订单。
     * @return bool 返回布尔值，表示是否存在满足条件的订单。存在返回true，否则返回false。
     */
    public function merStatusExists(int $id, ?int $merId)
    {
        // 定义查询条件，包括订单ID、未删除、未支付、未完成的状态。
        $where = ['order_id' => $id, 'is_del' => 0, 'paid' => 0, 'status' => 0];
        // 如果提供了商家ID，则将商家ID添加到查询条件中。
        if ($merId) $where['mer_id'] = $merId;
        // 调用dao层的方法，检查满足条件的订单是否存在。
        return $this->dao->merFieldExists($where);
    }

    /**
     * 检查用户删除记录是否存在
     *
     * 本函数用于确定是否存在一个特定用户的删除记录。它通过检查订单ID和可选的商户ID来识别记录。
     * 如果提供了商户ID，则只检查该商户下的删除记录。
     *
     * @param int $id 订单ID，用于查找删除记录的关键标识。
     * @param int|null $merId 商户ID，可选参数，用于限定删除记录的商户范围。
     * @return bool 返回true如果删除记录存在，否则返回false。
     */
    public function userDelExists(int $id, ?int $merId)
    {
        // 初始化条件，用于查询订单ID为$id且已标记为删除的记录
        $where = ['order_id' => $id, 'is_del' => 1];

        // 如果提供了商户ID，则将其加入查询条件，限定查询范围
        if ($merId) $where['mer_id'] = $merId;

        // 调用dao层的方法检查指定条件的记录是否存在
        return $this->dao->merFieldExists($where);
    }

    /**
     * 创建用于修改订单信息的表单
     *
     * 本函数通过传入订单ID，获取该订单的相关信息，并构建一个包含订单总价、实际支付金额和订单邮费的表单。
     * 这个表单旨在允许商家修改订单的支付价格和邮费等信息。
     *
     * @param int $id 订单的唯一标识符
     * @return Form|string
     */
    public function form($id)
    {
        // 根据订单ID查询订单的总价、实际支付金额、总邮费和实际支付邮费
        $data = $this->dao->getWhere([$this->dao->getPk() => $id], 'total_price,pay_price,total_postage,pay_postage');

        // 构建表单的URL，指向更新订单信息的处理程序
        $form = Elm::createForm(Route::buildUrl('merchantStoreOrderUpdate', ['id' => $id])->build());

        // 设置表单的验证规则，包括订单总价、实际支付金额和订单邮费，均为必需的数字字段
        $form->setRule([
            Elm::number('total_price', '订单总价：', $data['total_price'])->required('请输入订单总价'),
            Elm::number('total_postage', '订单邮费：', $data['total_postage'])->required('请输入订单邮费'),
            Elm::number('pay_price', '实际支付金额：', $data['pay_price'])->required('请输入实际支付金额'),
        ]);

        // 设置表单的标题为“修改订单”
        return $form->setTitle('修改订单');
    }

    /**
     *  修改订单价格
     * @param int $id
     * @param array $data
     * @author Qinii
     * @day 12/15/20
     */
    public function eidt(int $id, array $data, $service_id = 0)
    {

        /**
         * 1 计算出新的实际支付价格
         *      1.1 计算邮费
         *      1.2 计算商品总价
         * 2 修改订单信息
         * 3 计算总单数据
         * 4 修改总单数据
         * 5 修改订单商品单价
         *
         * pay_price = total_price - coupon_price + pay_postage
         */
        $order = $this->dao->get($id);
        if ($order->activity_type == 2) {
            throw new ValidateException('预售订单不支持改价');
        }
        $extension_total = (float)bcadd($order->extension_one, $order->extension_two, 2);
        $data['pay_price'] = $this->bcmathPrice($data['total_price'], $order['coupon_price'], $data['pay_postage']);
        if ($data['pay_price'] < 0) {
            throw new ValidateException('实际支付金额不能小于0');
        } else if ($data['pay_price'] < $extension_total) {
            throw new ValidateException('实际支付金额不能小于佣金' . $extension_total);
        }
        $make = app()->make(StoreGroupOrderRepository::class);
        $orderGroup = $make->dao->getWhere(['group_order_id' => $order['group_order_id']]);

        //总单总价格
        $_group['total_price'] = $this->bcmathPrice($orderGroup['total_price'], $order['total_price'], $data['total_price']);
        //总单实际支付价格
        $_group['pay_price'] = $this->bcmathPrice($orderGroup['pay_price'], $order['pay_price'], $data['pay_price']);
        //总单实际支付邮费
        $_group['pay_postage'] = $this->bcmathPrice($orderGroup['pay_postage'], $order['pay_postage'], $data['pay_postage']);
        //计算赠送积分
        $data['give_integral'] = $_group['give_integral'] = $this->recalculateGiveIntegral($order, $_group['pay_price']);
        event('order.changePrice.before', compact('order', 'data'));
        //订单记录
        $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);

        $orderStatus = [
            'order_id' => $order->order_id,
            'order_sn' => $order->order_sn,
            'type' => $storeOrderStatusRepository::TYPE_ORDER,
            'change_message' => '订单价格:' . $order['pay_price'] . '修改为:' . $data['pay_price'],
            'change_type' => $storeOrderStatusRepository::ORDER_STATUS_CHANGE,
        ];

        Db::transaction(function () use ($id, $data, $orderGroup, $order, $_group, $storeOrderStatusRepository, $orderStatus, $service_id) {
            $orderGroup->total_price = $_group['total_price'];
            $orderGroup->pay_price = $_group['pay_price'];
            $orderGroup->pay_postage = $_group['pay_postage'];
            $orderGroup->give_integral = $_group['give_integral'];
            $orderGroup->group_order_sn = $this->getNewOrderId(StoreOrderRepository::TYPE_SN_ORDER) . '0';
            $orderGroup->save();

            $this->dao->update($id, $data);
            $this->changOrderProduct($id, $data);

            if ($service_id) {
                $storeOrderStatusRepository->createServiceLog($service_id, $orderStatus);
            } else {
                $storeOrderStatusRepository->createAdminLog($orderStatus);
            }
            if ($data['pay_price'] != $order['pay_price']) Queue::push(SendSmsJob::class, ['tempId' => 'PRICE_REVISION_CODE', 'id' => $id]);
        });
        event('order.changePrice', compact('order', 'data'));
    }

    public function recalculateGiveIntegral($order, $payPrice)
    {
        $order_total_give_integral = 0;
        $svip_status = $order->svip_discount > 0 && systemConfig('svip_switch_status') == '1';
        $svip_integral_rate = $svip_status ? app()->make(MemberinterestsRepository::class)->getSvipInterestVal(MemberinterestsRepository::HAS_TYPE_PAY) : 0;
        //积分配置
        $sysIntegralConfig = systemConfig(['integral_money', 'integral_status', 'integral_order_rate']);
        $giveIntegralFlag = $sysIntegralConfig['integral_status'] && $sysIntegralConfig['integral_order_rate'] > 0;
        $total_give_integral = 0;
        //计算赠送积分, 只有普通商品赠送积分
        if ($giveIntegralFlag && $payPrice > 0) {
            $total_give_integral = floor(bcmul($payPrice, $sysIntegralConfig['integral_order_rate'], 0));
            if ($total_give_integral > 0 && $svip_status && $svip_integral_rate > 0) {
                $total_give_integral = bcmul($svip_integral_rate, $total_give_integral, 0);
            }
        }

        return bcadd($total_give_integral, $order_total_give_integral, 0);
    }

    /**
     *  改价后重新计算每个商品的单价
     * @param int $orderId
     * @param array $data
     * @author Qinii
     * @day 12/15/20
     */
    public function changOrderProduct(int $orderId, array $data)
    {
        $make = app()->make(StoreOrderProductRepository::class);
        $ret = $make->getSearch(['order_id' => $orderId])->field('order_product_id,product_num,product_price')->select();
        $count = $make->getSearch(['order_id' => $orderId])->sum('product_price');
        $_count = (count($ret->toArray()) - 1);
        $pay_price = $data['total_price'];
        foreach ($ret as $k => $item) {
            $_price = 0;
            /**
             *  比例 =  单个商品总价 / 订单原总价；
             *
             *  新的商品总价 = 比例 * 订单修改总价
             *
             *  更新数据库
             */
            if ($k == $_count) {
                $_price = $pay_price;
            } else {
                $_reta = bcdiv($item->product_price, $count, 3);
                $_price = bcmul($_reta, $data['total_price'], 2);
            }

            $item->product_price = $_price;
            $item->save();

            $pay_price = $this->bcmathPrice($pay_price, $_price, 0);
        }
    }

    /**
     *  计算的重复利用
     * @param $total
     * @param $old
     * @param $new
     * @return int|string
     * @author Qinii
     * @day 12/15/20
     */
    public function bcmathPrice($total, $old, $new)
    {
        $_bcsub = bcsub($total, $old, 2);
        $_count = (bccomp($_bcsub, 0, 2) == -1) ? 0 : $_bcsub;
        $count = bcadd($_count, $new, 2);
        return (bccomp($count, 0, 2) == -1) ? 0 : $count;
    }

    /**
     * 退款产品
     *
     * 本函数用于处理用户的退款请求。它首先验证订单是否存在，然后查找该订单中是否有可退款的商品。
     * 如果订单不存在或没有可退款的商品，将抛出一个验证异常。否则，将返回可退款商品的列表。
     *
     * @param int $id 订单ID
     * @param int $uid 用户ID
     * @return array 可退款商品列表
     * @throws ValidateException 如果订单不存在或没有可退款商品
     */
    public function refundProduct($id, $uid)
    {
        // 通过订单ID和用户ID获取订单信息
        $order = $this->dao->userOrder($id, $uid);
        // 如果订单不存在，抛出验证异常
        if (!$order)
            throw new ValidateException('订单不存在');

        // 实例化订单产品仓库，用于查询可退款商品
        // 查找可退款商品
        $orderProduct = app()->make(StoreOrderProductRepository::class);
        // 查询订单中可退款的商品列表（退款数量为0且退款开关打开）
        $orderList = $orderProduct->getSearch(['order_id' => $order['order_id'], 'refund_num' => 0, 'refund_switch' => 1])->select();
        // 如果查询结果为空，即没有可退款商品，抛出验证异常
        if (!count($orderList))
            throw new ValidateException('没有可退款商品');

        // 将查询结果转换为数组并返回
        return $orderList->toArray();
    }

    /**
     * 准备订单发货信息。
     * 该方法用于根据订单ID和提供的发货信息，组装并返回用于发货的数据。
     * 发货数据包括运输公司、收货人信息、发货人信息、商品详情等。
     *
     * @param int $id 订单ID。
     * @param array $data 发货相关数据，包括模板ID、发货人信息等。
     * @param int $merId 商家ID，用于获取商家配置信息。
     * @return array 返回组装好的发货数据。
     * @throws ValidateException 如果订单信息不满足发货条件，抛出异常。
     */
    public function orderDumpInfo($orderInfo, $data, $merId)
    {
        // 检查是否提供了模板ID、发货人姓名、电话和地址，缺少则抛出异常。
        if (!$data['temp_id'])
            throw new ValidateException('请填写模板ID');
        if (!$data['from_name'])
            throw new ValidateException('请填写发货人姓名');
        if (!$data['from_tel'])
            throw new ValidateException('请填写发货电话');
        if (!$data['from_addr'])
            throw new ValidateException('请填写发货地址');

        // 如果订单是虚拟商品，则不允许物理发货，抛出异常。
        if ($orderInfo['is_virtual'] == 1)
            throw new ValidateException('虚拟商品只能虚拟发货');

        // 初始化商品详情、数量和重量。
        $cargo = '';
        $count = 0;
        $weight = 0;
        $config = merchantConfig($merId, ['mer_is_cargo', 'mer_dump_type', 'mer_config_siid']);
        // 遍历订单中的每个商品，组装商品详情，并计算商品总数和总重量。
        foreach ($orderInfo->orderProduct as $item) {
            $cargo .= $item['cart_info']['product']['store_name']. ' ' . $item['cart_info']['productAttr']['sku'] . ' * ' . $item['product_num'] . $item['cart_info']['product']['unit_name'] . PHP_EOL;
            $count += $item['product_num'];
            $weight += $item['cart_info']['productAttr']['weight'];
        }
        if ($config['mer_is_cargo'] !== 0) {
            $expData['cargo'] = mb_substr($cargo, 0, 19) .'等';
        }
        // 组装发货数据，包括运输公司、收货人和发货人信息、模板ID等。
        $expData['com'] = $data['com'];
        $expData['to_name'] = $orderInfo->real_name;
        $expData['to_tel'] = $orderInfo->user_phone;
        $expData['to_addr'] = $orderInfo->user_address;
        $expData['from_name'] = $data['from_name'];
        $expData['from_tel'] = $data['from_tel'];
        $expData['from_addr'] = $data['from_addr'];
        $expData['temp_id'] = $data['temp_id'];
        $expData['count'] = $count;
        $expData['weight'] = $weight;
        $expData['order_id'] = $orderInfo->order_id;
        if ($config['mer_dump_type'])
            $expData['siid'] = $config['mer_config_siid'];
        // 返回组装好的发货数据。
        return $expData;
    }

    /**
     *  批量发货
     * @param int $merId
     * @param array $params
     * @author Qinii
     * @day 7/26/21
     */
    public function batchDelivery(int $merId, array $params)
    {

        $import = app()->make(StoreImportRepository::class)->create($merId, 'delivery', $params['delivery_type']);
        $make = app()->make(StoreImportDeliveryRepository::class);
        $data = [];
        $num = 0;
        if ($params['select_type'] == 'all') {
            $where = $params['where'];
            $status = $where['status'];
            unset($where['status']);
            $where['mer_id'] = $merId;
            $params['order_id'] = $this->dao->search($where)->where($this->getOrderType($status))->column('order_id');
        }
        $count = count($params['order_id']);

        foreach ($params['order_id'] as $item) {
            $order = $this->dao->get($item);
            $imp = [
                'order_sn' => $order['order_sn'] ?? $item,
                'delivery_id' => $params['delivery_id'],
                'delivery_type' => $params['delivery_type'],
                'delivery_name' => $params['delivery_name'],
                'import_id' => $import['import_id'],
                'mer_id' => $merId
            ];
            if (!$order ||
                $order['mer_id'] != $merId ||
                $order['is_del'] != 0 ||
                $order['paid'] != 1 ||
                $order['delivery_type'] ||
                $order['order_type'] == 1
            ) {
                $imp['status'] = 0;
                $imp['mark'] = '订单信息不存在或状态错误';
            } else {
                try {
                    if ($params['delivery_type'] == 4) {
                        $dump = [
                            'temp_id' => $params['temp_id'],
                            'from_tel' => $params['from_tel'],
                            'from_addr' => $params['from_addr'],
                            'from_name' => $params['from_name'],
                            'com' => $params['delivery_name'],
                            'delivery_name' => $params['delivery_name'],
                        ];
                        $dump = $this->orderDumpInfo($order, $dump, $merId);
                        $order = $this->dump($order, $merId, $dump);
                        $imp['delivery_id'] = $order['delivery_id'];
                        $imp['delivery_name'] = $order['delivery_name'];
                    } else {
                        $this->delivery($order, $merId, [
                            'delivery_id' => $params['delivery_id'],
                            'delivery_type' => $params['delivery_type'],
                            'delivery_name' => $params['delivery_name'],
                        ]);
                    }
                    $num++;
                    $imp['status'] = 1;
                } catch (Exception $exception) {
                    $imp['status'] = 0;
                    $imp['mark'] = $exception->getMessage();
                }
            }
            $data[] = $imp;
        }
        $_status = ($num == 0) ? -1 : (($count == $num) ? 1 : 10);
        $make->insertAll($data);
        $arr = ['count' => $count, 'success' => $num, 'status' => $_status];
        app()->make(StoreImportRepository::class)->update($import['import_id'], $arr);
    }

    public function getOrderCargo($order)
    {
        $cargo = '';
        foreach ($order->orderProduct as $item) {
            $cargo .= $item['cart_info']['product']['store_name']. ' ' . $item['cart_info']['productAttr']['sku'] . ' * ' . $item['product_num'] . $item['cart_info']['product']['unit_name'] . PHP_EOL;
        }
        if ($cargo)
            $cargo = substr($cargo, 0, strlen($cargo) - 1);
        return $cargo;
    }

    /**
     *  发货功能
     * @param $id
     * @param $merId
     * @param $data
     * @param $split
     * @param $method
     * @param $service_id
     * @return mixed
     * @author Qinii
     */
    public function runDelivery($id, $merId, $data, $split, $method, $service_id = 0)
    {
        return Db::transaction(function () use ($id, $merId, $data, $split, $method, $service_id) {
            $order = $this->dao->get($id);
            if ($order['is_virtual'] == 1 && $data['delivery_type'] != 3)
                throw new ValidateException('虚拟商品只能虚拟发货');

            if ($split['is_split'] && !empty($split['split'])) {
                foreach ($split['split'] as $v) {
                    if (!isset($v['num']) || !$v['num']) {
                        throw new ValidateException('请填写待发数量');
                    }
                    $splitData[$v['id']] = $v['num'];
                }
                $newOrder = app()->make(StoreOrderSplitRepository::class)->splitOrder($order, $splitData, $service_id);
                if ($newOrder) {
                    $order = $newOrder;
                } else {
                    throw new ValidateException('商品不能全部拆单');
                }
            }
            return $this->{$method}($order, $merId, $data, $service_id);
        });
    }

    public function shoipment($order, $merId, $data, $service_id)
    {
        if ($order->is_stock_up !== 0 )
            throw new ValidateException('请勿重复发起申请');
        $params = [
            'phone' => $order['user_phone'],
            'man_name' => $order['real_name'],
            'address' => $order['user_address'],
            'kuaidicom' => $data['delivery_name'],
            'send_real_name' => $data['from_name'],
            'send_phone' => $data['from_tel'],
            'send_address' => $data['from_addr'],
            'service_type' => $data['service_type'],
            'remark' => $data['remark'],
            'weight' => $this->getOrderWeight($order),
            'cargo' => $this->getOrderCargo($order),
            'day_type' => $data['day_type'],
            'pickup_start_time' => $data['pickup_start_time'],
            'pickup_end_time' => $data['pickup_end_time'],
            'temp_id' => $data['temp_id'],
        ];
        $config = merchantConfig($merId,['mer_dump_type','mer_config_siid']);
        $params['return_type'] = 20;
        if ($config['mer_dump_type'] == 1) {
            $params['return_type'] = 10;
            $params['siid'] = $config['mer_config_siid'];
        }
        $statusRepository = app()->make(StoreOrderStatusRepository::class);
        $service = app()->make(CrmebServeServices::class, [$merId]);
        $exprss = app()->make(ExpressRepository::class)->getWhere(['code' => $data['delivery_name']]);
        if (!$exprss) throw new ValidateException('快递公司不存在');
        $update = [
            'is_stock_up' => 1,
            'delivery_type' => $data['delivery_type'],
            'delivery_name' => $exprss['name'],
        ];
        $orderStatus = [
            'order_id' => $order->order_id,
            'order_sn' => $order->order_sn,
            'type' => $statusRepository::TYPE_ORDER,
            'change_type' => $statusRepository::ORDER_DELIVERY_SHIPMENT,
        ];

        return Db::transaction(function () use($service_id,$merId, $order,$service,$params,$update,$orderStatus,
            $statusRepository) {
            $dump = $service->express()->shipment($params,$merId);
            $update['kuaidi_label'] =  $dump['label'] ?? '';
            $update['task_id'] =  $dump['task_id'];
            $update['kuaidi_order_id'] =  $dump['order_id'];
            $update['delivery_id'] =  $dump['kuaidinum'];
            $orderStatus['change_message'] = '商家寄件创建订单:'.$dump['kuaidinum'] ?? $dump['order_id'];
            $this->dao->update($order->order_id, $update);
            if ($service_id) {
                $statusRepository->createServiceLog($service_id, $orderStatus);
            } else {
                $statusRepository->createAdminLog($orderStatus);
            }
        });
    }

    public function cancelShipment($orderInfo, $merId, $msg, $service_id = 0, $uid = 0)
    {

        $service = app()->make(CrmebServeServices::class, [$merId]);
        $data = [
            'task_id' => $orderInfo->task_id,
            'order_id' => $orderInfo->kuaidi_order_id,
            'cancel_msg' => $msg,
        ];

        Db::transaction(function()use($uid,$service_id,$service,$data,$orderInfo){
            $service->express()->shipmentCancelOrder($data);
            $this->cancelShipmentAfrer($orderInfo, $uid, $service_id);
        });
    }

    public function cancelShipmentAfrer($orderInfo, $uid = 0, $service_id = 0)
    {
        $statusRepository = app()->make(StoreOrderStatusRepository::class);
        $orderStatus = [
            'order_id' => $orderInfo->order_id,
            'order_sn' => $orderInfo->order_sn,
            'type' => $statusRepository::TYPE_ORDER,
            'change_type' => $statusRepository::ORDER_DELIVERY_SHIPMENT_CANCEL,
        ];
        $orderInfo->task_id = '';
        $orderInfo->kuaidi_order_id = '';
        $orderInfo->is_stock_up = 0;
        $orderInfo->delivery_type = '';
        $orderInfo->save();
        if ($uid) {
            $orderStatus['change_message'] = '用户取消订单，商家寄件取消：'.$orderInfo['delivery_id'];
            $statusRepository->createUserLog($uid, $orderStatus);
        } else {
            $orderStatus['change_message'] = '商家寄件已被商家取消或订单被快递公司取消:'.$orderInfo['delivery_id'];
            if ($service_id) {
                $statusRepository->createServiceLog($service_id, $orderStatus);
            } else {
                $statusRepository->createAdminLog($orderStatus);
            }
        }
    }

    /**
     *  发货订单操作
     * @param $id
     * @param $data
     * @return mixed
     * @author Qinii
     * @day 7/26/21
     */
    public function delivery($order, $merId, $data, $service_id = 0)
    {
        $data['status'] = 1;
        //订单记录
        $statusRepository = app()->make(StoreOrderStatusRepository::class);
        switch ($data['delivery_type']) {
            case 1:
                $exprss = app()->make(ExpressRepository::class)->getWhere(['code' => $data['delivery_name']]);
                if (!$exprss) throw new ValidateException('快递公司不存在');
                $data['delivery_name'] = $exprss['name'];
                $change_type = $statusRepository::ORDER_DELIVERY_COURIER;
                $change_message = '订单已配送【快递名称】:' . $exprss['name'] . '; 【快递单号】：' . $data['delivery_id'];
                $temp_code = 'DELIVER_GOODS_CODE';
                break;
            case 2:
                if (!preg_match("/^1[3456789]{1}\d{9}$/", $data['delivery_id'])) throw new ValidateException('手机号格式错误');
                $change_type = $statusRepository::ORDER_DELIVERY_SELF;
                $change_message = '订单已配送【送货人姓名】:' . $data['delivery_name'] . '; 【手机号】：' . $data['delivery_id'];
                $temp_code = 'ORDER_DELIVER_SUCCESS';
                break;
            case 3:
                $change_type = $statusRepository::ORDER_DELIVERY_NOTHING;
                $change_message = '订单已配送【虚拟发货】';
                $data['status'] = 2;
                $data['verify_time'] = date('Y-m-d H:i:s', time());
                break;
            case 4:
                $exprss = app()->make(ExpressRepository::class)->getWhere(['code' => $data['delivery_name']]);
                if (!$exprss) throw new ValidateException('快递公司不存在');
                $data['delivery_name'] = $exprss['name'];
                $change_type = $statusRepository::ORDER_DELIVERY_COURIER;
                $change_message = '订单已配送【快递名称】:' . $exprss['name'] . '; 【快递单号】：' . $data['delivery_id'];
                $temp_code = 'DELIVER_GOODS_CODE';
                break;
        }

        event('order.delivery.before', compact('order', 'data'));
        $this->dao->update($order->order_id, $data);

        $orderStatus = [
            'order_id' => $order->order_id,
            'order_sn' => $order->order_sn,
            'type' => $statusRepository::TYPE_ORDER,
            'change_message' => $change_message,
            'change_type' => $change_type,
        ];
        if ($service_id) {
            $statusRepository->createServiceLog($service_id, $orderStatus);
        } else {
            $statusRepository->createAdminLog($orderStatus);
        }

        //虚拟发货后用户直接确认收获
        if (in_array($data['status'], [2, 3])) {
            $user = app()->make(UserRepository::class)->get($order['uid']);
            //订单记录
            $this->takeAfter($order, $user);
            $orderStatus = [
                'order_id' => $order->order_id,
                'order_sn' => $order->order_sn,
                'type' => $statusRepository::TYPE_ORDER,
                'change_message' => '虚拟发货后',
                'change_type' => $statusRepository::ORDER_STATUS_TAKE,
            ];
            $statusRepository->createSysLog($orderStatus);

        }
        if (isset($temp_code)) Queue::push(SendSmsJob::class, ['tempId' => $temp_code, 'id' => $order->order_id]);

        // 小程序发货管理
        // 使用$merId查询商户信息，获取sub_mchid
        $merchant = app()->make(MerchantRepository::class)->get($merId);
        $sub_mchid = $merchant ? $merchant->sub_mchid : '';
        event('mini_order_shipping', ['product', $order, $data['delivery_type'], $data['delivery_id'], $data['delivery_name'], $sub_mchid]);

        event('order.delivery', compact('order', 'data'));
        return $data;
    }

    /**
     *  同城配送
     * @param int $id
     * @param int $merId
     * @param array $data
     * @author Qinii
     * @day 2/16/22
     */
    public function cityDelivery($order, int $merId, array $data, $service_id = 0)
    {
        $make = app()->make(DeliveryOrderRepository::class);
        if ($order['is_virtual']) {
            Log::info('同城配送创建订单，虚拟商品只能虚拟发货');
            return false;
        }
        $id = $order->order_id;
        $res = $make->create($id, $merId, $data, $order);
        if (!$res) {
            return false;
        }
        //订单记录
        $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
        $merchantTakeInfo = $order['merchant_take_info'] ?? [];
        $merchantTakeInfo[$merId]['sync_status'] = 1;
        $merchantTakeInfo[$merId]['sync_desc'] = 'success';
        $this->dao->update($id, ['delivery_type' => 5, 'status' => 1, 'merchant_take_info' => json_encode($merchantTakeInfo)]);

        $orderStatus = [
            'order_id' => $id,
            'order_sn' => $order->order_sn,
            'type' => $storeOrderStatusRepository::TYPE_ORDER,
            'change_message' => '订单配送【同城配送】',
            'change_type' => $storeOrderStatusRepository::ORDER_DELIVERY_SELF,
        ];
        if ($service_id) {
            $storeOrderStatusRepository->createServiceLog($service_id, $orderStatus);
        } else {
            $storeOrderStatusRepository->createAdminLog($orderStatus);
        }

        // 小程序发货管理
        event('mini_order_shipping', ['product', $order, OrderStatus::DELIVER_TYPE_SAME_CITY, '', '']);

        Queue::push(SendSmsJob::class, ['tempId' => 'ORDER_DELIVER_SUCCESS', 'id' => $id]);

        return true;
    }

    /**
     *  打印电子面单，组合参数
     * @param int $id
     * @param int $merId
     * @param array $data
     * @return mixed
     * @author Qinii
     * @day 7/26/21
     */
    public function dump($order, int $merId, array $data, $service_id = 0)
    {
        $make = app()->make(MerchantRepository::class);
        $data['com'] = $data['com'] ?? $data['delivery_name'];
        $data = $this->orderDumpInfo($order, $data, $merId);
        $num = $make->checkCrmebNum($merId, 'dump');
        $service = app()->make(CrmebServeServices::class, [$num ? 0 : $merId]);
        $result = $service->express()->dump($merId, $data);
        Log::info('电子面单返回数据:'.var_export($result, true));
        if (!merchantConfig($merId, 'mer_dump_type') && !isset($result['kuaidinum']))
            throw new ValidateException('打印失败');
        $delivery = [
            'delivery_type' => 4,
            'delivery_name' => $data['com'],
            'delivery_id' => $result['kuaidinum'],
            'remark' => $data['remark'] ?? '',
            'kuaidi_label' => $result['label'] ?? '',
            'task_id' => $result['taskId'] ?? '',
        ];
        $dump = [
            'delivery_name' => $delivery['delivery_name'],
            'delivery_id' => $delivery['delivery_id'],
            'from_name' => $data['from_name'],
            'order_id' => $data['order_id'],
            'to_name' => $data['to_name'],
        ];
        Db::transaction(function () use ($merId, $order, $delivery, $make, $dump, $service_id, $num) {
            $this->delivery($order, $merId, $delivery, $service_id);
            $arr = ['type' => 'mer_dump', 'num' => -1, 'message' => '电子面单', 'info' => $dump];
            if ($num) app()->make(ProductCopyRepository::class)->add($arr, $merId);
        });
        return $result;
    }

    /**
     * 重新打印电子面单
     * @param int $id
     * @param int $merId
     * @return mixed
     * @author Qinii
     * @day 7/26/21
     */
    public function repeat_dump(int $id, int $merId)
    {
        $order = $this->dao->get($id);
        if (!$order) throw new ValidateException('订单不存在');
        if ($order['mer_id'] != $merId)
            throw new ValidateException('订单不存在');
        if (merchantConfig($merId, 'mer_dump_type')) {
            $num = app()->make(MerchantRepository::class)->checkCrmebNum($merId, 'dump');
            $service = app()->make(CrmebServeServices::class, [$num ? 0 : $merId]);
            $res = $service->express()->repeat_dump($order['task_id'] ?? '', merchantConfig($merId, 'mer_config_siid'));
            return $res;
        } else {
            return ['label' => $order['kuaidi_label']];
        }
    }

    /**
     * 根据ID获取单条数据，包括关联信息。
     *
     * 此方法用于根据给定的ID和可选的商户ID获取一条数据记录，并包括其相关的多个实体信息。
     * - 主要数据实体：根据ID获取。
     * - 关联数据实体：订单产品、用户信息、退款订单、最终订单、推广信息等。
     * 如果指定了商户ID，则还会添加特定于商户的条件以确保数据的准确性。
     *
     * @param int $id 主要数据实体的ID。
     * @param int|null $merId 商户ID，可选参数，用于限定数据所属的商户。
     * @return array 返回包含主要数据实体及其关联数据实体的信息。
     * @throws ValidateException 如果找不到数据，则抛出异常。
     */
    public function getOne($id, ?int $merId)
    {
        // 初始化条件，用于根据主键ID查询数据
        $where = [$this->getPk() => $id];

        // 如果提供了商户ID，则添加商户ID和删除状态的条件
        if ($merId) {
            $where['mer_id'] = $merId;
            $where['is_system_del'] = 0;
        }

        // 根据条件查询数据，包括多个关联实体的信息
        $res = $this->dao->getWhere($where, '*', [
            'orderProduct',
            'staffs',
            'take',
            'user' => function ($query) {
                // 查询用户的特定字段
                $query->field('uid,real_name,nickname,is_svip,svip_endtime,phone');
            },
            'refundOrder' => function ($query) {
                // 查询状态为3的退款订单的特定字段
                $query->field('refund_order_id,order_id,extension_one,extension_two,refund_price,integral,status')
                    //->where('status', 3)
                ;
            },
            'finalOrder',
            'TopSpread' => function ($query) {
                // 查询推广人的特定字段
                $query->field('uid,nickname,avatar');
            },
            'spread' => function ($query) {
                // 查询被推广人的特定字段
                $query->field('uid,nickname,avatar');
            },
            'merchant' => function ($query) {
                // 查询商户的特定字段，并包含关联的商户类别和类型名称
                $query->field('mer_id,mer_name,mer_state,mer_avatar,delivery_way,commission_rate,category_id,type_id')
                    ->with(['merchantCategory', 'merchantType']);
            },
            'deliveryOrder' => function ($query) {
                $query->with(['deliveryService' => function ($query) {
                    $query->field('service_id,name,phone,avatar');
                }]);
            }
        ]);

        // 如果查询结果为空，则抛出异常
        if (!$res) throw new ValidateException('数据不存在');

        // 将查询结果中的integral字段转换为整型
        $res['integral'] = (int)$res['integral'];

        // 返回包含附加信息的查询结果
        return $res->append(['refund_extension_one', 'refund_extension_two']);
    }

    /**
     * 根据条件获取订单状态
     *
     * 本函数用于根据给定的条件和分页信息，查询特定类型的订单状态。
     * 它封装了对订单状态仓库的调用，使得外部不需要直接与仓库交互，提高了代码的封装性和易用性。
     *
     * @param array $where 查询条件，用于筛选订单状态。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页的数量，用于分页查询。
     * @return mixed 返回根据条件查询到的订单状态列表。
     */
    public function getOrderStatus($where, $page, $limit)
    {
        // 设置查询类型为订单
        $where['type'] = StoreOrderStatusRepository::TYPE_ORDER;

        // 通过依赖注入的方式获取订单状态仓库实例，并执行查询操作
        return app()->make(StoreOrderStatusRepository::class)->search($where, $page, $limit);
    }

    /**
     * 创建订单备注表单
     *
     * 本函数用于生成一个用于添加或编辑订单备注的表单。通过传入订单ID，获取当前订单的备注信息，
     * 并构建一个表单来允许用户输入或修改订单的备注内容。
     *
     * @param int $id 订单ID，用于获取订单备注信息及构建表单的提交URL。
     * @return Form|string
     */
    public function remarkForm($id)
    {
        // 通过订单ID获取订单备注信息
        $data = $this->dao->get($id);

        // 构建表单提交的URL
        $formActionUrl = Route::buildUrl('merchantStoreOrderRemark', ['id' => $id])->build();

        // 创建表单对象
        $form = Elm::createForm($formActionUrl);

        // 设置表单的验证规则
        $form->setRule([
            // 创建文本输入框用于填写订单备注，设置为必填项
            Elm::text('remark', '备注：', $data['remark'])->placeholder('请输入备注')->required('请输入备注'),
        ]);

        // 设置表单标题
        $form->setTitle('订单备注');

        // 返回生成的表单HTML代码
        return $form;
    }
    /**
     * 修改收货信息表单
     *
     * @param int $id
     * @return Form|string
     */
    public function collectCargoForm($id)
    {
        $data = $this->dao->get($id);
        // 构建表单提交的URL
        $formActionUrl = Route::buildUrl('merchantStoreOrderCollectCargo', ['id' => $id])->build();
        // 创建表单对象
        $form = Elm::createForm($formActionUrl);
        // 设置表单的验证规则
        $form->setRule([
            Elm::text('real_name', '收货人：', $data['real_name'])->placeholder('请输入收货人')->required('请输入收货人'),
            Elm::text('user_phone', '收货电话：', $data['user_phone'])->placeholder('请输入收货电话')->required('请输入收货电话'),
            Elm::text('user_address', '收货地址：', $data['user_address'])->placeholder('请输入收货地址')->required('请输入收货地址'),
        ]);
        // 设置表单标题
        $form->setTitle('修改收货信息');
        // 返回生成的表单HTML代码
        return $form;
    }

    /**
     * 创建管理员备注表单
     *
     * 该方法用于生成一个表单，允许管理员对特定订单进行备注。表单提交的URL是根据订单ID动态生成的。
     * 表单中包含一个文本输入字段，用于输入管理员的备注信息。表单标题为“订单备注”。
     *
     * @param int $id 订单ID，用于获取订单当前的管理员备注信息，并构建表单提交的URL。
     * @return string 返回生成的表单HTML代码。
     */
    public function adminMarkForm($id)
    {
        // 通过订单ID获取订单信息，主要用于获取当前的管理员备注内容。
        $data = $this->dao->get($id);

        // 创建表单对象，并设置表单提交的URL。
        $form = Elm::createForm(Route::buildUrl('systemMerchantOrderMark', ['id' => $id])->build());

        // 设置表单的验证规则，包括一个文本输入字段用于管理员备注，该字段为必填项。
        $form->setRule([
            Elm::text('admin_mark', '备注：', $data['admin_mark'])->placeholder('请输入备注')->required('请输入备注'),
        ]);

        // 设置表单的标题。
        return $form->setTitle('订单备注');
    }

    /**
     * 创建积分订单备注表单
     *
     * 本函数用于生成一个表单，该表单用于用户输入对积分订单的备注信息。
     * 表单提交的URL是通过路由生成的，确保了URL的正确性和安全性。
     * 使用Element UI的表单组件来构建表单，提高了表单的用户体验和一致性。
     *
     * @param int $id 积分订单的ID，用于获取订单的当前备注信息。
     * @return string 返回生成的表单HTML代码。
     */
    public function pointsMarkForm($id)
    {
        // 通过订单ID获取订单信息，主要是为了获取当前的备注信息。
        $data = $this->dao->get($id);

        // 构建表单提交的URL，确保表单提交到正确的处理程序。
        $formUrl = Route::buildUrl('pointsOrderMark', ['id' => $id])->build();

        // 创建表单对象，并设置表单提交的URL。
        $form = Elm::createForm($formUrl);

        // 设置表单的验证规则，这里主要是一个文本输入框用于用户输入备注信息。
        // 备注信息是必需的，以确保用户不会遗漏重要的备注。
        $form->setRule([
            Elm::text('remark', '备注：', $data['remark'])
                ->placeholder('请输入备注')
                ->required('请输入备注'),
        ]);

        // 设置表单的标题，让用户清楚这个表单是用于做什么的。
        $form->setTitle('订单备注');

        // 返回生成的表单HTML代码，供前端展示和使用。
        return $form;
    }


    /**
     *  平台每个商户的订单列表
     * @param $where
     * @param $page
     * @param $limit
     * @return array
     * @author Qinii
     * @day 2020-06-15
     */
    public function adminMerGetList($where, $page, $limit)
    {
        $where['paid'] = 1;
        $query = $this->dao->search($where, null);
        $count = $query->count();
        $list = $query->with([
            'orderProduct',
            'merchant' => function ($query) {
                $query->field('mer_id,mer_name,is_trader');
            },
            'groupOrder' => function ($query) {
                $query->field('group_order_id,group_order_sn');
            },
            'finalOrder',
            'user' => function ($query) {
                $query->field('uid,nickname,avatar');
            },
        ])->page($page, $limit)->select()->append(['refund_extension_one', 'refund_extension_two']);

        return compact('count', 'list');
    }

    /**
     * 根据条件获取商户结算订单列表
     *
     * @param array $where 查询条件
     * @param int $page 分页页码
     * @param int $limit 分页限制条数
     * @return array 返回包含总数和列表的数据数组
     */
    public function reconList($where, $page, $limit)
    {
        // 根据条件获取订单ID列表
        $ids = app()->make(MerchantReconciliationOrderRepository::class)->getIds($where);

        // 构建查询订单的查询构建器
        $query = $this->dao->search([], null)->whereIn('order_id', $ids);

        // 计算符合条件的订单总数
        $count = $query->count();

        // 分页查询订单，并处理每条订单数据
        $list = $query->with(['orderProduct'])->page($page, $limit)->select()->each(function ($item) {
            // 计算订单的佣金比例
            //(实付金额 - 一级佣金 - 二级佣金) * 抽成
            $commission_rate = ($item['commission_rate'] / 100);
            // 计算订单的扩展费用（一级佣金 + 二级佣金）
            // 佣金
            $_order_extension = bcadd($item['extension_one'], $item['extension_two'], 3);
            // 计算订单的手续费
            // 手续费 =  (实付金额 - 一级佣金 - 二级佣金) * 比例
            $_order_rate = bcmul(bcsub($item['pay_price'], $_order_extension, 3), $commission_rate, 3);
            // 对扩展费用和手续费四舍五入到小数点后两位
            $item['order_extension'] = round($_order_extension, 2);
            $item['order_rate'] = round($_order_rate, 2);
            return $item;
        });

        // 返回订单总数和列表的数组
        return compact('count', 'list');
    }

    /**
     * 获取商家列表
     *
     * 根据给定的条件和分页信息，查询商家的相关信息，包括订单产品、商家信息、核验服务人员、最终订单、拼团信息以及推广信息。
     * 特别地，对退款状态进行了计算和附加，以便在返回的商家列表中直接显示每个商家的退款状态汇总。
     *
     * @param array $where 查询条件，包含商家的状态和其他可能的过滤条件。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页的记录数，用于分页查询。
     * @return array 返回包含商家数量和商家列表的数组。
     */
    public function merchantGetList(array $where, $page, $limit)
    {
        // 获取查询条件中的商家状态，后续用于构造查询条件
        $status = $where['status'];
        // 移除查询条件中的状态字段，避免直接在where中使用可能导致的混淆
        unset($where['status']);
        // 构造查询，根据商家状态添加相应的订单状态查询条件，并加载关联数据
        $query = $this->dao->search($where)
          //  ->where('is_virtual','<>',4)
            ->where($this->getOrderType($status))
            ->with([
                'orderProduct',
                'merchant' => function ($query) {
                    // 加载商家的简要信息
                    $query->field('mer_id,mer_name');
                },
                'verifyService' => function ($query) {
                    // 加载核验服务人员的简要信息
                    $query->field('service_id,nickname');
                },
                'finalOrder',
                'groupUser.groupBuying',
                'TopSpread' => function ($query) {
                    // 加载顶级推广人的简要信息
                    $query->field('uid,nickname,avatar');
                },
                'spread' => function ($query) {
                    // 加载推广人的简要信息
                    $query->field('uid,nickname,avatar');
                },
            ]);

        // 计算满足条件的商家总数
        $count = $query->count();

        // 进行分页查询，并附加额外的字段，同时对每个商家的退款状态进行计算和附加
        $list = $query->page($page, $limit)
            ->select()
            ->append(['refund_extension_one', 'refund_extension_two'])
            ->each(function ($item) {
                // 初始化商家的退款状态
                $refunding = 0;
                // 如果商家有订单产品，则计算其退款状态
                if ($item['orderProduct']) {
                    // 获取所有订单产品的退款状态，去除重复
                    $is_refund = array_unique(array_column($item['orderProduct']->toArray(), 'is_refund'));
                    // 判断是否存在退款中的订单产品
                    if (in_array(1, $is_refund)) {
                        $refunding = 1; // 退款中
                    } else if (in_array(3, $is_refund)) {
                        // 判断是否存在已完成退款的订单产品
                        if (in_array(2, $is_refund)) {
                            $refunding = 2; // 部分退款
                        } else {
                            $refunding = 3; // 全部退款
                        }
                    }
                }
                // 附加商家的退款状态到商家信息中
                $item['refunding'] = $refunding;
            });

        $cityTakeFail = [];
        foreach ($list as $item) {
            if($item['order_type'] == 2 && isset($item['take']) && $item['take']['type']) {
                $merchantTakeInfo = $item['merchant_take_info'];
                $sync_status = (isset($merchantTakeInfo[$where['mer_id']]['sync_status']) && $merchantTakeInfo[$where['mer_id']]['sync_status'] == -1);

                if($sync_status && $item->status == 0) {
                    $cityTakeFail[] = $item['order_id'];
                }
            }
        }

        // 返回商家总数和商家列表
        return compact('count', 'list', 'cityTakeFail');
    }

    /**
     * 平台总的订单列表
     * @param array $where
     * @param $page
     * @param $limit
     * @return array
     * @author Qinii
     * @day 2020-06-15
     */
    public function adminGetList(array $where, $page, $limit)
    {
        $status = $where['status'];
        unset($where['status']);
        $query = $this->dao->search($where, null)->where($this->getOrderType($status))
            ->with([
                'orderProduct',
                'merchant' => function ($query) {
                    return $query->field('mer_id,mer_name,is_trader');
                },
                'verifyService' => function ($query) {
                    return $query->field('service_id,nickname');
                },
                'groupOrder' => function ($query) {
                    $query->field('group_order_id,group_order_sn');
                },
                'finalOrder',
                'groupUser.groupBuying',
                'TopSpread' => function ($query) {
                    $query->field('uid,nickname,avatar');
                },
                'spread' => function ($query) {
                    $query->field('uid,nickname,avatar');
                },
                'user' => function ($query) {
                    $query->field('uid,nickname,avatar');
                },
            ]);
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->append(['refund_extension_one', 'refund_extension_two']);

        return compact('count', 'list');
    }

    /**
     * 根据条件获取统计信息
     * @param array $where 查询条件
     * @param string $status 订单状态
     * @return array 统计数据数组
     */
    public function getStat(array $where, $status)
    {
        // 移除查询条件中的状态字段
        unset($where['status']);

        // 创建订单退款仓库实例
        $make = app()->make(StoreRefundOrderRepository::class);
        // 创建预售订单仓库实例
        $presellOrderRepository = app()->make(PresellOrderRepository::class);

        // 根据条件查询订单ID，并根据订单类型和状态统计退款金额
        // 退款订单id
        $orderId = $this->dao->search($where)->where($this->getOrderType($status))->column('order_id');
        // 退款金额
        $orderRefund = $make->refundPirceByOrder($orderId);

        // 统计已支付订单数量
        // 实际支付订单数量
        $all = $this->dao->search($where)->where($this->getOrderType($status))->where('paid', 1)->count();
        // 统计实际支付金额
        // 实际支付订单金额
        $countQuery = $this->dao->search($where)->where($this->getOrderType($status))->where('paid', 1);
        $countPay1 = $countQuery->sum('StoreOrder.pay_price');
        // 优化：这里原先可能打算合并预售订单的支付金额，但注释掉了相关代码
        $countPay = $countPay1;

        // 统计余额支付金额
        // 余额支付
        $banclQuery = $this->dao->search(array_merge($where, ['paid' => 1, 'pay_type' => 0]))->where($this->getOrderType($status));
        $banclOrderId = $banclQuery->column('order_id');
        $banclPay1 = $banclQuery->sum('StoreOrder.pay_price');
        $banclPay2 = $presellOrderRepository->search(['pay_type' => [0], 'paid' => 1, 'order_ids' => $banclOrderId])->sum('pay_price');
        $banclPay = bcadd($banclPay1, $banclPay2, 2);

        // 统计微信支付金额
        // 微信金额
        $wechatQuery = $this->dao->search($where)->where($this->getOrderType($status))->where('paid', 1)->where('pay_type', 'in', [1, 2, 3, 6]);
        $wechatOrderId = $wechatQuery->column('order_id');
        $wechatPay1 = $wechatQuery->sum('StoreOrder.pay_price');
        $wechatPay2 = $presellOrderRepository->search(['pay_type' => [1, 2, 3, 6], 'paid' => 1, 'order_ids' => $wechatOrderId])->sum('pay_price');
        $wechatPay = bcadd($wechatPay1, $wechatPay2, 2);

        // 统计支付宝支付金额
        // 支付宝金额
        $aliQuery = $this->dao->search($where)->where($this->getOrderType($status))->where('paid', 1)->where('pay_type', 'in', [4, 5]);
        $aliOrderId = $aliQuery->column('order_id');
        $aliPay1 = $aliQuery->sum('StoreOrder.pay_price');
        $aliPay2 = $presellOrderRepository->search(['pay_type' => [4, 5], 'paid' => 1, 'order_ids' => $aliOrderId])->sum('pay_price');
        $aliPay = bcadd($aliPay1, $aliPay2, 2);

        // 构建并返回统计数据数组
        $stat = [
            [
                'className' => 'el-icon-s-goods',
                'count' => $all,
                'field' => '件',
                'name' => '已支付订单数量'
            ],
            [
                'className' => 'el-icon-s-order',
                'count' => (float)$countPay,
                'field' => '元',
                'name' => '实际支付金额'
            ],
            [
                'className' => 'el-icon-s-cooperation',
                'count' => (float)$orderRefund,
                'field' => '元',
                'name' => '已退款金额'
            ],
            [
                'className' => 'el-icon-s-cooperation',
                'count' => (float)$wechatPay,
                'field' => '元',
                'name' => '微信支付金额'
            ],
            [
                'className' => 'el-icon-s-finance',
                'count' => (float)$banclPay,
                'field' => '元',
                'name' => '余额支付金额'
            ],
            [
                'className' => 'el-icon-s-cooperation',
                'count' => (float)$aliPay,
                'field' => '元',
                'name' => '支付宝支付金额'
            ],
        ];
        return $stat;
    }

    /**
     * 根据条件获取订单列表
     *
     * @param array $where 查询条件
     * @param int $page 分页页码
     * @param int $limit 每页数据条数
     * @return array 包含订单总数和订单列表的数组
     */
    public function getList(array $where, $page, $limit)
    {
        // 初始化查询
        $query = $this->dao->search($where)->where('StoreOrder.is_del', 0);
        // 计算订单总数
        $count = $query->count();

        // 执行查询，带关联数据
        $list = $query->with([
            'take',
            'orderProduct',
            'presellOrder',
            'merchant' => function ($query) {
                // 只获取商家的id和名称
                return $query->field('mer_id,mer_name');
            },
            'community',
            'receipt' => function ($query) {
                // 只获取订单收货信息的id和订单id
                return $query->field('order_id,order_receipt_id');
            },
            'deliveryOrder' => function ($query) {
                return $query->with(['deliveryService' => function ($query) {
                    return $query->field('service_id,name,phone,avatar');
                }]);
            }
        ])->page($page, $limit)
            ->order('pay_time DESC')
            ->append(['refund_status', 'open_receipt'], true)
            ->select();

        // 处理预售价和已支付价格
        foreach ($list as &$order) {
            if ($order->activity_type == 2) {
                if ($order->presellOrder) {
                    $order->presellOrder->append(['activeStatus']);
                    // 计算预售价
                    $order->presell_price = bcadd($order->pay_price, $order->presellOrder->pay_price, 2);
                } else {
                    $order->presell_price = $order->pay_price;
                }
            }

            if ($order->is_virtual == 4) {
                $order->merchant['checkin'] = merchantConfig($order->mer_id,['enable_assigned', 'enable_checkin', 'checkin_radius', 'enable_trace', 'trace_form_id','enable_tostore_assigned','checkin_take_photo']);
            }
            // 计算领取数量
            $order->takeOrderCount = count($order['takeOrderList']);
            // 删除不需要返回的字段
            unset($order['takeOrderList']);
        }

        // 返回订单总数和列表
        return compact('count', 'list');
    }

    /**
     * 获取用户列表
     *
     * 根据指定的用户ID和分页信息，查询已支付订单的用户列表。
     * 此函数用于实现分页查询，每次返回指定页码的用户列表及其总数。
     *
     * @param int $uid 用户ID，用于指定查询哪个用户的订单。
     * @param int $page 当前页码，用于指定要返回的页码。
     * @param int $limit 每页数量，用于指定每页返回的用户数量。
     * @return array 返回包含用户列表和总数的数组。
     */
    public function userList($uid, $page, $limit)
    {
        // 根据用户ID和支付状态为1查询用户订单
        $query = $this->dao->search([
            'uid' => $uid,
            'paid' => 1
        ]);

        // 统计查询结果的总数
        $count = $query->count();

        // 根据当前页码和每页数量进行分页查询，并返回用户列表
        $list = $query->page($page, $limit)->select();

        // 将总数和用户列表打包成数组返回
        return compact('count', 'list');
    }


    /**
     * 获取用户订单列表
     *
     * 根据用户ID、商家ID和分页信息，查询已支付的订单，并对预售价订单进行特殊处理。
     * 主要用于商家后台展示用户的订单情况，特别是涉及到预售价的订单计算。
     *
     * @param int $uid 用户ID
     * @param int $merId 商家ID
     * @param int $page 当前页码
     * @param int $limit 每页记录数
     * @return array 返回包含订单总数和订单列表的数组
     */
    public function userMerList($uid, $merId, $page, $limit)
    {
        // 根据用户ID、商家ID和支付状态查询订单
        $query = $this->dao->search([
            'uid' => $uid,
            'mer_id' => $merId,
            'paid' => 1
        ]);

        // 计算订单总数
        $count = $query->count();

        // 带上预售价信息，分页查询订单列表
        $list = $query->with(['presellOrder'])->page($page, $limit)->select();

        // 遍历订单列表，对预售价订单进行总价计算
        foreach ($list as $order) {
            // 判断订单是否为预售价订单且状态符合特定条件
            if ($order->activity_type == 2 && $order->status >= 0 && $order->status < 10 && $order->presellOrder) {
                // 计算预售价订单的总价，保留两位小数
                $order->pay_price = bcadd($order->pay_price, $order->presellOrder->pay_price, 2);
            }
        }

        // 返回订单总数和列表信息
        return compact('count', 'list');
    }


    /**
     * 根据订单ID查询快递信息
     *
     * 本函数用于通过订单ID获取相应的快递信息。它首先验证订单是否存在以及订单是否属于指定的商家，
     * 然后检查订单的配送类型是否支持查询快递信息，最后返回查询到的快递详情。
     *
     * @param int $orderId 订单ID，用于查询订单信息。
     * @param int|null $merId 商家ID，用于验证订单是否属于该商家。如果为null，则不进行商家验证。
     * @return string 返回查询到的快递信息。
     * @throws ValidateException 如果订单不存在或者订单状态不支持查询快递信息，则抛出异常。
     */
    public function express(int $orderId, ?int $merId)
    {
        // 根据订单ID查询订单信息
        $order = $this->dao->get($orderId);

        // 如果指定了商家ID且订单不属于该商家，则抛出异常
        if ($merId && $order['mer_id'] != $merId) {
            throw new ValidateException('订单信息不存在');
        }

        // 检查订单的配送类型是否支持查询快递信息，如果不支持，则抛出异常
        if (!in_array($order['delivery_type'], [1, 4])) {
            throw new ValidateException('订单状态错误');
        }
        // 调用快递服务类查询并返回快递信息
        return ExpressService::express($order->delivery_id, $order->delivery_name, $order->user_phone);
    }

    /**
     * 检查打印机配置
     *
     * 本函数用于验证商家的打印机配置是否完整且正确。它首先检查打印功能是否已开启，然后获取并验证相关配置参数，
     * 包括客户端ID、API密钥、合作伙伴ID和终端号码。如果任何一项配置缺失或无效，将抛出一个验证异常。
     *
     * @param int $merId 商家ID，用于获取商家的打印配置
     * @return array 返回包含打印机配置的数组，包括客户端ID、API密钥、合作伙伴ID和终端号码
     * @throws ValidateException 如果打印功能未开启或打印机配置不完整或无效，抛出此异常
     */
    public function checkPrinterConfig(int $merId)
    {
        // 检查打印功能是否已开启
        if (!merchantConfig($merId, 'printing_status'))
            throw new ValidateException('打印功能未开启');

        // 组装打印机配置数组
        $config = [
            'clientId' => merchantConfig($merId, 'printing_client_id'),
            'apiKey' => merchantConfig($merId, 'printing_api_key'),
            'partner' => merchantConfig($merId, 'develop_id'),
            'terminal' => merchantConfig($merId, 'terminal_number')
        ];

        // 验证打印机配置是否完整
        if (!$config['clientId'] || !$config['apiKey'] || !$config['partner'] || !$config['terminal'])
            throw new ValidateException('打印机配置错误');

        // 返回验证通过的打印机配置
        return $config;
    }

    /**
     * 打印机 -- 暂无使用
     * @param int $id
     * @param int $merId
     * @return bool|mixed|string
     * @author Qinii
     * @day 2020-07-30
     */
    public function printer(int $id, int $merId)
    {
        $order = $this->dao->getWhere(['order_id' => $id], '*', ['orderProduct', 'merchant' => function ($query) {
            $query->field('mer_id,mer_name');
        }]);
        foreach ($order['orderProduct'] as $item) {
            $product[] = [
                'store_name' => $item['cart_info']['product']['store_name'] . '【' . $item['cart_info']['productAttr']['sku'] . '】',
                'product_num' => $item['product_num'],
                'price' => bcdiv($item['product_price'], $item['product_num'], 2),
                'product_price' => $item['product_price'],
            ];
        }
        $data = [
            'order_sn' => $order['order_sn'],
            'pay_time' => $order['pay_time'],
            'real_name' => $order['real_name'],
            'user_phone' => $order['user_phone'],
            'user_address' => $order['user_address'],
            'total_price' => $order['total_price'],
            'coupon_price' => $order['coupon_price'],
            'pay_price' => $order['pay_price'],
            'total_postage' => $order['total_postage'],
            'pay_postage' => $order['pay_postage'],
            'mark' => $order['mark'],
        ];
        $config = $this->checkPrinterConfig($merId);
        $printer = new Printer('yi_lian_yun', $config);
        event('order.print.before', compact('order'));

        try {
            $res = $printer->setPrinterContent([
                'name' => $order['merchant']['mer_name'],
                'orderInfo' => $data,
                'product' => $product
            ])->startPrinter();
        } catch (Exception $exception) {
            Log::error('打印失败：' . $exception->getMessage());
        }
        event('order.print', compact('order', 'res'));
        return $res;
    }

    /**
     * 批量打印订单
     * 该方法用于根据订单ID和商家ID批量获取订单信息并进行打印。
     * @param int $id 订单ID
     * @param int $merId 商家ID
     */
    public function batchPrinter(int $id, int $merId, $print_type = 1)
    {
        $config = merchantConfig($merId, ['printing_auto_status','printing_status']);
        if (!$config['printing_status']) throw new ValidateException('打印功能未开启');
        // 根据订单ID获取订单及其产品和商家信息
        $order = $this->dao->getWhere(['order_id' => $id], '*', ['orderProduct', 'merchant' => function ($query) {
            // 仅获取商家ID和名称
            $query->field('mer_id,mer_name');
        }]);

        // 遍历订单产品，组装产品信息
        foreach ($order['orderProduct'] as $item) {
            $price = $item['cart_info']['productAttr']['show_svip_price'] ? $item['cart_info']['productAttr']['org_price'] : $item['cart_info']['productAttr']['price'];
            $product[] = [
                'store_name' => $item['cart_info']['product']['store_name'],
                'suk' => $item['cart_info']['productAttr']['sku'],
                'cart_num' => $item['product_num'],
                'price' => $price,
                'total_price' => bcmul($price, $item['product_num'], 2),
                'bar_code' => $item['cart_info']['productAttr']['bar_code'] ?? '',
            ];
        }

        // 触发订单打印前的事件
        event('order.print.before', compact('order'));
        $res = null;
        try {
            $res = app()->make(StorePrinterRepository::class)->startPrint($merId, $order, $product, $print_type);
            Log::info('打印结果:'.var_export([$res, true]));
        } catch (\think\Exception $exception) {
            // 打印失败时记录日志
            Log::error('打印失败：' . $exception->getMessage());
        }
        // 触发订单打印事件
        event('order.print', compact('order', 'res'));
    }


    /**
     * 核验并核销订单。
     *
     * 本函数用于验证订单的存在性、支付状态，并对指定的订单进行核销操作。
     * 核销操作包括分割订单、更新订单状态、记录订单变更，并触发相关的事件。
     *
     * @param int $id 订单ID
     * @param int $merId 商家ID
     * @param array $data 包含验证码和待核销商品数量的数据
     * @param int $serviceId 服务人员ID，默认为0表示管理员操作
     * @throws ValidateException 如果订单不存在、未支付或已全部核销，则抛出验证异常
     */
    public function verifyOrder($order, array $data, $serviceId = 0)
    {
        // 构建待核销商品的数量映射
        foreach ($data['data'] as $v) {
            $splitData[$v['id']] = $v['num'];
        }
        // 调用订单分割函数进行订单核销操作，并更新订单对象
        $spl = app()->make(StoreOrderSplitRepository::class)->splitOrder($order, $splitData, $serviceId, 1);
        if ($spl) $order = $spl;

        // 更新订单状态为已核销，并设置核销时间和服务人员ID
        $order->status = 2;
        $order->verify_time = date('Y-m-d H:i:s');
        $order->verify_service_id = $serviceId;

        // 触发订单核销前的事件
        event('order.verify.before', compact('order'));

        // 实例化订单状态仓库，用于后续订单状态的变更记录
        $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
        // 使用事务处理订单状态的更新和订单变更日志的记录
        Db::transaction(function () use ($order, $storeOrderStatusRepository, $serviceId) {
            // 执行订单核销后的处理逻辑
            $this->takeAfter($order, $order->user);
            // 保存更新后的订单信息
            $order->save();
            // 构建订单状态变更信息
            $orderStatus = [
                'order_id' => $order->order_id,
                'order_sn' => $order->order_sn,
                'type' => $storeOrderStatusRepository::TYPE_ORDER,
                'change_message' => '订单已核销',
                'change_type' => $storeOrderStatusRepository::ORDER_STATUS_TAKE,
            ];
            // 根据服务人员ID是否存在，分别记录服务人员日志或管理员日志
            if ($serviceId) {
                $storeOrderStatusRepository->createServiceLog($serviceId, $orderStatus);
            } else {
                $storeOrderStatusRepository->createAdminLog($orderStatus);
            }
        });

        // 触发订单核销完成后的事件
        event('order.verify', compact('order'));
        // 触发小程序发货管理事件，用于后续的发货操作
        // 小程序发货管理
        event('mini_order_shipping', ['product', $order, OrderStatus::DELIVER_TYPE_VERIFY, '', '']);
    }

    /**
     * 生成微信二维码
     *
     * 该函数用于根据订单信息生成微信支付二维码。二维码的内容是根据订单号和特定格式生成的URL，
     * 用于在微信支付中进行扫码支付。如果二维码图片已存在，则直接返回图片URL；否则，生成新的二维码图片
     * 并保存，然后返回新图片的URL。
     *
     * @param string $orderId 订单号
     * @param object $order 订单对象，包含订单的相关信息
     * @return string 二维码图片的URL
     */
    public function wxQrcode($orderId, $order)
    {
        // 从订单对象中获取验证码和商家ID
        $verify_code = $order->verify_code;
        $mer_id = $order->mer_id;

        // 获取网站的根URL，用于构造二维码的链接
        $siteUrl = systemConfig('site_url');

        // 根据订单号、当前日期和固定字符串生成唯一的二维码文件名
        $name = md5('owx' . $orderId . date('Ymd')) . '.jpg';

        // 获取附件仓库实例，用于后续操作二维码图片
        $attachmentRepository = app()->make(AttachmentRepository::class);

        // 根据文件名查询已存在的二维码图片信息
        $imageInfo = $attachmentRepository->getWhere(['attachment_name' => $name]);
        // 检查已存在的二维码图片是否有效，如果无效则删除并置为空
        if (isset($imageInfo['attachment_src']) && strstr($imageInfo['attachment_src'], 'http') !== false && curl_file_exist($imageInfo['attachment_src']) === false) {
            $imageInfo->delete();
            $imageInfo = null;
        }

        // 如果没有找到有效的二维码图片，则生成新的二维码
        if (!$imageInfo) {
            // 构造二维码的链接，包含验证码和商家ID
            $codeUrl = set_http_type(rtrim($siteUrl, '/') . '/pages/admin/cancellate_result/index?cal_code=' . $verify_code . '&mer_id=' . $mer_id . '&is_jump=1', request()->isSsl() ? 0 : 1);

            // 生成二维码图片并获取图片路径
            $imageInfo = app()->make(QrcodeService::class)->getQRCodePath($codeUrl, $name);

            // 如果生成二维码失败，则抛出异常
            if (is_string($imageInfo)) throw new ValidateException('二维码生成失败');

            // 处理二维码图片的路径，确保路径是相对网站根目录的
            $imageInfo['dir'] = tidy_url($imageInfo['dir'], null, $siteUrl);

            // 保存二维码图片信息到附件仓库
            $attachmentRepository->create(systemConfig('upload_type') ?: 1, -2, $orderId, [
                'attachment_category_id' => 0,
                'attachment_name' => $imageInfo['name'],
                'attachment_src' => $imageInfo['dir']
            ]);

            // 新二维码的URL
            $urlCode = $imageInfo['dir'];
        } else {
            // 使用已存在的二维码图片URL
            $urlCode = $imageInfo['attachment_src'];
        }

        // 返回二维码图片的URL
        return $urlCode;
    }

    /**
     * 根据商品ID获取订单数
     * @param int $activityId
     * @author Qinii
     * @day 2020-08-05
     */
    public function seckillOrderCounut(?int $activityId, $productId = null)
    {
        if (!$activityId) return 0;
        $where = [
            'activity_id' => $activityId,
            'product_type' => 1,
//            'day' => date('Y-m-d', time())
        ];
        if ($productId) {
            $where['product_id'] = $productId;
        }
        $count = $this->dao->getTattendCount($where, null)->sum('product_num');
        $count_ = $this->dao->getSeckillRefundCount($where, 2);
        $count__ = $this->dao->getSeckillRefundCount($where, 1);
        return $count - $count_ - $count__;
    }

    /**
     * 根据商品sku获取订单数
     * @param int $productId
     * @return int
     * @author Qinii
     * @day 2020-08-05
     */
    public function seckillSkuOrderCounut(string $sku)
    {
        $where = [
            'product_sku' => $sku,
            'product_type' => 1,
            'day' => date('Y-m-d', time())
        ];
        $count = $this->dao->getTattendCount($where, null)->sum('total_num');
        $count_ = $this->dao->getSeckillRefundCount($where, 2);
        $count__ = $this->dao->getSeckillRefundCount($where, 1);
        return $count - $count_ - $count__;
    }

    /**
     * 获取sku的总销量
     * @param string $sku
     * @return int|mixed
     * @author Qinii
     * @day 3/4/21
     */
    public function skuSalesCount(string $sku)
    {
        $where = [
            'product_sku' => $sku,
            'product_type' => 1,
        ];
        $count = $this->dao->getTattendSuccessCount($where, null)->count();
        $count_ = $this->dao->getSeckillRefundCount($where, 2);
        $count__ = $this->dao->getSeckillRefundCount($where, 1);
        return $count - $count_ - $count__;
    }

    /**
     * 秒杀获取个人当天限购
     * @param int $uid
     * @param object $product
     * @return int
     * @author Qinii
     * @day 2020-08-15
     */
    public function getDayPayCount(int $uid, int $productId, $cart_num)
    {
        $active = app()->make(ProductRepository::class)->getWhere(['product_id' => $productId], '*', ['seckillActive']);
        if ($active->seckillActive['once_pay_count'] == 0 || $active->seckillActive['once_pay_count'] == '') return true;
        $where = [
            'activity_id' => $active->seckillActive['seckill_active_id'],
            'product_type' => 1,
            'product_id' => $productId
//            'day' => date('Y-m-d', time())
        ];

        $count = (int)$this->dao->getTattendCount($where, $uid)->sum('total_num');//当前用户已购买数量

        //单次限购判断
        if ($cart_num > $active->seckillActive['once_pay_count']) {
            return false;
        } else if (($count + $cart_num) > $active->seckillActive['all_pay_count']) {
            //活动总限购判断
            return false;
        }
        return true;
    }

    /**
     * 秒杀获取个人总限购
     * @param int $uid
     * @param object $product
     * @return int
     * @author Qinii
     * @day 2020-08-15
     */
    public function getPayCount(int $uid, int $productId, $cart_num)
    {
        $active = app()->make(ProductRepository::class)->getWhere(['product_id' => $productId], '*', ['seckillActive']);
        if ($active->seckillActive['all_pay_count'] == 0) return true;
        $where = [
            'activity_id' => $productId,
            'product_type' => 1,
            'day' => date('Y-m-d', time())
        ];
        $count = (int)$this->dao->getTattendCount($where, $uid)->sum('total_num');
        if ($count >= $active->seckillActive['all_pay_count']) {
            return false;
        } else if (($count + $cart_num) > $active->seckillActive['all_pay_count']) {
            return false;
        }
        return true;
    }

    /**
     *  根据订单id查看是否全部退款
     * @Author:Qinii
     * @Date: 2020/9/11
     * @param int $orderId
     * @return bool
     */
    public function checkRefundStatusById(int $orderId, int $refundId)
    {
        return Db::transaction(function () use ($orderId, $refundId) {
            $res = $this->dao->search(['order_id' => $orderId])->with(['orderProduct'])->find();
            $refund = app()->make(StoreRefundOrderRepository::class)->getRefundCount($orderId, $refundId);
            if ($refund) return false;
            foreach ($res['orderProduct'] as $item) {
                if ($item['refund_num'] !== 0) return false;
                $item->is_refund = 3;
                $item->save();
            }
            $res->status = -1;
            $res->save();
            //如果是套餐，回复套餐库存
            if ($res->activity_type == 10) {
                app()->make(StoreDiscountRepository::class)->incStock($res->orderProduct[0]['activity_id']);
            }
            return true;
        });
    }

    /**
     * 处理订单相关的冻结资金等
     * 1. 验证订单是否还存在其他相关原订单或者子订单
     * 2. 其他订单是否完成状态
     * 3. 解冻商户冻结资金
     * @param object $order 订单的对象。
     */
    public function orderRefundAllAfter($order)
    {

        $main_id =  $order->main_id ?: $order->order_id;
        $count = $this->dao->getSearch([])->where('status', 'NotIn', [
            OrderStatus::ORDER_STATUS_PENDING_REVIEW,
            OrderStatus::ORDER_STATUS_COMPLETED,
            OrderStatus::ORDER_STATUS_REFUND
        ])->where(function($query) use ($main_id) {
            $query->where('order_id', $main_id)->whereOr('main_id', $main_id);
        })->count();

        //拆单后完全退完
        if (!$count) {
            // 根据主订单ID获取具体的订单信息
            if ($order->main_id) {
                $order = $this->dao->getWhere(['order_id' => $main_id]);
            }
            // 计算并锁定商家的退款金额
            app()->make(MerchantRepository::class)->computedLockMoney($order);
            // 初始化优惠券ID数组
            $couponId = [];
            // 如果订单使用了优惠券，将优惠券ID添加到数组中
            if ($order->coupon_id) {
                $couponId = explode(',', $order->coupon_id);
            }

            // 如果该订单所属的团单中所有订单都已退款，处理团单优惠券
            $num = $this->dao->getSearch([])
                ->where('group_order_id', $order->group_order_id)
                ->where('status',-1)
                ->count();
            if ($num) {
                if ($order->groupOrder->coupon_id) {
                    $couponId[] = $order->groupOrder->coupon_id;
                }
            }

            // 如果有优惠券被使用，更新这些优惠券的状态为未使用
            if (count($couponId)) {
                app()->make(StoreCouponUserRepository::class)->updates($couponId, ['status' => 0]);
            }
        }

        // 创建订单状态日志，记录订单全部退款的操作
        // 订单记录
        $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
        $orderStatus = [
            'order_id' => $order->order_id,
            'order_sn' => $order->order_sn,
            'type' => $storeOrderStatusRepository::TYPE_ORDER,
            'change_message' => '订单已全部退款',
            'change_type' => $storeOrderStatusRepository::ORDER_STATUS_REFUND_ALL,
        ];
        $storeOrderStatusRepository->createSysLog($orderStatus);

        // 触发订单全部退款后的事件
        event('order.refundAll', compact('order'));
    }

    /**
     * 用户删除订单操作。
     * 此函数用于处理用户请求删除订单的逻辑。它首先验证订单状态是否允许被删除，
     * 然后触发相关的事件以允许其他系统组件响应订单删除操作，
     * 最后实际执行订单的删除操作。
     *
     * @param int $id 订单ID。用于唯一标识订单。
     * @param int $uid 用户ID。用于确认订单属于哪个用户。
     * @throws ValidateException 如果订单状态不正确或已支付，则抛出验证异常。
     */
    public function userDel($id, $uid)
    {
        // 根据订单ID和用户ID查询订单，同时筛选出状态为可删除的订单。
        $order = $this->dao->getWhere([['status', 'in', [0, 3, -1, 11]], ['order_id', '=', $id], ['uid', '=', $uid], ['is_del', '=', 0]]);

        // 如果订单不存在或订单状态为0且已支付，则抛出异常，提示订单状态有误。
        if (!$order || ($order->status == 0 && $order->paid == 1))
            throw new ValidateException('订单状态有误');

        // 在执行删除操作前，触发'order.userDel.before'事件，允许其他组件响应。
        event('order.userDel.before', compact('order'));

        // 调用删除订单的内部方法，并提供订单信息和操作描述。
        $this->delOrder($order, '订单删除');

        // 删除操作完成后，触发'order.userDel'事件，允许其他组件进一步处理。
        event('order.userDel', compact('order'));
    }


    /**
     * 删除订单
     *
     * 本函数用于逻辑上删除一个订单。这里的“删除”指的是标记订单为已删除，而不是从数据库中物理删除。
     * 删除订单时，会进行以下操作：
     * 1. 更新订单状态，标记为已删除。
     * 2. 如果订单未支付，将订单中的商品库存回滚，即增加商品的可用库存。
     * 3. 记录订单状态的变更。
     *
     * @param object $order 待删除的订单对象。
     * @param string $info 订单删除原因的描述，默认为'订单删除'。
     */
    public function delOrder($order, $info = '订单删除')
    {
        // 创建订单状态仓库实例，用于后续操作订单状态
        //订单记录
        $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);

        // 构建订单状态变更信息
        $orderStatus = [
            'order_id' => $order->order_id,
            'order_sn' => $order->order_sn,
            'type' => $storeOrderStatusRepository::TYPE_ORDER,
            'change_message' => $info,
            'change_type' => $storeOrderStatusRepository::ORDER_STATUS_DELETE,
        ];

        // 创建商品仓库实例，用于后续操作商品库存
        $productRepository = app()->make(ProductRepository::class);

        // 使用事务确保订单状态更新和库存回滚的操作原子性
        Db::transaction(function () use ($info, $order, $orderStatus, $storeOrderStatusRepository, $productRepository) {
            // 标记订单为已删除
            $order->is_del = 1;
            $order->save();

            // 记录订单状态变更
            $storeOrderStatusRepository->createUserLog($order->uid,$orderStatus);

            // 如果订单未支付（状态不为3），则回滚订单中商品的库存
            if ($order->status != 3) {
                foreach ($order->orderProduct as $cart) {
                    $productRepository->orderProductIncStock($order, $cart);
                }
            }
        });
    }

    /**
     * 商品删除方法
     *
     * 本方法通过开启数据库事务，执行一系列删除操作，确保数据的一致性。
     * 主要包括将商品标记为系统删除，以及删除相关的订单收据信息。
     * 使用事务的原因是这些操作之间存在依赖关系，需要确保要么全部成功，要么全部回滚。
     *
     * @param int $id 商品ID
     */
    public function merDelete($id)
    {
        // 开启数据库事务
        Db::transaction(function () use ($id) {
            // 将商品的删除状态设置为系统删除，而不是物理删除，以保留数据审计的可能
            $data['is_system_del'] = 1;
            $this->dao->update($id, $data);

            // 删除该商品相关的所有订单收据信息，确保数据的关联删除
            app()->make(StoreOrderReceiptRepository::class)->deleteByOrderId($id);
        });
    }

    /**
     * 发送产品发货表单
     *
     * 该方法用于根据不同的发货类型生成并返回相应的发货信息表单。
     * 表单包括选择快递公司、输入快递单号、输入送货人信息等字段，根据发货类型动态调整字段显示。
     *
     * @param int $id 订单ID
     * @param array $data 包含发货类型及相关信息的数据数组
     * @return \Encore\Admin\Widgets\Form|Form
     */
    public function sendProductForm($id, $data)
    {
        // 获取快递公司列表
        $express = app()->make(ExpressRepository::class)->options();
        // 创建表单对象，并设置表单提交的URL
        $form = Elm::createForm(Route::buildUrl('merchantStoreOrderDelivery', ['id' => $id])->build());

        // 根据发货类型设置表单规则
        if (in_array($data['delivery_type'], [1, 2])) {
            if ($data['delivery_type'] == 1) {
                // 快递发货方式，设置相关字段
                $form->setRule([
                    Elm::hidden('delivery_type', 1),
                    [
                        'type' => 'span',
                        'title' => '原快递名称',
                        'children' => [(string)$data['delivery_name']]
                    ],
                    [
                        'type' => 'span',
                        'title' => '原快递单号',
                        'children' => [(string)$data['delivery_id']]
                    ],
                    Elm::select('delivery_name', '快递名称：')->options(function () use ($express) {
                        return $express;
                    })->placeholder('请选择快递名称'),
                    Elm::input('delivery_id', '快递单号：')->placeholder('请输入快递单号')->required('请输入快递单号'),
                ]);
            } else {
                // 送货上门方式，设置相关字段
                $form->setRule([
                    Elm::hidden('delivery_type', 2),
                    [
                        'type' => 'span',
                        'title' => '原送货人姓名',
                        'children' => [(string)$data['delivery_name']]
                    ],
                    [
                        'type' => 'span',
                        'title' => '原手机号',
                        'children' => [(string)$data['delivery_id']]
                    ],
                    Elm::input('delivery_name', '送货人姓名：')->placeholder('请输入送货人姓名')->required('请输入送货人姓名'),
                    Elm::input('delivery_id', '手机号：')->placeholder('请输入手机号')->required('请输入手机号'),
                ]);
            }
        }
        if ($data['delivery_type'] == 3) {
            // 无需配送方式，设置相关字段
            $form->setRule([
                Elm::hidden('delivery_type', 3),
                [
                    'type' => 'span',
                    'title' => '发货类型',
                    'children' => ['无需配送']
                ]
            ]);
        }
        if (!$data['delivery_type']) {
            // 未选择发货方式时，提供发货方式选择
            $form->setRule([
                Elm::radio('delivery_type', '发货类型：', 1)
                    ->setOptions([
                        ['value' => 1, 'label' => '发货'],
                        ['value' => 2, 'label' => '送货'],
                        ['value' => 3, 'label' => '无需配送'],
                    ])->control([
                        [
                            'value' => 1,
                            'rule' => [
                                Elm::select('delivery_name', '快递名称')->options(function () use ($express) {
                                    return $express;
                                })->placeholder('请选择快递名称'),
                                Elm::input('delivery_id', '快递单号')->placeholder('请输入快递单号')->required('请输入快递单号'),
                            ]
                        ],
                        [
                            'value' => 2,
                            'rule' => [
                                Elm::input('delivery_name', '送货人姓名：')->placeholder('请输入送货人姓名')->required('请输入送货人姓名'),
                                Elm::input('delivery_id', '手机号：')->placeholder('请输入手机号')->required('请输入手机号'),
                            ]
                        ],
                        [
                            'value' => 3,
                            'rule' => []
                        ],

                    ]),
            ]);
        }

        // 返回设置完成的表单对象
        return $form->setTitle('发货信息');
    }

    /**
     * 导入发货信息
     * @param array $data
     * @param $merId
     * @author Qinii
     * @day 3/16/21
     */
    public function setWhereDeliveryStatus(array $arrary)
    {
        $data = $arrary['data'];
        $import_id = $arrary['import_id'];
        $merId = $arrary['mer_id'];
        if (!$data) return [];
        Db::transaction(function () use ($data, $merId, $import_id) {
            $result = [];
            $num = $count = $status = 0;
            foreach ($data as $datum) {
                $ret = [];
                if ($datum['where']) {
                    $count = $count + 1;
                    if (empty($datum['value']['delivery_id'])) {
                        $mark = '发货单号为空';
                    } else {
                        $ret = $this->getSearch([])
                            ->where('status', 0)
                            ->where('paid', 1)
                            ->where('order_type', 0)
                            ->where('mer_id', $merId)
                            ->where($datum['where'])
                            ->find();
                        $mark = '数据有误或已发货';
                    }
                    if ($ret) {
                        try {
                            $value = array_merge($datum['value'], ['status' => 1]);
                            $value['delivery_type'] = 1;
                            $this->delivery($ret, $merId, $value);
                            $status = 1;
                            $mark = '';
                            $num = $num + 1;
                        } catch (\Exception $exception) {
                            $mark = $exception->getMessage();
                        }
                    }
                    $datum['where']['mark'] = $mark;
                    $datum['where']['mer_id'] = $merId;
                    $datum['where']['status'] = $status;
                    $datum['where']['import_id'] = $import_id;
                    $result[] = array_merge($datum['where'], $datum['value']);
                }
            }
            // 记录入库操作
            if (!empty($result)) app()->make(StoreImportDeliveryRepository::class)->insertAll($result);
            $_status = ($count == $num) ? 1 : (($num < 1) ? -1 : 10);
            app()->make(StoreImportRepository::class)->update($import_id, ['count' => $count, 'success' => $num, 'status' => $_status]);
        });
        if (file_exists($arrary['path'])) unlink($arrary['path']);
        return true;
    }

    /**
     * 根据订单查询相关联的自订单
     * @param $id
     * @param $merId
     * @return \think\Collection
     * @author Qinii
     * @day 2023/2/22
     */
    public function childrenList($id, $merId)
    {
        $data = $this->dao->get($id);
        $query = $this->dao->getSearch([])->with(['orderProduct'])->where('order_id', '<>', $id);
        if ($merId) $query->where('mer_id', $merId);
        if ($data['main_id']) {
            $query->where(function ($query) use ($data, $id) {
                $query->where('main_id', $data['main_id'])->whereOr('order_id', $data['main_id']);
            });
        } else {
            $query->where('main_id', $id);
        }
        return $query->select();
    }

    /**
     * 获取积分订单列表
     *
     * 根据给定的条件数组 $where，页码 $page 和每页数量 $limit，查询积分订单。
     * 这个方法主要用于处理前端请求，获取积分订单的分页列表。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页显示的数量
     * @return array 返回包含订单数量和订单列表的数组
     */
    public function pointsOrderList(array $where, int $page, int $limit)
    {
        // 初始化查询，应用给定的查询条件并排除已删除的订单
        $query = $this->dao->searchAll($where, 0, 1)->where('is_del', 0);

        // 计算满足条件的订单总数
        $count = $query->count();

        // 获取当前页码的订单列表，同时加载每个订单的产品信息
        $list = $query->with(['orderProduct'])->page($page, $limit)->select();

        // 返回订单总数和订单列表的数组
        return compact('count', 'list');
    }

    /**
     * 获取积分订单的管理员列表
     *
     * 该方法用于获取符合特定条件的积分订单列表，供管理员查看。它支持分页和条件查询，提高了数据检索的灵活性和性能。
     *
     * @param array $where 查询条件数组，用于指定获取哪些积分订单数据。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页显示的数据条数，用于分页查询。
     * @return array 返回一个包含订单数量和订单列表的数组。
     */
    public function pointsOrderAdminList(array $where, int $page, int $limit)
    {
        // 初始化查询，根据条件获取所有匹配的积分订单
        $query = $this->dao->searchAll($where, 0, 1);

        // 计算满足条件的积分订单总数
        $count = $query->count();

        // 带有订单产品信息的积分订单列表，分页获取
        $list = $query->with(['orderProduct'])->page($page, $limit)->select();

        // 返回包含订单总数和订单列表的数组
        return compact('count', 'list');
    }

    /**
     * 查询积分详情
     *
     * 本函数用于根据订单ID和用户ID查询积分详情。如果提供了用户ID，则查询该用户的积分详情；
     * 如果没有提供用户ID，则查询所有积分详情但只返回用户UID和昵称信息。
     *
     * @param int $id 订单ID
     * @param int $uid 用户ID，可选参数。如果提供此参数，将查询指定用户的积分详情。
     * @return array|null 返回订单的积分详情数组，如果未找到相关订单则返回null。
     */
    public function pointsDetail($id, $uid)
    {
        // 初始化查询条件
        $where = [];
        // 定义需要关联查询的模型
        $with = ['orderProduct',];

        // 如果提供了用户ID，添加到查询条件中
        if ($uid) {
            $where['uid'] = $uid;
        } // 如果没有提供用户ID，修改关联查询方式，只返回用户基础信息
        else if (!$uid) {
            $with['user'] = function ($query) {
                // 仅返回用户ID和昵称
                return $query->field('uid,nickname');
            };
        }

        // 执行查询，根据订单ID和其它条件获取积分详情
        $order = $this->dao->searchAll($where, 0, 1)->where('order_id', $id)->where('StoreOrder.is_del', 0)->with($with)->append(['cancel_time'], true)->find();

        // 如果查询结果为空，返回null
        if (!$order) return null;

        // 返回查询结果
        return $order;
    }

    /**
     * 判断该子订单是否有未发货的子订单
     * @param int $main_id
     * @param int $order_id
     * @return bool
     *
     * @date 2023/10/18
     * @author yyw
     */
    public function checkSubOrderNotSend(int $group_order_id, int $order_id)
    {
        $order_count = $this->dao->getSubOrderNotSend($group_order_id, $order_id);
        if ($order_count > 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 下线订单
     * 该方法用于处理特定商家订单的下线逻辑。它首先验证订单是否存在且未支付，
     * 然后更新订单状态，最后返回处理结果。
     *
     * @param int $id 订单ID
     * @param int $merId 商家ID
     */
    public function offline(int $id, int $merId)
    {
        // 定义查询条件，筛选特定商家、特定订单且支付方式为线下支付的订单
        $where = ['mer_id' => $merId, 'order_id' => $id, 'pay_type' => '7'];

        // 查询订单，如果订单不存在则抛出异常
        if (!$order = $this->dao->search($where)->find()) {
            throw new ValidateException('数据不存在');
        }

        //// 如果订单已支付或已设置支付时间，则抛出异常
        //if ($order['paid'] || $order['pay_time']) {
        //    throw new ValidateException('该订单已支付');
        //}

        // 实例化团体订单仓库，用于后续查询和操作团体订单
        $storeGroupOrderRepository = app()->make(StoreGroupOrderRepository::class);

        // 根据团体订单ID查询团体订单信息
        $groupOrder = $storeGroupOrderRepository->getWhere(['group_order_id' => $order['group_order_id']]);

        // 处理支付成功逻辑，并返回结果
        return $this->paySuccess($groupOrder);
    }

    /**
     *  订单直接数据获取， 获取支付类型状态，余额，支付金额等
     * @param $id
     * @param $uid
     * @return void
     * @author Qinii
     */
    public function payConfigPresell($uid, $id = 0)
    {
        $user = app()->make(UserRepository::class)->get($uid);
        $config = systemConfig(['recharge_switch', 'yue_pay_status', 'pay_weixin_open', 'alipay_open', 'offline_switch', 'auto_close_order_timer', 'balance_func_status']);
        $offline_switch = $config['offline_switch'] == 0 ? 0 : 1;
        $presellOrderRepository = app()->make(PresellOrderRepository::class);
        $order = $presellOrderRepository->userOrder($uid, intval($id));
        if ($offline_switch && !(($order->merchant['offline_switch']) ?? '')) {
            $offline_switch = 0;
        }
        $data = [
            'pay_price' => $order['pay_price'] ?? 0,
            'offline_switch' => $offline_switch,
            'now_money' => $user['now_money'] ?? 0,
            'pay_weixin_open' => $config['pay_weixin_open'],
            'alipay_open' => $config['alipay_open'],
            'yue_pay_status' => ($config['yue_pay_status'] && $config['balance_func_status']) ? 1 : 0,
            'invalid_time' => 0,
            'activity_type' => 2,
        ];
        return $data;
    }

    public function payConfig($id, $uid)
    {
        $groupOrder =null;
        $user = app()->make(UserRepository::class)->get($uid);
        $config = systemConfig(['recharge_switch', 'yue_pay_status', 'pay_weixin_open', 'alipay_open', 'offline_switch', 'auto_close_order_timer', 'balance_func_status']);
        $timer = (int)($config['auto_close_order_timer'] ?: 15);
        $offline_switch = $config['offline_switch'] == 0 ? 0 : 1;
        if ($id) {
            $storeGroupOrderRepository = app()->make(StoreGroupOrderRepository::class);
            $groupOrder = $storeGroupOrderRepository->search(['uid' => $uid, 'is_del' => 0])
                ->where('group_order_id', $id)
                ->with(['user'])->find();
            if (!$groupOrder) throw new ValidateException('数据不存在');
            if ($groupOrder['paid'] && $groupOrder['pay_time']) throw new ValidateException('订单已支付');
        }
        if ($groupOrder && $offline_switch) {
            if ((count($groupOrder['orderList']) > 1) || $groupOrder->activity_type == 20) {
                $offline_switch = 0;
            }
            if (!(($groupOrder['orderList'][0]->merchant['offline_switch']) ?? '')) {
                $offline_switch = 0;
            }
        }
        $data = [
            'pay_price' => $groupOrder['pay_price'] ?? 0,
            'offline_switch' => $offline_switch,
            'now_money' => $user['now_money'] ?? 0,
            'pay_weixin_open' => $config['pay_weixin_open'],
            'alipay_open' => $config['alipay_open'],
            'yue_pay_status' => ($config['yue_pay_status'] && $config['balance_func_status']) ? 1 : 0,
            'invalid_time' => $groupOrder ? strtotime($groupOrder['create_time'] . "+ $timer minutes") : 0,
            'activity_type' => $groupOrder['activity_type'] ?? 0,
        ];
        return $data;
    }

    /**
     *  订单配货单
     * @param string $ids
     * @param int $merId
     * @param array $merchant
     * @return array|mixed|\think\Collection|\think\db\BaseQuery[]
     * @author Qinii
     */
    public function note($where, $limit = 20)
    {
        $key = 'note_' . json_encode($where);
        if ($list = Cache::get($key)) { return $list; }
        // 获取查询条件中的商家状态，后续用于构造查询条件
        $status = $where['status'];
        // 移除查询条件中的状态字段，避免直接在where中使用可能导致的混淆
        unset($where['status']);
        $list = $this->dao->search($where)
            ->where($this->getOrderType($status))
            ->with([
                'orderProduct',
                'merchant' => function ($query) {
                    // 加载商家的简要信息
                    $query->field('mer_id,mer_name,mer_phone,mer_address');
                },
                'finalOrder',
            ])->select();
        foreach($list as &$v){
            $v['coupon_price'] = bcsub($v['coupon_price'], $v['platform_coupon_price'], 2);
        }
        Cache::set($key, $list, 60);
        return $list;
    }

    const ENABLE_ASSIGNED = [
        'RECEIVE' => 0, // 领取
        'DISPATCH' => 1, // 指派
    ];

    public function selfDispatch($merId, $order, $staffs_id)
    {
        return $this->merDispatch($merId, 0, $order, ['staffs_id' => $staffs_id], self::ENABLE_ASSIGNED['RECEIVE']);
    }

    /**
     * 预约订单派单
     *
     * @param integer $id
     * @param array $data
     * @return void
     */
    public function reservationDispatch(int $id, int $merId, array $data, int $serviceId = 0)
    {
        $order = $this->dao->getWhere(['order_id' => $id, 'mer_id' => $merId, 'status' => 0, 'paid' => 1, 'is_del' => 0], '*', ['refundOrder']);
        if (!$order) {
            throw new ValidateException('订单不存在或未支付，请检查');
        }
        if($order['staffs_id'] != 0){
            throw new ValidateException('订单已派单');
        }

        return $this->merDispatch($merId, $serviceId, $order, $data, self::ENABLE_ASSIGNED['DISPATCH']);
    }
    /**
     * 预约订单改派
     *
     * @param integer $id
     * @param array $data
     * @return void
     */
    public function reservationUpdateDispatch(int $id, int $merId, array $data, int $serviceId = 0)
    {
        $order = $this->dao->getWhere(['order_id' => $id, 'mer_id' => $merId, 'status' => 1, 'paid' => 1, 'is_del' => 0], '*', ['refundOrder']);
        if (!$order) {
            throw new ValidateException('订单不存在或未支付，请检查');
        }

        return $this->merDispatch($merId, $serviceId, $order, $data, self::ENABLE_ASSIGNED['DISPATCH']);
    }
    /**
     * 预约订单排单
     *
     * @param integer $merId
     * @param object $order
     * @param array $data
     * @return void
     */
    private function merDispatch(int $merId, int $serviceId, StoreOrder $order, array $data, int $enableAssigned)
    {
        $staffs = app()->make(StaffsRepository::class)->merExists($merId, $data['staffs_id']);
        if (!$staffs) {
            throw new ValidateException('员工状态异常，请检查');
        }
        foreach($order['refundOrder'] as $refundInfo){
            if ($refundInfo['status'] == 0) {
                throw new ValidateException('该订单存在待处理的退款单，请先处理～');
            }
        }
        $order->staffs_id = $data['staffs_id'];
        $order->status = OrderStatus::ORDER_STATUS_PENDING_RECEIPT;
        $order->enable_assigned = $enableAssigned;
        // 如果是上门订单并且打卡配置未开启，则将订单状态设置为服务中
        if ($order->order_type == 0) {
            $config = merchantConfig($merId,['enable_checkin']);
            if (!$config['enable_checkin']) {
                $order->status = OrderStatus::RESERVATION_ORDER_STATUS_INSERVICE;
            }
        }

        try {
            Db::transaction(function () use ($order, $serviceId) {
                $order->save();
                // 构建订单状态变更信息
                $orderStatus = [
                    'order_id' => $order->order_id,
                    'order_sn' => $order->order_sn,
                    'type' => StoreOrderStatusRepository::TYPE_ORDER,
                    'change_message' => '订单已派单',
                    'change_type' => StoreOrderStatusRepository::RESERVATION_ORDER_DISPATCH,
                ];
                // 根据服务人员ID是否存在，分别记录服务人员日志或管理员日志
                $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
                if ($serviceId) {
                    $storeOrderStatusRepository->createServiceLog($serviceId, $orderStatus);
                } else {
                    $storeOrderStatusRepository->createAdminLog($orderStatus);
                }
            });
            return true;
        } catch (\Exception $e) {
            throw new ValidateException('失败：' . $e->getMessage());
        }
    }
    /**
     * 预约订单改期
     *
     * @param integer $id
     * @param integer $merId
     * @param array $data
     * @param integer $serviceId
     * @return void
     */
    public function reservationReschedule(int $id, int $merId, array $data, int $serviceId = 0)
    {
        // 获取预约订单信息
        $order = $this->dao->getWhere(['order_id' => $id, 'mer_id' => $merId, 'paid' => 1, 'is_del' => 0], '*', ['refundOrder']);
        if (!$order) {
            throw new ValidateException('订单不存在或未支付，请检查');
        }
        foreach($order['refundOrder'] as $refundInfo){
            if ($refundInfo['status'] == 0) {
                throw new ValidateException('该订单存在待处理的退款单，请先处理～');
            }
        }
        // 判断订单状态是否为待派单或待服务
        if(!in_array($order->status,[OrderStatus::ORDER_STATUS_PENDING_SHIPMENT, OrderStatus::ORDER_STATUS_PENDING_RECEIPT])){
            throw new ValidateException('订单状态异常，请检查');
        }
        // 订单
        $order->real_name = $data['real_name'];
        $order->order_type = $data['order_type'];
        $order->user_phone = $data['user_phone'];
        $order->user_address = $data['user_address'];
        $order->order_extend = json_encode($data['order_extend']);
        // 主订单
        $groupOrder = $order->groupOrder;
        $groupOrder->real_name = $data['real_name'];
        $groupOrder->user_phone = $data['user_phone'];
        $groupOrder->user_address = $data['user_address'];
        // 订单商品
        $orderProduct = $order->orderProduct[0];
        $orderProduct->reservation_date = $data['reservation_date'];
        $orderProduct->reservation_time_part = $data['part_start'] .' - ' .$data['part_end'];

        try {
            Db::transaction(function () use ($order, $groupOrder, $orderProduct, $serviceId) {
                $order->save();
                $groupOrder->save();
                $orderProduct->save();
                // 构建订单状态变更信息
                $orderStatus = [
                    'order_id' => $order->order_id,
                    'order_sn' => $order->order_sn,
                    'type' => StoreOrderStatusRepository::TYPE_ORDER,
                    'change_message' => '订单已改约',
                    'change_type' => StoreOrderStatusRepository::RESERVATION_ORDER_RESCHEDULE
                ];
                // 根据服务人员ID是否存在，分别记录服务人员日志或管理员日志
                $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
                if ($serviceId) {
                    $storeOrderStatusRepository->createServiceLog($serviceId, $orderStatus);
                } else {
                    $storeOrderStatusRepository->createAdminLog($orderStatus);
                }
            });
            return true;
        } catch (\Exception $e) {
            throw new ValidateException('失败：' . $e->getMessage());
        }
    }
    /**
     * 单独修改预约时间
     *
     * @param integer $id
     * @param integer $merId
     * @param array $data
     * @return void
     */
    public function updateReservationTime(int $id, int $merId, array $data, int $serviceId = 0)
    {
        // 获取预约订单信息
        $order = $this->dao->getWhere(['order_id' => $id, 'mer_id' => $merId, 'paid' => 1, 'is_del' => 0], '*', ['refundOrder']);
        if (!$order) {
            throw new ValidateException('订单不存在或未支付，请检查');
        }
        foreach($order['refundOrder'] as $refundInfo){
            if ($refundInfo['status'] == 0) {
                throw new ValidateException('该订单存在待处理的退款单，请先处理～');
            }
        }
        // 判断订单状态是否为待派单或待服务
        if(!in_array($order->status,[OrderStatus::ORDER_STATUS_PENDING_SHIPMENT, OrderStatus::ORDER_STATUS_PENDING_RECEIPT])){
            throw new ValidateException('订单状态异常，请检查');
        }

        $orderProduct = $order->orderProduct[0];
        $orderProduct->reservation_date = $data['reservation_date'];
        $orderProduct->reservation_time_part = $data['part_start'] .' - ' .$data['part_end'];


        try {
            Db::transaction(function () use ($order, $orderProduct, $serviceId) {
                // 订单商品
                $orderProduct->save();
                // 构建订单状态变更信息
                $orderStatus = [
                    'order_id' => $order->order_id,
                    'order_sn' => $order->order_sn,
                    'type' => StoreOrderStatusRepository::TYPE_ORDER,
                    'change_message' => '订单已修改预约时间',
                    'change_type' => StoreOrderStatusRepository::RESERVATION_ORDER_RESCHEDULE
                ];
                // 根据服务人员ID是否存在，分别记录服务人员日志或管理员日志
                $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
                if ($serviceId) {
                    $storeOrderStatusRepository->createServiceLog($serviceId, $orderStatus);
                } else {
                    $storeOrderStatusRepository->createAdminLog($orderStatus);
                }
            });
            return true;
        } catch (\Exception $e) {
            throw new ValidateException('失败：' . $e->getMessage());
        }

        return true;
    }
    /**
     * 预约订单核销
     *
     * @param integer $id
     * @param integer $merId
     * @return void
     */
    public function reservationVerify(int $id, int $merId, int $serviceId = 0, int $staffsId = 0)
    {
        $order = $this->dao->getWhere(['order_id' => $id, 'mer_id' => $merId, 'paid' => 1, 'is_del' => 0], '*', ['refundOrder']);
        if (!$order) {
            throw new ValidateException('订单不存在或未支付，请检查');
        }
        foreach($order['refundOrder'] as $refundInfo){
            if ($refundInfo['status'] == 0) {
                throw new ValidateException('该订单存在待处理的退款单，请先处理～');
            }
        }
        // // 上门订单
        // if ($order->order_type == 0 && $order->status != OrderStatus::RESERVATION_ORDER_STATUS_INSERVICE) {
        //     throw new ValidateException('订单状态异常，请检查');
        // }
        // // 到店订单
        // if(
        //     $order->order_type == 1
        //     && !in_array($order->status, [OrderStatus::ORDER_STATUS_PENDING_SHIPMENT, OrderStatus::ORDER_STATUS_PENDING_SHIPMENT]))
        // {
        //     throw new ValidateException('订单状态异常，请检查');
        // }

        $order->status = OrderStatus::ORDER_STATUS_PENDING_REVIEW;
        $order->verify_time = date('Y-m-d H:i:s');
        $order->verify_service_id = $serviceId;
        $order->staffs_id = $staffsId;

        try {
            Db::transaction(function () use ($order, $serviceId) {
                // 执行订单核销后的处理逻辑
                $this->takeAfter($order, $order->user);
                // 构建订单状态变更信息
                $orderStatus = [
                    'order_id' => $order->order_id,
                    'order_sn' => $order->order_sn,
                    'type' => StoreOrderStatusRepository::TYPE_ORDER,
                    'change_message' => '订单已核销',
                    'change_type' => StoreOrderStatusRepository::ORDER_STATUS_TAKE,
                ];
                // 根据服务人员ID是否存在，分别记录服务人员日志或管理员日志
                $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
                if ($serviceId) {
                    $storeOrderStatusRepository->createServiceLog($serviceId, $orderStatus);
                } else {
                    $storeOrderStatusRepository->createAdminLog($orderStatus);
                }
            });
            return true;
        } catch (\Exception $e) {
            throw new ValidateException('失败：' . $e->getMessage());
        }
    }

    /**
     *  用户取消订单
     * @param StoreOrder $order
     * @return void
     * @author Qinii
     */
    public function cancelOrder(StoreOrder $order)
    {
        $cancel_status = false;
        $reservation = $order['orderProduct'][0]['cart_info']['reservation'] ?? '';
        if ($order->is_cancel && $reservation && $reservation['is_cancel_reservation'] == 1) {
            $star = explode('-',$order['orderProduct'][0]['reservation_time_part']);
            $cancelTime = strtotime($order['orderProduct'][0]['reservation_date'] . ' ' .$star[0]);
            $cancelTime = $cancelTime - time() >= $reservation['cancel_reservation_time'] * 3600;
            $cancel_status = $cancelTime;
        }
        if (!$cancel_status) throw new ValidateException('订单无法取消，请联系客服');
        $order->is_del = 0;
        $order->is_cancel = 0;
        if ($order->paid) {
            $repository = app()->make(StoreRefundOrderRepository::class);
            $refund = $repository->createRefund($order,1,'用户取消预约订单');
            $repository->agree($refund[$repository->getPk()], []);
        }
        $order->save();
    }

    public function getStaffOrders(int $merId, array $params)
    {
        $list = app()->make(StaffsRepository::class)->fetchMerAll($merId, $params['staff_id'])->toArray(); // 获取员工
        [$noStaffsOrder, $staffsOrder, $count] = $this->searchOrders($merId, $params); // 获取订单
        foreach ($list as &$staff) {
            $staff['orders'] = array_values(array_filter($staffsOrder, function($order) use ($staff) { // 筛选属于当前员工的订单
                return $order['staffs_id'] == $staff['staffs_id'];
            }));
        }
        array_unshift($list, $this->noAssigned($merId, $noStaffsOrder)); // 加入未指派数据到第一条

        return compact('count', 'list');
    }

    /**
     * 未指派数据
     *
     * @param integer $merId
     * @param array $noStaffsOrder
     * @return array
     */
    protected function noAssigned(int $merId, array $noStaffsOrder) : array
    {
        return ['staffs_id' => 0, 'mer_id' => $merId, 'phone' => '', 'name' => '未指派', 'orders' => $noStaffsOrder];
    }

    protected function searchOrders(int $merId, $where)
    {
        $field = 'StoreOrder.order_id,StoreOrder.real_name,StoreOrder.user_phone,StoreOrder.user_address,StoreOrder.staffs_id,StoreOrder.status,StoreOrder.order_type,StoreOrder.create_time';
        $with = [
            'orderProduct' => function ($query) {
                $query->field('order_id,product_id,reservation_date,reservation_id,reservation_time_part,cart_info');
            }
        ];
        $where['mer_id'] = $merId;
        $where['paid'] = 1;
        $where['staffs_id'] = $where['staff_id'];
        // 构建查询语句
        $query = $this->dao->search($where)
            ->field($field)
            ->with($with);

        $count = $query->count();

        $orders = $query->select()->toArray();

        $staffsOrder = []; // 已指派订单
        $noStaffsOrder = []; // 未指派订单

        array_map(function($order) use (&$staffsOrder, &$noStaffsOrder) {
            // 处理订单商品信息
            $orderProduct = array_shift($order['orderProduct']);
            $cartInfo = array_pop($orderProduct);
            // 构建商品信息
            $productInfo = array_merge($orderProduct, [
                'store_name' => $cartInfo['product']['store_name'] ?? '',
                'sku_name' => $cartInfo['productAttr']['sku'] ?? ''
            ]);
            // 处理预约时间
            [$productInfo['reservation_start'], $productInfo['reservation_end']] = explode('-', $orderProduct['reservation_time_part']);
            unset($productInfo['reservation_time_part']);
            // 更新订单信息
            $order['productInfo'] = $productInfo;
            unset($order['orderProduct']);

            if ($order['staffs_id'] == 0) {
                $noStaffsOrder[] = $order;
            }
            $staffsOrder[] = $order;
        }, $orders);

        return [$noStaffsOrder, $staffsOrder, $count];
    }

    /**
     * 获取运费
     * @param array $data
     * @return array|mixed
     * @author Qinii
     */
    public function getPrice($id, array $data)
    {
        $order = $this->dao->getWhere(['order_id' => $id],'*',['orderProduct']);
        $merId = $order['mer_id'];
        $data['weight'] = $this->getOrderWeight($order);
        $data['address'] = $order['user_address'];
        $service = app()->make(CrmebServeServices::class, [$merId]);
        $result = $service->express()->getPrice($data);
        return $result;
    }

    public function getOrderWeight($order)
    {
        $weight = 0;
        foreach ($order['orderProduct'] as $item) {
            $weight = $weight + bcmul($item['product_num'],$item['cart_info']['productAttr']['weight'],2);
        }
        return $weight;
    }

    /**
     *  获取快递公司
     * @param $merId
     * @return array|mixed
     * @author Qinii
     */
    public function getKuaidiComs($merId)
    {
        $services = app()->make(CrmebServeServices::class, [$merId]);
        return $services->express()->getKuaidiComs();
    }

    public function shipmentList($merId, $page, $limit)
    {
        $services = app()->make(CrmebServeServices::class, [$merId]);
        return $services->express()->getShipmentOrderList(compact('page','limit'));
    }

    public function shipmentNotify($data)
    {
        $merId = $data['id'];
        $type = $data['type'];
        $data = $data['data'];
        $orderInfo = $this->dao->getSearch([])->where('mer_id',$merId)->where('task_id', $data['task_id'])->find();
        if (!$orderInfo) return true;
        $statusRepository = app()->make(StoreOrderStatusRepository::class);
        $orderStatus = [
            'order_id' => $orderInfo->order_id,
            'order_sn' => $orderInfo->order_sn,
            'type' => $statusRepository::TYPE_ORDER
        ];
        $event = false;
        try {
            switch ($type) {
                case 'order_fail': //下单失败回调
                    $orderStatus['change_message'] = '商家寄件订单创建失败';
                    $orderStatus['change_type'] = $statusRepository::ORDER_DELIVERY_SHIPMENT_CANCEL;
                    $orderInfo->task_id = '';
                    $orderInfo->kuaidi_order_id = '';
                    $orderInfo->is_stock_up = 0;
                    break;
                case 'order_cancel': //取消回调
                    $orderInfo->is_stock_up = 0;
                    $this->cancelShipmentAfrer($orderInfo);
                    $orderStatus = [];
                    break;
                case 'order_take':   //取件回调
                    $orderStatus['change_message'] = '商家寄件：快递已揽件';
                    $orderStatus['change_type'] = $statusRepository::ORDER_DELIVERY_SHIPMENT_PACKAGE;
                    $orderInfo->status = OrderStatus::ORDER_STATUS_PENDING_RECEIPT;
                    $orderInfo->is_stock_up = 10;
                    $event = true;
                    break;
                case 'order_receipt': //快递签收回调
                    $orderStatus['change_message'] = '商家寄件：快递已签收';
                    $orderStatus['change_type'] = $statusRepository::ORDER_DELIVERY_SHIPMENT_SUCCESS;
                    $orderInfo->status = OrderStatus::ORDER_STATUS_PENDING_REVIEW;
                    break;
                default:
                    $orderInfo->is_stock_up = 1;
                    $orderStatus = [];
                    break;
            }
            $orderInfo->save();
            if (!empty($orderStatus)) $statusRepository->createAdminLog($orderStatus);

            if ($event) event('mini_order_shipping', ['product', $orderInfo, OrderStatus::DELIVER_TYPE_SHIP_MENT, '', '']);
        }catch (\Exception $e) {
            Log::info('商家寄件回调处理失败：'.$e->getMessage());
        }
    }
    /**
     * 生成第三方订单失败的订单手动同步
     *
     * @param array $orderIds
     * @param integer $merId
     * @return void
     */
    public function deliveryOrderSync(array $orderIds, int $merId)
    {
        $where['status'] = 0;
        $where['mer_id'] = $merId;
        $where['order_ids'] = $orderIds;
        $orders = $this->dao->search($where)->with(['orderProduct'])->select();
        foreach ($orders as $order) {
            $res = $this->cityDelivery($order, $merId, $order->orderProduct->toArray());
            if (!$res) {
                throw new ValidateException('同步失败, 订单号：'.$order['order_sn'].'，'.$order['merchant_take_info'][$merId]['sync_desc']);
            }
        }
        return true;
    }

    public function getDeliveryTrack(int $orderId)
    {
        // 同城配送订单并且是第三方配送
        $where['order_type'] = 2; // 同城配送
        $where['order_id'] = $orderId;
        $orderInfo = $this->dao->getWhere(['order_id' => $orderId], 'order_id, order_sn order_type,merchant_take_id', ['deliveryOrder', 'take']);
        if(!$orderInfo) return [];

        $deliveryTrack = [];
        if($orderInfo->take->type && $orderInfo->deliveryOrder && $orderInfo->deliveryOrder['status'] > 0){
            $deliveryTrack = app()->make(DeliveryOrderRepository::class)->getDeliveryTrack($orderInfo->deliveryOrder);
            $address = lbs_address([], $orderInfo['user_address']);
            $lat = $address['location']['lat'];
            $lng = $address['location']['lng'];

            $distance = getDistance($lat, $lng, $deliveryTrack['lat'], $deliveryTrack['lng']) ?: 0;
            // 距离单位转换
            if ($distance < 0.9) {
                $distance = max(bcmul($distance, 1000, 0), 1).'m';
                if ($distance == '1m') {$distance = '100m以内';}
            } else {
                $distance.= 'km';
            }

            $deliveryTrack['distance'] = $distance;
        }

        return $deliveryTrack;
    }


    public function hasRefund($id)
    {
        $res = $this->dao->get($id);
        if ($res->refundOrder) {
            foreach ($res->refundOrder as $refund) {
                if ($refund->status == 0) {
                    return false;
                }
            }
        }
        return true;
    }
}
