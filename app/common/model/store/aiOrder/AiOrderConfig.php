<?php
// +----------------------------------------------------------------------
// | AI 点餐 - 商户配置
// +----------------------------------------------------------------------

namespace app\common\model\store\aiOrder;

use app\common\model\BaseModel;

class AiOrderConfig extends BaseModel
{
    public static function tablePk(): string
    {
        return 'mer_id';
    }

    public static function tableName(): string
    {
        return 'ai_order_config';
    }
}
