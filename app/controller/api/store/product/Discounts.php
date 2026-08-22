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
        $data = $this->request->params([['product_id',0],['limit',50],['mer_id',0]]);
        $id    = (int)$data['product_id'];
        $limit = min((int)$data['limit'], 100);
        $where = [
            'status'   => 1,
            'is_show'  => 1,
            'end_time' => 1,
            'is_del'   => 0,
        ];

        if ($id) {
            $discount_id = app()->make(StoreDiscountProductRepository::class)
                ->getSearch(['product_id' => $id])
                ->column('discount_id');
            if (!$discount_id) {
                return app('json')->success([]);
            }
            $where['discount_id'] = $discount_id;
        }

        if ((int)$data['mer_id'] > 0) {
            $where['mer_id'] = (int)$data['mer_id'];
        }

        $result = $this->repository->getApilist($where, $limit);
        return app('json')->success($result);
    }

    public function detail($id)
    {
        try {
            $data = $this->repository->detail((int)$id, 0);
            return app('json')->success($data);
        } catch (\Exception $e) {
            return app('json')->fail($e->getMessage());
        }
    }

}
