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


namespace app\controller\api\server;


use app\common\repositories\delivery\DeliveryStationRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\delivery\DeliveryServiceRepository;
use app\common\repositories\store\order\StoreRefundOrderRepository;
use app\common\repositories\delivery\DeliveryOrderRepository;
use app\common\repositories\store\staff\StaffsRepository;
use app\validate\merchant\OrderValidate;
use app\controller\merchant\Common;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;
use think\response\Json;

/**
 * Class StoreOrder
 * app\controller\api\server
 * 移动端客服 - 订单管理
 */
class StoreOrder extends BaseController
{
    /**
     * @var StoreOrderRepository
     */
    protected $repository;
    public function __construct(App $app, StoreOrderRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     *  数据统计
     * @param $merId
     * @return Json
     * @author Qinii
     */
    public function orderStatistics($merId)
    {
        $order = $this->repository->OrderTitleNumber($merId, null);
        $order['refund'] = app()->make(StoreRefundOrderRepository::class)->getWhereCount(['is_system_del' => 0, 'mer_id' => $merId]);
        $common = app()->make(Common::class);
        $data = [];
        $data['today'] = $common->mainGroup('today', $merId);
        $data['yesterday'] = $common->mainGroup('yesterday', $merId);
        $data['month'] = $common->mainGroup('month', $merId);
        return app('json')->success(compact('order', 'data'));
    }

    /**
     *  数据统计  - 时间筛选
     * @param $merId
     * @return Json
     * @author Qinii
     */
    public function orderDetail($merId)
    {
        [$page, $limit] = $this->getPage();
        list($start, $stop) = $this->request->params([
            ['start', strtotime(date('Y-m'))],
            ['stop', time()],
        ], true);
        if ($start == $stop) return app('json')->fail('参数有误');
        if ($start > $stop) {
            $middle = $stop;
            $stop = $start;
            $start = $middle;
        }
        $where = $this->request->has('start') ? ['dateRange' => compact('start', 'stop')] : [];
        $list = $this->repository->orderGroupNumPage($where, $page, $limit, $merId);
        return app('json')->success($list);
    }

    /**
     *  订单列表
     * @param $merId
     * @return Json
     * @author Qinii
     */
    public function orderList($merId)
    {
        [$page, $limit] = $this->getPage();
        $where['status'] = $this->request->param('status');
        $where['is_verify'] = $this->request->param('is_verify');
        $where['search'] = $this->request->param('store_name');
        $where['mer_id'] = $merId;
        $where['is_del'] = 0;
        if($where['status'] == 2) $where['order_type'] = 0;
        return app('json')->success($this->repository->merchantGetList($where, $page, $limit));
    }

    /**
     * 订单详情
     * @param $merId
     * @param $id
     * @return Json
     * @author Qinii
     */
    public function order($merId, $id)
    {
        $detail = $this->repository->getDetail($id);
        if (!$detail)
            return app('json')->fail('订单不存在');
        if ($detail['mer_id'] != $merId)
            return app('json')->fail('没有权限');
        return app('json')->success($detail->toArray());
    }

    /**
     * 检查用户是否有权限
     * @param $merId
     * @param $id
     * @return void
     * @author Qinii
     */
    protected function checkOrderAuth($merId, $id)
    {
        if (!$this->repository->existsWhere(['mer_id' => $merId, 'order_id' => $id]))
            throw new ValidateException('没有权限');
    }

    /**
     *  备注
     * @param $merId
     * @param $id
     * @return Json
     * @author Qinii
     */
    public function mark($merId, $id)
    {
        $this->checkOrderAuth($merId, $id);
        $data = $this->request->params(['remark']);
        $this->repository->update($id, $data);
        return app('json')->success('备注成功');
    }

    /**
     *  修改价格
     * @param $merId
     * @param $id
     * @return Json
     * @author Qinii
     */
    public function price($merId, $id)
    {
        $this->checkOrderAuth($merId, $id);

        $data = $this->request->params(['total_price', 'pay_postage']);

        if ($data['total_price'] < 0 || $data['pay_postage'] < 0)
            return app('json')->fail('金额不可未负数');
        if (!$this->repository->merStatusExists((int)$id, $merId))
            return app('json')->fail('订单信息或状态错误');
        $this->repository->eidt($id, $data);
        return app('json')->success('修改成功');
    }

    /**
     *  发货操作
     * @param $merId
     * @param $id
     * @return Json
     * @author Qinii
     * @day 6/1/22
     */
    public function delivery($merId, $id)
    {
        $this->checkOrderAuth($merId, $id);
        $type = $this->request->param('delivery_type');
        $split = $this->request->params(['is_split',['split',[]]]);
        if (!$this->repository->merDeliveryExists($id, $merId))
            return app('json')->fail('订单信息或状态错误');
        switch ($type)
        {
            case 2:
                $data = $this->request->params(['delivery_type', 'delivery_name', 'delivery_id', 'remark',]);
                if (!$data['delivery_type'] || !$data['delivery_name'] || !$data['delivery_id'])
                    return app('json')->fail('填写配送信息');
                $ser = app()->make(DeliveryServiceRepository::class)->get($data['delivery_name']);
                $data['delivery_name'] = $ser['name'] ?? $data['delivery_name'];
                $method = 'delivery';
                break;
            case 3: //虚拟发货
                $data  = $this->request->params([
                    'delivery_type',
                    'remark',
                ]);
                $data['delivery_name'] = '';
                $data['delivery_id'] = '';
                $method = 'delivery';
                break;
            case 4: //电子面单
                if (!systemConfig('crmeb_serve_dump'))
                    return app('json')->fail('电子面单功能未开启');
                $data = $this->request->params([
                    'delivery_type',
                    'delivery_name',
                    'from_name',
                    'from_tel',
                    'from_addr',
                    'temp_id',
                    'remark',
                ]);
                if (!$data['from_name'] ||
                    !$data['delivery_name'] ||
                    !$data['from_tel'] ||
                    !$data['from_addr'] ||
                    !$data['temp_id']
                )
                    return app('json')->fail('填写配送信息');
                $method = 'dump';
                break;
            case 5: //同城配送
                if (systemConfig('delivery_status') != 1)
                    return app('json')->fail('未开启同城配送');
                $data = $this->request->params([
                    'delivery_type',
                    'station_id',
                    'mark',
                    ['cargo_weight',0],
                    'remark',
                ]);
                if ($data['cargo_weight'] < 0) return app('json')->fail('包裹重量能为负数');
                if (!$data['station_id']) return app('json')->fail('请选择门店');
                $method = 'cityDelivery';
                break;
            default: //快递
                $data  = $this->request->params([
                    'delivery_type',
                    'delivery_type',
                    'delivery_name',
                    'delivery_id',
                    'remark',
                ]);
                if (!$data['delivery_type'] || !$data['delivery_name'] || !$data['delivery_id'])
                    return app('json')->fail('填写配送信息');

                $method = 'delivery';
                break;
        }
        $this->repository->runDelivery($id,$merId, $data, $split, $method, $this->request->serviceInfo()->service_id);
        return app('json')->success('发货成功');
    }

    /**
     *  订单金额统计 图表
     * @param $merId
     * @param $id
     * @return Json
     * @author Qinii
     */
    public function payPrice($merId)
    {
        list($start, $stop, $month) = $this->request->params([
            ['start', strtotime(date('Y-m'))],
            ['stop', time()],
            'month'
        ], true);

        if ($month) {
            $start = date('Y/m/d', strtotime(getStartModelTime('month')));
            $stop = date('Y/m/d H:i:s', strtotime('+ 1day'));
            $front = date('Y/m/d', strtotime('first Day of this month', strtotime('-1 day', strtotime('first Day of this month'))));
            $end = date('Y/m/d H:i:s', strtotime($start . ' -1 second'));
        } else {
            if ($start == $stop) return app('json')->fail('参数有误');
            if ($start > $stop) {
                $middle = $stop;
                $stop = $start;
                $start = $middle;
            }
            $space = bcsub($stop, $start, 0);//间隔时间段
            $front = bcsub($start, $space, 0);//第一个时间段

            $front = date('Y/m/d H:i:s', $front);
            $start = date('Y/m/d H:i:s', $start);
            $stop = date('Y/m/d H:i:s', $stop);
            $end = date('Y/m/d H:i:s', strtotime($start . ' -1 second'));
        }
        $frontPrice = $this->repository->dateOrderPrice($front . '-' . $end, $merId);
        $afterPrice = $this->repository->dateOrderPrice($start . '-' . date('Y/m/d H:i:s', strtotime($stop . '-1 second')), $merId);
        $chartInfo = $this->repository->chartTimePrice($start, date('Y/m/d H:i:s', strtotime($stop . '-1 second')), $merId);
        $data['chart'] = $chartInfo;//营业额图表数据
        $data['time'] = $afterPrice;//时间区间营业额
        $increase = (float)bcsub((string)$afterPrice, (string)$frontPrice, 2); //同比上个时间区间增长营业额
        $growthRate = abs($increase);
        if ($growthRate == 0) $data['growth_rate'] = 0;
        else if ($frontPrice == 0) $data['growth_rate'] = bcmul($growthRate, 100, 0);
        else $data['growth_rate'] = (int)bcmul((string)bcdiv((string)$growthRate, (string)$frontPrice, 2), '100', 0);//时间区间增长率
        $data['increase_time'] = abs($increase); //同比上个时间区间增长营业额
        $data['increase_time_status'] = $increase >= 0 ? 1 : 2; //同比上个时间区间增长营业额增长 1 减少 2

        return app('json')->success($data);
    }

    /**
     * 订单数量统计 图表
     * @return Json
     * @author xaboy
     * @day 2020/8/27
     */
    public function payNumber($merId)
    {
        list($start, $stop, $month) = $this->request->params([
            ['start', strtotime(date('Y-m'))],
            ['stop', time()],
            'month'
        ], true);

        if ($month) {
            $start = date('Y/m/d', strtotime(getStartModelTime('month')));
            $stop = date('Y/m/d H:i:s', strtotime('+ 1day'));
            $front = date('Y/m/d', strtotime('first Day of this month', strtotime('-1 day', strtotime('first Day of this month'))));
            $end = date('Y/m/d H:i:s', strtotime($start . ' -1 second'));
        } else {
            if ($start == $stop) return app('json')->fail('参数有误');
            if ($start > $stop) {
                $middle = $stop;
                $stop = $start;
                $start = $middle;
            }
            $space = bcsub($stop, $start, 0);//间隔时间段
            $front = bcsub($start, $space, 0);//第一个时间段

            $front = date('Y/m/d H:i:s', $front);
            $start = date('Y/m/d H:i:s', $start);
            $stop = date('Y/m/d H:i:s', $stop);
            $end = date('Y/m/d H:i:s', strtotime($start . ' -1 second'));
        }
        $frontNumber = $this->repository->dateOrderNum($front . '-' . $end, $merId);
        $afterNumber = $this->repository->dateOrderNum($start . '-' . date('Y/m/d H:i:s', strtotime($stop . '-1 second')), $merId);
        $chartInfo = $this->repository->chartTimeNum($start . '-' . date('Y/m/d H:i:s', strtotime($stop . '-1 second')), $merId);
        $data['chart'] = $chartInfo;//订单数图表数据
        $data['time'] = $afterNumber;//时间区间订单数
        $increase = $afterNumber - $frontNumber; //同比上个时间区间增长订单数
        $growthRate = abs($increase);
        if ($growthRate == 0) $data['growth_rate'] = 0;
        else if ($frontNumber == 0) $data['growth_rate'] = bcmul($growthRate, 100, 0);
        else $data['growth_rate'] = (int)bcmul((string)bcdiv((string)$growthRate, (string)$frontNumber, 2), '100', 0);//时间区间增长率
        $data['increase_time'] = abs($increase); //同比上个时间区间增长营业额
        $data['increase_time_status'] = $increase >= 0 ? 1 : 2; //同比上个时间区间增长营业额增长 1 减少 2

        return app('json')->success($data);
    }

    /**
     * 获取商户配置
     * @param $merId
     * @return Json
     * @author Qinii
     */
    public function getFormData($merId)
    {
        $config = [
            'mer_from_com',
            'mer_from_name',
            'mer_from_tel',
            'mer_from_addr',
            'mer_config_siid',
            'mer_config_temp_id'
        ];
        $data = merchantConfig($merId,$config);
        return app('json')->success($data);
    }

    /**
     * 获取配送配置
     * @return Json
     * @author Qinii
     */
    public function getDeliveryConfig()
    {
        $data = systemConfig(['crmeb_serve_dump','delivery_status']);
        return app('json')->success($data);
    }

    /**
     * 获取同城配送配置
     * @return Json
     * @author Qinii
     */
    public function getDeliveryOptions($merId, DeliveryStationRepository $repository)
    {
        if (!systemConfig('delivery_status')) {
            return app('json')->success([]);
        }
        $where = [
            'status' => 1,
            'mer_id' => $merId,
            'type' => systemConfig('delivery_type'),
        ];
        $data = $repository->getOptions($where)->toArray();
        $type = systemConfig('delivery_type') == 1 ? 'UU' : '达达';
        if (empty($data)) return app('json')->fail('请前往商户后台添加'.$type.'发货点');
        return app('json')->success($data);
    }

    /**
     * 订单核销
     * @param $merId
     * @param $id
     * @return Json
     * @author Qinii
     */
    public function verify($merId,$id)
    {
        $order = $this->repository->getWhere(['order_id' => $id,'mer_id' => $merId]);
        if (!$order)  return app('json')->fail('数据不存在');
        $data = $this->request->params(['verify_code','data']);

        // 根据订单ID、商家ID、验证码和订单类型查询订单，并连带查询订单产品信息
        $order = $this->repository->getWhere(['order_id' => $id, 'mer_id' => $merId, 'verify_code' => $data['verify_code'], 'order_type' => 1], '*', ['orderProduct']);
        // 如果订单不存在，则抛出验证异常
        if (!$order)  return app('json')->fail('订单不存在');
        // 如果订单未支付，则抛出验证异常
        if (!$order->paid)  return app('json')->fail('订单未支付');
        // 如果订单已全部核销，则抛出验证异常
        if ($order['status'])  return app('json')->fail('订单已全部核销，请勿重复操作');

        $this->repository->verifyOrder($order, $data,  $this->request->serviceInfo()->service_id);
        return app('json')->success('订单核销成功');
    }

    public function options($merId)
    {
        $where = [
            'status' => 1,
            'mer_id' => $merId,
        ];
        $data = app()->make(DeliveryServiceRepository::class)->getOptions($where);
        return app('json')->success($data);
    }
    /**
     * 预约订单派单
     *
     * @param integer $id
     * @return json
     */
    public function reservationDispatch(int $merId, int $id)
    {
        $params = $this->request->params(['staffs_id']);
        if (!$params['staffs_id']) {
            return app('json')->fail('请选择服务人员');
        }

        $res = $this->repository->reservationDispatch($id, $merId, $params, $this->request->serviceInfo()->service_id);
        if (!$res) {
            return app('json')->fail('派单失败');
        }

        return app('json')->success('派单成功');
    }
    /**
     * 派单员工列表
     *
     * @param integer $merId
     * @param StaffsRepository $repository
     * @return void
     */
    public function staffList(int $merId, StaffsRepository $repository)
    {
        $where = $this->request->params(['keyword']);
        $where['mer_id'] = $merId;
        $where['status'] = 1;
        [$page, $limit] = $this->getPage();
        $list = $repository->getList($where, $page, $limit);

        return app('json')->success($list);
    }
    /**
     * 预约订单改派
     *
     * @param integer $id
     * @return json
     */
    public function reservationUpdateDispatch(int $merId, int $id)
    {
        $params = $this->request->params(['staffs_id']);
        if (!$params['staffs_id']) {
            return app('json')->fail('请选择服务人员');
        }

        $res = $this->repository->reservationUpdateDispatch($id, $merId, $params, $this->request->serviceInfo()->service_id);
        if (!$res) {
            return app('json')->fail('改派失败');
        }

        return app('json')->success('改派成功');
    }
    /**
     * 预约订单改期
     *
     * @param [type] $id
     * @return void
     */
    public function reservationReschedule(int $merId, int $id)
    {
        $params = $this->request->params(
            [
                'order_type',
                'reservation_date',
                'real_name',
                'user_phone',
                'user_address',
                'order_extend',
                'part_start',
                'part_end'
            ]
        );

        $validate = app()->make(OrderValidate::class);
        if (!$validate->sceneReservationReschedule($params)) {
            return app('json')->fail($validate->getError());
        }
        $res = $this->repository->reservationReschedule($id, $merId, $params, $this->request->serviceInfo()->service_id);
        if (!$res) {
            return app('json')->fail('改约失败');
        }

        return app('json')->success('改约成功');
    }
    /**
     * 预约订单核销
     *
     * @param integer $id
     * @return void
     */
    public function reservationVerify(int $merId, int $id)
    {
        $res = $this->repository->reservationVerify($id, $merId, $this->request->serviceInfo()->service_id);
        if (!$res) {
            return app('json')->fail('核销失败');
        }
        return app('json')->success('核销成功');
    }
    /**
     * 预约配置
     *
     * @return json
     */
    public function reservationConfig(int $merId)
    {
        if (!$merId) {
            throw new ValidateException('参数错误');
        }

        $config = merchantConfig($merId, ['enable_assigned', 'enable_checkin', 'checkin_radius', 'enable_trace', 'trace_form_id']);
        $config['enable_assigned'] = $config['enable_assigned'] ?: 0;
        $config['enable_checkin'] = $config['enable_checkin'] ?: 0;
        $config['checkin_radius'] = $config['checkin_radius'] ?: 0;
        $config['enable_trace'] = $config['enable_trace'] ?: 0;
        $config['trace_form_id'] = $config['trace_form_id'] ?: 0;

        return app('json')->success($config);
    }
    /**
     * 配送人员列表
     *
     * @param integer $merId
     * @return void
     */
    public function deliveryPersonList(int $merId, DeliveryServiceRepository $repository)
    {
        $where = $this->request->params(['keyword']);
        $where['mer_id'] = $merId;
        $where['status'] = 1;
        [$page, $limit] = $this->getPage();
        $list = $repository->getList($where, $page, $limit);

        return app('json')->success($list);
    }
    /**
     * 同城配送派单
     *
     * @param integer $merId
     * @param integer $id
     * @return void
     */
    public function deliveryDispatch(int $merId, int $id)
    {
        $params = $this->request->params(['service_id']);
        if (!$params['service_id']) {
            return app('json')->fail('请选择配送人员');
        }

        $res = app()->make(DeliveryOrderRepository::class)->merDispatch($id, $merId, $params);
        if (!$res) {
            return app('json')->fail('派单失败');
        }

        return app('json')->success('派单成功');
    }
    /**
     * 同城配送改派
     *
     * @param integer $merId
     * @param integer $id
     * @return void
     */
    public function deliveryUpdateDispatch(int $merId, int $id)
    {
        $params = $this->request->params(['service_id']);
        if (!$params['service_id']) {
            return app('json')->fail('请选择配送人员');
        }

        $res = app()->make(DeliveryOrderRepository::class)->merUpdateDispatch($id, $merId, $params);
        if (!$res) {
            return app('json')->fail('改派失败');
        }

        return app('json')->success('改派成功');
    }
    /**
     * 同城配送确认
     *
     * @param integer $id
     * @return void
     */
    public function deliveryConfirm(int $merId, int $id)
    {
        $res = app()->make(DeliveryOrderRepository::class)->confirm($id, $merId, $this->request->serviceInfo()->service_id);
        if (!$res) {
            return app('json')->fail('确认失败');
        }
        return app('json')->success('确认成功');
    }

    public function offline($merId,$id)
    {
        $this->repository->offline($id, $merId);
        return app('json')->success('确认成功');
    }
}
