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
 * 领养保证金表
 * Class AdoptionDeposit
 * @package app\common\model\animal_rescue
 */
class AdoptionDeposit extends BaseModel
{
    /** 状态常量 */
    const STATUS_FROZEN = 1;   // 冻结中
    const STATUS_THAWED = 2;   // 已解冻
    const STATUS_DEDUCTED = 3; // 已扣除(违约)

    /**
     * @return string
     */
    public static function tablePk(): string
    {
        return 'deposit_id';
    }

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return 'adoption_deposit';
    }

    /**
     * 关联领养人
     */
    public function user()
    {
        return $this->hasOne(User::class, 'uid', 'uid');
    }

    /**
     * 关联领养申请
     */
    public function application()
    {
        return $this->hasOne(AdoptionApplication::class, 'application_id', 'application_id');
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

    public function searchStatusAttr($query, $value)
    {
        $query->where('status', $value);
    }

    public function searchApplicationIdAttr($query, $value)
    {
        $query->where('application_id', $value);
    }

    public function searchOrderSnAttr($query, $value)
    {
        $query->where('order_sn', $value);
    }
}
