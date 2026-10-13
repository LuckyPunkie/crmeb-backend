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
use app\common\model\user\User;
use app\common\model\system\merchant\Merchant;
use crmeb\services\DeliverySevices;

class DeliveryService extends BaseModel
{

    public static function tablePk(): string
    {
        return 'service_id';
    }

    public static function tableName(): string
    {
        return 'delivery_service';
    }

    public function user()
    {
        return $this->hasOne(User::class,'uid','uid');
    }

    public function deliveryOrder()
    {
        return $this->hasMany(DeliveryOrder::class,'service_id','service_id')
            ->field('delivery_order_id,station_id,order_id,order_sn as delivrey_order_sn,status,service_id,service_fee,fee,create_time')
            ->with([
                'station' => function ($query) {
                    $query->field('station_id,station_name');
                },
                'storeOrder' => function ($query) {
                    $query->field('order_id,order_sn,pay_price')->with(['orderProduct']);
                }
            ])
            ->where('status', '<>', -1)->group('order_id')->order('create_time DESC, order_id DESC');
    }

    public function searchStatusAttr($query,$value)
    {
        $query->where('status',$value);
    }

    public function searchMerIdAttr($query,$value)
    {
        $query->where('mer_id',$value);
    }

    public function searchServiceIdAttr($query,$value)
    {
        $query->where('service_id',$value);
    }

    public function searchIsDelAttr($query,$value)
    {
        $query->where('is_del',$value);
    }

    public function searchTypeAttr($query,$value)
    {
        $query->where('type',$value);
    }

    public function searchDateAttr($query,$value)
    {
        getModelTime($query, $value, 'create_time');
    }
}
