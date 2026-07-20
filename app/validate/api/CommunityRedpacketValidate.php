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

class CommunityRedpacketValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'title|标题' => 'require',
        'content|内容' => 'require',
        'amount_per_person|单个红包金额' => 'require|float|>=:0.01|<=:200.00',
        'total_count|参与人数' => 'require|integer|>=:1|<=:50',
        'deadline|截止时间' => 'require|dateFormat:Y-m-d H:i:s',
    ];

    protected $message = [
        'amount_per_person.float' => '红包金额必须为数字',
        'amount_per_person.>=' => '红包金额不能低于0.01元',
        'amount_per_person.<=' => '单个红包金额不能超过200.00元',
        'total_count.integer' => '参与人数必须为整数',
        'total_count.>=' => '参与人数至少为1',
        'total_count.<=' => '参与人数上限为50',
    ];
}
