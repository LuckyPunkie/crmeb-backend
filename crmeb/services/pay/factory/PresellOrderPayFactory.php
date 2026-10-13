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

use app\common\model\store\order\PresellOrder;
use app\common\model\user\User;
use crmeb\services\pay\dto\PayOrderDto;
use crmeb\services\pay\PayAttach;

class PresellOrderPayFactory
{
    public static function make(string $type, User $user, PresellOrder $order, ?string $returnUrl = '', bool $isApp = false): PayOrderDto
    {
        $returnUrl = $returnUrl ?: '';

        if (in_array($type, ['weixin', 'alipay'], true) && $isApp) {
            $type .= 'App';
        }

        $isCombine = in_array($type, ['weixin', 'weixinApp', 'routine', 'h5', 'weixinQr'], true)
            && systemConfig('open_wx_combine');
        $driver = $isCombine ? 'combine' : self::driver($type);

        $dto = new PayOrderDto($driver, $type);
        $dto->orderSn = (string)$order['presell_order_sn'];
        $dto->payPrice = $isCombine ? '' : (string)$order['pay_price'];
        $dto->body = '尾款支付';
        $dto->attach = PayAttach::PRESELL;
        $dto->returnUrl = $type === 'alipay' ? $returnUrl : '';
        $dto->wechatUserId = (int)$user['wechat_user_id'];
        $dto->orderId = (string)$order['presell_order_id'];
        $dto->isApp = $isApp;

        if ($isCombine) {
            $dto->subOrders = [[
                'pay_price' => $order['pay_price'],
                'order_sn' => $order['presell_order_sn'],
                'sub_mchid' => $order->merchant->sub_mchid,
            ]];
        }

        return $dto;
    }

    protected static function driver(string $type): string
    {
        return strpos($type, 'alipay') === 0 ? 'alipay' : 'weixin';
    }
}
