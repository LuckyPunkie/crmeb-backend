<?php
// +----------------------------------------------------------------------
// | AI 点餐 - 通话会话
// +----------------------------------------------------------------------

namespace app\common\model\store\aiOrder;

use app\common\model\BaseModel;

class AiOrderSession extends BaseModel
{
    const STATUS_ACTIVE = 0;
    const STATUS_ENDED = 1;
    const STATUS_FAILED = 2;

    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'ai_order_session';
    }
}
