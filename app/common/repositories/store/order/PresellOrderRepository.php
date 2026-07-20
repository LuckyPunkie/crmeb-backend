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


use app\common\dao\store\order\PresellOrderDao;
use app\common\model\store\order\PresellOrder;
use app\common\model\user\User;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\product\ProductPresellSkuRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\system\merchant\FinancialRecordRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserMerchantRepository;
use app\common\repositories\user\UserRepository;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\jobs\UserBrokerageLevelJob;
use crmeb\services\pay\Pay;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;

/**
 * Class PresellOrderRepository
 * @package app\common\repositories\store\order
 * @author xaboy
 * @day 2020/10/27
 * @mixin PresellOrderDao
 */
class PresellOrderRepository extends BaseRepository
{
    public function __construct(PresellOrderDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建订单
     *
     * 本函数用于创建一个新的订单。它通过收集用户ID、订单ID、价格以及订单的开始和结束时间，
     * 来构造订单的基本信息。此外，它还会为预售价订单生成一个新的订单号。
     *
     * @param int $uid 用户ID。标识订单所属的用户。
     * @param string $orderId 订单ID。用于唯一标识订单。
     * @param float $price 订单价格。表示订单的总金额。
     * @param int $final_start_time 订单开始时间。表示订单的有效起始时间。
     * @param int $final_end_time 订单结束时间。表示订单的有效结束时间。
     * @return bool|object 返回创建的订单对象或false，如果创建失败。
     */
    public function createOrder($uid, $orderId, $price, $final_start_time, $final_end_time)
    {
        // 通过DAO层创建订单，传入包括用户ID、订单ID、开始时间、结束时间、价格以及预售价订单号等信息。
        return $this->dao->create([
            'uid' => $uid,
            'order_id' => $orderId,
            'final_start_time' => $final_start_time,
            'final_end_time' => $final_end_time,
            'pay_price' => $price,
            'presell_order_sn' => app()->make(StoreOrderRepository::class)->getNewOrderId(StoreOrderRepository::TYPE_SN_PRESELL)
        ]);
    }

    /**
     * 处理预售订单的支付逻辑。
     *
     * 根据支付方式的不同，选择不同的支付处理方式。支持余额支付以及微信、支付宝等第三方支付。
     * 对于移动端支付，会根据支付类型添加App后缀以区分支付渠道。
     * 在支付前，会触发一个事件，允许其他功能模块在支付前进行额外的操作或校验。
     * 根据支付类型和系统配置，选择使用合并支付服务或普通支付服务来生成支付配置。
     *
     * @param string $type 支付方式，可以是余额(balance)、微信(weixin)、支付宝(alipay)等。
     * @param User $user 进行支付的用户对象。
     * @param PresellOrder $order 预售订单对象。
     * @param string $return_url 支付宝支付时的回调URL，可选。
     * @param bool $isApp 是否为APP支付，用于区分微信支付的普通支付和APP支付。
     * @return array 返回包含支付配置和订单ID的信息。
     */
    public function pay($type, User $user, PresellOrder $order, $return_url = '', $isApp = false)
    {
        // 如果是余额支付，则直接调用余额支付方法
        if ($type === 'balance') {
            return $this->payBalance($user, $order);
        }

        // 如果是微信或支付宝支付，并且是APP支付，则在支付方式后添加App后缀
        if (in_array($type, ['weixin', 'alipay'], true) && $isApp) {
            $type .= 'App';
        }

        // 在支付前触发一个事件，允许其他功能模块进行额外的操作或校验
        event('order.presell.pay.before', compact('order', 'type', 'isApp'));

        // 根据支付类型和系统配置，选择合单驱动或普通驱动
        $isCombine = in_array($type, ['weixin', 'weixinApp', 'routine', 'h5', 'weixinQr'], true)
            && systemConfig('open_wx_combine');
        $driver = $isCombine ? 'combine' : null;
        $orderParams = $isCombine
            ? $order->getCombinePayParams()
            : $order->getPayParams($type === 'alipay' ? $return_url : '');

        $config = $this->callPayDriver($type, $orderParams, $user, $driver);

        // 返回支付配置和订单ID，供前端进行支付操作
        return app('json')->status($type, $config + ['order_id' => $order['presell_order_id']]);
    }


    /**
     * 使用余额支付预售价订单。
     *
     * 此方法处理用户使用余额支付预售价订单的流程。它首先检查余额支付是否已开启，然后确保用户余额充足。
     * 如果条件满足，它将在数据库事务中执行支付操作，包括更新用户余额、记录账单和标记订单为支付成功。
     *
     * @param User $user 当前进行支付的用户对象。
     * @param PresellOrder $order 需要支付的预售价订单对象。
     * @throws ValidateException 如果支付方式未开启或用户余额不足，则抛出验证异常。
     * @return json 返回支付成功的响应。
     */
    public function payBalance(User $user, PresellOrder $order)
    {
        // 检查余额支付是否开启，如果没有开启则抛出异常。
        if (!systemConfig('yue_pay_status'))
            throw new ValidateException('未开启余额支付');

        // 检查用户余额是否足够支付订单，如果不足则抛出异常。
        if ($user['now_money'] < $order['pay_price'])
            throw new ValidateException('余额不足，请更换支付方式');

        // 使用数据库事务来确保支付操作的原子性。
        Db::transaction(function () use ($user, $order) {
            // 更新用户的余额，并保存更改。
            $user->now_money = bcsub($user->now_money, $order['pay_price'], 2);
            $user->save();

            // 创建用户账单记录，记录用户余额的减少。
            $userBillRepository = app()->make(UserBillRepository::class);
            $userBillRepository->decBill($user['uid'], 'now_money', 'presell', [
                'link_id' => $order['presell_order_id'],
                'status' => 1,
                'title' => '支付预售尾款',
                'number' => $order['pay_price'],
                'mark' => '余额支付支付' . floatval($order['pay_price']) . '元购买商品',
                'balance' => $user->now_money
            ]);

            // 标记订单为支付成功。
            $this->paySuccess($order);
        });

        // 返回支付成功的响应。
        return app('json')->status('success', '余额支付成功', ['order_id' => $order['presell_order_id']]);
    }

    /**
     * 处理预售订单支付成功后的业务逻辑。
     *
     * @param PresellOrder $order 预售订单对象
     * @param int $is_combine 是否为合并支付，0表示正常支付，1表示合并支付
     * @param array $subOrders 子订单数组，包含交易号等信息
     *
     * 此函数在支付成功后，更新订单状态，记录支付信息，并处理相关的财务记录。
     * 同时，它还处理了订单相关的佣金计算和分佣逻辑。
     */
    public function paySuccess(PresellOrder $order, $is_combine = 0, array $subOrders = [])
    {
        Db::transaction(function () use ($is_combine, $order, $subOrders) {
            $time = date('Y-m-d H:i:s');
            $order->paid = 1;
            $order->pay_time = $time;
            if (isset($subOrders[$order->presell_order_sn])) {
                $order->transaction_id = $subOrders[$order->presell_order_sn]['transaction_id'];
            }
            $order->is_combine = $is_combine;
            $order->order->status = 0;

            $storeOrderRepository = app()->make(StoreOrderRepository::class);
            if ($order->order->order_type == 1) {
                $order->order->verify_code = $storeOrderRepository->verifyCode();
            }
            if (empty($order->order_sn)) {
                $order->order_sn = $storeOrderRepository->getOrderSn($order->order_id);
            }
            $order->order->save();
            $order->save();
            //订单记录
            $statusRepository = app()->make(StoreOrderStatusRepository::class);
            $orderStatus = [
                'order_id' => $order->order_id,
                'order_sn' => $order->order_sn,
                'type' => $statusRepository::TYPE_ORDER,
                'change_message' => '订单尾款支付成功',
                'change_type' => $statusRepository::ORDER_STATUS_PRESELL,
            ];
            $i = 1;
            $finance = [];

            $final_price = $order->order->pay_price;
            $order_price = $order->pay_price;
            $pay_price = bcadd($order_price, $final_price, 2);
            $sn = app()->make(FinancialRecordRepository::class)->getSn();

            $finance[] = [
                'order_id' => $order->order_id,
                'order_sn' => $order->presell_order_sn,
                'user_info' => $order->user->nickname,
                'user_id' => $order->uid,
                'financial_type' => 'presell',
                'financial_pm' => 1,
                'type' => 2,
                'number' => $order->pay_price,
                'mer_id' => $order->mer_id,
                'financial_record_sn' => $sn . ($i++),
                'pay_type' => $order->pay_type,
            ];

            $finance[] = [
                'order_id' => $order->order->order_id,
                'order_sn' => $order->order->order_sn,
                'user_info' => $order->user->nickname,
                'user_id' => $order->uid,
                'financial_type' => 'mer_presell',
                'financial_pm' => 1,
                'type' => 0,
                'number' => $pay_price,
                'mer_id' => $order->mer_id,
                'financial_record_sn' => $sn . ($i++),
                'pay_type' => $order->pay_type,
            ];

//            $pay_price = bcsub($pay_price, bcadd($order->order['extension_one'], $order->order['extension_two'], 3), 2);
            if (isset($order->order->orderProduct[0]['cart_info']['presell_extension_one']) && $order->order->orderProduct[0]['cart_info']['presell_extension_one'] > 0) {
                $order_price = bcsub($order_price, $order->order->orderProduct[0]['cart_info']['presell_extension_one'], 2);
            }
            if (isset($order->order->orderProduct[0]['cart_info']['presell_extension_two']) && $order->order->orderProduct[0]['cart_info']['presell_extension_two'] > 0) {
                $order_price = bcsub($order_price, $order->order->orderProduct[0]['cart_info']['presell_extension_two'], 2);
            }
            if (isset($order->order->orderProduct[0]['cart_info']['final_extension_one']) && $order->order->orderProduct[0]['cart_info']['final_extension_one'] > 0) {
                $final_price = bcsub($final_price, $order->order->orderProduct[0]['cart_info']['final_extension_one'], 2);
            }
            if (isset($order->order->orderProduct[0]['cart_info']['final_extension_two']) && $order->order->orderProduct[0]['cart_info']['final_extension_two'] > 0) {
                $final_price = bcsub($final_price, $order->order->orderProduct[0]['cart_info']['final_extension_two'], 2);
            }
            if ($order->order['extension_one'] > 0) {
                $finance[] = [
                    'order_id' => $order->order->order_id,
                    'order_sn' => $order->order->order_sn,
                    'user_info' => $order->user->nickname,
                    'user_id' => $order->uid,
                    'financial_type' => 'brokerage_one',
                    'financial_pm' => 0,
                    'type' => 1,
                    'number' => $order->order['extension_one'],
                    'mer_id' => $order->mer_id,
                    'financial_record_sn' => $sn . ($i++),
                    'pay_type' => $order->pay_type,
                ];
            }

            if ($order->order['extension_two'] > 0) {
                $finance[] = [
                    'order_id' => $order->order->order_id,
                    'order_sn' => $order->order->order_sn,
                    'user_info' => $order->user->nickname,
                    'user_id' => $order->uid,
                    'financial_type' => 'brokerage_two',
                    'financial_pm' => 0,
                    'type' => 1,
                    'number' => $order->order['extension_two'],
                    'mer_id' => $order->mer_id,
                    'financial_record_sn' => $sn . ($i++),
                    'pay_type' => $order->pay_type,
                ];
            }
            if ($order->order->commission_rate > 0) {
                $commission_rate = ($order->order->commission_rate / 100);
                $finalRatePrice = bcmul($final_price, $commission_rate, 2);
                $orderRatePrice = bcmul($order_price, $commission_rate, 2);
                $ratePrice = bcadd($finalRatePrice, $orderRatePrice, 2);
                $finance[] = [
                    'order_id' => $order->order->order_id,
                    'order_sn' => $order->order->order_sn,
                    'user_info' => $order->user->nickname,
                    'user_id' => $order->uid,
                    'financial_type' => 'presell_charge',
                    'financial_pm' => 1,
                    'type' => 1,
                    'number' => $ratePrice,
                    'mer_id' => $order->mer_id,
                    'financial_record_sn' => $sn . ($i++),
                    'pay_type' => $order->pay_type,
                ];
//                $pay_price = bcsub($pay_price, $ratePrice, 2);
                $order_price = bcsub($order_price, $orderRatePrice, 2);
                $final_price = bcsub($final_price, $finalRatePrice, 2);
            }
            $finance[] = [
                'order_id' => $order->order->order_id,
                'order_sn' => $order->order->order_sn,
                'user_info' => $order->user->nickname,
                'user_id' => $order->uid,
                'financial_type' => 'presell_true',
                'financial_pm' => 1,
                'type' => 2,
                'number' => bcadd($order_price, $final_price, 2),
                'mer_id' => $order->mer_id,
                'financial_record_sn' => $sn . ($i++),
                'pay_type' => $order->pay_type,
            ];
            if (!$is_combine && $order->pay_type != 7) {
                app()->make(MerchantRepository::class)->addLockMoney($order->mer_id, 'presell', $order->presell_order_id, !$order->order->groupOrder->is_combine ? bcadd($order_price, $final_price, 2) : $order_price);
//                app()->make(MerchantRepository::class)->addMoney($order->mer_id, !$order->order->groupOrder->is_combine ? bcadd($order_price, $final_price, 2) : $order_price);
            } else if (!$order->order->groupOrder->is_combine && $order->pay_type != 7) {
                app()->make(MerchantRepository::class)->addLockMoney($order->mer_id, 'presell', $order->presell_order_id, $final_price);
//                app()->make(MerchantRepository::class)->addMoney($order->mer_id, $final_price);
            }

            if ($is_combine) {
                $storeOrderProfitsharingRepository = app()->make(StoreOrderProfitsharingRepository::class);
                $storeOrderProfitsharingRepository->create([
                    'profitsharing_sn' => $storeOrderProfitsharingRepository->getOrderSn(),
                    'order_id' => $order->order->order_id,
                    'mer_id' => $order->mer_id,
                    'transaction_id' => $order->transaction_id ?? '',
                    'profitsharing_price' => $order->pay_price,
                    'profitsharing_mer_price' => $order_price,
                    'type' => $storeOrderProfitsharingRepository::PROFITSHARING_TYPE_PRESELL,
                ]);
            }
            app()->make(UserRepository::class)->update($order->uid, [
                'pay_price' => Db::raw('pay_price+' . $order->pay_price),
            ]);

            app()->make(ProductPresellSkuRepository::class)->incCount($order->order->orderProduct[0]['activity_id'], $order->order->orderProduct[0]['product_sku'], 'two_pay');

            app()->make(UserMerchantRepository::class)->updatePayTime($order->uid, $order->mer_id, $order->pay_price, false);
            app()->make(FinancialRecordRepository::class)->insertAll($finance);
            $statusRepository->createUserLog($order->uid,$orderStatus);
        });

        if ($order->user->spread_uid) {
            Queue::push(UserBrokerageLevelJob::class, ['uid' => $order->user->spread_uid, 'type' => 'spread_money', 'inc' => $order->pay_price]);
        }
        Queue::push(UserBrokerageLevelJob::class, ['uid' => $order->uid, 'type' => 'pay_money', 'inc' => $order->pay_price]);
        event('order.presll.paySuccess', compact('order'));
    }

    /**
     * 取消预售订单
     * 当预售订单超过支付时间未支付时，调用此函数进行订单取消操作。
     * 主要包括以下几个步骤：
     * 1. 根据预售订单ID查询订单信息，确认订单未支付。
     * 2. 更新订单状态为关闭，并对相关商品库存进行回滚。
     * 3. 如果订单有关联的预付分账信息，处理预付分账的退款及记录。
     * 4. 生成相关的财务记录。
     * 5. 触发相关的事件，供其他系统监听并执行相应的操作。
     *
     * @param int $id 预售订单ID
     */
    public function cancel($id)
    {
        $order = $this->dao->getWhere(['presell_order_id' => $id, 'paid' => 0]);
        if (!$order) return;
        //订单记录
        $statusRepository = app()->make(StoreOrderStatusRepository::class);

        $orderStatus = [
            'order_id' => $order->order_id,
            'order_sn' => $order->order_sn,
            'type' => $statusRepository::TYPE_ORDER,
            'change_message' => '预售订单超时支付自动关闭',
            'change_type' => $statusRepository::ORDER_STATUS_PRESELL_CLOSE,
        ];
        event('order.presll.fail.before', compact('order'));
        $productRepository = app()->make(ProductRepository::class);
        Db::transaction(function () use ($productRepository, $order, $orderStatus,$statusRepository) {
            $statusRepository->createSysLog($orderStatus);
            $order->order->status = 11;
            $order->status = 0;
            $order->save();
            $order->order->save();
            foreach ($order->order->orderProduct as $cart) {
                try{
                    $productRepository->orderProductIncStock($order->order, $cart);
                }catch (Exception $e) {}
            }
            if ($order->order->firstProfitsharing && $order->order->firstProfitsharing->profitsharing_price > 0) {
                $make = app()->make(FinancialRecordRepository::class);
                $sn = $make->getSn();
                $financial = [[
                    'order_id' => $order->order->order_id,
                    'order_sn' => $order->order->order_sn,
                    'user_info' => $order->user->nickname,
                    'user_id' => $order->uid,
                    'financial_type' => 'presell_charge',
                    'financial_pm' => 1,
                    'type' => 1,
                    'number' => bcsub($order->order->firstProfitsharing->profitsharing_price, $order->order->firstProfitsharing->profitsharing_mer_price, 2),
                    'mer_id' => $order->mer_id,
                    'financial_record_sn' => $sn . '0'
                ], [
                    'order_id' => $order->order->order_id,
                    'order_sn' => $order->order->order_sn,
                    'user_info' => $order->user->nickname,
                    'user_id' => $order->uid,
                    'financial_type' => 'mer_presell',
                    'financial_pm' => 1,
                    'type' => 0,
                    'number' => $order->order->firstProfitsharing->profitsharing_mer_pric,
                    'mer_id' => $order->mer_id,
                    'financial_record_sn' => $sn . '1'
                ], [
                    'order_id' => $order->order->order_id,
                    'order_sn' => $order->order->order_sn,
                    'user_info' => $order->user->nickname,
                    'user_id' => $order->uid,
                    'financial_type' => 'presell_true',
                    'financial_pm' => 1,
                    'type' => 2,
                    'number' => $order->order->firstProfitsharing->profitsharing_mer_price,
                    'mer_id' => $order->mer_id,
                    'financial_record_sn' => $sn . '2'
                ]];
                $make->insertAll($financial);
                try {
                    app()->make(StoreOrderProfitsharingRepository::class)->profitsharing($order->order->firstProfitsharing);
                } catch (\Exception $e) {
                    Log::info('预售定金分账失败' . $order->order_id . $e->getMessage());
                }
            }
        });
        event('order.presll.fail', compact('order'));
    }

    /**
     * 统一支付调用：根据类型获取 openid 并调用 Pay 驱动
     *
     * @param string      $type       支付类型
     * @param array       $orderParams 订单参数
     * @param User|null   $user       用户对象
     * @param string|null $driver     驱动名称，null 使用默认驱动
     * @return array
     */
    protected function callPayDriver(string $type, array $orderParams, ?User $user, ?string $driver = null): array
    {
        $pay = app()->make(Pay::class);
        if ($driver) {
            $pay = $pay->driver($driver);
        }

        switch ($type) {
            case 'weixin':
                $wechatUserRepo = app()->make(WechatUserRepository::class);
                $openId = $wechatUserRepo->idByOpenId($user['wechat_user_id']);
                if (!$openId) throw new ValidateException('请关联微信公众号!');
                return $pay->pay($type, $orderParams, $openId);

            case 'routine':
                $wechatUserRepo = app()->make(WechatUserRepository::class);
                $openId = $wechatUserRepo->idByRoutineId($user['wechat_user_id']);
                if (!$openId) throw new ValidateException('请关联微信小程序!');
                return $pay->pay($type, $orderParams, $openId);

            case 'alipay':
                $orderParams['attach'] = 'presell';
                return (new Pay('alipay'))->pay($type, $orderParams, '');

            case 'alipayApp':
                $orderParams['attach'] = 'presell';
                return (new Pay('alipay'))->pay($type, $orderParams, '');

            case 'alipayQr':
                $orderParams['attach'] = 'presell';
                return (new Pay('alipay'))->pay($type, $orderParams, '');

            default:
                return $pay->pay($type, $orderParams, '');
        }
    }
}
