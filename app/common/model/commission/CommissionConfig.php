<?php

namespace app\common\model\commission;

use app\common\model\BaseModel;

class CommissionConfig extends BaseModel
{
    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'commission_config';
    }
}
