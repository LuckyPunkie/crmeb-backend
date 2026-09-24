<?php

namespace app\common\model\user;

use app\common\model\BaseModel;

class UserProfileField extends BaseModel
{
    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'user_profile_field';
    }
}
