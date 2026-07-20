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

class CommunityResumeValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'real_name|真实姓名' => 'max:50',
        'gender|性别' => 'integer|in:0,1,2',
        'phone|手机号' => 'max:20|mobile',
        'email|邮箱' => 'max:100|email',
        'education|最高学历' => 'max:50',
        'work_years|工作年限' => 'max:20',
        'city|现居城市' => 'max:50',
        'expect_job|期望职位' => 'max:100',
        'expect_salary|期望薪资' => 'max:50',
        'self_evaluation|自我评价' => 'max:2000',
    ];
}
