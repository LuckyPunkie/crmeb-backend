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

use think\File;
use think\Validate;

class StoreSeckillActiveValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        "name|活动名称" => 'require|max:64',
        "seckill_time_ids|活动场次" => 'require',
        "start_day|开始日期" => "require",
        "end_day|结束日期" => "require",
    ];
}
