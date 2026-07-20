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


namespace app\controller\merchant\system\admin;


use app\common\repositories\system\auth\RoleRepository;
use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantAdminRepository;
use app\validate\admin\AdminEditValidate;
use app\validate\admin\AdminValidate;
use FormBuilder\Exception\FormBuilderException;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * Class MerchantAdmin
 * @package app\controller\admin\system\admin
 * @author xaboy
 * @day 2020-04-18
 */
class MerchantAdmin extends BaseController
{
    /**
     * @var MerchantAdminRepository
     */
    protected $repository;

    /**
     * @var int
     */
    protected $merId;

    /**
     * 构造函数
     *
     * @param App $app 应用实例
     * @param MerchantAdminRepository $repository 商家管理仓库实例
     */
    public function __construct(App $app, MerchantAdminRepository $repository)
    {
        // 调用父类构造函数
        parent::__construct($app);
        // 初始化商家管理仓库实例
        $this->repository = $repository;
        // 获取当前商家ID
        $this->merId = $this->request->merId();
    }

    /**
     * 获取列表
     *
     * @return mixed
     */
    public function getList()
    {
        // 获取查询条件
        $where = $this->request->params(['keyword', 'date', 'status']);
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 调用商家管理仓库实例的获取列表方法并返回结果
        return app('json')->success($this->repository->getList($this->merId, $where, $page, $limit));
    }

    /**
     * 切换状态
     *
     * @param int $id 商品ID
     * @return mixed
     */
    public function switchStatus($id)
    {
        // 获取状态值
        $status = $this->request->param('status');
        // 判断商品是否存在
        if (!$this->repository->exists($id, $this->merId, 1))
            return app('json')->fail('数据不存在');
        // 更新商品状态
        $this->repository->update($id, ['status' => $status == 1 ? 1 : 0]);
        // 返回操作成功结果
        return app('json')->success('编辑成功');
    }


    /**
     * 创建表单
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createForm()
    {
        // 调用 formToData 方法将表单转换为数据并返回 JSON 格式的成功响应
        return app('json')->success(formToData($this->repository->form($this->merId)));
    }

    /**
     * 更新表单
     *
     * @param int $id 表单 ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateForm($id)
    {
        // 判断表单是否存在，若不存在则返回 JSON 格式的失败响应
        if (!$this->repository->exists($id, $this->merId, 1))
            return app('json')->fail('数据不存在');
        // 调用 repository 的 updateForm 方法更新表单，并将结果转换为数据并返回 JSON 格式的成功响应
        return app('json')->success(formToData($this->repository->updateForm($this->merId, $id)));
    }

    /**
     * 密码表单
     *
     * @param int $id 表单 ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function passwordForm($id)
    {
        if (!$this->repository->exists($id, $this->merId))
            return app('json')->fail('数据不存在');
        // 调用 repository 的 passwordForm 方法获取密码表单，并将结果转换为数据并返回 JSON 格式的成功响应
        return app('json')->success(formToData($this->repository->passwordForm($id)));
    }


    /**
     * 创建管理员
     *
     * @param AdminValidate $validate 验证器实例
     * @return \think\response\Json
     */
    public function create(AdminValidate $validate)
    {
        $data = $this->request->params(['account', 'phone', 'pwd', 'againPassword', 'real_name', ['roles', []], ['status', 0]]);
        // 验证参数
        $validate->check($data);

        // 判断两次密码是否一致
        if ($data['pwd'] !== $data['againPassword'])
            return app('json')->fail('两次密码输入不一致');
        // 删除再次确认密码字段
        unset($data['againPassword']);
        // 判断账号是否已存在
        if ($this->repository->merFieldExists($this->merId, 'account', $data['account']))
            return app('json')->fail('账号已存在');
        // 对密码进行加密
        $data['pwd'] = $this->repository->passwordEncode($data['pwd']);
        // 设置商家ID和等级
        $data['mer_id'] = $this->merId;
        $data['level'] = 1;
        // 检查角色是否可用
        $check = app()->make(RoleRepository::class)->checkRole($data['roles'], $this->merId);
        if (!$check) {
            return app('json')->fail('未开启或者不存在的身份不能添加');
        }
        // 创建管理员
        $this->repository->create($data);

        return app('json')->success('添加成功');
    }

