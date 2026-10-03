<?php

namespace app\common\dao\store\dividend;

use app\common\dao\BaseDao;
use app\common\model\store\dividend\MerchantDividendDetail;

class MerchantDividendDetailDao extends BaseDao
{
    protected function getModel(): string
    {
        return MerchantDividendDetail::class;
    }

    public function search(array $where = [])
    {
        return MerchantDividendDetail::getDB()
            ->when(isset($where['batch_id']) && $where['batch_id'] !== '', function ($query) use ($where) {
                $query->where('batch_id', $where['batch_id']);
            });
    }
}
