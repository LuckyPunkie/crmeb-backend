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

use app\common\model\user\User;
use crmeb\services\pay\dto\PayOrderDto;
use crmeb\services\pay\PayAttach;

class UserOrderPayFactory
{
    public static function make(string $type, User $user, string $orderSn, $payPrice, array $params): PayOrderDto
    {
        if (in_array($type, ['weixin', 'alipay'], true) && ($params['is_app'] ?? false)) {
            $type .= 'App';
        }

        $dto = new PayOrderDto(self::driver($type), $type);
        $dto->orderSn = $orderSn;
        $dto->payPrice = (string)$payPrice;
        $dto->body = '付费会员';
        $dto->attach = PayAttach::USER_ORDER;
        $dto->returnUrl = $type === 'alipay' ? (string)($params['return_url'] ?? '') : '';
        $dto->wechatUserId = (int)$user['wechat_user_id'];
        $dto->orderId = $orderSn;
        $dto->isApp = (bool)($params['is_app'] ?? false);
        return $dto;
    }

    protected static function driver(string $type): string
    {
        return strpos($type, 'alipay') === 0 ? 'alipay' : 'weixin';
    }
}
