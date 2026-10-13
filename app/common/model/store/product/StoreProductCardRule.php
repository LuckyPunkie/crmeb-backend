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

class StoreProductCardRule extends BaseModel
{
    public const USE_LIMIT_TYPE_FIXED = 1;
    public const USE_LIMIT_TYPE_UNLIMITED = 2;
    public const USE_LIMIT_TYPES = [
        self::USE_LIMIT_TYPE_FIXED,
        self::USE_LIMIT_TYPE_UNLIMITED,
    ];
    public const USE_LIMIT_TYPE_LABELS = [
        self::USE_LIMIT_TYPE_FIXED => '固定次数',
        self::USE_LIMIT_TYPE_UNLIMITED => '有效期内不限次数',
    ];

    public const VALIDITY_TYPE_FOREVER = 1;
    public const VALIDITY_TYPE_AFTER_PAY_DAYS = 2;
    public const VALIDITY_TYPE_DATE_RANGE = 3;
    public const VALIDITY_TYPES = [
        self::VALIDITY_TYPE_FOREVER,
        self::VALIDITY_TYPE_AFTER_PAY_DAYS,
        self::VALIDITY_TYPE_DATE_RANGE,
    ];
    public const VALIDITY_TYPE_LABELS = [
        self::VALIDITY_TYPE_FOREVER => '永久有效',
        self::VALIDITY_TYPE_AFTER_PAY_DAYS => '购买后有效天数',
        self::VALIDITY_TYPE_DATE_RANGE => '固定有效期',
    ];

    public const REFUND_RULE_UNVERIFIED = 1;
    public const REFUND_RULE_DISABLED = 2;
    public const REFUND_RULES = [
        self::REFUND_RULE_UNVERIFIED,
        self::REFUND_RULE_DISABLED,
    ];
    public const REFUND_RULE_LABELS = [
        self::REFUND_RULE_UNVERIFIED => '未核销可退',
        self::REFUND_RULE_DISABLED => '不支持退款',
    ];

    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;
    public const STATUSES = [
        self::STATUS_DISABLED,
        self::STATUS_ENABLED,
    ];
    public const STATUS_LABELS = [
        self::STATUS_DISABLED => '禁用',
        self::STATUS_ENABLED => '启用',
    ];

    public const IS_DEL_NO = 0;
    public const IS_DEL_YES = 1;
    public const TOTAL_COUNT_EMPTY = 0;
    public const VALID_DAYS_EMPTY = 0;

    public static function tablePk(): ?string
    {
        return 'card_rule_id';
    }

    public static function tableName(): string
    {
        return 'store_product_card_rule';
    }

    public function attrValue()
    {
        return $this->hasOne(ProductAttrValue::class, 'value_id', 'value_id');
    }

    public function product()
    {
        return $this->hasOne(Product::class, 'product_id', 'product_id');
    }
}
