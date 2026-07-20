<?php

namespace app\controller\merchant\store\product;

use app\common\repositories\store\product\ProductUnitRepository as repository;
use app\validate\admin\ProductUnitValidate;
use crmeb\basic\BaseController;
use think\App;

class ProductUnit extends BaseController
{

    protected $repository;

    /**
     * Product constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取列表数据
     *
     * @return \think\response\Json
     */
    public function list()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 获取查询条件
        $where = $this->request->params(['value']);
        // 设置商家ID
        $where['mer_id'] = $this->request->merId();
        // 调用仓库方法获取数据
        $data = $this->repository->list($where, $page, $limit);
        // 返回JSON格式的数据
        return app('json')->success($data);
    }

    /**
     * 创建表单
     *
     * @return \think\response\Json
     */
    public function createForm()
    {
        // 调用仓库方法获取表单数据
        return app('json')->success(formToData($this->repository->createForm($this->request->merId())));
    }

    /**
     * 创建产品单位
     *
     * @param ProductUnitValidate $validate 验证器实例
     * @return \think\response\Json
     */
    public function create(ProductUnitValidate $validate)
    {
        // 验证参数
        $data = $this->checkParams($validate);
        // 调用仓库方法创建产品单位
        $this->repository->create($this->request->merId(), $data);
        return app('json')->success('添加成功');
    }


    /**
     * 更新表单
     *
     * @param int $id 表单ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateForm($id)
    {
        // 调用 repository 的 updateForm 方法更新表单数据，并将结果转换为 JSON 格式返回
        return app('json')->success(formToData($this->repository->updateForm((int)$id, $this->request->merId())));
    }

    /**
     * 更新商品单位
     *
     * @param int $id 商品单位ID
     * @param ProductUnitValidate $validate 商品单位验证器
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, ProductUnitValidate $validate)
    {
        // 获取验证通过的参数数据
        $data = $this->checkParams($validate);
        // 调用 repository 的 update 方法更新商品单位数据
        $this->repository->update($id, $this->request->merId(), $data);
        // 返回操作成功的 JSON 格式响应
        return app('json')->success('编辑成功');
    }

    /**
     * 删除商品单位
     *
     * @param int $id 商品单位ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($id)
    {
        // 调用 repository 的 delete 方法删除商品单位数据
        $this->repository->delete($id, $this->request->merId());
        return app('json')->success('删除成功');
    }


    /**
     * 获取选择列表
     *
     * @return array
     */
    public function getSelectList()
    {
        // 调用 repository 类的 getSelectList 方法获取选择列表数据，并通过 json 组件返回成功状态和数据
        return app('json')->success($this->repository->getSelectList($this->request->merId()));
    }


    /**
     * 校验参数
     *
     * @param ProductUnitValidate $validate 参数校验器实例
     * @return array 校验后的参数数组
     */
    public function checkParams(ProductUnitValidate $validate)
    {
        // 需要校验的参数列表
        $params = ['value', 'sort'];
        // 从请求中获取参数数组
        $data = $this->request->params($params);
        // 使用参数校验器对参数进行校验
        $validate->check($data);
        // 返回校验后的参数数组
        return $data;
    }


}
