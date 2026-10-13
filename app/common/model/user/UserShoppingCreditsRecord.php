<?php

namespace app\common\model\user;

use app\common\model\BaseModel;

/**
 * 用户购物金流水记录模型，对应 user_shopping_credits_record 表，记录用户购物金的收支变动明细
 */
class UserShoppingCreditsRecord extends BaseModel
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
     * @return string 数据表名 user_shopping_credits_record
     */
    public static function tableName(): string
    {
        return 'user_shopping_credits_record';
    }
}
