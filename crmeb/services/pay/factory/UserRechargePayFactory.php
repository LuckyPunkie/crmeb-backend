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
use app\common\model\user\UserRecharge;
use crmeb\services\pay\dto\PayOrderDto;
use crmeb\services\pay\PayAttach;

class UserRechargePayFactory
{
    public static function make(string $type, User $user, UserRecharge $recharge, ?string $returnUrl = '', bool $isApp = false): PayOrderDto
    {
        $returnUrl = $returnUrl ?: '';

        if (in_array($type, ['weixin', 'alipay'], true) && $isApp) {
            $type .= 'App';
        }

        $dto = new PayOrderDto(self::driver($type), $type);
        $dto->orderSn = (string)$recharge['order_id'];
        $dto->payPrice = (string)$recharge['price'];
        $dto->body = '用户充值';
        $dto->attach = PayAttach::USER_RECHARGE;
        $dto->returnUrl = $type === 'alipay' ? $returnUrl : '';
        $dto->wechatUserId = (int)$user['wechat_user_id'];
        $dto->orderId = (string)$recharge['recharge_id'];
        $dto->isApp = $isApp;
        return $dto;
    }


    protected static function driver(string $type): string
    {
        return strpos($type, 'alipay') === 0 ? 'alipay' : 'weixin';
    }
}
