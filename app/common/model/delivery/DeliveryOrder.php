<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------


namespace app\common\model\delivery;

use app\common\model\BaseModel;
use app\common\model\store\order\StoreOrder;
use app\common\model\store\order\StoreOrderStatus;
use app\common\model\system\merchant\Merchant;
use crmeb\services\DeliverySevices;

class DeliveryOrder extends BaseModel
{

    public static function tablePk(): string
    {
        return 'delivery_order_id';
    }

    public static function tableName(): string
    {
        return 'delivery_order';
    }

    public function getReceiverPhoneAttr($value)
    {
        if (env('SHOW_PHONE',false) && app('request')->hasMacro('adminInfo') && $value && is_numeric($value)){
            if (app('request')->userType() !== 2 || app('request')->adminInfo()['level'] != 0) {
                return substr_replace($value, '****', 3, 4);
            }
        }
        return $value;
    }

    public function setPickupImagesAttr($value): string
    {
        return is_array($value) ? implode(',', array_filter($value, static fn($item) => $item !== null && $item !== '')) : (string)$value;
    }

    public function getPickupImagesAttr($value): array
    {
        return $value ? array_values(array_filter(explode(',', $value), static fn($item) => $item !== '')) : [];
    }

    public function setConfirmImagesAttr($value): string
    {
        return is_array($value) ? implode(',', array_filter($value, static fn($item) => $item !== null && $item !== '')) : (string)$value;
    }

    public function getConfirmImagesAttr($value): array
    {
        return $value ? array_values(array_filter(explode(',', $value), static fn($item) => $item !== '')) : [];
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'mer_id','mer_id');
    }

    public function station()
    {
        return $this->hasOne(DeliveryStation::class, 'station_id','station_id');
    }

    public function storeOrder()
    {
        return $this->hasOne(StoreOrder::class, 'order_id','order_id')->order('create_time DESC, order_id DESC');
    }

    public function deliveryService()
    {
        return $this->hasOne(DeliveryService::class, 'service_id','service_id');
    }

    public function storeOrderStatus()
    {
        return $this->hasMany(StoreOrderStatus::class, 'order_id','order_id')->order('change_time DESC');
    }

    public function getDistanceAttr($value, $data)
    {
        if ((int)($data['station_type'] ?? 0) === 0 && (float)$value <= 0) {
            return $this->storeOrder['merchant_take_info'][$data['mer_id']]['distance'] ?? '';
        }

        if ($value >= 1000) {
            return round(($value / 1000),2) . ' km';
        } else {
            return $value . 'm';
        }
    }

    public function searchStatusAttr($query,$value)
    {
        $query->where('status',$value);
    }

    public function searchKeywordAttr($query,$value)
    {
        $merchantIds = Merchant::whereLike('mer_name', "%{$value}%")->column('mer_id');
        $query->where(function ($query) use ($value, $merchantIds) {
            $query->whereLike('order_sn|from_address', "%{$value}%");
            if ($merchantIds) {
                $query->whereOr('mer_id', 'in', $merchantIds);
            }
        });
    }

    public function searchMerIdAttr($query,$value)
    {
        $query->where('mer_id',$value);
    }

    public function searchStationIdAttr($query,$value)
    {
        $query->where('station_id',$value);
    }

    public function searchStationNameAttr($query, $value)
    {
        $ids = DeliveryStation::whereLike('station_name', "%{$value}%")->column('station_id');
        $query->whereIn('station_id', $ids);
    }

    public function searchServiceIdAttr($query,$value)
    {
        $query->where('service_id',$value);
    }

    public function searchStationTypeAttr($query,$value)
    {
        $query->where('station_type',$value);
    }

    public function searchOrderIdAttr($query,$value)
    {
        $query->where('order_id',$value);
    }
    public function searchSnAttr($query,$value)
    {
        $query->where('order_sn',$value);
    }
    public function searchDateAttr($query,$value)
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})-(\d{4}-\d{2}-\d{2})$/', $value, $matches)) {
            $value = str_replace('-', '/', $matches[1]) . '-' . str_replace('-', '/', $matches[2]);
        }
        getModelTime($query, $value);
    }
    public function searchOrderSnAttr($query,$value)
    {
        $ids = StoreOrder::whereLike('order_sn',"%{$value}%")->column('order_id');
        $query->whereIn('order_id', $ids);
    }
}
