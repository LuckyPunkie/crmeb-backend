<?php

namespace app\common\model\merchant;

use app\common\model\BaseModel;

/**
 * 店铺会员权益模型，对应 merchant_member_benefits 表，记录会员等级对应的权益内容
 */
class MerchantMemberBenefits extends BaseModel
{
    public const TYPE_MEMBER_PRICE = 1;
    public const TYPE_MEMBER_COUPON = 2;
    public const TYPE_SHOPPING_CREDITS = 3;
    public const TYPE_UPGRADE_GIFT = 4;

    public const SYSTEM_TYPE_NAMES = [
        self::TYPE_MEMBER_PRICE => '会员专享价',
        self::TYPE_MEMBER_COUPON => '会员专享券',
        self::TYPE_SHOPPING_CREDITS => '购物金',
        self::TYPE_UPGRADE_GIFT => '入会/升级有礼',
    ];

    /**
     * 获取模型主键字段名
     * @return string|null 主键字段名 id
     */
    public static function tablePk(): ?string
    {
        return 'id';
    }

    /**
     * 获取模型对应的数据表名
     * @return string 数据表名 merchant_member_benefits
     */
    public static function tableName(): string
    {
        return 'merchant_member_benefits';
    }
}
