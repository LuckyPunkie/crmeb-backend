<?php

namespace app\common\model\store\equity;

use app\common\model\BaseModel;

class EquityTransaction extends BaseModel
{
    const TYPE_CONSUME = 1;
    const TYPE_INVEST = 2;
    const TYPE_REFUND = 3;

    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'equity_transaction';
    }
}
