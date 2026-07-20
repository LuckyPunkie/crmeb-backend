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
namespace app\controller\admin\store;

use app\common\repositories\store\product\ProductLabelRepository;
use app\validate\admin\ProductLabelValidate;
use think\App;
use crmeb\basic\BaseController;

/**
 * 商品标签
 */
class ProductLabel extends BaseController
{

    protected $repository;

    public function __construct(App $app, ProductLabelRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 列表
     * @return \think\response\Json
     * @author Qinii
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['name', 'type', ['mer_id', 0], 'status']);
        $data = $this->repository->getList($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 创建表单
     * @return \think\response\Json
     * @author Qinii
     */
    public function createForm()
    {
        return app('json')->success(formToData($this->repository->form(null, 'systemStoreProductLabelCreate')));
    }

    /**
     * 创建
     * @param ProductLabelValidate $validate
     * @return \think\response\Json
     * @author Qinii
     */
    public function create(ProductLabelValidate $validate)
    {
        $data = $this->request->params(['label_name', 'status', 'sort', 'info']);
        $validate->check($data);
        if (!$this->repository->check($data['label_name'], 0))
            return app('json')->fail('名称重复');
        $this->repository->create($data);
        return app('json')->success('添加成功');
    }

    /**
     * 修改表单
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function updateForm($id)
    {
        return app('json')->success(formToData($this->repository->updateForm($id, 'systemStoreProductLabelUpdate')));
    }

    /**
     * 修改
     * @param $id
     * @param ProductLabelValidate $validate
     * @return \think\response\Json
     * @author Qinii
     */
    public function update($id, ProductLabelValidate $validate)
    {
        $data = $this->request->params(['label_name', 'status', 'sort', 'info']);
        $validate->check($data);
        if (!$this->repository->check($data['label_name'], 0, $id))
            return app('json')->fail('名称重复');
        $getOne = $this->repository->getWhere(['product_label_id' => $id, 'mer_id' => 0]);
        if (!$getOne) return app('json')->fail('数据不存在');
        $this->repository->update($id, $data);
        return app('json')->success('编辑成功');
    }

    /**
     *
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function detail($id)
    {
        $getOne = $this->repository->getWhere(['product_label_id' => $id, 'mer_id' => 0, 'is_del' => 0]);
        if (!$getOne) return app('json')->fail('数据不存在');
        return app('json')->success($getOne);
    }

    /**
     * 删除
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function delete($id)
    {
        $getOne = $this->repository->getWhere(['product_label_id' => $id, 'mer_id' => 0]);
        if (!$getOne) return app('json')->fail('数据不存在');
        $this->repository->delete($id);
        return app('json')->success('删除成功');
    }


    /**
     * 状态修改
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function switchWithStatus($id)
    {
        $status = $this->request->param('status') == 1 ? 1 : 0;
        $getOne = $this->repository->getWhere(['product_label_id' => $id, 'mer_id' => 0]);
        if (!$getOne) return app('json')->fail('数据不存在');
        $this->repository->update($id, ['status' => $status]);
        return app('json')->success('修改成功');
    }

    /**
     * 获取商品标签
     * @return \think\response\Json
     * @author Qinii
     */
    public function getOptions()
    {
        $data = $this->repository->getOptions(0);
        return app('json')->success($data);
    }

}
