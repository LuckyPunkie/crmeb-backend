<?php
// +----------------------------------------------------------------------
// | 扫码下单 - 台号 Dao
// +----------------------------------------------------------------------

namespace app\common\dao\store\scanOrder;

use app\common\dao\BaseDao;
use app\common\model\store\scanOrder\ScanOrderTable;

class ScanOrderTableDao extends BaseDao
{
    protected function getModel(): string
    {
        return ScanOrderTable::class;
    }
}
