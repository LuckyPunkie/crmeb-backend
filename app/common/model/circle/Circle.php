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

namespace app\common\model\circle;

use app\common\model\BaseModel;
use app\common\model\system\merchant\Merchant;

class Circle extends BaseModel
{
    protected $updateTime = 'update_time';

    const SYSTEMC_COMMISSION = [
        0 => 'one_agent_commission',
        1 => 'two_agent_commission',
        2 => 'three_agent_commission',
    ];

    public static function tablePk(): ?string
    {
        return 'circle_id';
    }

    public static function tableName(): string
    {
        return 'circle';
    }

    public function getCommissionRateAttr($value, $data)
    {
        return ($data['commission_type'] == 0) ? systemConfig(self::SYSTEMC_COMMISSION[$data['level']]) : $value;
    }

    public function circleAgent()
    {
        return $this->hasOne(CircleAgent::class, 'circle_agent_id', 'circle_agent_id')
            ->field('circle_agent_id, name, phone, qualification, create_time, status')
            ->where('status', 1);
    }

    public function merchant()
    {
        $data = $this->getData();
        $type = $data['type'];
        if($type == 0) {
            return $this->hasMany(Merchant::class, 'region_id', 'circle_id')->field('mer_id,mer_name,region_id,business_id,status');
        }
        if ($type == 1) {
            return $this->hasMany(Merchant::class, 'business_id', 'circle_id')->field('mer_id,mer_name,region_id,business_id,status');
        }
    }

    public function parent()
    {
        return $this->hasOne(Circle::class, 'circle_id', 'pid')
            ->field('name, circle_id, circle_agent_id, pid, commission_type, commission_rate, level, status, type');
    }

    public function getMerchantCountAttr()
    {
        return $this->merchant()->count();
    }
}
