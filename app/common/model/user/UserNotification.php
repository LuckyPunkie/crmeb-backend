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

class UserNotification extends BaseModel
{
    public static function tablePk(): ?string
    {
        return 'notification_id';
    }

    public static function tableName(): string
    {
        return 'user_notification';
    }

    public function fromUser()
    {
        return $this->hasOne(User::class, 'uid', 'from_uid')->field('uid,nickname,avatar');
    }

    public function searchUidAttr($query, $value)
    {
        $query->where('uid', $value);
    }

    public function searchIsReadAttr($query, $value)
    {
        $query->where('is_read', $value);
    }

    public function searchTypeAttr($query, $value)
    {
        $query->where('type', $value);
    }
}
