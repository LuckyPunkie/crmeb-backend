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

namespace app\controller\merchant\system\openapi;

use app\common\repositories\openapi\OpenAuthRepository;
use app\validate\merchant\OpenAuthValidate;
use crmeb\basic\BaseController;
use think\App;

class OpenApi extends BaseController
{
    protected $repository;

    /**
     * 构造函数
     *
     * @param App $app 应用实例
     * @param OpenAuthRepository $repository 开放认证仓库实例
     */
    public function __construct(App $app, OpenAuthRepository $repository)
    {
        // 调用父类构造函数
        parent::__construct($app);
        // 初始化仓库实例
        $this->repository = $repository;
    }

    /**
     * 获取列表数据
     *
     * @return \think\response\Json
     */
    public function lst()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['title', 'access_key','status']);
        // 添加商家ID条件
        $where['mer_id'] = $this->request->merId();
        // 获取数据列表
        $data = $this->repository->getList($where, $page, $limit);
        // 返回成功响应
        return app('json')->success($data);
    }

    /**
     * 创建表单数据
     *
     * @return \think\response\Json
     */
    public function createForm()
    {
        // 获取表单数据并转换为数组
        return app('json')->success(formToData($this->repository->form($this->request->merId())));
    }


    /**
     * 创建数据
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        // 检查参数
        $data = $this->checkParams();
        $this->repository->create($this->request->merId(), $data);
        // 返回成功信息
        return app('json')->success('添加成功');
    }

    /**
     * 获取表单数据
     *
     * @param int $id 数据 ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateForm($id)
    {
        return app('json')->success(formToData($this->repository->form($this->request->merId(), $id)));
    }

    /**
     * 更新数据
     *
     * @param int $id 数据 ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id)
    {
        // 检查参数
        $data = $this->checkParams();
        $data['update_time'] = date('Y-m-d H:i:s', time());
        $data['auth'] = implode(',', $data['auth']);
        $this->repository->update($id, $data);
        return app('json')->success('编辑成功');
    }


    /**
     * 根据ID切换状态
     * @param int $id ID
     * @return \think\response\Json 返回JSON格式的修改结果
     */
    public function switchWithStatus($id)
    {
        // 获取状态参数，默认为0
        $status = $this->request->param('status', 0) == 1 ?: 0;
        // 调用repository的update方法更新状态
        $this->repository->update($id, ['status' => $status]);
        // 返回JSON格式的修改成功结果
        return app('json')->success('修改成功');
    }

    /**
     * 根据ID删除记录
     * @param int $id ID
     * @return \think\response\Json 返回JSON格式的删除结果
     */
    public function delete($id)
    {
        // 调用repository的update方法更新is_del和delete_time字段
        $this->repository->update($id, ['is_del' => 1, 'delete_time' => date('Y-m-d H:i:s', time())]);
        // 返回JSON格式的删除成功结果
        return app('json')->success('删除成功');
    }

    /**
     * 根据ID获取密钥
     * @param int $id ID
     * @return \think\response\Json 返回JSON格式的密钥数据
     */
    public function getSecretKey($id)
    {
        // 调用repository的getSecretKey方法获取密钥数据
        $data = $this->repository->getSecretKey($id);
        // 返回JSON格式的密钥数据
        return app('json')->success($data);
    }


    /**
     * 设置密钥
     *
     * @param int $id 密钥ID
     * @return \Illuminate\Http\JsonResponse 返回JSON格式的数据
     */
    public function setSecretKey($id)
    {
        // 调用 repository 类的 setSecretKey 方法，传入 ID 和 merId，获取数据并存储到 data 变量中
        $data = $this->repository->setSecretKey($id, $this->request->merId());
        return app('json')->success('重置成功', $data);
    }

    /**
     * 检查参数
     *
     * @return array 返回检查后的参数数组
     */
    public function checkParams()
    {
        $data = $this->request->params(['title', 'status', 'mark', 'auth', 'sort']);
        // 实例化 OpenAuthValidate 类，并调用其 check 方法，传入 $data 作为参数，检查参数是否合法
        app()->make(OpenAuthValidate::class)->check($data);
        // 返回检查后的参数数组
        return $data;
    }

}
