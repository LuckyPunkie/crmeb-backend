<?php

namespace app\common\dao\store\dividend;

use app\common\dao\BaseDao;
use app\common\model\store\dividend\MerchantDividendBatch;

class MerchantDividendBatchDao extends BaseDao
{
    protected function getModel(): string
    {
        return MerchantDividendBatch::class;
    }

    public function search(array $where = [])
    {
        return MerchantDividendBatch::getDB()
            ->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->whereLike('batch_sn', "%{$where['keyword']}%");
            });
    }
}
