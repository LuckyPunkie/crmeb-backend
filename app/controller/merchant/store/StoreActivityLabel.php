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

namespace app\controller\merchant\store;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\StoreActivityCateRepository;
use app\common\repositories\store\StoreActivityLabelRepository;

class StoreActivityLabel extends BaseController
{
    protected $repository;

    public function __construct(App $app, StoreActivityLabelRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function select()
    {
        $where = ['status' => 1];
        return app('json')->success($this->repository->select($where));
    }


    public function options(StoreActivityCateRepository $repository)
    {
        $where = ['status' => 1];
        return app('json')->success($repository->options($where));
    }

    public function batchCreate(ProductRepository $repository)
    {
        $productIds = $this->request->param('product_ids',[]);
        $labelIds = $this->request->param('label_ids',[]);
        $type = $this->request->param('type',2);
        if ($type == 2 && (empty($productIds) || empty($labelIds)))
            return app('json')->fail('请选择标签和商品');
        $repository->batchLabelCreate($productIds,$labelIds, $this->request->merId(), $type);
        return app('json')->success('添加成功');
    }
}
