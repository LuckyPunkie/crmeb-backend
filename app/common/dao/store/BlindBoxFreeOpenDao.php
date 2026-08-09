<?php

namespace app\common\dao\store;

use app\common\dao\BaseDao;
use app\common\model\store\BlindBoxFreeOpen;

class BlindBoxFreeOpenDao extends BaseDao
{
    protected function getModel(): string
    {
        return BlindBoxFreeOpen::class;
    }
}
