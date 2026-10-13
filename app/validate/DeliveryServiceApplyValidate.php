<?php

namespace app\validate;

use think\Validate;

class DeliveryServiceApplyValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'avatar|配送员头像' => 'max:250',
        'name|配送员姓名' => 'require|max:50',
        'phone|配送员手机号' => 'require|mobile',
        'certificate_front|证件照正面' => 'require|max:250',
        'certificate_back|证件照反面' => 'require|max:250',
        'certificate_images|证明证书' => 'array|max:10',
        'remark|申请备注' => 'max:255',
        'extend|扩展字段' => 'array',
        'status|审核状态' => 'require|in:1,-1',
        'audit_reason|审核说明' => 'requireIf:status,-1|max:255',
    ];

    protected $scene = [
        'apply' => [
            'avatar',
            'name',
            'phone',
            'certificate_front',
            'certificate_back',
            'certificate_images',
            'remark',
            'extend',
        ],
        'audit' => ['status', 'audit_reason'],
    ];
}
