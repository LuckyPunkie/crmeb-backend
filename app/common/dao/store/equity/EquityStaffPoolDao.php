<?php

namespace app\common\dao\store\equity;

use app\common\dao\BaseDao;
use app\common\model\store\equity\EquityStaffPool;

class EquityStaffPoolDao extends BaseDao
{
    protected function getModel(): string
    {
        return EquityStaffPool::class;
    }
}
