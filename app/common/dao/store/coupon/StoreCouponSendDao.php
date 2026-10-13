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


namespace app\common\dao\store\coupon;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\store\coupon\StoreCouponSend;

class StoreCouponSendDao extends BaseDao
{

    /**
     * 获取模型类名
     *
     * 本方法返回一个字符串，代表了StoreCouponSend类的完全限定名。
     * 它被设计为一个受保护的方法，意味着它只能在当前类或其子类中被调用。
     * 这种设计模式常用于框架中，通过这种方式，子类可以重写此方法以返回不同的模型类名，
     * 从而实现不同的行为或逻辑，而不需要修改父类的其他部分。
     *
     * @return string 返回StoreCouponSend类的完全限定名
     */
    protected function getModel(): string
    {
        return StoreCouponSend::class;
    }


    /**
     * 根据条件搜索优惠券发送记录
     *
     * 本函数用于根据提供的条件从数据库中检索优惠券发送记录。它支持搜索优惠券名称、
     * 发送日期范围、优惠券类型、状态和商家ID。搜索结果基于这些条件的组合。
     *
     * @param array $where 搜索条件数组，包含可能的键：coupon_name（优惠券名称）、
     *                     date（日期范围）、coupon_type（优惠券类型）、status（状态）、mer_id（商家ID）。
     * @return \think\db\Query 返回一个数据库查询对象，该对象可用于进一步的查询操作或数据检索。
     */
    public function search(array $where)
    {
        // 初始化查询，指定别名为A，并左连接到StoreCoupon表（别名为B） on B.coupon_id = A.coupon_id
        return StoreCouponSend::getDB()->alias('A')->leftJoin('StoreCoupon B', 'B.coupon_id = A.coupon_id')
            // 当coupon_name条件存在且不为空时，添加LIKE条件到查询中
            ->when(isset($where['coupon_name']) && $where['coupon_name'] !== '', function ($query) use ($where) {
                $query->whereLike('B.title', "%{$where['coupon_name']}%");
            })
            // 当date条件存在且不为空时，调用getModelTime函数来处理日期范围条件
            ->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['date'], 'A.create_time');
            })
            // 当coupon_type条件存在且不为空时，添加类型条件到查询中
            ->when(isset($where['coupon_type']) && $where['coupon_type'] !== '', function ($query) use ($where) {
                $query->where('B.type', $where['coupon_type']);
            })
            // 当status条件存在且不为空时，添加状态条件到查询中
            ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                $query->where('A.status', $where['status']);
            })
            // 当mer_id条件存在且不为空时，添加商家ID条件到查询中
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('A.mer_id', $where['mer_id']);
            });
    }
}
