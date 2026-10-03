<?php

namespace app\common\model\store\dividend;

use app\common\model\BaseModel;
use app\common\model\system\merchant\Merchant;

/**
 * 商家分红明细
 */
class MerchantDividendDetail extends BaseModel
{
    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'merchant_dividend_detail';
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'mer_id', 'mer_id');
    }
}
