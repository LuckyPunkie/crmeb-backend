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

use crmeb\services\pay\dto\PayOrderDto;
use crmeb\services\pay\PayAttach;

class ServeOrderPayFactory
{
    public static function makeQrCode(array $params, int $payType): PayOrderDto
    {
        $type = $payType == 1 ? 'weixinQr' : 'alipayQr';
        $driver = $type === 'alipayQr' ? 'alipay' : 'weixin';

        $extra = $params;
        foreach (['order_sn', 'pay_price', 'body', 'attach', 'return_url', 'auth_code', 'authCode', 'sub_orders'] as $key) {
            unset($extra[$key]);
        }

        $dto = new PayOrderDto($driver, $type);
        $dto->orderSn = (string)($params['order_sn'] ?? '');
        $dto->payPrice = isset($params['pay_price']) ? (string)$params['pay_price'] : '';
        $dto->body = (string)($params['body'] ?? '');
        $dto->attach = PayAttach::SERVE_ORDER;
        $dto->orderId = $dto->orderSn;
        //$dto->extra = $extra;
        return $dto;
    }
}
