<?php

namespace app\common\model\merchant;

use app\common\model\BaseModel;

/**
 * 店铺会员等级记录模型，对应 merchant_member_level_record 表，记录用户入会、升级及奖励发放情况
 */
class MerchantMemberLevelRecord extends BaseModel
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
     * @return string 数据表名 merchant_member_level_record
     */
    public static function tableName(): string
    {
        return 'merchant_member_level_record';
    }
}
