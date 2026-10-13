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

namespace app\common\model\store\product;

use app\common\model\BaseModel;
use app\common\model\store\order\StoreOrder;
use app\common\model\store\order\StoreOrderProduct;
use app\common\model\user\User;

class StoreUserCard extends BaseModel
{
    public const STATUS_DISABLED = 0;
    public const STATUS_AVAILABLE = 1;
    public const STATUS_USED = 2;
    public const STATUS_EXPIRED = 3;
    public const STATUS_REFUNDED = 4;
    public const STATUSES = [
        self::STATUS_DISABLED,
        self::STATUS_AVAILABLE,
        self::STATUS_USED,
        self::STATUS_EXPIRED,
        self::STATUS_REFUNDED,
    ];

    public const USED_COUNT_DEFAULT = 0;
    public const REMAINING_COUNT_EMPTY = 0;
    public const UNLIMITED_REMAINING_COUNT = -1;


    public static function tablePk(): ?string
    {
        return 'user_card_id';
    }

    public static function tableName(): string
    {
        return 'store_user_card';
    }

    public function rule()
    {
        return $this->hasOne(StoreProductCardRule::class, 'card_rule_id', 'card_rule_id');
    }

    public function product()
    {
        return $this->hasOne(Product::class, 'product_id', 'product_id');
    }

    public function order()
    {
        return $this->hasOne(StoreOrder::class, 'order_id', 'order_id');
    }

    public function orderProduct()
    {
        return $this->hasOne(StoreOrderProduct::class, 'order_product_id', 'order_product_id');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'uid', 'uid');
    }

    public function verifyLog()
    {
        return $this->hasMany(StoreUserCardVerifyLog::class, 'user_card_id', 'user_card_id');
    }
}
