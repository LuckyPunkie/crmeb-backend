<?php

namespace app\controller\merchant\store;

use app\common\repositories\store\staff\StaffsRepository;
use app\validate\merchant\StaffValidate;
use crmeb\basic\BaseController;
use Psr\Http\Message\ResponseInterface;
use think\App;
use think\db\exception\DbException;
use think\response\Json;
use app\common\model\store\staff\Staffs as StaffsModel;
use think\exception\ValidateException;

class Staffs extends BaseController
{
    protected $repository;

    /**
     * 构造函数
     * @param App $app 应用实例
     * @param StaffsRepository $repository 员工仓库实例
     */
    public function __construct(App $app, StaffsRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取列表数据
     * @return \think\response\Json
     */
    public function lst()
    {
        // 从请求参数中获取关键字和状态
        $where = $this->request->params(['name', 'status', 'uid', 'phone']);
        // 获取分页信息
        [$page, $limit] = $this->getPage();
        // 设置商家ID
        $where['mer_id'] = $this->request->merId();
        // 调用 repository 层获取列表数据
        $data = $this->repository->getList($where, $page, $limit);
        // 返回 JSON 格式的成功响应
        return app('json')->success($data);
    }

    /**
     * 创建表单
     * @return \think\response\Json
     * @throws \FormBuilder\Exception\FormBuilderException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function createForm()
    {
        // 调用仓库的表单方法并返回结果
        return app('json')->success(formToData($this->repository->form()));
    }

    /**
     * 创建
     *
     * @param StaffValidate $validate 商品属性模板验证器
     * @return ResponseInterface JSON格式的响应结果
     */
    public function create(StaffValidate $validate)
    {
        // 调用checkParams方法获取验证后的数据
        $data = $this->checkParams($validate);
        $isExist = $this->repository->getWhere(['uid' => $data['uid'], 'mer_id' => $this->request->merId()]);
        if($isExist) {
            throw new ValidateException('该员工已存在，请勿重复创建');
        }
        // 设置商家ID
        $data['mer_id']      = $this->request->merId();
        $data['delete_time'] = null;
        // 调用repository的create方法创建商品属性模板
        $this->repository->create($data);
        // 返回JSON格式的成功响应结果
        return app('json')->success('添加成功');
    }

    public function updateForm($id)
    {
        // 判断表单是否存在
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->form($id)));
    }

    /**
     * 更新
     * @param $id
     * @param StaffValidate $validate
     * @return Json
     * @throws DbException
     */
    public function update($id, StaffValidate $validate)
    {
        // 获取当前商家ID
        $merId = $this->request->merId();
        // 判断商家是否存在该属性模板
        if (!$this->repository->merExists($merId, $id))
            return app('json')->fail('数据不存在');
        // 校验参数并添加商家ID
        $data           = $this->checkParams($validate);
        $data['mer_id'] = $merId;
        // 更新属性模板
        $this->repository->update($id, $data);
        // 返回成功响应
        return app('json')->success('编辑成功');
    }

    /**
     * 修改状态
     * @param $id
     * @return Json
     * @throws DbException
     */
    public function changeStatus($id)
    {
        // 获取请求参数中的状态值
        $status = $this->request->param('status');
        // 判断商品是否存在
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 更新商品状态
        $this->repository->update($id, ['status' => $status == 1 ? 1 : 0]);
        // 返回操作结果
        return app('json')->success('修改成功');
    }

    /**
     * 根据ID删除数据
     * @param $id
     * @return Json
     * @throws DbException
     */
    public function delete($id)
    {
        // 获取当前商家ID
        $merId = $this->request->merId();
        // 判断数据是否存在
        if (!$this->repository->merExists($merId, $id))
            return app('json')->fail('数据不存在');
        // 删除数据
        StaffsModel::destroy($id);
        // 返回成功响应结果
        return app('json')->success('删除成功');
    }


    /**
     * 检查参数是否符合要求
     * @param StaffValidate $validate
     * @param bool $isUpdate
     * @return array|mixed|string|string[]
     */
    public function checkParams(StaffValidate $validate, $isUpdate = false)
    {
        // 获取请求参数
        $data = $this->request->params([['uid', []], 'photo', 'name', 'phone', 'remark', 'status', 'sort']);
        // 如果是更新操作，则调用验证器的update方法
        if ($isUpdate) {
            $validate->update();
        }
        // 对数据进行验证
        $validate->check($data);
        // 如果没有上传头像，则将其设置为uid的src属性值
        if (!$data['photo']) $data['photo'] = $data['uid']['src'];
        // 将uid设置为uid的id属性值
        $data['uid'] = $data['uid']['id'];
        // 返回处理后的数据
        return $data;
    }
    /**
     * 统计列表
     *
     * @return void
     */
    public function statisticsList()
    {
        // 从请求参数中获取关键字和状态
        $where = $this->request->params(['name', 'uid', 'phone', 'service_date']);
        // 获取分页信息
        [$page, $limit] = $this->getPage();
        // 设置商家ID
        $where['mer_id'] = $this->request->merId();
        // 调用 repository 层获取列表数据
        $data = $this->repository->getStatisticsList($where, $page, $limit);
        // 返回 JSON 格式的成功响应
        return app('json')->success($data);
    }


    public function statisticsDetail($id)
    {
        // 获取当前商家ID
        $merId = $this->request->merId();
        // 判断数据是否存在
        if (!$this->repository->merExists($merId, $id))
            return app('json')->fail('数据不存在');
        // 获取统计详情
        $data = $this->repository->getStatisticsDetail($id);
        // 返回JSON格式的成功响应结果
        return app('json')->success($data);
    }

}