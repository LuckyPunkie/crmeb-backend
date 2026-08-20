<?php

namespace app\common\model\store\equity;

use app\common\model\BaseModel;

class EquityDividendNotice extends BaseModel
{
    const STATUS_DRAFT = 1;
    const STATUS_PUBLISHED = 2;
    const STATUS_WITHDRAWN = 3;

    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'equity_dividend_notice';
    }
}
