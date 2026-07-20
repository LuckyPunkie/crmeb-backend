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


namespace app\controller\merchant\system\auth;


use crmeb\basic\BaseController;
use app\common\repositories\system\auth\RoleRepository;
use app\validate\admin\RoleValidate;
use FormBuilder\Exception\FormBuilderException;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

class Role extends BaseController
{
    protected $repository;

    public function __construct(App $app, RoleRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 创建表单
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createForm()
    {
        // 获取商家信息
        $merchant = $this->request->merchant();
        // 调用 formToData 方法将表单转换为数据并返回成功响应
        return app('json')->success(formToData($this->repository->form((int)$merchant->type_id)));
    }

    /**
     * 更新表单
     *
     * @param int $id 表单 ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateForm($id)
    {
        // 判断商家是否存在该表单
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 获取商家信息
        $merchant = $this->request->merchant();
        // 调用 updateForm 方法更新表单并将结果转换为数据并返回成功响应
        return app('json')->success(formToData($this->repository->updateForm((int)$merchant->type_id, $id)));
    }

    /**
     * 获取列表
     *
     * @return \think\response\Json
     */
    public function getList()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 调用 repository 的 search 方法获取数据并返回 JSON 格式的成功响应
        return app('json')->success($this->repository->search($this->request->merId(), [], $page, $limit));
    }

    /**
     * 切换状态
     *
     * @param int $id 商户 ID
     * @return \think\response\Json
     */
    public function switchStatus($id)
    {
        // 获取状态参数
        $status = $this->request->param('status');
        // 判断商户是否存在
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 更新商户状态
        $this->repository->update($id, ['status' => $status == 1 ? 1 : 0]);
        // 返回 JSON 格式的成功响应
        return app('json')->success('编辑成功');
    }

    /**
     * 根据ID删除数据
     *
     * @param int $id 数据ID
     * @return \think\response\Json 返回JSON格式的响应结果
     */
    public function delete($id)
    {
        // 判断数据是否存在
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 删除数据
        $this->repository->delete($id);
        // 返回成功响应
        return app('json')->success('删除成功');
    }

    /**
     * 创建新数据
     *
     * @param RoleValidate $validate 验证器实例
     * @return \think\response\Json 返回JSON格式的响应结果
     */
    public function create(RoleValidate $validate)
    {
        // 获取参数并验证
        $data = $this->checkParam($validate);
        $data['mer_id'] = $this->request->merId();

        // 创建新数据
        $this->repository->create($data);
        return app('json')->success('添加成功');
    }

    /**
     * 更新角色信息
     *
     * @param int $id 角色ID
     * @param RoleValidate $validate 角色验证器
     * @return \think\response\Json 返回JSON格式的响应结果
     */
    public function update($id, RoleValidate $validate)
    {
        // 获取参数并进行验证
        $data = $this->checkParam($validate);
        // 判断角色是否存在
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 更新角色信息
        $this->repository->update($id, $data);
        // 返回成功响应
        return app('json')->success('编辑成功');
    }

    /**
     * 检查参数并进行验证
     *
     * @param RoleValidate $validate 角色验证器
     * @return array 返回验证通过的参数数组
     */
    private function checkParam(RoleValidate $validate)
    {
        $data = $this->request->params(['role_name', ['rules', []], ['status', 0]]);
        // 进行验证
        $validate->check($data);
        // 返回验证通过的参数数组
        return $data;
    }

}
