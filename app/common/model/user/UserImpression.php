<?php

namespace app\common\model\user;

use app\common\model\BaseModel;

class UserImpression extends BaseModel
{
    public static function tablePk(): ?string
    {
        return 'impression_id';
    }

    public static function tableName(): string
    {
        return 'user_impression';
    }

    public function fromUser()
    {
        return $this->hasOne(User::class, 'uid', 'from_uid')->field('uid,nickname,avatar,sex');
    }

    public function searchOwnerUidAttr($query, $value)
    {
        $query->where('owner_uid', $value);
    }

    public function searchFromUidAttr($query, $value)
    {
        $query->where('from_uid', $value);
    }

    public function searchIsDeletedAttr($query, $value)
    {
        $query->where('is_deleted', $value);
    }
}
