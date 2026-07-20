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

namespace app\controller\merchant\store\delivery;

use app\common\repositories\delivery\DeliveryServiceRepository;
use app\common\repositories\system\serve\ServeOrderRepository;
use app\validate\merchant\DeliveryServiceValidate;
use crmeb\services\DeliverySevices;
use think\App;
use crmeb\basic\BaseController;
use app\validate\merchant\DeliveryStationValidate;
use think\exception\ValidateException;

class DeliveryService extends BaseController
{
    protected $repository;

    public function __construct(App $app, DeliveryServiceRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     *  列表
     * @return \think\response\Json
     * @author Qinii
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword','name','status','type','date','uid','phone','nickname']);
        $where['mer_id'] = $this->request->merId();
        $data = $this->repository->getList($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 详情
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function detail($id)
    {
        $data = $this->repository->detail($id,$this->request->merId());
        return app('json')->success($data);
    }

    /**
     *  添加表单
     * @return \think\response\Json
     * @author Qinii
     */
    public function createForm()
    {
        $form = $this->repository->form(null);
        return app('json')->success(formToData($form));
    }

    /**
     * 编辑表单
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function updateForm($id)
    {
        $form = $this->repository->form($id);
        return app('json')->success(formToData($form));
    }

    /**
     * 添加
     * @return \think\response\Json
     * @author Qinii
     */
    public function create()
    {
        $data = $this->checkParams();
        $isExist = $this->repository->getWhere(['uid' => $data['uid'], 'mer_id' => $this->request->merId()]);
        if($isExist) {
            throw new ValidateException('该配送员已存在,请勿重复创建');
        }
        $data['mer_id'] = $this->request->merId();

        $this->repository->create($data);
        return app('json')->success('添加成功');
    }

    /**
     * 编辑
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function update($id)
    {
        $data = $this->checkParams();
        $this->repository->update($id, $data);
        return app('json')->success('编辑成功');
    }

    /**
     * 删除
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function delete($id)
    {
        $getOne = $this->repository->getWhere(['service_id' => $id, 'mer_id' => $this->request->merId()]);
        if (!$getOne){
            return app('json')->fail('该配送员不存在或不属于您的商户，无法删除');
        }
        $this->repository->delete($id);
        return app('json')->success('删除成功');
    }

    /**
     * 根据ID切换服务状态
     * @param int $id 服务ID
     * @return \think\response\Json
     */
    public function switchWithStatus($id)
    {
        // 获取请求参数中的状态值，如果没有则默认为0
        $status = $this->request->param('status') == 1 ? 1 : 0;
        // 根据条件查询服务信息
        $getOne = $this->repository->getWhere(['service_id' => $id, 'mer_id' => $this->request->merId()]);
        // 如果查询结果为空，则返回错误信息
        if (!$getOne) return app('json')->fail('数据不存在');
        // 更新服务状态
        $this->repository->update($id, ['status' => $status]);
        // 返回成功信息
        return app('json')->success('修改成功');
    }


    /**
     * 检查请求参数
     * @return array
     */
    public function checkParams()
    {
        // 从请求参数中获取头像、姓名、电话和状态信息
        $data = $this->request->params([['uid', []],'avatar', 'name', 'phone', 'remark', 'status', 'sort']);
        // 使用DeliveryServiceValidate类对数据进行校验
        app()->make(DeliveryServiceValidate::class)->check($data);
        // 如果没有上传头像，则将其设置为uid的src属性值
        if (!$data['avatar']) $data['avatar'] = $data['uid']['src'];
        // 将uid设置为uid的id属性值
        $data['uid'] = $data['uid']['id'];
        // 返回校验后的数据
        return $data;
    }


    /**
     * 配送员所有下啦筛选
     * @return \think\response\Json
     * @author Qinii
     */
    public function options()
    {
        $where = [
            'status' => 1,
            'mer_id' => $this->request->merId(),
        ];
        return app('json')->success($this->repository->getOptions($where));
    }
    /**
     * 统计列表
     *
     * @return void
     */
    public function statisticsList()
    {
        // 从请求参数中获取关键字和状态
        $where = $this->request->params(['name', 'uid', 'phone', ['delivery_date', []]]);
        // 获取分页信息
        [$page, $limit] = $this->getPage();
        // 设置商家ID
        $where['mer_id'] = $this->request->merId();
        // 调用 repository 层获取列表数据
        $data = $this->repository->getStatisticsList($where, $page, $limit);
        // 返回 JSON 格式的成功响应
        return app('json')->success($data);
    }
    /**
     * 统计详情
     *
     * @param int $id
     * @return void
     */
    public function statisticsDetail($id)
    {
        // 获取当前商家ID
        $merId = $this->request->merId();
        // 判断数据是否存在
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        // 获取统计详情
        $data = $this->repository->getStatisticsDetail($id);
        // 返回JSON格式的成功响应结果
        return app('json')->success($data);
    }
}
