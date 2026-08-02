<?php
// +----------------------------------------------------------------------
// | 扫码下单 - 台号
// +----------------------------------------------------------------------

namespace app\common\model\store\scanOrder;

use app\common\model\BaseModel;

class ScanOrderTable extends BaseModel
{
    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'scan_order_table';
    }
}