    /**
     * 编辑管理员
     *
     * @param int $id 管理员ID
     * @param AdminValidate $validate 验证器实例
     * @return \think\response\Json
     */
    public function update($id, AdminValidate $validate)
    {
        $data = $this->request->params(['account', 'phone', 'real_name', ['roles', []], ['status', 0]]);
        $validate->isUpdate()->check($data);
        if ($this->repository->merFieldExists($this->merId, 'account', $data['account'], $id))
            return app('json')->fail('账号已存在');

        // 检查角色是否可用
        $check = app()->make(RoleRepository::class)->checkRole($data['roles'], $this->merId);
        if (!$check) {
            return app('json')->fail('未开启或者不存在的身份不能添加');
        }
        $this->repository->update($id, $data);

        return app('json')->success('编辑成功');
    }


    /**
     * 修改管理员密码
     *
     * @param int $id 管理员ID
     * @param AdminValidate $validate 管理员验证器实例
     * @return \think\response\Json
     */
    public function password($id, AdminValidate $validate)
    {
        // 获取请求参数
        $data = $this->request->params(['pwd', 'againPassword']);
        // 验证密码是否符合规则
        $validate->isPassword()->check($data);

        // 判断两次密码是否一致
        if ($data['pwd'] !== $data['againPassword'])
            return app('json')->fail('两次密码输入不一致');
        // 判断管理员是否存在
        if (!$this->repository->exists($id, $this->merId))
            return app('json')->fail('管理员不存在');
        // 对密码进行加密
        $data['pwd'] = $this->repository->passwordEncode($data['pwd']);
        // 删除再次确认密码字段
        unset($data['againPassword']);
        // 更新管理员密码
        $this->repository->update($id, $data);

        return app('json')->success('修改密码成功');
    }

    /**
     * 删除管理员
     *
     * @param int $id 管理员ID
     * @return \think\response\Json
     */
    public function delete($id)
    {
        if (!$this->repository->exists($id, $this->merId, 1))
            return app('json')->fail('数据不存在');
        // 更新管理员状态为已删除
        $this->repository->update($id, ['is_del' => 1]);
        return app('json')->success('删除成功');
    }


    /**
     * 编辑管理员信息
     *
     * @param AdminEditValidate $validate 管理员编辑验证器
     * @return \think\response\Json 返回操作结果
     */
    public function edit(AdminEditValidate $validate)
    {
        // 从请求参数中获取真实姓名和电话号码
        $data = $this->request->params(['real_name', 'phone']);
        // 对获取到的数据进行校验
        $validate->check($data);
        // 调用仓库的更新方法，更新管理员信息
        $this->repository->update($this->request->adminId(), $data);
        // 返回操作成功的JSON响应
        return app('json')->success('修改成功');
    }


    /**
     * 编辑表单页面
     *
     * @return \think\response\Json
     */
    public function editForm()
    {
        // 获取当前管理员信息
        $adminInfo = $this->request->adminInfo();
        // 最后通过 app('json') 函数返回一个 JSON 格式的响应
        return app('json')->success(formToData($this->repository->editForm(['real_name' => $adminInfo->real_name, 'phone' => $adminInfo->phone,'merchant_admin_id' => $adminInfo->merchant_admin_id])));
    }


    /**
     * 编辑管理员密码
     *
     * @param AdminValidate $validate 验证器实例
     * @return \think\response\Json 返回操作结果
     */
    public function editPassword(AdminValidate $validate)
    {
        // 获取表单提交的密码和确认密码
        $data = $this->request->params(['pwd', 'againPassword']);
        // 验证密码是否符合规则
        $validate->isPassword()->check($data);

        // 判断两次输入的密码是否一致
        if ($data['pwd'] !== $data['againPassword'])
            return app('json')->fail('两次密码输入不一致');
        // 对密码进行加密处理
        $data['pwd'] = $this->repository->passwordEncode($data['pwd']);
        // 删除确认密码字段
        unset($data['againPassword']);
        // 更新管理员密码
        $this->repository->update($this->request->adminId(), $data);

        // 返回操作成功结果
        return app('json')->success('修改密码成功');
    }


    /**
     * 编辑密码表单
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function editPasswordForm()
    {
        return app('json')->success(formToData($this->repository->passwordForm($this->request->adminId(), 3)));
    }


}
