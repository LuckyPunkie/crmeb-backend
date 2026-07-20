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

class StaffValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'uid|关联用户' => 'require|array',
        'uid.id|关联用户' => 'require|integer',
        'name|员工姓名' => 'require|max:12',
        'phone|联系电话' => 'require|mobile',
        'photo|证件照' => 'max:250',
        'status|状态' => 'require|in:0,1',
        'sort|排序' => 'require|integer',
    ];

    protected $message = [
        'uid.require' => '请选择一个用户绑定为员工',
        'uid.id.require' => '用户ID不能为空'
    ];

    public function update()
    {
        return $this;
    }
}
