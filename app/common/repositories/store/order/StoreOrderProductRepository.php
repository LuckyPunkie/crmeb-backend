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


namespace app\common\repositories\store\order;


use app\common\dao\store\order\StoreOrderProductDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\product\SpuRepository;

/**
 * Class StoreOrderProductRepository
 * @package app\common\repositories\store\order
 * @author xaboy
 * @day 2020/6/8
 * @mixin StoreOrderProductDao
 */
class StoreOrderProductRepository extends BaseRepository
{
    /**
     * StoreOrderProductRepository constructor.
     * @param StoreOrderProductDao $dao
     */
    public function __construct(StoreOrderProductDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取用户支付产品信息
     * 根据关键词、用户ID、分页和限制数量来查询用户支付的产品列表。
     *
     * @param string|null $keyword 搜索关键词，可选。
     * @param int $uid 用户ID。
     * @param int $page 当前页码。
     * @param int $limit 每页记录数。
     *
     * @return array 返回包含记录总数和产品列表的数组。
     */
    public function getUserPayProduct(?string $keyword, int $uid, int $page, int $limit)
    {
        // 根据关键词和用户ID查询支付产品，并按产品ID分组
        $query = $this->dao->getUserPayProduct($keyword, $uid)->group('product_id');

        // 计算满足条件的产品总数
        $count = $query->count();

        // 设置查询字段，指定需要获取的字段列表，并进行分页查询，然后将结果转换为数组
        $list = $query->setOption('field',[])->field('StoreOrderProduct.uid,StoreOrderProduct.product_id,StoreOrderProduct.product_type,spu_id,image,store_name,price')
            ->page($page, $limit)->select()->toArray();

        // 返回记录总数和产品列表的数组
        return compact('count', 'list');
    }

}
