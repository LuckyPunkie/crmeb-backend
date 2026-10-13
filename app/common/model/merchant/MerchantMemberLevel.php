<?php

namespace app\common\model\merchant;

use app\common\model\BaseModel;

/**
 * 店铺会员等级模型，对应 merchant_member_level 表，记录店铺的会员等级及对应的权益、优惠券配置
 */
class MerchantMemberLevel extends BaseModel
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
     * @return string 数据表名 merchant_member_level
     */
    public static function tableName(): string
    {
        return 'merchant_member_level';
    }

    /**
     * 权益 ID 读取器：将数据库中逗号分隔的字符串转为去重后的整型数组，空值返回空数组
     * @param mixed $value 数据库中存储的权益 ID 原始值
     * @return array 权益 ID 整型数组
     */
    public function getBenefitsAttr($value)
    {
        return $value ? array_values(array_filter(array_map('intval', explode(',', $value)))) : [];
    }

    /**
     * 权益 ID 修改器：将整型数组转为逗号分隔的字符串存储，非数组时直接转为字符串
     * @param mixed $value 待写入的权益 ID 值（数组或字符串）
     * @return string 逗号分隔的权益 ID 字符串
     */
    public function setBenefitsAttr($value)
    {
        return is_array($value) ? implode(',', array_filter(array_map('intval', $value))) : (string)$value;
    }

    /**
     * 优惠券 ID 读取器：将数据库中逗号分隔的字符串转为去重后的整型数组，空值返回空数组
     * @param mixed $value 数据库中存储的优惠券 ID 原始值
     * @return array 优惠券 ID 整型数组
     */
    public function getCouponIdsAttr($value)
    {
        return $value ? array_values(array_filter(array_map('intval', explode(',', $value)))) : [];
    }

    /**
     * 优惠券 ID 修改器：将整型数组转为逗号分隔的字符串存储，非数组时直接转为字符串
     * @param mixed $value 待写入的优惠券 ID 值（数组或字符串）
     * @return string 逗号分隔的优惠券 ID 字符串
     */
    public function setCouponIdsAttr($value)
    {
        return is_array($value) ? implode(',', array_filter(array_map('intval', $value))) : (string)$value;
    }
}
