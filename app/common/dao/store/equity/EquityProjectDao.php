<?php

namespace app\common\dao\store\equity;

use app\common\dao\BaseDao;
use app\common\model\store\equity\EquityProject;

class EquityProjectDao extends BaseDao
{
    protected function getModel(): string
    {
        return EquityProject::class;
    }
}
