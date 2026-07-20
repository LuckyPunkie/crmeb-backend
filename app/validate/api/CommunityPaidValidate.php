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

class CommunityPaidValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'title|标题' => 'require',
        'free_content|免费预览内容' => 'require|min:10',
        'paid_content|付费内容' => 'require',
        'price|解锁价格' => 'require|float|>=:0.01|<=:999.00',
        'trial_ratio|试读比例' => 'integer|>=:0|<=:100',
    ];

    protected $message = [
        'free_content.min' => '免费预览内容不能少于10个字符',
        'price.float' => '价格必须为数字',
        'price.>=' => '价格不能低于0.01元',
        'price.<=' => '解锁价格不能超过999.00元',
    ];
}
