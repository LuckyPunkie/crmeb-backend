<?php

namespace app\common\model\store\equity;

use app\common\model\BaseModel;

class EquityProject extends BaseModel
{
    const STATUS_RAISING = 1;
    const STATUS_PENDING = 2;
    const STATUS_OPERATING = 3;

    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'equity_project';
    }
}
