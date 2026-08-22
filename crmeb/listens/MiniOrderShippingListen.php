<?php

namespace crmeb\listens;

use app\common\repositories\store\order\OrderStatus;
use app\common\repositories\store\order\StoreGroupOrderRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\user\UserRepository;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\jobs\MiniOrderShippingJob;
use crmeb\services\wechat\MiniProgram;
use think\exception\ValidateException;
use think\facade\Log;
use think\facade\Queue;

/**
 * 小程序发货信息管理事件
 * Class MiniOrderShippingListen
 * @author yyw @date 2023/10/19
 */
class MiniOrderShippingListen implements ListenerInterface
{
    public function handle($event): void
    {
        [$order_type, $order, $delivery_type, $delivery_id, $delivery_name] = $event;
        $sub_mchid = $event[5] ?? '';
        $order_shipping_open = systemConfig('order_shipping_open', 0);  // 小程序发货信息管理服务开关
        if (empty($order) || !$order_shipping_open) {
            return;
        }
        try {
            $this->pushDeliveryMiniOrder($order_type, $order, $delivery_type, $delivery_id, $delivery_name, $sub_mchid);
        } catch (\Exception $exception) {
            Log::error([
                'title' => '小程序发发货管理异常',
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ]);
        }
    }

    public function pushDeliveryMiniOrder($order_type, $order, $delivery_type = 1, $delivery_id = '', $delivery_name = '', $sub_mchid = '')
    {
        $secs = 0;
        //判断订单是否拆单
        $delivery_mode = 1;
        $is_all_delivered = true;

        switch ($order_type) {
            case 'product':
                $order_id = (int)$order['order_id'];
                if ($order['pay_type'] != 2 && $order['pay_price'] <= 0) {
                    return false;
                }
                $item_desc = '订单支付';
                $storeGroupOrderRepository = app()->make(StoreGroupOrderRepository::class);
                // 产找group 订单
                $group_order_info = $storeGroupOrderRepository->get($order['group_order_id']);
                if (empty($group_order_info)) {
                    throw new ValidateException('用户订单异常');
                }

                $order_sn = $group_order_info['group_order_sn'];

                // 判断是不是拆单发货
                /** @var StoreOrderRepository $orderServices */
                $orderServices = app()->make(StoreOrderRepository::class);
                $order_son_count = $orderServices->query(['group_order_id' => $order['group_order_id'], 'status' => 0])->count();
                if ($order_son_count > 1) {
                    $delivery_mode = 2;
                    $is_all_delivered = $orderServices->checkSubOrderNotSend((int)$order['group_order_id'], $order_id);
                }
                $out_trade_no = $order_sn;

                if ($order['order_type'] == 1) {
                    $secs = 60;
                }
                if ($order['activity_type'] == 20) {    // 积分商品
                    $path = '/pages/points_mall/integral_order_details?order_id=' . $order_id;
                } else {
                    $path = '/pages/order_details/index?order_id=' . $order_id;
                }
                break;
            case 'recharge':
                if ($order['recharge_type'] != 'routine' && $order['price'] <= 0) {   // 不是小程序订单终止
                    return false;
                }
                $order_id = $order['order_id'];
                $delivery_type = 3;
                $item_desc = '用户充值' . $order['price'];
                $out_trade_no = $order_id;
                $secs = 60;
                $path = '/pages/users/user_bill/index';
                break;
            case 'member':
                if ($order['pay_type'] != 'routine' && $order['pay_price'] <= 0) {
                    return false;
                }
                $order_id = $order['order_sn'];
                $delivery_type = 3;
                $item_desc = '用户购买' . $order['member_type'] . '会员卡';
                $out_trade_no = $order_id;
                $secs = 60;
                $path = '/pages/annex/vip_center/index';
                break;
            default:
                throw new ValidateException('订单类型异常');
        }

        // 整理商品信息
        $logistics_type = $this->getLogisticsType($delivery_type);
        $shipping_list = $this->getShippingList($logistics_type, $item_desc, $order['user_phone'] ?? '', $delivery_id, $delivery_name);
        //查找支付者openid
        $payer_openid = $this->getPayerOpenid($order['uid']);
        $queue_param = compact('out_trade_no', 'logistics_type', 'shipping_list', 'payer_openid', 'path', 'delivery_mode', 'is_all_delivered', 'sub_mchid');

        if ($secs) {
            Queue::later($secs, MiniOrderShippingJob::class, $queue_param);
        } else {
            Queue::push(MiniOrderShippingJob::class, $queue_param);
        }
    }

    /**
     * 转换发货类型
     * @param string $delivery_type
     * @return int
     *
     * @date 2023/10/18
     * @author yyw
     */
    public function getLogisticsType(string $delivery_type)
    {

        switch ($delivery_type) {
            case OrderStatus::DELIVER_TYPE_SHIPPING:   // 发货
            case OrderStatus::DELIVER_TYPE_DUMP:   //电子面单
            case OrderStatus::DELIVER_TYPE_SHIP_MENT:   //商家寄件
                $logistics_type = 1;
                break;
            case OrderStatus::DELIVER_TYPE_SAME_CITY:  // 同城
            case OrderStatus::DELIVER_TYPE_DELIVERY:    // 送货
                $logistics_type = 2;
                break;
            case OrderStatus::DELIVER_TYPE_VIRTUAL:    // 虚拟
            case OrderStatus::DELIVER_TYPE_CARD_KEY:    // 卡密
                $logistics_type = 3;
                break;
            case OrderStatus::DELIVER_TYPE_VERIFY:    // 自提
                $logistics_type = 4;
                break;
            default:
                throw new ValidateException('发货类型异常');
        }

        return $logistics_type;
    }

    /**
     * 获取商品发货信息
     * @param string $logistics_type
     * @param string $receiver_contact
     * @param string $delivery_id
     * @param string $delivery_name
     * @return array|array[]
     *
     * @date 2023/10/18
     * @author yyw
     */
    public function getShippingList(string $logistics_type, string $item_desc, string $receiver_contact = '', string $delivery_id = '', string $delivery_name = '')
    {
        if ($logistics_type == 1) {
            return [
                [
                    'tracking_no' => $delivery_id ?? '',
                    'express_company' => $delivery_name ?? '',
                    'contact' => [
                        'receiver_contact' => $receiver_contact
                    ],
                    'item_desc' => $item_desc
                ]
            ];
        } else {
            return [
                ['item_desc' => $item_desc]
            ];
        }
    }

    /**
     * 获取支付者openid
     * @param int $uid
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     *
     * @date 2023/10/18
     * @author yyw
     */
    public function getPayerOpenid(int $uid)
    {
        $user = app()->make(UserRepository::class)->get($uid);
        if (empty($user)) {
            throw new ValidateException('用户异常');
        }
        $wechatUser = app()->make(WechatUserRepository::class)->get($user['wechat_user_id']);
        if (empty($wechatUser)) {
            throw new ValidateException('微信用户异常');
        }
        if (empty($wechatUser['routine_openid'])) {
            throw new ValidateException('订单支付者不是小程序');
        }

        return $wechatUser['routine_openid'];
    }
}
