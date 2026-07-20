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


namespace app\validate\merchant;


use think\Validate;

class MerchantTakeValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'name|名称' => 'require|max:100',
        'phone|手机号' => 'require|max:13',
        'address|地址' => 'require|max:100',
        'long|经度' => 'require|float|max:20',
        'lat|纬度' => 'require|float|max:20',
        'business_date|营业日期' => 'require|array|max:7',
        'business_time_start|营业开始时间' => 'require|date|max:20',
        'business_time_end|营业结束时间' => 'require|date|max:20',
        'type|提货方式' => 'require|array|max:2',
        'status|状态' => 'require|in:0,1'
    ];

    protected $scene = [
        'add'  =>  ['name', 'phone', 'address', 'long', 'lat', 'business_date', 'business_time_start', 'business_time_end', 'type', 'status'],
        'edit'  =>  ['name', 'phone', 'address', 'long', 'lat', 'business_date', 'business_time_start', 'business_time_end', 'type', 'status'],
        'status'  =>  ['status']
    ];

    public function add(array $data)
    {
        if (!$this->scene('add')->check($data)) {
            return false;
        }

        return true;
    }

    public function edit(array $data)
    {
        if (!$this->scene('edit')->check($data)) {
            return false;
        }

        return true;
    }

    public function status(array $data)
    {
        if (!$this->scene('status')->check($data)) {
            return false;
        }

        return true;
    }
}
