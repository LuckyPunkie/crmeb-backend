<?php
// +----------------------------------------------------------------------
// | 扫码下单 - 商户配置
// +----------------------------------------------------------------------

namespace app\common\model\store\scanOrder;

use app\common\model\BaseModel;

class ScanOrderConfig extends BaseModel
{
    public static function tablePk(): string
    {
        return 'mer_id';
    }

    public static function tableName(): string
    {
        return 'scan_order_config';
    }
}
