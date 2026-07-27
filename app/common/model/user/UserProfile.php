<?php

namespace app\common\model\user;

use app\common\model\BaseModel;

class UserProfile extends BaseModel
{
    protected $updateTime = false;

    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'user_profile';
    }
}
