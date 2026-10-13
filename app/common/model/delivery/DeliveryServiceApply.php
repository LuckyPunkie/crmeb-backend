<?php

namespace app\common\model\delivery;

use app\common\model\BaseModel;
use app\common\model\system\admin\Admin;
use app\common\model\user\User;

class DeliveryServiceApply extends BaseModel
{
    protected $updateTime = 'update_time';

    public static function tablePk(): string
    {
        return 'delivery_service_apply_id';
    }

    public static function tableName(): string
    {
        return 'delivery_service_apply';
    }

    public function setCertificateImagesAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }

    public function getCertificateImagesAttr($value): array
    {
        return $value ? explode(',', $value) : [];
    }

    public function setExtendAttr($value)
    {
        return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
    }

    public function getExtendAttr($value): array
    {
        if (!$value) {
            return [];
        }
        $extend = json_decode($value, true);
        return is_array($extend) ? $extend : [];
    }

    public function user()
    {
        return $this->hasOne(User::class, 'uid', 'uid')->field('uid,nickname,phone,avatar');
    }

    public function auditAdmin()
    {
        return $this->hasOne(Admin::class, 'admin_id', 'audit_admin_id')->field('admin_id,real_name,account');
    }
}
