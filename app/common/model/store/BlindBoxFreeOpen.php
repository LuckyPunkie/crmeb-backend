<?php

namespace app\common\model\store;

use app\common\model\BaseModel;

class BlindBoxFreeOpen extends BaseModel
{
    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'blindbox_free_open';
    }
}
