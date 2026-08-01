<?php
namespace app\common\model\user;

use app\common\model\BaseModel;

class UserPushClient extends BaseModel
{
    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'user_push_client';
    }
}
