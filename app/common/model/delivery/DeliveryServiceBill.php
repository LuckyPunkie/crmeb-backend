<?php

namespace app\common\model\delivery;

use app\common\model\BaseModel;
use app\common\model\store\order\StoreOrder;

class DeliveryServiceBill extends BaseModel
{
    protected $updateTime = 'update_time';

    public static function tablePk(): string
    {
        return 'delivery_service_bill_id';
    }

    public static function tableName(): string
    {
        return 'delivery_service_bill';
    }

    public function orderInfo()
    {
        return $this->hasOne(StoreOrder::class, 'order_id', 'order_id');
    }

    public function deliveryService()
    {
        return $this->hasOne(DeliveryService::class, 'service_id', 'service_id');
    }

    public function deliveryOrder()
    {
        return $this->hasOne(DeliveryOrder::class, 'delivery_order_id', 'delivery_order_id');
    }

    public function withdrawInfo()
    {
        return $this->hasOne(
            DeliveryServiceWithdraw::class,
            'delivery_service_bill_id',
            'delivery_service_bill_id'
        );
    }
}
