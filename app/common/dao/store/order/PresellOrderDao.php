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
use app\common\model\store\order\PresellOrder;

class PresellOrderDao extends BaseDao
{

    protected function getModel(): string
    {
        return PresellOrder::class;
    }

    /**
     * 根据条件搜索预售订单。
     *
     * 该方法通过接收一个包含搜索条件的数组，动态地构建查询语句来搜索预售订单。
     * 可以根据支付方式、支付状态、商家ID和订单ID列表来过滤结果。
     *
     * @param array $where 包含搜索条件的数组，其中可能包含pay_type、paid、mer_id和order_ids键。
     * @return \think\db\Query|null 返回构建的查询对象，或者在没有符合条件的查询时返回null。
     */
    public function search(array $where)
    {
        // 获取数据库查询对象
        return PresellOrder::getDB()->when(isset($where['pay_type']) && $where['pay_type'] !== '', function ($query) use ($where) {
            // 如果支付方式被指定且不为空，则添加支付方式的查询条件
            $query->whereIn('pay_type', $where['pay_type']);
        })->when(isset($where['paid']) && $where['paid'] !== '', function ($query) use ($where) {
            // 如果支付状态被指定且不为空，则添加支付状态的查询条件
            $query->where('paid', $where['paid']);
        })->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            // 如果商家ID被指定且不为空，则添加商家ID的查询条件
            $query->where('mer_id', $where['mer_id']);
        })->when(isset($where['order_ids']) && $where['order_ids'] !== '', function ($query) use ($where) {
            // 如果订单ID列表被指定且不为空，则添加订单ID在指定列表中的查询条件
            $query->where('order_id','in',$where['order_ids']);
        });
    }

    /**
     * 根据用户ID和订单ID查询预售订单信息
     *
     * 本函数旨在通过用户ID和订单ID从数据库中检索特定的预售订单。它使用了PresellOrder类的数据库访问方法来执行查询。
     * 查询条件是用户ID（uid）和订单ID（order_id）匹配，返回的是匹配的订单数据。
     *
     * @param int $uid 用户ID，用于指定订单所属的用户。
     * @param string $orderId 订单ID，用于唯一标识订单。
     * @return array|false 返回匹配的预售订单数据，如果找不到则返回false。
     */
    public function userOrder($uid, $orderId)
    {
        // 使用PresellOrder类的静态方法getDB来获取数据库实例，并构造查询条件，最后执行查询并返回结果
        return PresellOrder::getDB()->where('uid', $uid)->where('order_id', $orderId)->find();
    }

    /**
     * 获取过期未支付的预售订单ID列表
     *
     * 本函数用于查询数据库中状态为进行中（status=1）、未支付（paid=0）、且结束时间早于指定时间（$time）的预售订单ID。
     * 这些ID可用于后续的过期订单处理，比如提醒用户、或者自动取消订单等操作。
     *
     * @param int|string $time 结束时间的判断标准。可以是Unix时间戳或者符合数据库查询格式的日期字符串。
     * @return array 过期未支付的预售订单ID列表。
     */
    public function getTimeOutIds($time)
    {
        // 使用查询构建器查询符合条件的预售订单，并只返回presell_order_id列
        return PresellOrder::getDB()->where('status', 1)->where('paid', 0)
            ->where('final_end_time', '<', $time)->column('presell_order_id');
    }

    /**
     * 获取指定日期内的未支付预订单的短信ID列表
     *
     * 本函数用于查询指定日期内，状态为有效（1）且未支付的预订单的订单ID列表。
     * 查询条件基于订单的支付状态和最终开始时间，通过like操作符匹配日期部分。
     *
     * @param string $date 日期字符串，格式为Y-m-d，用于查询订单的最终开始时间。
     * @return array 返回符合条件的预订单ID列表。
     */
    public function sendSmsIds($date)
    {
        // 使用预订单模型的数据库查询方法，指定查询条件为状态为1，未支付，且最终开始时间like传入日期字符串加百分号。
        // 返回查询结果中订单ID一列的值。
        return PresellOrder::getDB()->where('status', 1)->where('paid', 0)
            ->whereLike('final_start_time', $date . '%')->column('order_id');
    }

}
