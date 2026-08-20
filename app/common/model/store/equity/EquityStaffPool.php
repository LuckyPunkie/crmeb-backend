<?php

namespace app\common\model\store\equity;

use app\common\model\BaseModel;

class EquityStaffPool extends BaseModel
{
    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'equity_staff_pool';
    }
}
