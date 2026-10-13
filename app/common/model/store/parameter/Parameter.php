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

namespace app\common\model\store\parameter;

use app\common\model\BaseModel;

class Parameter extends BaseModel
{


    public static function tablePk(): string
    {
        return 'parameter_id';
    }

    public static function tableName(): string
    {
        return 'parameter';
    }

    public function getValueAttr($value)
    {
        return explode('&',$value);
    }

    public function paramValues()
    {
        return $this->hasMany(ParameterValue::class, 'parameter_id', 'parameter_id');
    }

    public function getValuesAttr()
    {
        return ParameterValue::where('parameter_id',$this->parameter_id)->where('mer_id',request()->merId())->column('parameter_value_id,value');
    }

    public function searchTemplateIdAttr($query, $value)
    {
        if (is_array($value)) {
            $query->whereIn('template_id',$value);
        } else {
            $query->where('template_id',$value);
        }
    }

}
