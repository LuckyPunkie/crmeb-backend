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

namespace app\common\dao\circle;

use app\common\dao\BaseDao;
use app\common\model\circle\CircleFinancialRecord;

class CircleFinancialRecordDao extends BaseDao
{

    protected function getModel(): string
    {
        return CircleFinancialRecord::class;
    }

    public function search(array $where)
    {
        $query = ($this->getModel()::getDB())->field(
            'c.*, o.create_time as order_create_time, o.status as order_status, o.pay_price as order_total_price'
        )->alias('c')->join('store_order o', 'o.order_id = c.order_id', 'left');

        $query->when(isset($where['circle_id']) && $where['circle_id'] !== '', function ($query) use ($where) {
            $query->where('c.circle_id', $where['circle_id']);
        })->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            $query->where('c.mer_id', $where['mer_id']);
        })->when(isset($where['agent_id']) && $where['agent_id'] !== '', function ($query) use ($where) {
            $query->where('c.agent_id', $where['agent_id']);
        })->when(isset($where['order_id']) && $where['order_id'] !== '', function ($query) use ($where) {
            $query->where('c.order_id', $where['order_id']);
        })->when(isset($where['order_sn']) && $where['order_sn'] !== '', function ($query) use ($where) {
            $query->where('c.order_sn', $where['order_sn']);
        })->when(isset($where['order_status']) && $where['order_status'] !== '', function ($query) use ($where) {
            $query->where('o.status', $where['order_status']);
        })->when(isset($where['order_time']) && !empty($where['order_time']), function ($query) use ($where) {
            $where['order_time'][0] = $where['order_time'][0] . ' 00:00:00';
            $where['order_time'][1] = $where['order_time'][1] . ' 23:59:59';
            $query->whereBetween('o.create_time', $where['order_time']);
        })->when(isset($where['circle_name']) && $where['circle_name'] !== '', function ($query) use ($where) {
            $query->whereLike('c.circle_name', "%{$where['circle_name']}%");
        })->when(isset($where['mer_name']) && $where['mer_name'] !== '', function ($query) use ($where) {
            $query->whereLike('c.mer_name', "%{$where['mer_name']}%");
        })->when(isset($where['agent_name']) && $where['agent_name'] !== '', function ($query) use ($where) {
            $query->whereLike('c.agent_name', "%{$where['agent_name']}%");
        });

        $query->order('c.create_time DESC');

        return $query;
    }
}
