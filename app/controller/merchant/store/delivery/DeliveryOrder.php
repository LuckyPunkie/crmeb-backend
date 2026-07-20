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

use app\common\repositories\delivery\DeliveryOrderRepository;
use app\common\repositories\system\serve\ServeOrderRepository;
use think\App;
use crmeb\basic\BaseController;
use think\exception\ValidateException;

class DeliveryOrder extends BaseController
{
    protected $repository;

    /**
     * 构造函数
     *
     * @param App $app 应用实例
     * @param DeliveryOrderRepository $repository 配送订单仓库实例
     */
    public function __construct(App $app, DeliveryOrderRepository $repository)
    {
        // 调用父类构造函数
        parent::__construct($app);
        // 初始化配送订单仓库实例
        $this->repository = $repository;
    }

    /**
     * 获取配送订单列表
     *
     * @return \think\response\Json
     */
    public function lst()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'station_id', 'status', 'date', 'order_sn', 'station_type']);
        // 添加商家ID条件
        $where['mer_id'] = $this->request->merId();
        // 获取配送订单列表
        $data = $this->repository->merList($where, $page, $limit);
        // 返回成功响应
        return app('json')->success($data);
    }

    /**
     * 获取配送订单详情
     *
     * @param int $id 配送订单ID
     * @return \think\response\Json
     */
    public function detail($id)
    {
        $data = $this->repository->detail($id, $this->request->merId());
        // 返回成功响应
        return app('json')->success($data);
    }

    /**
     * 取消配送订单
     *
     * @param int $id 配送订单ID
     * @return \think\response\Json
     */
    public function cancelForm($id)
    {
        // 转换表单数据为数组并取消配送订单
        return app('json')->success(formToData($this->repository->cancelForm($id, $this->request->merId())));
    }


    /**
     * 取消操作
     * @param int $id 订单ID
     * @return \think\response\Json 返回JSON格式的结果
     */
    public function cancel($id)
    {
        $reason = $this->request->params(['reason', 'cancel_reason']);
        // 判断取消理由是否为空
        if (empty($reason['reason']))
            return app('json')->fail('取消理由不能为空');
        // 调用仓库的取消方法
        $this->repository->cancel($id, $this->request->merId(), $reason);
        // 返回成功结果
        return app('json')->success('取消成功');
    }

    /**
     * 删除操作
     * @param int $id 订单ID
     * @return \think\response\Json 返回JSON格式的结果
     */
    public function delete($id)
    {
        // 调用仓库的删除方法
        $this->repository->destory($id, $this->request->merId());
        return app('json')->success('删除成功');
    }

    /**
     * 切换状态操作
     * @param int $id 订单ID
     * @return \think\response\Json 返回JSON格式的结果
     */
    public function switchWithStatus($id)
    {
        // 获取状态值
        $status = $this->request->param('status') == 1 ? 1 : 0;
        $this->repository->update($id, ['status' => $status]);
        return app('json')->success('修改成功');
    }


}
