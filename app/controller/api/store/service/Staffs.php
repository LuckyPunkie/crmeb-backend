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
namespace app\controller\api\store\service;

use think\App;
use crmeb\basic\BaseController;
use think\exception\ValidateException;
use app\common\repositories\store\order\OrderStatus;
use app\common\repositories\store\order\StoreOrderRepository;

class Staffs extends BaseController
{
    protected $repository;

    public function __construct(App $app, StoreOrderRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     *  员工订单
     * @return \think\response\Json
     * @author Qinii
     */
    public function order_lst()
    {
        [$page, $limit] =  $this->getPage();
        $where = $this->request->params([
            ['paid',1],
            ['status',''],
            ['assigned',''], // 0待领取 1已分配
            ['is_del',0],
            ['filter_product',4],
            ['order_type',0],
            ['store_name',''],
        ]);
        $where['reservation_date'] = $this->request->param('date','');
        $where['search'] = $this->request->param('store_name','');
        $where['staffs_ids'] = $this->request->staffsIds();
        if ($where['assigned'] == 1) {
            $where['enable_assigned'] = 0;
            $where['staffs_id'] = 0;
            $where['status'] = 0;
            unset($where['assigned'], $where['staffs_ids']);
        }
        $where['mer_ids'] = $this->request->staffsMerIds();
        $data = $this->repository->getList($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     *  订单详情
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function orderDetail($id)
    {
        $detail =  $this->repository->getDetail($id);
        $this->checkAuth($detail, 1);
        return app('json')->success($detail->toArray());
    }

    /**
     * 领取订单
     * @param int $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function reservationDispatch(int $id)
    {
        $order = $this->repository->getWhere(['order_id' => $id,'order_type' => 0, 'status' => 0, 'paid' => 1, 'is_del' => 0], '*', ['refundOrder']);
        $this->checkAuth($order, 1);
        if($order['staffs_id'] != 0){
            return app('json')->fail('订单已派单，请检查');
        }
        $staffs_id = $this->request->staffsList()[$order['mer_id']]['staffs_id'];
        $res = $this->repository->selfDispatch($order['mer_id'], $order, $staffs_id);

        if (!$res)return app('json')->fail('领取失败');
        return app('json')->success('领取成功');
    }

    /**
     * 打卡
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function checkIn($id)
    {
        $clock_in_info = $this->request->param('clock_in_info');
        $order = $this->repository->getWhere(['order_id' => $id, 'status' => 1, 'paid' => 1, 'is_del' => 0]);
        if (!$order) {
            throw new ValidateException('订单不存在或未支付，请检查');
        }

        $this->checkAuth($order, $order['order_type']);
        $data['status'] = OrderStatus::RESERVATION_ORDER_STATUS_INSERVICE;
        $data['clock_in_info'] = json_encode($clock_in_info);
        $this->repository->update($id,$data);
        return app('json')->success('打卡成功');
    }
    /**
     * 提交服务凭证
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function addTrace($id)
    {
        $reservation_service_voucher = $this->request->param('reservation_service_voucher');
        $order = $this->repository->getWhere(['order_id' => $id, 'status' => 20, 'paid' => 1, 'is_del' => 0]);
        if (!$order) {
            throw new ValidateException('订单不存在或未支付，请检查');
        }

        $this->checkAuth($order, $order['order_type']);
        $data['reservation_service_voucher'] = json_encode($reservation_service_voucher);
        $this->repository->update($id,$data);
        return app('json')->success('提交成功');
    }

    /**
     * 核销订单
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function verify($id)
    {
        $params = $this->request->params(['mer_id']);
        if (!$params['mer_id']) {
            throw new ValidateException('参数错误');
        }

        $res = $this->repository->reservationVerify($id, $params['mer_id'], 0, $this->request->staffsList()[$params['mer_id']]['staffs_id']);
        if (!$res) {
            return app('json')->fail('核销失败');
        }
        return app('json')->success('核销成功');
    }

    public function checkAuth($order, $orderType = 0)
    {
        if (!$order)
            throw new ValidateException('订单不存在或未支付，请检查');
        if (!in_array($order['mer_id'],$this->request->staffsMerIds()))
            throw new ValidateException('没有权限');
        if (!$orderType) {
            if (!in_array($order['staffs_id'],$this->request->staffsIds()))
                return app('json')->fail('订单不属于你');
        }
        return true;
    }
    /**
     * 预约配置
     *
     * @return json
     */
    public function reservationConfig()
    {
        $data = $this->request->params(['mer_id']);

        if (!$data['mer_id']) {
            throw new ValidateException('参数错误');
        }

        $config = merchantConfig($data['mer_id'], ['enable_assigned', 'enable_checkin', 'checkin_radius','checkin_take_photo', 'enable_trace', 'trace_form_id']);
        $config['enable_assigned'] = $config['enable_assigned'] ?: 0;
        $config['enable_checkin'] = $config['enable_checkin'] ?: 0;
        $config['checkin_radius'] = $config['checkin_radius'] ?: 0;
        $config['enable_trace'] = $config['enable_trace'] ?: 0;
        $config['trace_form_id'] = $config['trace_form_id'] ?: 0;

        return app('json')->success($config);
    }

    public function mark($id, StoreOrderRepository $repository)
    {
        $order = $repository->getWhere(['order_id' => $id,]);
        $this->checkAuth($order);
        $data = $this->request->params(['remark']);
        $repository->update($id, $data);
        return app('json')->success('备注成功');
    }
}
