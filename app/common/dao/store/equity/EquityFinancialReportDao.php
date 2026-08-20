<?php

namespace app\common\dao\store\equity;

use app\common\dao\BaseDao;
use app\common\model\store\equity\EquityFinancialReport;

class EquityFinancialReportDao extends BaseDao
{
    protected function getModel(): string
    {
        return EquityFinancialReport::class;
    }
}
