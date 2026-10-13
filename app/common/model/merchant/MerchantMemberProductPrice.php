<?php

namespace app\common\model\merchant;

use app\common\model\BaseModel;

/**
 * 店铺会员商品价格模型，对应 merchant_member_product_price 表，记录会员等级对应的商品专属价格
 */
class MerchantMemberProductPrice extends BaseModel
{
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
     * @return string 数据表名 merchant_member_product_price
     */
    public static function tableName(): string
    {
        return 'merchant_member_product_price';
    }
}
