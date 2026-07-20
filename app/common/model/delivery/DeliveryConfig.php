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


namespace app\common\model\delivery;

use app\common\model\BaseModel;
use app\common\model\system\merchant\Merchant;
use app\common\repositories\store\CityAreaRepository;

class DeliveryConfig extends BaseModel
{
    protected $updateTime = 'update_time';

    public static function tablePk(): string
    {
        return 'delivery_config_id';
    }

    public static function tableName(): string
    {
        return 'delivery_config';
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'mer_id','mer_id');
    }

    public function getDistancePremiumConfigAttr($value)
    {
        return json_decode($value, true);
    }

    public function getWeightPremiumConfigAttr($value)
    {
        return json_decode($value, true);
    }
}
