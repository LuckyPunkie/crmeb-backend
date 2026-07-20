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
use app\common\model\store\coupon\StoreCouponProduct;
use think\Collection;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * Class StoreCouponProductDao
 * @package app\common\dao\store\coupon
 * @author xaboy
 * @day 2020-05-13
 */
class StoreCouponProductDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return StoreCouponProduct::class;
    }

    /**
     * 新增数据
     * @param array $data
     * @return int
     * @author xaboy
     * @day 2020-05-13
     */
    public function insertAll(array $data)
    {
        return StoreCouponProduct::getDB()->insertAll($data);
    }

    /**
     * 根据优惠券id清除数据
     * @param $couponId
     * @return int
     * @throws DbException
     * @author xaboy
     * @day 2020-05-13
     */
    public function clear($couponId)
    {
        return StoreCouponProduct::getDB()->where('coupon_id', $couponId)->delete();
    }

    /**
     * 根据商品id查询优惠券id
     * @param $productId
     * @return array
     * @author xaboy
     * @day 2020/6/1
     */
    public function productByCouponId($productId)
    {
        return StoreCouponProduct::getDB()->whereIn('product_id', $productId)->column('coupon_id');
    }

    /**
     * 根据条件搜索优惠券产品信息。
     *
     * 本函数旨在根据传入的条件数组，查询优惠券产品数据库。条件数组可以包含coupon_id和type两个字段，
     * 函数将根据这两个字段的值进行查询条件的动态添加，实现灵活的数据库查询。
     *
     * @param array $where 包含查询条件的数组，可能包含coupon_id和type两个键。
     * @return \think\db\BaseQuery|\think\db\Query
     */
    public function search(array $where)
    {
        // 获取数据库操作对象
        return StoreCouponProduct::getDB()
            // 当传入的$where数组中包含coupon_id键且值不为空时，添加where条件查询coupon_id
            ->when(isset($where['coupon_id']) && $where['coupon_id'] !== '', function ($query) use ($where) {
                return $query->where('coupon_id', $where['coupon_id']);
            })
            // 当传入的$where数组中包含type键且值不为空时，添加where条件查询type
            ->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
                return $query->where('type', $where['type']);
            });
    }
}
