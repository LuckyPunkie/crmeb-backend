<?php

namespace app\common\dao\store\equity;

use app\common\dao\BaseDao;
use app\common\model\store\equity\EquityDividend;

class EquityDividendDao extends BaseDao
{
    protected function getModel(): string
    {
        return EquityDividend::class;
    }
}
