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
namespace crmeb\services\pay\factory;

use app\common\model\store\order\StoreGroupOrder;
use app\common\model\user\User;
use crmeb\services\pay\dto\PayOrderDto;
use crmeb\services\pay\PayAttach;

class StoreOrderPayFactory
{
    public static function make(
        string $type,
        ?User $user,
        StoreGroupOrder $groupOrder,
        ?string $returnUrl = '',
        bool $isApp = false,
        $combine = null,
        string $authCode = ''
    ): PayOrderDto {
        $returnUrl = $returnUrl ?: '';
        $driver = 'weixin';
        $orderSn = (string)$groupOrder['group_order_sn'];
        $payPrice = (string)$groupOrder['pay_price'];
        $subOrders = [];
        $extra = [];

        if (strpos($type, 'alipay') === 0) {
            $driver = 'alipay';
        } else {
            $config = systemConfig(['open_wx_combine', 'wechat_service_tool']);
            if (is_null($combine)) {
                $combine = $config['open_wx_combine'];
                if (
                    $combine &&
                    in_array($type, ['weixin', 'weixinApp', 'routine', 'h5', 'weixinQr', 'weixinBarCode'], true)
                ) {
                    if ($config['wechat_service_tool']) {
                        $combine = $groupOrder['activity_type'] != 20
                            && count($groupOrder['orderList']->toArray()) == 1
                            && $groupOrder->orderList[0]->merchant->applyment_id
                            && $groupOrder->orderList[0]->merchant->applyment_switch;
                    }
                } else {
                    $combine = false;
                }
            }

            if ($combine) {
                if ($config['wechat_service_tool']) {
                    $driver = 'partner';
                    foreach ($groupOrder->orderList as $order) {
                        $orderSn = (string)$order->order_sn;
                        $payPrice = (string)$order->pay_price;
                        $extra['sub_mchid'] = $order->merchant->applyment_id;
                    }
                } else {
                    $driver = 'combine';
                    $payPrice = '';
                    foreach ($groupOrder->orderList as $order) {
                        if ($order->pay_price > 0) {
                            $subOrders[] = [
                                'pay_price' => $order->pay_price,
                                'order_sn' => $order->order_sn,
                                'sub_mchid' => $order->merchant->sub_mchid,
                            ];
                        }
                    }
                }
            }
        }

        if (in_array($type, ['weixin', 'alipay'], true) && $isApp) {
            $type .= 'App';
        }

        $dto = new PayOrderDto($driver, $type);
        $dto->orderSn = $orderSn;
        $dto->payPrice = $payPrice;
        $dto->body = '订单支付';
        $dto->attach = PayAttach::STORE_ORDER;
        $dto->returnUrl = $returnUrl;
        $dto->authCode = $authCode;
        $dto->wechatUserId = (int)($user['wechat_user_id'] ?? 0);
        $dto->orderId = (string)$groupOrder['group_order_id'];
        $dto->isApp = $isApp;
        $dto->subOrders = $subOrders;
        $dto->extra = $extra;
        return $dto;
    }
}
