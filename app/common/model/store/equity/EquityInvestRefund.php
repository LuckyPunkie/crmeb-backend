<?php

namespace app\common\model\store\equity;

use app\common\model\BaseModel;

class EquityInvestRefund extends BaseModel
{
    const STATUS_PENDING = 1;
    const STATUS_PASS = 2;
    const STATUS_REJECT = 3;

    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'equity_invest_refund';
    }
}
