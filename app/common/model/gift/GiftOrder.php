<?php

namespace app\common\model\gift;

use app\common\model\BaseModel;

class GiftOrder extends BaseModel
{
    public static function tablePk(): string
    {
        return 'order_id';
    }

    public static function tableName(): string
    {
        return 'gift_order';
    }

    public function sender()
    {
        return $this->hasOne(\app\common\model\user\User::class, 'uid', 'sender_id');
    }

    public function receiver()
    {
        return $this->hasOne(\app\common\model\user\User::class, 'uid', 'receiver_id');
    }

    public function searchOrderNoAttr($query, $value)
    {
        $query->where('order_no', $value);
    }

    public function searchSenderIdAttr($query, $value)
    {
        $query->where('sender_id', $value);
    }

    public function searchReceiverIdAttr($query, $value)
    {
        $query->where('receiver_id', $value);
    }

    public function searchPayStatusAttr($query, $value)
    {
        $query->where('pay_status', $value);
    }

    public function searchSceneTypeAttr($query, $value)
    {
        $query->where('scene_type', $value);
    }
}
