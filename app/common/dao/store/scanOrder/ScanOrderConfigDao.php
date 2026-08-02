<?php
// +----------------------------------------------------------------------
// | 扫码下单 - 配置 Dao
// +----------------------------------------------------------------------

namespace app\common\dao\store\scanOrder;

use app\common\dao\BaseDao;
use app\common\model\store\scanOrder\ScanOrderConfig;

class ScanOrderConfigDao extends BaseDao
{
    protected function getModel(): string
    {
        return ScanOrderConfig::class;
    }
}
