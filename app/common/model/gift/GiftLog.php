<?php

namespace app\common\model\gift;

use app\common\model\BaseModel;

class GiftLog extends BaseModel
{
    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'gift_log';
    }
}
