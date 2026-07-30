<?php

namespace app\common\model\user;

use app\common\model\BaseModel;

class UserCertification extends BaseModel
{
    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'user_certification';
    }

    public function user()
    {
        return $this->hasOne(User::class, 'uid', 'uid');
    }
}
