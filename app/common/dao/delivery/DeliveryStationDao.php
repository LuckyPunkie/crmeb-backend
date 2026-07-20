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
namespace app\common\dao\delivery;

use app\common\dao\BaseDao;
use app\common\model\delivery\DeliveryStation;

class DeliveryStationDao extends BaseDao
{
    const SWITCH_TYPE = [
        1 => 'switch_city', // 同城配送
        2 => 'switch_take', // 到店自提
    ];

    protected function getModel(): string
    {
        return DeliveryStation::class;
    }

    public function search($where)
    {
        $query = ($this->getModel()::getDB());

        $query->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            $query->where('mer_id', $where['mer_id']);
        })->when(isset($where['switch_city']) && !empty($where['switch_city']), function ($query) use ($where) {
            $query->where('switch_city', $where['switch_city']);
        })->when(isset($where['switch_take']) && !empty($where['switch_take']), function ($query) use ($where) {
            $query->where('switch_take', $where['switch_take']);
        })->when(isset($where['station_name']) && $where['station_name'] !== '', function ($query) use ($where) {
            $query->where('station_name', 'like', '%' . $where['station_name'] . '%');
        })->when(isset($where['contact_name']) && $where['contact_name'] !== '', function ($query) use ($where) {
            $query->where('contact_name', 'like', '%' . $where['contact_name'] . '%');
        })->when(isset($where['phone']) && $where['phone'] !== '', function ($query) use ($where) {
            $query->where('phone', $where['phone']);
        })->when(isset($where['station_address']) && $where['station_address'] !== '', function ($query) use ($where) {
            $query->where('station_address', 'like', '%' . $where['station_address'] . '%');
        })->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            $query->where('status', $where['status']);
        })->when(isset($where['swtich_type']) && $where['swtich_type'] !== '', function ($query) use ($where) {
            $query->where(self::SWITCH_TYPE[$where['swtich_type']], 1);
        })->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
            $query->where('type', $where['type']);
        })->when(isset($where['mer_delivery_type']) && $where['mer_delivery_type'] !== '', function ($query) use ($where) {
            $query->whereIn('type', $where['mer_delivery_type']);
        })->when(isset($where['name_and_address_search']) && $where['name_and_address_search'] !== '', function ($query) use ($where) {
            $query->where(function ($query) use ($where) {
                $query->where('station_name', 'like', '%' . $where['name_and_address_search'] . '%')
                    ->whereOr('station_address', 'like', '%' . $where['name_and_address_search'] . '%');
            });
        });

        return $query;
    }
}
