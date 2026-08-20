<?php

namespace app\common\dao\store\equity;

use app\common\dao\BaseDao;
use app\common\model\store\equity\EquityInvestRefund;

class EquityInvestRefundDao extends BaseDao
{
    protected function getModel(): string
    {
        return EquityInvestRefund::class;
    }
}
