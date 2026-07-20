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
use app\common\model\delivery\DeliveryOrder;
use app\common\model\store\order\StoreOrder;

class DeliveryOrderDao extends BaseDao
{

    protected function getModel(): string
    {
        return DeliveryOrder::class;
    }

    public function search(array $where)
    {
        return DeliveryOrder::getDB()
            ->when(isset($where['service_ids']) && $where['service_ids'] !== '', function ($query) use ($where) {
                $query->whereIn('service_id', $where['service_ids']);
            })->when(isset($where['mer_ids']) && $where['mer_ids'] !== '', function ($query) use ($where) {
                $query->whereIn('mer_id', $where['mer_ids']);
            })->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('mer_id', $where['mer_id']);
            })->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                $query->where('status', $where['status']);
            })->when(isset($where['station_id']) && $where['station_id'] !== '', function ($query) use ($where) {
                $query->where('station_id', $where['station_id']);
            })->when(isset($where['order_sn']) && $where['order_sn'] !== '', function ($query) use ($where) {
                $orderId = StoreOrder::getDB()->where('order_sn', $where['order_sn'])->value('order_id');
                $query->where('order_id', $orderId);
            })->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->where('order_sn', $where['keyword']);
            })->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                $date = explode('-', $where['date']);
                $startTime = date('Y-m-d', strtotime($date[0])) . ' 00:00:00';
                $endTime = date('Y-m-d', strtotime($date[1])) . ' 23:59:59';

                $query->whereTime('create_time', 'between', [$startTime, $endTime]);
            });
    }
}
