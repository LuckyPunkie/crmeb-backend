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

class StoreProductAdminValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        "is_hot|是否热卖" => "in:0,1",
        "is_benefit|是否促销" => "in:0,1",
        "is_best|是否精品" => "in:0,1",
        "is_new|是否新品" => "in:0,1",
        'rank|排序' => 'require|min:1',
        'star|评分'=>'require|in:0,1,2,3,4,5'
    ];
}
