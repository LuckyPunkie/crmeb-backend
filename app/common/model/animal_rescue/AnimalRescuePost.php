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
use app\common\model\store\CityArea;
use app\common\model\system\merchant\Merchant;
use app\common\model\user\User;

/**
 * 流浪动物救助帖子表
 * Class AnimalRescuePost
 * @package app\common\model\animal_rescue
 */
class AnimalRescuePost extends BaseModel
{
    /** 类型常量 */
    const TYPE_RESCUE = 1;   // 救助
    const TYPE_ADOPTION = 2; // 领养
    const TYPE_CLOUD = 3;    // 云养/救助站月捐

    /** 状态常量（展示/生命周期，与拨款 fund_status 分离） */
    const STATUS_AUDITING = 0;   // 审核中
    const STATUS_ACTIVE = 1;     // 进行中
    const STATUS_COMPLETED = 2;  // 已完成
    const STATUS_CLOSED = 3;     // 已关闭
    const STATUS_REJECTED = -1;  // 审核驳回

    /** 拨款状态 fund_status */
    const FUND_NONE = 0;
    const FUND_RAISING = 1;
    const FUND_WAIT_VOUCHER = 2;
    const FUND_AUDITING = 3;
    const FUND_WAIT_PAY = 4;
    const FUND_PAID = 5;
    const FUND_REFUNDED = 6;
    const FUND_REJECTED = -1;

    /** 动物类型映射 */
    const ANIMAL_TYPE_MAP = [
        'dog' => '狗',
        'cat' => '猫',
        'rabbit' => '兔',
        'other' => '其他',
    ];

    /**
     * @return string
     */
    public static function tablePk(): string
    {
        return 'post_id';
    }

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return 'animal_rescue_post';
    }

    /**
     * 图片属性获取器 - 逗号分隔转为数组
     */
    public function getImagesAttr($value)
    {
        return $value ? explode(',', $value) : [];
    }

    /**
     * 关联发布者
     */
    public function author()
    {
        return $this->hasOne(User::class, 'uid', 'uid');
    }

    /**
     * 关联城市
     */
    public function city()
    {
        return $this->hasOne(CityArea::class, 'id', 'city_id');
    }

    /**
     * 关联救助站商户
     */
    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'mer_id', 'mer_id');
    }

    /**
     * 关联拨款审核
     */
    public function fundAudit()
    {
        return $this->hasOne(PostFundAudit::class, 'audit_id', 'audit_id');
    }

    public function searchFundStatusAttr($query, $value)
    {
        $query->where('fund_status', $value);
    }

    public function searchMerIdAttr($query, $value)
    {
        $query->where('mer_id', $value);
    }

    /**
     * 关联捐款订单
     */
    public function orders()
    {
        return $this->hasMany(AnimalRescueOrder::class, 'post_id', 'post_id');
    }

    /**
     * 关联参与记录
     */
    public function participants()
    {
        return $this->hasMany(AnimalRescueParticipant::class, 'post_id', 'post_id');
    }

    // ==================== 搜索器 ====================

    public function searchTypeAttr($query, $value)
    {
        $query->where('type', $value);
    }

    public function searchCityIdAttr($query, $value)
    {
        $query->where('city_id', $value);
    }

    public function searchStatusAttr($query, $value)
    {
        $query->where('status', $value);
    }

    public function searchIsShowAttr($query, $value)
    {
        $query->where('is_show', $value);
    }

    public function searchUidAttr($query, $value)
    {
        $query->where('uid', $value);
    }

    public function searchKeywordAttr($query, $value)
    {
        $query->whereLike('title|content|animal_name', "%{$value}%");
    }

    public function searchAnimalTypeAttr($query, $value)
    {
        $query->where('animal_type', $value);
    }

    public function searchPostIdAttr($query, $value)
    {
        $query->where('post_id', $value);
    }

    public function searchIsDelAttr($query, $value)
    {
        $query->where('is_del', $value);
    }
}
