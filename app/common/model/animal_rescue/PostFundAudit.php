<?php

namespace app\common\model\animal_rescue;

use app\common\model\BaseModel;
use app\common\model\user\User;

/**
 * 救助帖拨款审核记录
 */
class PostFundAudit extends BaseModel
{
    const STATUS_PENDING = 0;
    const STATUS_PASSED = 1;
    const STATUS_REJECTED = 2;

    public static function tablePk(): string
    {
        return 'audit_id';
    }

    public static function tableName(): string
    {
        return 'post_fund_audit';
    }

    public function getInvoiceImagesAttr($value)
    {
        if (!$value) return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function setInvoiceImagesAttr($value)
    {
        return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;
    }

    public function getOtherFilesAttr($value)
    {
        if (!$value) return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function setOtherFilesAttr($value)
    {
        return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;
    }

    public function post()
    {
        return $this->hasOne(AnimalRescuePost::class, 'post_id', 'post_id');
    }

    public function author()
    {
        return $this->hasOne(User::class, 'uid', 'uid');
    }

    public function searchStatusAttr($query, $value)
    {
        $query->where('status', $value);
    }

    public function searchPostIdAttr($query, $value)
    {
        $query->where('post_id', $value);
    }
}
