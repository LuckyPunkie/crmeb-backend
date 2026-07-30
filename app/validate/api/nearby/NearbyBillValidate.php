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

namespace app\validate\api\nearby;

use think\Validate;

class NearbyBillValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'mer_id|商户ID' => 'require|integer|>:0',
        'pay_price|支付金额' => 'require|float|>=:1|<=:50000',
        'pay_type|支付方式' => 'require|in:weixin,weixinApp,alipay,alipayApp,routine,mock,balance',
        'coupon_id|优惠券ID' => 'integer',
    ];

    protected $message = [
        'pay_price.float' => '请输入有效的支付金额',
        'pay_price.>=' => '支付金额不能低于1元',
        'pay_price.<=' => '支付金额不能超过50000元',
        'pay_type.in' => '支付方式仅支持微信或支付宝',
    ];
}
