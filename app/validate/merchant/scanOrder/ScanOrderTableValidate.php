<?php
// +----------------------------------------------------------------------
// | 扫码下单 - 台号校验
// +----------------------------------------------------------------------

namespace app\validate\merchant\scanOrder;

use think\Validate;

class ScanOrderTableValidate extends Validate
{
    protected $rule = [
        'table_label|台号文案' => 'require|max:20',
    ];

    protected $message = [
        'table_label.require' => '请填写台号文案',
        'table_label.max' => '台号文案最长20字符',
    ];
}
