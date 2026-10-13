<?php

namespace app\common\model\merchant;

use app\common\model\BaseModel;
use app\common\model\user\User;

/**
 * 购物金退款订单模型，对应 shopping_credits_refund_order 表，记录购物金充值订单的退款信息
 */
class ShoppingCreditsRefundOrder extends BaseModel
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
     * @return string 数据表名 shopping_credits_refund_order
     */
    public static function tableName(): string
    {
        return 'shopping_credits_refund_order';
    }

    /**
     * 一对一关联用户表 User（关联字段 uid）
     * @return \think\model\relation\HasOne 用户关联
     */
    public function user()
    {
        return $this->hasOne(User::class, 'uid', 'uid');
    }

    /**
     * 一对一关联购物金充值订单表 ShoppingCreditsOrder（关联字段 order_no）
     * @return \think\model\relation\HasOne 购物金充值订单关联
     */
    public function order()
    {
        return $this->hasOne(ShoppingCreditsOrder::class, 'order_no', 'order_no');
    }
}
