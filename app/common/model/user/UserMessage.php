<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace app\common\model\user;

use app\common\model\BaseModel;
use think\model\concern\SoftDelete;

class UserMessage extends BaseModel
{
    use SoftDelete;

    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = null;

    public static function tablePk(): ?string
    {
        return 'message_id';
    }

    public static function tableName(): string
    {
        return 'user_message';
    }

    public function fromUser()
    {
        return $this->hasOne(User::class, 'uid', 'from_uid')->field('uid,nickname,avatar');
    }

    public function toUser()
    {
        return $this->hasOne(User::class, 'uid', 'to_uid')->field('uid,nickname,avatar');
    }

    public function dialog()
    {
        return $this->hasOne(UserDialog::class, 'dialog_id', 'dialog_id');
    }

    public function searchDialogIdAttr($query, $value)
    {
        $query->where('dialog_id', $value);
    }

    public function searchFromUidAttr($query, $value)
    {
        $query->where('from_uid', $value);
    }

    public function searchToUidAttr($query, $value)
    {
        $query->where('to_uid', $value);
    }

    public function searchIsReadAttr($query, $value)
    {
        $query->where('is_read', $value);
    }
}
