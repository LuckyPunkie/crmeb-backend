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

namespace app\common\model\community;

use app\common\model\BaseModel;
use app\common\model\user\User;

class CommunityRecruit extends BaseModel
{
    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'community_recruit';
    }

    public function community()
    {
        return $this->hasOne(Community::class, 'community_id', 'community_id');
    }

    public function merchant()
    {
        return $this->hasOne(User::class, 'uid', 'mer_uid');
    }

    public function applies()
    {
        return $this->hasMany(CommunityRecruitApply::class, 'recruit_id', 'id');
    }

    public function searchCommunityIdAttr($query, $value)
    {
        $query->where('community_id', $value);
    }

    public function searchMerUidAttr($query, $value)
    {
        $query->where('mer_uid', $value);
    }

    public function searchStatusAttr($query, $value)
    {
        $query->where('status', $value);
    }
}
