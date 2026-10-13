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


namespace app\common\model\user;


use app\common\model\BaseModel;
use app\common\repositories\store\CityAreaRepository;

class UserAddress extends BaseModel
{

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'address_id';
    }

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tableName(): string
    {
        return 'user_address';
    }

    public function getAreaAttr()
    {
        return app()->make(CityAreaRepository::class)->search([])->whereIn('id', [$this->province_id, $this->city_id, $this->district_id, $this->street_id])->order('id ASC')->select();
    }

    public function getPhoneAttr($value)
    {
        if (env('SHOW_PHONE',false) && app('request')->hasMacro('adminInfo') && $value && is_numeric($value)){
            if (app('request')->userType() !== 2 || app('request')->adminInfo()['level'] != 0) {
                return substr_replace($value, '****', 3, 4);
            }
        }
        return $value;
    }
}
