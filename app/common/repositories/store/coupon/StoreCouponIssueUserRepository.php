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


namespace app\common\repositories\store\coupon;


use app\common\dao\store\coupon\StoreCouponIssueUserDao;
use app\common\repositories\BaseRepository;

/**
 * Class StoreCouponIssueUserRepository
 * @package app\common\repositories\store\coupon
 * @author xaboy
 * @day 2020/6/1
 * @mixin StoreCouponIssueUserDao
 */
class StoreCouponIssueUserRepository extends BaseRepository
{
    /**
     * StoreCouponIssueUserRepository constructor.
     * @param StoreCouponIssueUserDao $dao
     */
    public function __construct(StoreCouponIssueUserDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 发行优惠券
     *
     * 本函数用于向指定用户发行优惠券。它通过调用数据访问对象（DAO）来创建一条新的优惠券发行记录。
     *
     * @param int $couponId 优惠券ID
     *        识别要发行的具体优惠券的唯一标识符。
     * @param int $uid 用户ID
     *        接收优惠券的用户的唯一标识符。
     * @return bool|mixed
     *         返回值取决于DAO的create方法。通常是布尔值表示成功或失败，但在某些情况下，也可能返回新创建的记录ID。
     */
    public function issue($couponId, $uid)
    {
        // 调用DAO的create方法，传入发行优惠券所需的数据
        // 这里包含了优惠券ID和用户ID，用于在数据库中创建新的发行记录。
        return $this->dao->create([
            'coupon_id' => $couponId,
            'uid' => $uid,
        ]);
    }

    /**
     * 获取列表数据
     *
     * 根据给定的条件数组和分页信息，从数据库中检索并返回列表数据。
     * 这个方法主要用于处理数据的查询和分页，为前端提供分页列表的数据。
     *
     * @param array $where 查询条件数组，用于构建SQL查询的WHERE子句。
     * @param int $page 当前的页码，用于计算查询的起始位置。
     * @param int $limit 每页显示的数据条数，用于限制查询的结果集大小。
     * @return array 返回包含‘count’和‘list’两个元素的数组，‘count’表示总数据条数，‘list’表示当前页的数据列表。
     */
    public function getList(array $where, $page, $limit)
    {
        // 根据条件查询数据，这里不直接使用$where参数以避免潜在的SQL注入风险。
        $query = $this->dao->search($where);

        // 统计查询结果的总条数，用于分页计算。
        $count = $query->count();

        // 带上关联数据（如优惠券和用户信息）进行分页查询，返回当前页的数据列表。
        $list = $query->with(['coupon', 'user'])->page($page, $limit)->select();

        // 将总条数和当前页的数据列表打包成数组返回。
        return compact('count', 'list');
    }
}
