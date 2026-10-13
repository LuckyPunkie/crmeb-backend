<?php

namespace app\validate;

use think\Validate;

class DeliveryServiceWithdrawManageValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'service_id|配送员' => 'require|integer|gt:0',
        'amount|提现金额' => 'require|regex:/^\d{1,8}(\.\d{1,2})?$/|gt:0',
        'payment_method|收款方式' => 'require|integer|between:0,4',
        'remark|备注' => 'max:255',
        'status|审核状态' => 'require|integer|in:-1,1',
        'audit_reason|拒绝原因' => 'max:255|requireIf:status,-1',
        'payment_sn|转账流水号' => 'max:64',
        'payment_vouchers|转账凭证' => 'require|array|max:10',
    ];

    protected $message = [
        'audit_reason.requireIf' => '拒绝时请填写拒绝原因',
        'audit_reason.max' => '拒绝原因不得超过 200字',
    ];

    protected $scene = [
        'create' => ['service_id', 'amount', 'payment_method', 'remark'],
        'update' => ['amount', 'payment_method', 'remark'],
        'audit' => ['status', 'audit_reason'],
        'remark' => ['remark'],
        'transfer' => ['payment_sn', 'payment_vouchers'],
    ];
}
