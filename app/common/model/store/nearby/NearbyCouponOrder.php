<?php

namespace app\common\model\store\nearby;

use app\common\model\BaseModel;

class NearbyCouponOrder extends BaseModel
{
    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'nearby_coupon_order';
    }
}
