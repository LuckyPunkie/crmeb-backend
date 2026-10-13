<?php

namespace app\common\model\user;

use app\common\model\BaseModel;

/**
 * 用户购物金账户模型，对应 user_shopping_credits 表，记录用户的购物金余额等信息
 */
class UserShoppingCredits extends BaseModel
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
     * @return string 数据表名 user_shopping_credits
     */
    public static function tableName(): string
    {
        return 'user_shopping_credits';
    }
}
