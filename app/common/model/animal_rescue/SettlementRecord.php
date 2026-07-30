<?php

namespace app\common\model\animal_rescue;

use app\common\model\BaseModel;
use app\common\model\system\merchant\Merchant;

/**
 * 救助站月捐结算记录
 */
class SettlementRecord extends BaseModel
{
    const STATUS_SETTLED = 1;
    const STATUS_WITHDRAWN = 2;

    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'settlement_records';
    }

    public function post()
    {
        return $this->hasOne(AnimalRescuePost::class, 'post_id', 'post_id');
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'mer_id', 'merchant_id');
    }

    public function searchMerchantIdAttr($query, $value)
    {
        $query->where('merchant_id', $value);
    }

    public function searchSettlementMonthAttr($query, $value)
    {
        $query->where('settlement_month', $value);
    }
}
