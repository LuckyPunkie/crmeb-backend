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

use app\common\repositories\store\product\ProductLabelRepository;
use app\validate\admin\ProductLabelValidate;
use think\App;
use crmeb\basic\BaseController;

class ProductLabel extends BaseController
{

    protected $repository;

    /**
     * 构造函数
     *
     * @param App $app 应用实例
     * @param ProductLabelRepository $repository 商品标签仓库实例
     */
    public function __construct(App $app, ProductLabelRepository $repository)
    {
        // 调用父类构造函数
        parent::__construct($app);
        // 初始化商品标签仓库实例
        $this->repository = $repository;
    }

    /**
     * 获取商品标签列表
     *
     * @return \think\response\Json
     */
    public function lst()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 获取查询条件
        $where = $this->request->params(['name', 'type', 'status']);
        // 添加商家ID查询条件
        $where['mer_id'] = $this->request->merId();
        // 获取商品标签列表
        $data = $this->repository->getList($where, $page, $limit);
        // 返回JSON格式的商品标签列表数据
        return app('json')->success($data);
    }

    /**
     * 创建商品标签表单
     *
     * @return \think\response\Json
     */
    public function createForm()
    {
        // 获取商品标签创建表单数据
        return app('json')->success(formToData($this->repository->form(null, 'merchantStoreProductLabelCreate')));
    }


    /**
     * 创建商品标签
     *
     * @param ProductLabelValidate $validate 商品标签验证器
     * @return \Psr\Http\Message\ResponseInterface JSON格式的响应结果
     */
    public function create(ProductLabelValidate $validate)
    {
        // 获取验证通过的参数
        $data = $this->checkParams($validate);
        // 检查标签名称是否已存在
        if (!$this->repository->check($data['label_name'], $this->request->merId()))
            return app('json')->fail('名称重复');
        // 设置商家ID
        $data['mer_id'] = $this->request->merId();
        // 创建商品标签
        $this->repository->create($data);
        // 返回操作成功的JSON响应
        return app('json')->success('添加成功');
    }

    /**
     * 更新商品标签表单
     *
     * @param int $id 商品标签ID
     * @return \Psr\Http\Message\ResponseInterface JSON格式的响应结果
     */
    public function updateForm($id)
    {
        // 返回更新表单的JSON响应
        return app('json')->success(formToData($this->repository->updateForm($id, 'merchantStoreProductLabelUpdate', $this->request->merId())));
    }


    /**
     * 更新商品标签
     * @param int $id 商品标签ID
     * @param ProductLabelValidate $validate 商品标签验证器
     * @return \think\response\Json
     */
    public function update($id, ProductLabelValidate $validate)
    {
        // 获取验证通过的参数
        $data = $this->checkParams($validate);
        // 检查商品标签名称是否重复
        if (!$this->repository->check($data['label_name'], $this->request->merId(), $id))
            return app('json')->fail('名称重复');
        // 获取指定ID的商品标签
        $getOne = $this->repository->getWhere(['product_label_id' => $id, 'mer_id' => $this->request->merId()]);
        // 如果商品标签不存在则返回错误信息
        if (!$getOne) return app('json')->fail('数据不存在');
        // 更新商品标签信息
        $this->repository->update($id, $data);
        // 返回操作成功信息
        return app('json')->success('编辑成功');
    }

    /**
     * 获取商品标签详情
     * @param int $id 商品标签ID
     * @return \think\response\Json
     */
    public function detail($id)
    {
        $getOne = $this->repository->getWhere(['product_label_id' => $id, 'mer_id' => $this->request->merId(), 'is_del' => 0]);
        // 如果商品标签不存在则返回错误信息
        if (!$getOne) return app('json')->fail('数据不存在');
        // 返回商品标签详情
        return app('json')->success($getOne);
    }


    /**
     * 根据ID删除商品标签
     * @param int $id 商品标签ID
     * @return \think\response\Json
     */
    public function delete($id)
    {
        // 从仓库中获取指定ID和商家ID的商品标签
        $getOne = $this->repository->getWhere(['product_label_id' => $id, 'mer_id' => $this->request->merId()]);
        // 如果没有获取到商品标签，则返回失败信息
        if (!$getOne) return app('json')->fail('数据不存在');
        // 调用仓库的删除方法删除商品标签
        $this->repository->delete($id);
        // 返回成功信息
        return app('json')->success('删除成功');
    }

    /**
     * 切换商品标签状态
     * @param int $id 商品标签ID
     * @return \think\response\Json
     */
    public function switchWithStatus($id)
    {
        // 获取请求参数中的状态值，如果没有则默认为0
        $status = $this->request->param('status') == 1 ? 1 : 0;
        // 从仓库中获取指定ID和商家ID的商品标签
        $getOne = $this->repository->getWhere(['product_label_id' => $id, 'mer_id' => $this->request->merId()]);
        // 如果没有获取到商品标签，则返回失败信息
        if (!$getOne) return app('json')->fail('数据不存在');
        // 调用仓库的更新方法更新商品标签状态
        $this->repository->update($id, ['status' => $status]);
        return app('json')->success('修改成功');
    }


    /**
     * 获取选项列表
     *
     * @return \think\response\Json
     */
    public function getOptions()
    {
        // 从仓库中获取选项数据
        $data = $this->repository->getOptions($this->request->merId());
        // 返回JSON格式的成功响应和数据
        return app('json')->success($data);
    }

    /**
     * 校验参数
     *
     * @param ProductLabelValidate $validate 产品标签验证器实例
     * @return array 校验通过的参数数组
     */
    public function checkParams(ProductLabelValidate $validate)
    {
        // 需要校验的参数列表
        $params = ['label_name', 'status', 'sort', 'info'];
        // 从请求中获取参数
        $data = $this->request->params($params);
        // 校验参数
        $validate->check($data);
        // 返回校验通过的参数数组
        return $data;
    }


}
