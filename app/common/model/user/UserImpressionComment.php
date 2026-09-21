<?php

namespace app\common\model\user;

use app\common\model\BaseModel;

class UserImpressionComment extends BaseModel
{
    public static function tablePk(): ?string
    {
        return 'comment_id';
    }

    public static function tableName(): string
    {
        return 'user_impression_comment';
    }

    public function fromUser()
    {
        return $this->hasOne(User::class, 'uid', 'from_uid')->field('uid,nickname,avatar,sex');
    }

    public function replyToUser()
    {
        return $this->hasOne(User::class, 'uid', 'reply_to_uid')->field('uid,nickname,avatar,sex');
    }

    public function searchImpressionIdAttr($query, $value)
    {
        $query->where('impression_id', $value);
    }

    public function searchParentIdAttr($query, $value)
    {
        $query->where('parent_id', $value);
    }

    public function searchIsDeletedAttr($query, $value)
    {
        $query->where('is_deleted', $value);
    }
}
