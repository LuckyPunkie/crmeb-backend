<?php

namespace app\common\model\store\equity;

use app\common\model\BaseModel;

class EquityFinancialReport extends BaseModel
{
    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'equity_financial_report';
    }
}
