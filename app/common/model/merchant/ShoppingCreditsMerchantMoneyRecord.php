<?php

namespace app\common\model\merchant;

use app\common\model\BaseModel;

/**
 * 购物金商户资金记录模型，对应 shopping_credits_merchant_money_record 表，记录购物金相关的商户资金变动流水
 */
class ShoppingCreditsMerchantMoneyRecord extends BaseModel
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
     * @return string 数据表名 shopping_credits_merchant_money_record
     */
    public static function tableName(): string
    {
        return 'shopping_credits_merchant_money_record';
    }
}
