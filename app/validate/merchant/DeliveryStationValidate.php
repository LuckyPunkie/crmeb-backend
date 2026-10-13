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

class DeliveryStationValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'switch_city|同城配送' => 'require|in:0,1',
        'switch_take|到店自提' => 'require|in:0,1',
        'station_name|提货点名称' => 'require',
        'type' => 'require|integer|in:0,1,2',
        'business|配送品类' => 'requireIf:type,1|requireIf:type,2|number',
        'city_name|所属城市' => 'requireIf:type,1|requireIf:type,2',
        'station_address|提货点地址' => 'require',
        'lng|提货点经度' => 'require',
        'lat|提货点纬度' => 'require',
        'contact_name|联系人姓名' => 'require',
        'card_number|身份证号码' => 'number|length:18',
        'phone|联系人电话' => 'require|mobile',
        'business_date|营业日期' => 'require|array|max:7',
        'business_time_start|营业开始时间' => 'require|date|max:20',
        'business_time_end|营业结束时间' => 'require|date|max:20',
        'status|状态' => 'require|in:0,1',
        'bind_type|门店关联方式' => 'require|in:0,1',
        'origin_shop_id|关联门店ID' => 'requireIf:bind_type,1',
        'range_type|配送范围类型' => 'require|integer|in:1,2,3',
        'radius|服务半径' => 'requireIf:range_type,1|float',
        'region|服务行政区' => 'requireIf:range_type,2|array',
        'fence|电子围栏设置' => 'requireIf:range_type,3|array'
    ];

    public function sceneDada()
    {
        return $this->append('username','mobile');
    }

    public $message = [
        'username.mobile' => '达达账号必须是手机号'
    ];
}
