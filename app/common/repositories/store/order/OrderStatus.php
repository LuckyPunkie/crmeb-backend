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

use app\common\model\store\order\StoreOrder;

class OrderStatus
{

    //订单支付状态：0-未支付，1-已支付
    const PAY_STATUS_UNPAID = 0;
    const PAY_STATUS_PAID = 1;

    /**
     * 订单主流程 status 字段
     * （订单支付状态 0-未支付）
     * 下单 -> 待付款
     *
     * （订单支付状态 1-已支付）
     * 待发货 -> 待收货 -> 待评价 -> 已完成
     * 通用订单状态（0：待发货；1：待收货；2：待评价；3：已完成；）
     *
     * -1：已退款
     *  is_del = 1 已取消
     */
    const ORDER_STATUS_PENDING_SHIPMENT =  0;  // 待发货
    const ORDER_STATUS_PENDING_RECEIPT  =  1;  // 待收货
    const ORDER_STATUS_PENDING_REVIEW   =  2;  // 待评价
    const ORDER_STATUS_COMPLETED        =  3;  // 已完成
    const ORDER_STATUS_REFUND           = -1;  // 已退款

    /**
     * 其他扩展状态：9: 拼团中 10: 待付尾款 11: 尾款超时未付 20 : 预约订单服务中【已打卡/待核销】
    */
    const ORDER_STATUS_GROUPING             = 9;
    const ORDER_STATUS_PENDING_PAY_TAIL     = 10;
    const ORDER_STATUS_PENDING_PAY_TIMEOUT  = 11;
    const RESERVATION_ORDER_STATUS_INSERVICE  = 20;

    /**
     *  product_type = 0 && is_virtual = 4 预约商品订单
     *
     * 预约商品订单流程
     * 下单 -> 待付款 ->
     *  status
     * 待指派/领取 -> 待服务 -> [已打卡 -> ] 核销 -> 待评价 -> 完成
     * status 0：待发货； 1：待收货；   20 已打卡/待核销   2：待评价； 3：已完成；
     * order_type = 0 上门 1 到店
     *  (status = 20 || (order_type == 1 && status == 1)) 待核销
     *
     *
     */
    const  ORDER_STATUS_CHECKIN = 20;

    public static function getStatusText($order, $product_type = 0, $type = 0)
    {
        $status = $order;
        if ($order instanceof StoreOrder) {
            $status = $order->status;
            $product_type = $order->product_type;
            $type = $order->type;
        }
        switch ($product_type) {
            case 0:
                break;
            default:
                break;
        }
    }

    /**
     * 发货类型(1:发货 2: 送货 3: 虚拟,4电子面单，5同城 6 卡密自动发货 7 自提/核销 8 商家寄件)
     */
    const DELIVER_TYPE_SHIPPING = 1;
    const DELIVER_TYPE_DELIVERY = 2;
    const DELIVER_TYPE_VIRTUAL = 3;
    const DELIVER_TYPE_DUMP = 4;
    const DELIVER_TYPE_SAME_CITY = 5;
    const DELIVER_TYPE_CARD_KEY = 6;
    const DELIVER_TYPE_VERIFY = 7;
    const DELIVER_TYPE_SHIP_MENT = 8;
}
