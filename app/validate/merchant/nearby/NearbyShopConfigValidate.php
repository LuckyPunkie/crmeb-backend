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

namespace app\validate\merchant\nearby;

use think\Validate;

class NearbyShopConfigValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'wechat|微信号' => 'max:40',
        'fan_group_img|粉丝群二维码' => 'max:500',
        'nearby_is_show|是否展示' => 'in:0,1',
        'nearby_category_id|店铺分类' => 'integer',
        'nearby_latitude|纬度' => 'float',
        'nearby_longitude|经度' => 'float',
        'nearby_avg_price|人均消费' => 'float|>=:0',
        'nearby_business_hours|营业时间' => 'max:200',
        'nearby_announcement|商家公告' => 'max:500',
        'nearby_tags|商家标签' => 'max:500',
        'hero_images|宣传图' => 'array',
    ];
}
