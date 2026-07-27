<?php

namespace app\common\dao\commission;

use app\common\dao\BaseDao;
use app\common\model\commission\CommissionConfig;

class CommissionConfigDao extends BaseDao
{
    protected function getModel(): string
    {
        return CommissionConfig::class;
    }

    public function getByType(string $type): ?CommissionConfig
    {
        return CommissionConfig::getDB()->where('type', $type)->find();
    }

    public function setRate(string $type, float $rate): void
    {
        CommissionConfig::getDB()->where('type', $type)->update([
            'rate'            => $rate,
            'effective_date'  => date('Y-m-d', strtotime('+1 day')),
            'update_time'     => date('Y-m-d H:i:s'),
        ]);
    }
}
