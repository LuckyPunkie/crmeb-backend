<?php

namespace app\common\dao\store\equity;

use app\common\dao\BaseDao;
use app\common\model\store\equity\EquityTransaction;

class EquityTransactionDao extends BaseDao
{
    protected function getModel(): string
    {
        return EquityTransaction::class;
    }
}
