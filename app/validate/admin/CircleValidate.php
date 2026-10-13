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

class CircleValidate extends Validate
{
    protected $rule = [
        'pid|上级组织ID' => 'requireIf:type,0|number|egt:0',
        'name|组织名称' => 'require|max:64',
        'circle_agent_id|区域代理ID' => 'requireIf:type,0|number|gt:0',
        'circle_agent_id|商户管理ID' => 'requireIf:type,1|number|gt:0',
        'commission_type|佣金类型' => 'requireIf:type,0|number|in:0,1',
        'commission_rate|佣金比例' => 'requireIf:commission_type,1|float|between:0,100',
        'sort|排序' => 'number',
        'status|状态' => 'number|in:0,1',
        'merchant_ids|关联商户ID' => 'array',
        'type|组织类型' => 'require|number|in:0,1',
        'role_id|身份权限ID' => 'require|number|gt:0',
        'business_store_category|商户店铺分类' => 'requireIf:type,1',
        'business_store_type|商户店铺类型' => 'requireIf:type,1'
    ];

    protected $scene = [
        'add' => ['pid','name','circle_agent_id','commission_type','commission_rate','sort','status', 'merchant_ids', 'type', 'role_id', 'business_store_category', 'business_store_type'],
        'edit' => ['pid','name','circle_agent_id','commission_type','commission_rate','sort','status', 'merchant_ids', 'type', 'role_id', 'business_store_category', 'business_store_type'],
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
