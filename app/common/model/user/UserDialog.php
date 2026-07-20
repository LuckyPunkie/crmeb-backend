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

class UserDialog extends BaseModel
{
    public static function tablePk(): ?string
    {
        return 'dialog_id';
    }

    public static function tableName(): string
    {
        return 'user_dialog';
    }

    public function userA()
    {
        return $this->hasOne(User::class, 'uid', 'uid_a')->field('uid,nickname,avatar');
    }

    public function userB()
    {
        return $this->hasOne(User::class, 'uid', 'uid_b')->field('uid,nickname,avatar');
    }
}
