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
namespace app\validate\api;

use think\Validate;

class CircleAgentValidate extends Validate
{
    protected $rule = [
        'name|代理人姓名' => 'require|max:64|gt:0',
        'phone|代理人电话' => 'require|mobile|gt:0',
        'qualification|申请资质' => 'array|max:10',
        'remark|说明' => 'max:255',
        'extend|扩展表单' => 'array',
        'type|类型' => 'require|in:0,1',
        'business_name|商户名称' => 'requireIf:type,1|max:64',
        'business_store_category' => 'requireIf:type,1',
        'business_store_type' => 'requireIf:type,1',
    ];

    protected $scene = [
        'create'  =>  ['name', 'phone', 'qualification', 'remark', 'type', 'business_name', 'extend','business_store_category','business_store_type']
    ];

    public function add(array $data): bool
    {
        if (!$this->scene('create')->check($data)) {
            return false;
        }

        return true;
    }

    public function edit(array $data): bool
    {
        if (!$this->scene('create')->check($data)) {
            return false;
        }

        return true;
    }
}
