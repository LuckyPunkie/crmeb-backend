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


use app\common\dao\store\coupon\StoreCouponProductDao;
use app\common\repositories\BaseRepository;

/**
 * Class StoreCouponProductRepository
 * @package app\common\repositories\store\coupon
 * @author xaboy
 * @day 2020/6/1
 * @mixin StoreCouponProductDao
 */
class StoreCouponProductRepository extends BaseRepository
{

    /**
     * StoreCouponProductRepository constructor.
     * @param StoreCouponProductDao $dao
     */
    public function __construct(StoreCouponProductDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取优惠券关联产品的列表
     *
     * 本函数用于根据优惠券ID获取关联产品的分页列表。它首先通过优惠券ID查询数据库，
     * 然后限制返回的结果集包含特定的字段，最后返回符合条件的产品数量和列表。
     *
     * @param int $coupon_id 优惠券ID，用于查询关联的产品。
     * @param int $page 当前的页码，用于分页查询产品列表。
     * @param int $limit 每页显示的产品数量，用于分页查询。
     * @return array 返回包含产品数量和列表的数组。
     */
    public function productList($coupon_id, $page, $limit)
    {
        // 根据优惠券ID查询优惠券关联的产品信息
        $query = $this->dao->search(compact('coupon_id'));

        // 限制返回的产品信息只包含指定的字段
        $query->with(['product' => function ($query) {
            $query->field('product_id,store_name,image,price,stock,sales');
        }]);

        // 计算满足条件的产品总数
        $count = $query->count();

        // 获取当前页码的产品列表
        $list = $query->page($page, $limit)->select();

        // 返回产品总数和列表信息
        return compact('count', 'list');
    }
}
