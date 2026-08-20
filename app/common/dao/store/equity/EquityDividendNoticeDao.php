<?php

namespace app\common\dao\store\equity;

use app\common\dao\BaseDao;
use app\common\model\store\equity\EquityDividendNotice;

class EquityDividendNoticeDao extends BaseDao
{
    protected function getModel(): string
    {
        return EquityDividendNotice::class;
    }
}
