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
            })->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
                $query->where('ds.type', $where['type']);
            })->when(isset($where['is_del']) && $where['is_del'] !== '', function ($query) use ($where) {
                $query->where('ds.is_del', $where['is_del']);
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

    /**
     * 构建配送员统计查询：按配送订单时间统计订单数量、状态及配送费
     * @param array $where 配送员及配送日期筛选条件
     * @return \think\db\BaseQuery 配送员统计查询对象
     */
    public function statisticsSearch(array $where)
    {
        return DeliveryService::getDB()
            ->field('ds.*, count(do.delivery_order_id) as orderCount, COALESCE(sum(CASE WHEN do.status = 4 THEN do.service_fee ELSE 0 END), 0) AS serviceFee, sum(CASE WHEN do.status IN (0, 1, 2, 3) THEN 1 ELSE 0 END) as pendingNum, sum(CASE WHEN do.status = 4 THEN 1 ELSE 0 END) as completedNum')
            ->alias('ds')
            ->join('delivery_order do' ,'do.service_id = ds.service_id','left')
            ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                $query->where('ds.status', $where['status']);
            })->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
                $query->where('ds.type', $where['type']);
            })->when(isset($where['is_del']) && $where['is_del'] !== '', function ($query) use ($where) {
                $query->where('ds.is_del', $where['is_del']);
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
            })->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->whereLike('ds.service_id|ds.name|ds.phone', "%{$where['keyword']}%");
            })->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                $date = $where['date'];
                // 自定义日期区间不走 getModelTime：其 default 分支的 whereBetweenTime 会按主表
                // delivery_service 的 int 型 create_time 把值绑定成时间戳，而联查的 do.create_time
                // 是 timestamp 列，导致区间查询永远无结果；这里与 lately30 等预设分支保持一致，
                // 直接用日期字符串绑定 between。
                if (strpos($date, '-') !== false && strlen(explode('-', $date, 2)[0]) > 4) {
                    [$startTime, $endTime] = explode('-', $date, 2);
                    $query->whereBetween('do.create_time', [
                        date('Y-m-d 00:00:00', strtotime($startTime)),
                        date('Y-m-d 23:59:59', strtotime($endTime)),
                    ]);
                } else {
                    getModelTime($query, $date, 'do.create_time');
                }
            })->when((!isset($where['date']) || $where['date'] === '') && isset($where['delivery_date']) && !empty($where['delivery_date']), function ($query) use ($where) {
                $query->whereBetween('do.create_time', $where['delivery_date']);
            })
            ->where('do.status', '<>', -1)
            ->group('ds.service_id');

    }
}
