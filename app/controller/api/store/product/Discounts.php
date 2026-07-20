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
namespace app\controller\api\store\product;

use app\common\repositories\store\product\StoreDiscountProductRepository;
use app\common\repositories\store\product\StoreDiscountRepository;
use crmeb\basic\BaseController;
use think\App;

class Discounts extends BaseController
{

    protected  $repository ;

    /**
     * Product constructor.
     * @param App $app
     * @param StoreDiscountRepository $repository
     */
    public function __construct(App $app ,StoreDiscountRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取指定产品ID的促销列表
     *
     * 此方法用于根据请求中的产品ID，查询具有特定促销状态的促销活动列表。
     * 它首先尝试从请求中获取产品ID，然后根据该ID查询相关的促销活动ID列表。
     * 如果存在相关促销，它将这些促销ID作为查询条件之一，以获取最终的促销活动列表。
     *
     * @return json 返回查询到的促销活动列表数据
     */
    public function lst()
    {
        // 从请求中获取产品ID，默认为0
        $data = $this->request->params([['product_id',0],['limit',5]]);
        $id = $data['product_id'];
        $limit = $data['limit'];
        // 定义查询条件，包括状态、展示、结束时间和未删除的促销
        $where = [
            'status' => 1,
            'is_show'=> 1,
            'end_time' => 1,
            'is_del' => 0,
        ];

        // 如果提供了产品ID
        if ($id){
            // 查询与产品ID相关的促销ID列表
            $discount_id = app()->make(StoreDiscountProductRepository::class)
                ->getSearch(['product_id' => $id])
                ->column('discount_id');
            if (!$discount_id) {
                return  app('json')->success([]);
            }
            // 将促销ID列表作为查询条件之一
            $where['discount_id'] = $discount_id;
        };
        // 根据所有的查询条件获取促销活动列表
        $data = $this->repository->getApilist($where,$limit);
        // 返回查询结果
        return app('json')->success($data);
    }


}
