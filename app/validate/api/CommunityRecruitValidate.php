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

class CommunityRecruitValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'job_title|岗位名称' => 'require|max:30',
        'work_city|工作城市' => 'require|array|max:3',
        'salary_range|薪酬范围' => 'require',
        'job_desc|工作职责' => 'require',
        'job_require|岗位要求' => 'require',
        'hire_count|招聘人数' => 'integer|>=:0',
    ];

    protected $message = [
        'job_title.max' => '岗位名称不能超过30个字符',
        'work_city.array' => '工作城市格式不正确',
        'work_city.max' => '最多选择3个城市',
        'hire_count.integer' => '招聘人数必须为整数',
    ];
}
