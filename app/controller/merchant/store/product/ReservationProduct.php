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
namespace app\controller\merchant\store\product;

use think\App;
use app\Request;
use crmeb\basic\BaseController;
use app\validate\merchant\StoreProductValidate;
use app\common\repositories\store\product\NewProductRepository;
use app\common\repositories\store\product\ProductAttrValueRepository;
use app\common\repositories\store\product\ProductReservationRepository;

/**
 * Class ReservationProduct
 * @package app\controller\merchant\store\product;
 * @author Weeks
 */
class ReservationProduct extends BaseController
{
    protected $validate;
    protected $repository;
    protected $attrValueRepository;
    protected $productReservationRepository;

    /**
     * Product constructor.
     * @param App $app
     * @param StoreProductValidate $validate
     * @param NewProductRepository $repository
     */
    public function __construct(
        App $app,
        StoreProductValidate $validate,
        NewProductRepository $repository,
        ProductAttrValueRepository $attrValueRepository,
        ProductReservationRepository $productReservationRepository
    ) {
        parent::__construct($app);
        $this->validate = $validate;
        $this->repository = $repository;
        $this->attrValueRepository = $attrValueRepository;
        $this->productReservationRepository = $productReservationRepository;
    }

    public function __destruct()
    {
        unset($this->validate);
        unset($this->repository);
        unset($this->attrValueRepository);
        unset($this->productReservationRepository);
    }

    protected function getValidate()
    {
        return $this->validate;
    }

    protected function getRepository()
    {
        return $this->repository;
    }

    protected function getAttrValueRepository()
    {
        return $this->attrValueRepository;
    }
    protected function getProductReservationRepository()
    {
        return $this->productReservationRepository;
    }
    /**
     * 创建商品
     *
     * @param Request $request
     * @return json
     */
    public function create(Request $request)
    {
        $params = $request->params(NewProductRepository::CREATE_PARAMS);
        $params['mer_id'] = $request->merId();

        $validate = $this->getValidate();
        if (!$validate->sceneCreate($params)) {
            return app('json')->fail($validate->getError());
        }
        $repository = $this->getRepository();
        $params = $repository->checkParams($params, $request->merId());

        $res = $repository->createProduct($params, $request->merchant(), $request->adminInfo());
        if (!$res) {
            return app('json')->fail('创建失败');
        }
        return app('json')->success('创建成功', $res);
    }
    /**
     * 编辑商品
     *
     * @param integer $id
     * @param Request $request
     * @return json
     */
    public function edit(int $id, Request $request)
    {
        $params = $request->params(NewProductRepository::CREATE_PARAMS);
        $params['mer_id'] = $request->merId();

        $validate = $this->getValidate();
        if (!$validate->sceneUpdate($params)) {
            return app('json')->fail($validate->getError());
        }
        $repository = $this->getRepository();
        $params = $repository->checkParams($params, $request->merId());

        $res = $repository->editProduct($id, $params, $request->merchant(), $request->adminInfo());
        if (!$res) {
            return app('json')->fail('编辑失败');
        }
        return app('json')->success('编辑成功', $res);
    }
    /**
     * 删除商品
     *
     * @param integer $id
     * @return json
     */
    public function delete(int $id) {}
    /**
     * 商品列表
     *
     * @param Request $request
     * @return json
     */
    public function list(Request $request) {}
    /**
     * 商品详情
     *
     * @param integer $id
     * @return json
     */
    public function detail(int $id)
    {
        if (!$id) {
            return app('json')->fail('缺少参数');
        }

        return app('json')->success($this->getRepository()->productDetail($id));
    }
    /**
     * 编辑商品信息获取
     *
     * @param integer $id
     * @return void
     */
    public function editInfo(int $id)
    {
        if (!$id) {
            return app('json')->fail('缺少参数');
        }

        return app('json')->success($this->getRepository()->editInfo($id));
    }
    /**
     * 批量设置预约商品库存
     *
     * @param Request $request
     * @return json
     */
    public function batchSetReservationProductStock(int $id, Request $request)
    {
        $params = $request->params(['stockValue']);

        $validate = $this->getValidate();
        if (!$validate->sceneBatchProductStock($params)) {
            return app('json')->fail($validate->getError());
        }
        $res = $this->getAttrValueRepository()->batchSetReservationProductStock($id, $params);
        if (!$res) {
            return app('json')->fail('修改失败');
        }

        return app('json')->success('修改成功');
    }


    /**
     * 商品日历month
     *
     * @param integer $id
     * @return void
     */
    public function showMonth($id)
    {
        $where = $this->request->params([
            ['sku_id',''],
            ['date',date('Y-m')],
        ]);
        $data = $this->getProductReservationRepository()->showMonth($id, $where);
        return app('json')->success($data);
    }
    /**
     * 商品日历day
     *
     * @param integer $id
     * @return void
     */
    public function showDay(int $id )
    {
        $where = $this->request->params([
            ['day',date('d')],
            ['date',date('Y-m')],
            ['sku_id',0]
        ]);
        $data = $this->getProductReservationRepository()->showDay($id, $where);
        return app('json')->success($data);
    }
}
