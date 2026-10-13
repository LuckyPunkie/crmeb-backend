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
use app\common\model\store\order\StoreRefundProduct;

class StoreRefundProductDao extends BaseDao
{

    protected function getModel(): string
    {
        return StoreRefundProduct::class;
    }

    /**
     * 根据条件搜索订单。
     *
     * 本函数用于根据提供的条件数组搜索订单。它支持通过订单ID进行筛选，并且总是按照创建时间对结果进行排序。
     * 这种方法的设计允许灵活地添加更多的搜索条件，而不需要修改函数的主体结构。
     *
     * @param array $where 包含搜索条件的数组。其中可能包含订单ID等用于筛选订单的键值对。
     * @return \Illuminate\Database\Eloquent\Builder|static 返回一个构建器对象，用于进一步的查询操作或数据检索。
     */
    public function search(array $where)
    {
        // 从模型中获取数据库实例，并根据条件应用订单ID的筛选。
        $query = $this->getModel()::getDB()
            ->when(isset($where['order_id']) && $where['order_id'] !== '', function($query) use ($where) {
                // 如果提供了订单ID，并且不为空，则在查询中添加订单ID的条件。
                $query->where('order_id', $where['order_id']);
            });

        // 按照创建时间对查询结果进行排序。
        return $query->order('create_time');
    }

    /**
     * 根据订单产品ID数组，计算退款详情
     *
     * 本函数通过查询订单退款详情和退款订单信息，统计指定订单产品ID数组中每个订单产品的退款总金额、平台退款金额、退款邮费、退款积分等信息。
     * 主要用于在用户退款时，快速汇总相关退款数据。
     *
     * @param array $ids 订单产品ID数组
     * @return array 返回一个键为订单产品ID，值为包含各种退款详情的数组
     */
    public function userRefundPrice(array $ids)
    {
        // 构建查询语句，左连接退款订单表，筛选出状态大于-1的退款记录，按订单产品ID分组，计算各项退款金额和积分
        $lst = $this->getModel()::getDB()->alias('A')->leftJoin('StoreRefundOrder B', 'A.refund_order_id = B.refund_order_id')
            ->where('B.status', '>', -1)
            ->whereIn('A.order_product_id', $ids)->group('A.order_product_id')
            ->field('A.order_product_id, SUM(A.refund_price) as refund_price, SUM(A.platform_refund_price) as platform_refund_price, SUM(A.refund_postage) as refund_postage, SUM(A.refund_integral) as refund_integral')
            ->select()->toArray();

        // 将查询结果重新组织为以订单产品ID为键的数组，方便后续根据订单产品ID快速查找退款详情
        $data = [];
        foreach ($lst as $item) {
            $data[$item['order_product_id']] = $item;
        }

        // 返回重新组织后的数据数组
        return $data;
    }
}
