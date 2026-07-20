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


namespace app\common\dao\store\order;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\store\order\StoreOrderStatus;
use app\common\repositories\store\order\StoreOrderStatusRepository;

/**
 * Class StoreOrderStatusDao
 * @package app\common\dao\store\order
 * @author xaboy
 * @day 2020/6/12
 */
class StoreOrderStatusDao extends BaseDao
{

    /**
     * @return string
     * @author xaboy
     * @day 2020/6/12
     */
    protected function getModel(): string
    {
        return StoreOrderStatus::class;
    }

    /**
     * 根据条件搜索数据。
     *
     * 本函数用于构建查询条件，根据传入的$where数组，动态地添加查询条件到数据库查询中。
     * 这样做的目的是为了提高代码的灵活性和可维护性，避免硬编码的查询条件，同时允许根据不同的条件进行数据检索。
     *
     * @param array $where 包含搜索条件的数组。数组的键是条件的字段名，值是条件的值。
     *                    如果值为空字符串或者未设置，该条件将被忽略。
     * @return static 返回构建好的查询对象，可以进一步调用其他查询方法，比如获取数据。
     */
    public function search($where)
    {
        // 获取数据库实例，并通过链式调用进行条件构建
        $query = ($this->getModel()::getDB())
            // 当'id'字段在$where数组中存在且不为空时，添加where条件查询订单号
            ->when(isset($where['id']) && $where['id'] !== '', function ($query) use ($where) {
                $query->where('order_id', $where['id']);
            })
            // 当'type'字段在$where数组中存在且不为空时，添加where条件查询类型
            ->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
                $query->where('type', $where['type']);
            })
            // 当'user_type'字段在$where数组中存在且不为空时，添加where条件查询用户类型
            ->when(isset($where['user_type']) && $where['user_type'] !== '', function ($query) use ($where) {
                $query->where('user_type', $where['user_type']);
            })
            // 当'user_type'字段在$where数组中存在且不为空时，添加where条件查询用户类型
            ->when(isset($where['change_type']) && $where['change_type'] !== '', function ($query) use ($where) {
                is_array($where['change_type']) ? $query->whereIn('change_type', $where['change_type']) : $query->where('change_type', $where['change_type']);
            })
            // 当'date'字段在$where数组中存在且不为空时，调用getModelTime函数动态添加时间查询条件
            ->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['date'], 'change_time');
            });

        // 返回构建好的查询对象
        return $query;
    }


    /**
     * 获取超时未配送的订单ID列表
     *
     * 本函数通过查询订单状态数据库，筛选出在指定时间内，订单状态为配送中、待配送或已配送但未支付的订单ID。
     * 主要用于统计或处理超时未完成配送的订单。
     *
     * @param string $start 查询开始时间，格式为日期字符串
     * @param string $end 查询结束时间，格式为日期字符串
     * @return array 返回符合条件的订单ID列表
     */
    public function getTimeoutDeliveryOrder($start, $end)
    {
        // 使用数据库查询语言构造查询语句
        return StoreOrderStatus::getDB()->alias('A')
            ->leftJoin('StoreOrder B', 'A.order_id = B.order_id')
            ->whereIn('A.change_type',
                [
                    StoreOrderStatusRepository::ORDER_DELIVERY_SELF,
                    StoreOrderStatusRepository::ORDER_DELIVERY_NOTHING,
                    StoreOrderStatusRepository::ORDER_DELIVERY_COURIER
                ]
            )
            ->where('A.type', 'order')
            ->whereBetweenTime('A.change_time', $start, $end)
            ->where('B.paid', 1)->where('B.status', 1)
            ->column('A.order_id');
    }

    /**
     *  获取订单的自动确认收货时间
     * @param $id
     * @return string
     * @author Qinii
     */
    public function getTakeStatus($id)
    {
        $res = StoreOrderStatus::getDB()
            ->where('order_id', $id)
            ->whereIn('change_type',
                [
                    StoreOrderStatusRepository::ORDER_DELIVERY_SELF,
                    StoreOrderStatusRepository::ORDER_DELIVERY_NOTHING,
                    StoreOrderStatusRepository::ORDER_DELIVERY_COURIER
                ]
            )->find();
        $time = '';
        if ($res) {
            $timer = ((int)systemConfig('auto_take_order_timer')) ?: 15;
            $time = date('Y-m-d H:i:s',strtotime("+ $timer day"));
        }
        return $time;
    }
}
