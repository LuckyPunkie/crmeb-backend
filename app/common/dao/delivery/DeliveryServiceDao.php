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
use app\common\model\delivery\DeliveryService;

class DeliveryServiceDao extends BaseDao
{

    protected function getModel(): string
    {
        return DeliveryService::class;
    }

    public function search(array $where)
    {
        return DeliveryService::getDB()->field('ds.*,u.nickname')->alias('ds')
            ->join('user u','u.uid = ds.uid','left')
            ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                $query->where('ds.status', $where['status']);
            })->when(isset($where['name']) && $where['name'] !== '', function ($query) use ($where) {
                $query->whereLike('ds.name', "%{$where['name']}%");
            })->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('ds.mer_id', $where['mer_id']);
            })->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
                $query->where('ds.uid', $where['uid']);
            })->when(isset($where['phone']) && $where['phone'] !== '', function ($query) use ($where) {
                $query->whereLike('ds.phone', "%{$where['phone']}%");
            })->when(isset($where['service_id']) && $where['service_id'] !== '', function ($query) use ($where) {
                $query->where('ds.service_id', $where['service_id']);
            })->when(isset($where['nickname']) && $where['nickname'] !== '', function ($query) use ($where) {
                $query->whereLike('u.nickname', "%{$where['nickname']}%");
            })->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->whereLike('ds.service_id|ds.name|ds.phone', "%{$where['keyword']}%");
            });
    }

    public function statisticsSearch(array $where)
    {
        return DeliveryService::getDB()->field('ds.*,u.nickname, count(do.delivery_order_id) as orderCount, sum(do.service_fee) AS serviceFee, sum(if(do.status = 2, 1, if(do.status = 3, 1, 0))) as pendingNum')->alias('ds')
            ->join('user u','u.uid = ds.uid','left')
            ->join('delivery_order do' ,'do.service_id = ds.service_id','left')
            ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                $query->where('ds.status', $where['status']);
            })->when(isset($where['name']) && $where['name'] !== '', function ($query) use ($where) {
                $query->whereLike('ds.name', "%{$where['name']}%");
            })->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('ds.mer_id', $where['mer_id']);
            })->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
                $query->where('ds.uid', $where['uid']);
            })->when(isset($where['phone']) && $where['phone'] !== '', function ($query) use ($where) {
                $query->whereLike('ds.phone', "%{$where['phone']}%");
            })->when(isset($where['service_id']) && $where['service_id'] !== '', function ($query) use ($where) {
                $query->where('ds.service_id', $where['service_id']);
            })->when(isset($where['nickname']) && $where['nickname'] !== '', function ($query) use ($where) {
                $query->whereLike('u.nickname', "%{$where['nickname']}%");
            })->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->whereLike('ds.service_id|ds.name|ds.phone', "%{$where['keyword']}%");
            })->when(isset($where['delivery_date']) && !empty($where['delivery_date']), function ($query) use ($where) {
                $query->whereBetween('do.create_time', $where['delivery_date']);
            })
            ->where('do.status', '<>', -1)
            ->group('ds.service_id');

    }
}
