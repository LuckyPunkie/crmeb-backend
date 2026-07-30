<?php

namespace app\common\dao\animal_rescue;

use app\common\dao\BaseDao;
use app\common\model\animal_rescue\SettlementRecord;

class SettlementRecordDao extends BaseDao
{
    protected function getModel(): string
    {
        return SettlementRecord::class;
    }

    public function search(array $where)
    {
        return SettlementRecord::getDB()
            ->when(isset($where['merchant_id']) && $where['merchant_id'] !== '', function ($q) use ($where) {
                $q->where('merchant_id', $where['merchant_id']);
            })
            ->when(isset($where['settlement_month']) && $where['settlement_month'] !== '', function ($q) use ($where) {
                $q->where('settlement_month', $where['settlement_month']);
            })
            ->when(isset($where['post_id']) && $where['post_id'] !== '', function ($q) use ($where) {
                $q->where('post_id', $where['post_id']);
            })
            ->order('id DESC');
    }
}
