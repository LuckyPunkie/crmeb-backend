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
use app\common\model\store\order\StoreOrderProfitsharing;

class StoreOrderProfitsharingDao extends BaseDao
{
    protected function getModel(): string
    {
        return StoreOrderProfitsharing::class;
    }

    /**
     * 生成订单编号
     *
     * 本函数用于生成唯一的订单编号。编号由前缀、时间戳和随机数组成，确保了订单编号的唯一性和可追踪性。
     * - 前缀为"pr"，用于标识订单类型或业务领域。
     * - 时间戳部分基于微秒级时间，提高了编号的唯一性，避免了在高并发场景下的重复问题。
     * - 随机数部分确保即使在微秒级别的时间内有重复，也可以通过随机数避免编号重复。
     *
     * @return string 返回生成的订单编号
     * @throws \Exception
     */
    public function getOrderSn()
    {
        // 获取当前时间的微秒和秒部分
        list($msec, $sec) = explode(' ', microtime());

        // 将微秒和秒合并，并转换为毫秒，去除了小数点，确保数字部分全是整数
        $msectime = number_format((floatval($msec) + floatval($sec)) * 1000, 0, '', '');

        // 生成订单编号：前缀 + 毫秒时间戳 + 4位随机数
        // 随机数生成考虑了微秒时间戳的重复性和随机性，避免了直接使用微秒时间戳可能导致的重复问题
        $orderId = 'pr' . $msectime . random_int(10000, max(intval($msec * 10000) + 10000, 98369));

        return $orderId;
    }


    /**
     * 根据条件搜索分佣记录
     *
     * @param array $where 搜索条件
     * @return \think\Collection|\think\db\BaseQuery
     */
    public function search(array $where)
    {
        // 使用分佣记录模型获取数据库实例
        return StoreOrderProfitsharing::getDB()->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            // 如果条件中包含商户ID，则添加对应查询条件
            $query->where('mer_id', $where['mer_id']);
        })->when(isset($where['order_id']) && $where['order_id'] !== '', function ($query) use ($where) {
            // 如果条件中包含订单ID，则添加对应查询条件
            $query->where('order_id', $where['order_id']);
        })->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
            // 如果条件中包含类型，则添加对应查询条件
            $query->where('type', $where['type']);
        })->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            // 如果条件中包含状态，则添加对应查询条件
            $query->where('status', $where['status']);
        })->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
            // 如果条件中包含日期，则调用getModelTime函数添加查询条件
            getModelTime($query, $where['date']);
        })->when(isset($where['profit_date']) && $where['profit_date'] !== '', function ($query) use ($where) {
            // 如果条件中包含分佣时间，则调用getModelTime函数添加查询条件，并指定字段
            getModelTime($query, $where['profit_date'], 'profitsharing_time');
        })->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
            // 如果条件中包含关键字，则添加模糊查询条件
            $query->whereLike('keyword', "%{$where['keyword']}%");
        });
    }

    /**
     * 获取自动分配的利润分享ID列表
     *
     * 本函数用于查询在特定时间点之前已验证但未分配的订单利润分享ID。
     * 它通过连接订单和利润分享信息表，筛选出符合条件的利润分享记录。
     * 具体的筛选条件包括订单状态和利润分享状态，以及订单的验证时间。
     *
     * @param int $time 用于筛选的特定时间点，以UNIX时间戳形式表示。
     * @return array 返回符合条件的利润分享ID列表。
     */
    public function getAutoProfitsharing($time, $status = 0)
    {
        // 使用数据库查询工具并设置别名为A
        return StoreOrderProfitsharing::getDB()->alias('A')
            // 加入订单表B，并指定连接条件
            ->join('StoreOrder B', 'A.order_id = B.order_id', 'left')
            // 筛选订单状态为大于1或为-1的记录
            ->where(function ($query) {
                $query->where('B.status', '>', 1)->whereOr('B.status', -1);
            })
            // 筛选利润分享状态为0的记录
            ->where('A.status', $status)
            // 筛选已验证且验证时间早于指定时间的订单
            ->where(function ($query) use ($time) {
                $query->whereNotNull('B.verify_time')->where('B.verify_time', '<', $time);
            })
            // 返回满足条件的利润分享ID列表
            ->column('A.profitsharing_id');
    }

    public function getAutoProfitsharingStatus()
    {
        // 使用数据库查询工具并设置别名为A
        return StoreOrderProfitsharing::getDB()
            // 筛选利润分享状态为0的记录
            ->where('A.status', 2)
            // 返回满足条件的利润分享ID列表
            ->column('A.profitsharing_id');
    }
}
