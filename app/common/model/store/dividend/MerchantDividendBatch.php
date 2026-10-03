<?php

namespace app\common\model\store\dividend;

use app\common\model\BaseModel;

/**
 * 商家分红批次
 */
class MerchantDividendBatch extends BaseModel
{
    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'merchant_dividend_batch';
    }

    public function details()
    {
        return $this->hasMany(MerchantDividendDetail::class, 'batch_id', 'id');
    }
}
