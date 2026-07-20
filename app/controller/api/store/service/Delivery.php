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
use app\common\repositories\delivery\DeliveryOrderRepository;
use app\common\repositories\store\order\StoreOrderRepository;

class Delivery extends BaseController
{
    protected $repository;

    public function __construct(App $app, StoreOrderRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 员工订单
     * @return \think\response\Json
     * @author Qinii
     */
    public function order_lst()
    {
        [$page, $limit] =  $this->getPage();
        $params = $this->request->params([['type', 0], ['status', 0], 'delivery_keywords']);
        $merIds = $this->request->deliveryMerIds();
        // 订单查询条件
        $orderIds = [];
        $where['status'] = $params['status'];
        if($params['type']){
            $deliveryOrderwhere['service_ids'] = $this->request->deliveryIds();
            $deliveryOrderwhere['mer_ids'] = $merIds;
            $orderIds = app()->make(DeliveryOrderRepository::class)->getOrderIds($deliveryOrderwhere);
            if(!$orderIds) {
                return app('json')->success(['count' => 0, 'list' => []]);
            }
            $where['order_ids'] = $orderIds;
            if($params['status'] == 2) {
                $where['status'] = 11;
            }
        }

        $where['service_type'] = 2;
        $where['mer_ids'] = $merIds;
        $where['paid'] = 1;
        $where['delivery_keywords'] = $params['delivery_keywords'] ?: '';
        $order = $this->repository->getList($where, $page, $limit);
        // 剔除使用uu、达达配送以及不支持领取的订单
        $orderList = $order['list']->toArray();
        foreach($orderList as $key => &$item) {
            if((isset($item['take']['type']) && $item['take']['type'])) {
                unset($orderList[$key]);
            }

            if(!merchantConfig($item['mer_id'], 'mer_delivery_order_status') && $params['type'] == 0 && $params['status'] == 0) {
                unset($orderList[$key]);
            }
        }
        $order['list'] = array_values($orderList);
        $order['count'] = count($order['list']);
        unset($orderList);

        return app('json')->success($order);
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
    public function receive(int $id)
    {
        $order = $this->repository->get($id);
        $this->checkAuth($order, 1);

        $deliveryId = $this->request->deliveryList()[$order['mer_id']]['service_id'];
        $res = app()->make(DeliveryOrderRepository::class)->selfReceive($order['mer_id'], $id, $deliveryId);
        if (!$res) {
            return app('json')->fail('领取失败');
        }
        return app('json')->success('领取成功');
    }
    /**
     * 确认订单
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function confirm($id)
    {
        $params = $this->request->params(['mer_id']);
        if (!$params['mer_id']) {
            throw new ValidateException('参数错误');
        }

        $res = app()->make(DeliveryOrderRepository::class)->confirm($id, $params['mer_id']);
        if (!$res) {
            return app('json')->fail('确认失败');
        }
        return app('json')->success('确认成功');
    }
    /**
     * 备注订单
     *
     * @param int $id
     * @param StoreOrderRepository $repository
     * @return void
     */
    public function mark(int $id)
    {
        $data = $this->request->params(['remark']);
        if (!$data['remark']) {
            throw new ValidateException('请输入备注信息');
        }

        $order = $this->repository->get($id);
        $this->checkAuth($order);
        $this->repository->update($id, $data);

        return app('json')->success('备注成功');
    }
    /**
     * 检查订单权限
     *
     * @param object $order
     * @param integer $orderType
     * @return void
     */
    public function checkAuth($order, $orderType = 0)
    {
        if (!$order)
            throw new ValidateException('订单不存在或未支付，请检查');
        if (!in_array($order['mer_id'],$this->request->deliveryMerIds()))
            throw new ValidateException('没有权限');
        if (!$orderType) {
            if ($order['deliveryOrder'] && !in_array($order['deliveryOrder']['service_id'],$this->request->deliveryIds()))
                throw new ValidateException('订单不属于你');
        }
        return true;
    }
}
