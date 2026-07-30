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

namespace app\common\model\animal_rescue;

use app\common\model\BaseModel;
use app\common\model\user\User;

/**
 * 救助参与记录表
 * Class AnimalRescueParticipant
 * @package app\common\model\animal_rescue
 */
class AnimalRescueParticipant extends BaseModel
{
    /** 类型常量 */
    const TYPE_DONATE = 1;   // 救助捐款
    const TYPE_ADOPTION = 2; // 领养保证金
    const TYPE_CLOUD = 3;    // 云养月捐

    /** 状态常量 */
    const STATUS_COMPLETED = 1; // 已完成
    const STATUS_ACTIVE = 2;    // 进行中
    const STATUS_THAWED = 3;    // 已解冻

    /**
     * @return string
     */
    public static function tablePk(): string
    {
        return 'participant_id';
    }

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return 'animal_rescue_participant';
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->hasOne(User::class, 'uid', 'uid');
    }

    /**
     * 关联帖子
     */
    public function post()
    {
        return $this->hasOne(AnimalRescuePost::class, 'post_id', 'post_id');
    }

    // ==================== 搜索器 ====================

    public function searchUidAttr($query, $value)
    {
        $query->where('uid', $value);
    }

    public function searchPostIdAttr($query, $value)
    {
        $query->where('post_id', $value);
    }

    public function searchTypeAttr($query, $value)
    {
        $query->where('type', $value);
    }

    public function searchStatusAttr($query, $value)
    {
        $query->where('status', $value);
    }
}
