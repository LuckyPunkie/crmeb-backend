<?php

namespace app\common\dao\store\equity;

use app\common\dao\BaseDao;
use app\common\model\store\equity\EquityShareholder;

class EquityShareholderDao extends BaseDao
{
    protected function getModel(): string
    {
        return EquityShareholder::class;
    }
}
