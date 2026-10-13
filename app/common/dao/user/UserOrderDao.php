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


namespace app\common\dao\user;

use app\common\dao\BaseDao;
use app\common\model\user\LabelRule;
use app\common\model\user\UserOrder;

class UserOrderDao extends BaseDao
{

    protected function getModel(): string
    {
        return UserOrder::class;
    }

    /**
     * 根据条件搜索用户订单。
     *
     * 该方法通过接收一个包含各种搜索条件的数组，来查询符合这些条件的用户订单。
     * 搜索条件可以包括用户ID、关键词、电话、订单号、订单标题、订单类型、支付状态、支付方式、支付时间、商户ID、支付金额和创建时间等。
     * 方法使用了Eloquent ORM的链式调用功能，动态地构建SQL查询语句。
     *
     * @param array $where 包含搜索条件的数组。
     * @return \Illuminate\Database\Eloquent\Builder|static 返回构建的查询构建器实例。
     */
    public function search(array $where)
    {
        // 根据用户ID、关键词、电话进行查询条件构建
        return UserOrder::hasWhere('user', function ($query) use ($where) {
            // 当uid条件存在且不为空时，添加到查询条件中
            $query->when(isset($where['uid']) && $where['uid'] != '', function ($query) use ($where) {
                $query->where('uid', $where['uid']);
            })
            // 当keyword条件存在且不为空时，以LIKE方式添加到查询条件中
            ->when(isset($where['keyword']) && $where['keyword'] != '', function ($query) use ($where) {
                $query->whereLike('nickname', "%{$where['keyword']}%");
            })
            // 当phone条件存在且不为空时，添加到查询条件中
            ->when(isset($where['phone']) && $where['phone'] != '', function ($query) use ($where) {
                $query->where('phone', $where['phone']);
            });
            // 保证这个where子句总是生效，用于后续连接其他条件
            $query->where(true);
        })
        // 根据订单号、订单标题进行查询条件构建
        ->when(isset($where['order_sn']) && $where['order_sn'] !== '', function ($query) use ($where) {
            $query->whereLike('order_sn', "%{$where['order_sn']}%");
        })
        ->when(isset($where['title']) && $where['title'] !== '', function ($query) use ($where) {
            $query->whereLike('title', "%{$where['title']}%");
        })
        // 根据订单类型、支付状态、支付方式进行查询条件构建
        ->when(isset($where['order_type']) && $where['order_type'] !== '', function ($query) use ($where) {
            $query->where('order_type', $where['order_type']);
        })
        ->when(isset($where['paid']) && $where['paid'] !== '', function ($query) use ($where) {
            $query->where('paid', $where['paid']);
        })
        ->when(isset($where['pay_type']) && $where['pay_type'] !== '', function ($query) use ($where) {
            $query->where('pay_type', $where['pay_type']);
        })
        // 根据支付时间和商户ID进行查询条件构建
        ->when(isset($where['pay_time']) && $where['pay_time'] !== '', function ($query) use ($where) {
            $query->whereDay('pay_time', $where['pay_time']);
        })
        ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            $query->whereDay('mer_id', $where['mer_id']);
        })
        // 根据支付金额进行查询条件构建
        ->when(isset($where['pay_price']) && $where['pay_price'] !== '', function ($query) use ($where) {
            $query->where('UserOrder.pay_price', '>', $where['pay_price']);
        })
        // 根据创建时间进行查询条件构建
        ->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
            getModelTime($query, $where['date'], 'UserOrder.create_time');
        })
        ->when(isset($where['mom_date']) && $where['mom_date'] !== '', function ($query) use ($where) {
            getModelTime($query, $where['mom_date'], 'UserOrder.create_time');
        });
    }
}
