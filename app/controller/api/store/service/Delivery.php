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

use app\common\model\delivery\DeliveryService;
use app\common\model\delivery\DeliveryStation;
use think\App;
use crmeb\basic\BaseController;
use think\exception\ValidateException;
use app\common\repositories\delivery\DeliveryOrderRepository;
use app\common\repositories\delivery\DeliveryServiceBillRepository;
use app\common\repositories\delivery\DeliveryServiceRepository;
use app\common\repositories\delivery\DeliveryServiceWithdrawRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\validate\api\DeliveryServiceWithdrawValidate;

class Delivery extends BaseController
{
    protected $repository;

    public function __construct(App $app, StoreOrderRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 可抢配送订单
     * @return \think\response\Json
     */
    public function grabList()
    {
        [$page, $limit] = $this->getPage();
        $params = $this->request->params([['status', 0], 'delivery_keywords', ['lat', ''], ['lng', ''], ['long', '']]);
        if ($params['lng'] === '' && $params['long'] !== '') {
            $params['lng'] = $params['long'];
        }
        $deliveryRole = $this->request->deliveryRole();
        $merchantIds = $this->request->deliveryMerchantIds();
        $where = [
            'status' => $params['status'],
            'service_type' => 2,
            'paid' => 1,
            'delivery_grab_status' => 1,
            'delivery_keywords' => $params['delivery_keywords'] ?: '',
            'lat' => $params['lat'],
            'lng' => $params['lng'],
        ];

        if ($this->request->isPlatformDelivery() && (int)systemConfig('platform_delivery_status') === 1) {
            $where['is_city_delivery'] = [DeliveryStation::TYPE_PLATFORM];
        } else if ($merchantIds) {
            $where['is_city_delivery'] = [0];
            $where['mer_ids'] = $merchantIds;
        } else {
            return app('json')->success([
                'count' => 0,
                'list' => [],
                'delivery_role' => $deliveryRole,
            ]);
        }

        $order = $this->repository->getList($where, $page, $limit);
        $order['delivery_role'] = $deliveryRole;
        return app('json')->success($order);
    }

    /**
     * 当前配送员的订单
     * @return \think\response\Json
     */
    public function myList()
    {
        [$page, $limit] = $this->getPage();
        $params = $this->request->params([['status', 0], 'delivery_keywords', ['lat', ''], ['lng', ''], ['long', '']]);
        if ($params['lng'] === '' && $params['long'] !== '') {
            $params['lng'] = $params['long'];
        }
        $deliveryRole = $this->request->deliveryRole();
        $orderIds = app()->make(DeliveryOrderRepository::class)->getOrderIds([
            'service_ids' => $this->request->deliveryIds(),
            'exclude_status' => 0,
        ]);
        if (!$orderIds) {
            return app('json')->success([
                'count' => 0,
                'list' => [],
                'delivery_role' => $deliveryRole,
            ]);
        }

        $where = [
            'status' => (int)$params['status'] === 2 ? 11 : $params['status'],
            'order_ids' => $orderIds,
            'is_city_delivery' => [0, DeliveryStation::TYPE_PLATFORM],
            'service_type' => 2,
            'paid' => 1,
            'delivery_keywords' => $params['delivery_keywords'] ?: '',
            'lat' => $params['lat'],
            'lng' => $params['lng'],
        ];
        $order = $this->repository->getList($where, $page, $limit);
        $order['delivery_role'] = $deliveryRole;
        return app('json')->success($order);
    }

    /**
     * 平台指派后待配送员确认的订单
     * @return \think\response\Json
     */
    public function acceptList()
    {
        [$page, $limit] = $this->getPage();
        $params = $this->request->params(['delivery_keywords', ['lat', ''], ['lng', ''], ['long', '']]);
        if ($params['lng'] === '' && $params['long'] !== '') {
            $params['lng'] = $params['long'];
        }
        $deliveryRole = $this->request->deliveryRole();
        if (
            !$this->request->isPlatformDelivery()
            || (int)systemConfig('platform_delivery_status') !== 1
        ) {
            return app('json')->success([
                'count' => 0,
                'list' => [],
                'delivery_role' => $deliveryRole,
            ]);
        }

        $orderIds = app()->make(DeliveryOrderRepository::class)->getOrderIds([
            'service_ids' => $this->request->platformDeliveryIds(),
            'station_type' => DeliveryStation::TYPE_PLATFORM,
            'status' => 0,
        ]);
        if (!$orderIds) {
            return app('json')->success([
                'count' => 0,
                'list' => [],
                'delivery_role' => $deliveryRole,
            ]);
        }

        $where = [
            'status' => 1,
            'order_ids' => $orderIds,
            'is_city_delivery' => [DeliveryStation::TYPE_PLATFORM],
            'service_type' => 2,
            'paid' => 1,
            'delivery_keywords' => $params['delivery_keywords'] ?: '',
            'lat' => $params['lat'],
            'lng' => $params['lng'],
        ];
        $order = $this->repository->getList($where, $page, $limit);
        $order['delivery_role'] = $deliveryRole;
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

        if ((int)$order['city_delivery_type'] === DeliveryStation::TYPE_PLATFORM) {
            $platformDeliveryIds = $this->request->platformDeliveryIds();
            $deliveryId = reset($platformDeliveryIds);
        } else {
            $deliveryList = $this->request->deliveryList();
            $deliveryId = $deliveryList[$order['mer_id']]['service_id'] ?? 0;
        }
        if (!$deliveryId) {
            return app('json')->fail('配送员身份异常');
        }
        $res = app()->make(DeliveryOrderRepository::class)->selfReceive($order['mer_id'], $id, $deliveryId);
        if (!$res) {
            return app('json')->fail('领取失败');
        }
        return app('json')->success('领取成功');
    }

    /**
     * 平台配送员更新配送状态
     * @param int $id
     * @return \think\response\Json
     */
    public function accept(int $id)
    {
        $params = $this->request->params([['status', -1], ['pickup_images', []]]);
        $currentStatus = (int)$params['status'];
        if (!in_array($currentStatus, [0, 1, 2], true)) {
            return app('json')->fail('当前配送状态参数错误');
        }
        $order = $this->repository->get($id);
        $this->checkAuth($order);
        $deliveryId = (int)$order['deliveryOrder']['service_id'];
        if (!in_array($deliveryId, $this->request->platformDeliveryIds(), true)) {
            return app('json')->fail('当前账号不是平台配送员');
        }

        $status = app()->make(DeliveryOrderRepository::class)->serviceUpdateStatus($id, $deliveryId, $currentStatus, $params['pickup_images']);
        return app('json')->success('操作成功', ['status' => $status]);
    }

    /**
     * 确认订单
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function confirm($id)
    {
        $params = $this->request->params([['confirm_images', []]]);
        $order = $this->repository->get($id);
        $this->checkAuth($order);
        $deliveryOrder = $order['deliveryOrder'];
        $deliveryId = (int)($deliveryOrder['service_id'] ?? 0);
        if (!$deliveryId || !in_array($deliveryId, $this->request->deliveryIds(), true)) {
            return app('json')->fail('订单不属于你');
        }

        if ((int)$order['city_delivery_type'] === DeliveryStation::TYPE_PLATFORM) {
            if (!in_array($deliveryId, $this->request->platformDeliveryIds(), true)) {
                return app('json')->fail('当前账号不是平台配送员');
            }
            $res = app()->make(DeliveryOrderRepository::class)->sysConfirmByDeliveryService($id, $deliveryId, $params['confirm_images']);
        } else {
            $res = app()->make(DeliveryOrderRepository::class)->confirmByDeliveryService($id, (int)$order['mer_id'], $deliveryId, $params['confirm_images']);
        }
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
            return app('json')->fail('请输入备注信息');
        }

        $order = $this->repository->get($id);
        $this->checkAuth($order);
        $deliveryOrder = $order ? $order->deliveryOrder : null;
        if (!$deliveryOrder || !in_array((int)$deliveryOrder['service_id'], $this->request->deliveryIds(), true)) {
            return app('json')->fail('订单不属于你');
        }
        $deliveryOrder->mark = $data['remark'];
        $deliveryOrder->save();

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
            return app('json')->fail('订单不存在或未支付，请检查');
        if ((int)$order['city_delivery_type'] === DeliveryStation::TYPE_PLATFORM) {
            if (
                !$this->request->isPlatformDelivery()
                || (int)systemConfig('platform_delivery_status') !== 1
            ) {
                return app('json')->fail('没有平台配送订单操作权限');
            }
        } else if (!in_array((int)$order['mer_id'], $this->request->deliveryMerchantIds(), true)) {
            throw new ValidateException('没有权限');
        }
        if (!$orderType) {
            if ($order['deliveryOrder'] && !in_array((int)$order['deliveryOrder']['service_id'], $this->request->deliveryIds(), true))
            return app('json')->fail('订单不属于你');
        }
        return true;
    }

    public function config()
    {
        $config = systemConfig([
            'platform_delivery_status',
            'platform_delivery_grab_status',
            'platform_delivery_accept_status',
            'platform_delivery_pickup_status',
            'platform_delivery_start_status',
            'platform_delivery_finish_status',
        ]);

        return app()->make('json')->success($config);
    }

    /**
     * 当前用户的全部配送员身份
     * @return \think\response\Json
     */
    public function info()
    {
        $list = app()->make(DeliveryServiceRepository::class)->getSearch([
            'uid' => $this->request->uid(),
            'is_del' => 0,
        ])->with(['merchant' => function ($query) {
            $query->field('mer_id,mer_name,mer_avatar');
        }])->field('service_id,mer_id,avatar,name,phone,status,type')
            ->order('service_id')
            ->select()
            ->toArray();

        foreach ($list as &$item) {
            if ((int)$item['type'] === DeliveryService::TYPE_PLATFORM) {
                $item['merchant'] = [
                    'mer_id' => 0,
                    'mer_name' => '平台',
                    'mer_avatar' => systemConfig('site_logo') ?: '',
                ];
            } elseif (empty($item['merchant'])) {
                $item['merchant'] = [
                    'mer_id' => (int)$item['mer_id'],
                    'mer_name' => '',
                    'mer_avatar' => '',
                ];
            }
        }
        unset($item);

        return app('json')->success([
            'status' => (int)($list[0]['status'] ?? 0),
            'delivery_role' => $this->request->deliveryRole(),
            'list' => $list,
        ]);
    }

    /**
     * 配送员日账单。
     */
    public function dayBill(DeliveryServiceBillRepository $repository)
    {
        $merIdParam = $this->request->param('mer_id', null);
        $date = $this->request->param('date', date('Y-m-d'));
        if ($merIdParam === null || filter_var($merIdParam, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            return app('json')->fail('商户参数错误');
        }
        $merId = (int)$merIdParam;
        $dateObject = \DateTime::createFromFormat('!Y-m-d', $date);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
            return app('json')->fail('日期格式应为Y-m-d');
        }
        $serviceIds = $this->getBillServiceIds($merId);
        if (!$serviceIds) {
            return app('json')->fail($merId === 0 ? '您不是平台配送员' : '您不是该商户配送员');
        }
        [$page, $limit] = $this->getPage();
        return app('json')->success($repository->getDayList(
            $serviceIds,
            $merId,
            $date,
            $page,
            $limit
        ));
    }

    /**
     * 配送员月账单。
     */
    public function monthBill(DeliveryServiceBillRepository $repository)
    {
        $merIdParam = $this->request->param('mer_id', null);
        $month = $this->request->param('date', $this->request->param('month', date('Y-m')));
        if ($merIdParam === null || filter_var($merIdParam, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            return app('json')->fail('商户参数错误');
        }
        $merId = (int)$merIdParam;
        $monthObject = \DateTime::createFromFormat('!Y-m', $month);
        if (!$monthObject || $monthObject->format('Y-m') !== $month) {
            return app('json')->fail('月份格式应为Y-m');
        }
        $serviceIds = $this->getBillServiceIds($merId);
        if (!$serviceIds) {
            return app('json')->fail($merId === 0 ? '您不是平台配送员' : '您不是该商户配送员');
        }

        return app('json')->success($repository->getMonthList(
            $serviceIds,
            $merId,
            $month
        ));
    }

    /**
     * 配送员近7天收入图表。
     */
    public function dayBillChart(DeliveryServiceBillRepository $repository)
    {
        $merIdParam = $this->request->param('mer_id', null);
        $date = $this->request->param('date', date('Y-m-d'));
        if ($merIdParam === null || filter_var($merIdParam, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            return app('json')->fail('商户参数错误');
        }
        $merId = (int)$merIdParam;
        $dateObject = \DateTime::createFromFormat('!Y-m-d', $date);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
            return app('json')->fail('日期格式应为Y-m-d');
        }
        $serviceIds = $this->getBillServiceIds($merId);
        if (!$serviceIds) {
            return app('json')->fail($merId === 0 ? '您不是平台配送员' : '您不是该商户配送员');
        }

        return app('json')->success($repository->getDayChart($serviceIds, $merId, $date));
    }

    /**
     * 配送员近6个月收入图表。
     */
    public function monthBillChart(DeliveryServiceBillRepository $repository)
    {
        $merIdParam = $this->request->param('mer_id', null);
        $month = $this->request->param('month', date('Y-m'));
        if ($merIdParam === null || filter_var($merIdParam, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            return app('json')->fail('商户参数错误');
        }
        $merId = (int)$merIdParam;
        $monthObject = \DateTime::createFromFormat('!Y-m', $month);
        if (!$monthObject || $monthObject->format('Y-m') !== $month) {
            return app('json')->fail('月份格式应为Y-m');
        }
        $serviceIds = $this->getBillServiceIds($merId);
        if (!$serviceIds) {
            return app('json')->fail($merId === 0 ? '您不是平台配送员' : '您不是该商户配送员');
        }

        return app('json')->success($repository->getMonthChart($serviceIds, $merId, $month));
    }

    /**
     * 提交配送员提现申请。
     */
    public function withdraw(
        DeliveryServiceWithdrawValidate $validate,
        DeliveryServiceWithdrawRepository $repository
    ) {
        $data = $this->request->params([
            'mer_id',
            'amount',
            'payment_method',
            'payment_info',
            ['remark', ''],
        ]);
        if (!$validate->check($data)) {
            return app('json')->fail($validate->getError());
        }

        $merId = (int)$data['mer_id'];
        $serviceIds = $this->getBillServiceIds($merId);
        if (!$serviceIds) {
            return app('json')->fail($merId === 0 ? '您不是平台配送员' : '您不是该商户配送员');
        }

        $withdraw = $repository->create(
            (int)$serviceIds[0],
            (int)$this->request->uid(),
            $merId,
            $data
        );

        return app('json')->success('提现申请已提交', [
            'delivery_service_withdraw_id' => $withdraw->delivery_service_withdraw_id,
            'withdraw_sn' => $withdraw->withdraw_sn,
            'amount' => $withdraw->amount,
            'status' => $withdraw->status,
        ]);
    }

    /**
     * 获取指定平台或商户身份下的配送员ID。
     */
    protected function getBillServiceIds(int $merId): array
    {
        if ($merId === 0) {
            return $this->request->platformDeliveryIds();
        }

        $serviceIds = [];
        foreach ($this->request->deliveryList() as $delivery) {
            if (
                (int)$delivery['type'] === DeliveryService::TYPE_MERCHANT
                && (int)$delivery['mer_id'] === $merId
            ) {
                $serviceIds[] = (int)$delivery['service_id'];
            }
        }
        return $serviceIds;
    }

    /**
     * 开启或关闭当前用户的全部配送员身份
     * @return \think\response\Json
     */
    public function status()
    {
        $status = app()->make(DeliveryServiceRepository::class)
            ->toggleUserStatus($this->request->uid());
        return app('json')->success('操作成功', ['status' => $status]);
    }

    /**
     * 获取配送员提现方式和可提现金额。
     */
    public function withdrawInfo()
    {
        $params = $this->request->params([['mer_id',0]]);
        $merId = (int)$params['mer_id'];
        $extract_switch = systemConfig(['sys_extension_type','withdraw_type']);
        if ($merId > 0) {
            $mer_withdraw_type = merchantConfig($merId,'mer_withdraw_type') ?: [1];
            $extract_switch = [
                'sys_extension_type' => '',
                'withdraw_type' => $mer_withdraw_type,
            ];
        }

        $serviceIds = $this->getBillServiceIds($merId);
        $service = null;
        if ($serviceIds) {
            $service = DeliveryService::getDB()
                ->where('service_id', (int)$serviceIds[0])
                ->where('uid', $this->request->uid())
                ->where('is_del', 0)
                ->field('service_id,type,mer_id,name,total_amount,available_amount,frozen_amount,withdrawn_amount,total_amount')
                ->find();
        }
        $services = $service ? [$service->toArray()] : [];
        $amounts = [
            'total_amount' =>  bcadd((string)($service->total_amount ?? '0'), '0', 2),
            'available_amount' => bcadd((string)($service->available_amount ?? '0'), '0', 2),
            'frozen_amount' => bcadd((string)($service->frozen_amount ?? '0'), '0', 2),
            'withdrawn_amount' => bcadd((string)($service->withdrawn_amount ?? '0'), '0', 2),
        ];

        return app('json')->success([
            'extract_switch' => $extract_switch,
            'mer_id' => $merId,
            'available_amount' => $amounts['available_amount'],
            'frozen_amount' => $amounts['frozen_amount'],
            'withdrawn_amount' => $amounts['withdrawn_amount'],
            'total_amount' => $amounts['total_amount'],
            'services' => $services,
        ]);
    }

    /**
     * 配送员提现记录列表。
     */
    public function withdrawList(DeliveryServiceWithdrawRepository $repository)
    {
        $params = $this->request->params([
            ['mer_id', 0],
            ['date', ''],
            ['start', 0],
            ['stop', 0],
        ]);
        if ($params['start'] && $params['stop']) {
            $params['date'] = date('Y/m/d', (int)$params['start']) . '-' . date('Y/m/d', (int)$params['stop']);
        }
        $merId = (int)$params['mer_id'];
        $serviceType = $merId === 0 ? DeliveryService::TYPE_PLATFORM : DeliveryService::TYPE_MERCHANT;
        [$page, $limit] = $this->getPage();
        $where = [
            'uid' => $this->request->uid(),
            'mer_id' => $merId,
            'service_type' => $serviceType,
            'date' => $params['date'],
        ];

        return app('json')->success($repository->getList($where, $page, $limit));
    }

    /**
     * 配送员提现记录详情。
     */
    public function withdrawDetail(int $id, DeliveryServiceWithdrawRepository $repository)
    {
        $withdraw = $repository->getDetail($id, null, null, (int)$this->request->uid());
        if (!$withdraw) {
            return app('json')->fail('提现记录不存在');
        }

        return app('json')->success($withdraw);
    }

    /**
     * 配送员取消提现申请。
     */
    public function withdrawCancel(int $id, DeliveryServiceWithdrawRepository $repository)
    {
        $withdraw = $repository->getDetail($id, null, null, (int)$this->request->uid());
        if (!$withdraw) {
            return app('json')->fail('提现记录不存在');
        }

        $repository->cancel(
            $id,
            (int)$withdraw->service_type,
            (int)$withdraw->mer_id
        );

        return app('json')->success('提现申请已取消');
    }
}
