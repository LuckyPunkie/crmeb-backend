<?php

namespace app\validate\api;

use think\Validate;

class DeliveryServiceWithdrawValidate extends Validate
{
    protected $rule = [
        'mer_id|商户身份' => 'require|integer|egt:0',
        'amount|提现金额' => 'require|regex:/^\d{1,8}(\.\d{1,2})?$/|gt:0',
        'payment_method|收款方式' => 'require|integer|between:0,4',
        'remark|备注' => 'max:255',
    ];
}
