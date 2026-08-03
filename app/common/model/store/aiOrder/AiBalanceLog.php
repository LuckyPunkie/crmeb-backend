<?php
// +----------------------------------------------------------------------
// | AI 点餐 - 余额流水
// +----------------------------------------------------------------------

namespace app\common\model\store\aiOrder;

use app\common\model\BaseModel;

class AiBalanceLog extends BaseModel
{
    const TYPE_RECHARGE = 'recharge';
    const TYPE_DEDUCT = 'deduct';
    const TYPE_ADJUST = 'adjust';

    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'ai_balance_log';
    }
}
