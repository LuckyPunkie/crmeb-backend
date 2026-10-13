<?php

namespace app\common\model\user;

use app\common\model\BaseModel;

class UserFields extends BaseModel
{

    public static function tablePk(): string
    {
        return 'id';
    }


    public static function tableName(): string
    {
        return 'user_fields';
    }

    public function searchUidAttr($query, $value)
    {
        if (!empty($value)) $query->where('uid', $value);
    }

}
