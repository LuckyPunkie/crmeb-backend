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

class CommunityPaidOrder extends BaseModel
{
    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'community_paid_order';
    }

    public function paidContent()
    {
        return $this->hasOne(CommunityPaidContent::class, 'id', 'paid_content_id');
    }

    public function community()
    {
        return $this->hasOne(Community::class, 'community_id', 'community_id');
    }

    public function buyer()
    {
        return $this->hasOne(\app\common\model\user\User::class, 'uid', 'buyer_uid');
    }

    public function seller()
    {
        return $this->hasOne(\app\common\model\user\User::class, 'uid', 'seller_uid');
    }

    public function searchOrderNoAttr($query, $value)
    {
        $query->where('order_no', $value);
    }

    public function searchPaidContentIdAttr($query, $value)
    {
        $query->where('paid_content_id', $value);
    }

    public function searchCommunityIdAttr($query, $value)
    {
        $query->where('community_id', $value);
    }

    public function searchBuyerUidAttr($query, $value)
    {
        $query->where('buyer_uid', $value);
    }

    public function searchSellerUidAttr($query, $value)
    {
        $query->where('seller_uid', $value);
    }

    public function searchPayStatusAttr($query, $value)
    {
        $query->where('pay_status', $value);
    }
}
