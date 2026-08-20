<?php

namespace app\common\model\store\equity;

use app\common\model\BaseModel;

class MerchantEquityConfig extends BaseModel
{
    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'merchant_equity_config';
    }
}
