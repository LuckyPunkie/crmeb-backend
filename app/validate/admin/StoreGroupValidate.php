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
namespace app\validate\admin;

use think\Validate;

class StoreGroupValidate extends Validate
{
    protected $rule = [
        'pid|上级组织ID' => 'require|number|egt:0',
        'name|名称' => 'require|max:64',
        'sort|排序' => 'number',
        'status|状态' => 'require|number|in:0,1',
        'positioning_status|定位状态' => 'require|number|in:0,1',
        'longitude|经度' => 'requireIf:positioning_status,1|float',
        'latitude|纬度' => 'requireIf:positioning_status,1|float',
        'address|地址' => 'requireIf:positioning_status,1|max:100',
        'merchant_ids|关联商户ID' => 'array'
    ];

    protected $scene = [
        'add' => ['pid', 'name','sort', 'status', 'positioning_status', 'longitude', 'latitude', 'merchant_ids', 'address'],
        'edit' => ['pid', 'name','sort', 'status', 'positioning_status', 'longitude', 'latitude', 'merchant_ids', 'address'],
        'switch' => ['status']
    ];

    public function add(array $data): bool
    {
        if (!$this->scene('add')->check($data)) {
            return false;
        }

        return true;
    }

    public function edit(array $data): bool
    {
        if (!$this->scene('edit')->check($data)) {
            return false;
        }

        return true;
    }

    public function switch(array $data): bool
    {
        if (!$this->scene('switch')->check($data)) {
            return false;
        }

        return true;
    }
}
