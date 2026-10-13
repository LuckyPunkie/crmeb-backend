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

namespace app\common\model\store\product;

use app\common\model\BaseModel;

class ProductReservation extends BaseModel
{
    public static function tablePk(): string
    {
        return 'product_reservation_id';
    }

    public static function tableName(): string
    {
        return 'store_product_reservation';
    }

    public function getTimePeriodAttr($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    public function getSaleTimeWeekAttr($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    public function searchProductIdAttr($query, $value)
    {
        $query->where('product_id', $value);
    }
}
