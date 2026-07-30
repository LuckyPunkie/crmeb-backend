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
 * 领养申请表
 * Class AdoptionApplication
 * @package app\common\model\animal_rescue
 */
class AdoptionApplication extends BaseModel
{
    /** 状态常量 */
    const STATUS_AUDITING = 1;   // 审核中
    const STATUS_APPROVED = 2;   // 审核通过
    const STATUS_ADOPTED = 3;    // 已领养
    const STATUS_COMPLETED = 4;  // 已完成
    const STATUS_REJECTED = -1;  // 审核拒绝

    /** 住房类型 */
    const HOUSING_OWNED = 'owned';   // 自有
    const HOUSING_RENTED = 'rented'; // 租住

    /**
     * @return string
     */
    public static function tablePk(): string
    {
        return 'application_id';
    }

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return 'adoption_application';
    }

    /**
     * 关联申请人
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

    /**
     * 关联保证金（取最新一条）
     */
    public function deposit()
    {
        return $this->hasOne(AdoptionDeposit::class, 'application_id', 'application_id')->order('deposit_id', 'desc');
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

    public function searchStatusAttr($query, $value)
    {
        $query->where('status', $value);
    }
}
