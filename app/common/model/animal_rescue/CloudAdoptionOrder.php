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
 * 云养月捐订单表
 * Class CloudAdoptionOrder
 * @package app\common\model\animal_rescue
 */
class CloudAdoptionOrder extends BaseModel
{
    /**
     * @return string
     */
    public static function tablePk(): string
    {
        return 'cloud_order_id';
    }

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return 'cloud_adoption_order';
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

    public function searchPaidAttr($query, $value)
    {
        $query->where('paid', $value);
    }

    public function searchOrderSnAttr($query, $value)
    {
        $query->where('order_sn', $value);
    }
}
