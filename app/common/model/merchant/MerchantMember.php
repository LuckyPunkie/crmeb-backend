<?php

namespace app\common\model\merchant;

use app\common\model\BaseModel;
use app\common\model\system\merchant\Merchant;
use app\common\model\user\User;

/**
 * 店铺会员模型，对应 merchant_member 表，记录用户在商户下的会员身份及等级信息
 */
class MerchantMember extends BaseModel
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
     * @return string 数据表名 merchant_member
     */
    public static function tableName(): string
    {
        return 'merchant_member';
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
     * 关联会员所属店铺。
     */
    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'mer_id', 'mer_id');
    }

    /**
     * 一对一关联会员等级表 MerchantMemberLevel，并限定同一商户下的等级（关联字段 level，且 mer_id 与 merchant_member.mer_id 一致）
     * @return \think\model\relation\HasOne 会员等级关联
     */
    public function levelInfo()
    {
        return $this->hasOne(MerchantMemberLevel::class, 'level', 'level')
            ->whereColumn('mer_id', 'merchant_member.mer_id');
    }
}
