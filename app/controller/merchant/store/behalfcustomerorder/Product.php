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
namespace app\controller\merchant\store\behalfcustomerorder;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\StoreCategoryRepository;

class Product extends BaseController
{
    protected $productRepository;
    protected $storeCategoryRepository;

    public function __construct(App $app, ProductRepository $productRepository, StoreCategoryRepository $storeCategoryRepository)
    {
        parent::__construct($app);
        $this->productRepository = $productRepository;
        $this->storeCategoryRepository = $storeCategoryRepository;
    }

    public function __destruct()
    {
        unset($this->productRepository);
        unset($this->storeCategoryRepository);
    }

    /**
     * 获取商品分类
     *
     * @return void
     */
    public function category()
    {
        $merId = $this->request->merId();
        return app('json')->success($this->storeCategoryRepository->getCategoryByMerId($merId));
    }
    /**
     * 获取商品列表
     *
     * @return void
     */
    public function list()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 获取查询参数
        $merId = $this->request->merId();
        $type = $this->request->param('type', 1);
        $where = $this->request->params(['search', 'mer_cate_id']);
        $where = array_merge($where, $this->productRepository->switchType($type, $this->request->merId(), 0));

        return app('json')->success($this->productRepository->getBehalfProductList($merId, $where, $page, $limit));
    }
    /**
     * 获取商品详情
     *
     * @param [type] $id
     * @return void
     */
    public function detail($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }
        $merId = $this->request->merId();

        return app('json')->success($this->productRepository->getBehalfCustomerOrderDetail($merId, $id));
    }
}
