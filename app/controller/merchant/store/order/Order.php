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

namespace app\controller\merchant\store\order;

use think\App;
use think\facade\Queue;
use crmeb\basic\BaseController;
use crmeb\jobs\BatchDeliveryJob;
use crmeb\services\ExcelService;
use app\common\repositories\store\order\{
    OrderStatus,
    StoreOrderRepository,
    StoreOrderRepository as repository,
    MerchantReconciliationRepository
};
use app\common\repositories\delivery\DeliveryOrderRepository;
use app\services\serve\ServeServices;
use think\exception\ValidateException;
use app\validate\merchant\OrderValidate;
use app\common\repositories\delivery\DeliveryServiceRepository;

class Order extends BaseController
{
    protected $repository;

    /**
     * Product constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取订单统计数据
     *
     * @return \think\response\Json
     */
    public function title()
    {
        $where = $this->request->params(['status', 'date', 'order_sn', 'username', 'order_type', 'keywords', 'order_id', 'activity_type', 'filter_delivery', 'filter_product', 'delivery_id', 'uid', 'nickname', 'real_name', 'phone']);
        // 添加商家ID查询条件
        $where['mer_id'] = $this->request->merId();
        $pay_type = $this->request->param('pay_type', '');
        // 如果支付类型不为空，则添加支付类型查询条件
        if ($pay_type != '') $where['pay_type'] = $this->repository::PAY_TYPE_FILTEER[$pay_type];
        // 调用仓库的获取统计数据方法，并返回JSON格式的结果
        return app('json')->success($this->repository->getStat($where, $where['status']));
    }

    /**
     * 订单列表
     * @return mixed
     * @author Qinii
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['status', 'date', 'order_sn', 'username', 'order_type', 'keywords', 'order_id', 'activity_type', 'group_order_sn', 'store_name', 'filter_delivery', 'filter_product', 'delivery_id', 'group_order_id', 'uid', 'nickname', 'real_name', 'phone','is_behalf', 'is_virtual', 'merchant_take_id']);
        $where['mer_id'] = $this->request->merId();
        $pay_type = $this->request->param('pay_type', '');
        if ($pay_type != '') $where['pay_type'] = $this->repository::PAY_TYPE_FILTEER[$pay_type];
        return app('json')->success($this->repository->merchantGetList($where, $page, $limit));
    }

    /**
     * 获取标题统计数据
     *
     * @return \think\response\Json
     */
    public function takeTitle()
    {
        // 从请求参数中获取日期、订单号、用户名和关键字
        $where = $this->request->params(['date', 'order_sn', 'username', 'keywords']);
        $where['take_order'] = 1; // 只查询已接单的订单
        $where['status'] = -1; // 只查询未完成的订单
        $where['verify_date'] = $where['date']; // 将日期作为验证日期
        unset($where['date']); // 删除日期参数
        $where['mer_id'] = $this->request->merId(); // 添加商家ID查询条件
        $pay_type = $this->request->param('pay_type', '');
        if ($pay_type != '') $where['pay_type'] = $this->repository::PAY_TYPE_FILTEER[$pay_type];
        // 调用仓库的 getStat 方法获取统计数据并返回 JSON 格式的响应
        return app('json')->success($this->repository->getStat($where, ''));
    }


    /**
     * 自提订单列表
     * @return mixed
     * @author Qinii
     * @day 2020-08-17
     */
    public function takeLst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date', 'order_sn', 'username', 'keywords']);
        $where['take_order'] = 1;
        $where['status'] = -1;
        $where['verify_date'] = $where['date'];
        unset($where['date']);
        $where['mer_id'] = $this->request->merId();
        $pay_type = $this->request->param('pay_type', '');
        if ($pay_type != '') $where['pay_type'] = $this->repository::PAY_TYPE_FILTEER[$pay_type];
        return app('json')->success($this->repository->merchantGetList($where, $page, $limit));
    }

    /**
     *  订单头部统计
     * @return mixed
     * @author Qinii
     */
    public function chart()
    {
        $where = $this->request->params(['date', 'order_sn', 'username', 'order_type', 'keywords', 'order_id', 'activity_type', 'group_order_sn', 'store_name', 'filter_delivery', 'filter_product', 'delivery_id', 'group_order_id', 'uid', 'nickname', 'real_name', 'phone','is_behalf', 'is_virtual']);
        $where['mer_id'] = $this->request->merId();
        $pay_type = $this->request->param('pay_type', '');
        if ($pay_type != '') $where['pay_type'] = $this->repository::PAY_TYPE_FILTEER[$pay_type];

        return app('json')->success($this->repository->OrderTitleNumber($this->request->merId(), null, $where));
    }

    /**
     * 自提订单头部统计
     * @return mixed
     * @author Qinii
     * @day 2020-08-17
     */
    public function takeChart()
    {
        $where = $this->request->params(['date', 'order_sn', 'username', 'keywords']);
        $where['take_order'] = 1;
        $where['verify_date'] = $where['date'];
        unset($where['date']);
        $where['mer_id'] = $this->request->merId();
        $pay_type = $this->request->param('pay_type', '');
        if ($pay_type != '') $where['pay_type'] = $this->repository::PAY_TYPE_FILTEER[$pay_type];

        return app('json')->success($this->repository->OrderTitleNumber($this->request->merId(), 1, $where));
    }


    /**
     * 订单类型
     * @return mixed
     * @author Qinii
     * @day 2020-08-15
     */
    public function orderType()
    {
        $where = $this->request->params(['date', 'order_sn', 'username', 'order_type', 'keywords', 'order_id', 'activity_type', 'group_order_sn', 'store_name', 'filter_delivery', 'filter_product', 'delivery_id', 'group_order_id', 'uid', 'nickname', 'real_name', 'phone','is_behalf', 'is_virtual']);
        $where['mer_id'] = $this->request->merId();
        $pay_type = $this->request->param('pay_type', '');
        if ($pay_type != '') $where['pay_type'] = $this->repository::PAY_TYPE_FILTEER[$pay_type];

        return app('json')->success($this->repository->orderType($where));
    }

    /**
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function deliveryForm($id)
    {
        $data = $this->repository->getWhere(['order_id' => $id, 'mer_id' => $this->request->merId(), 'is_del' => 0]);
        if (!$data) return app('json')->fail('数据不存在');
        if (!$data['paid']) return app('json')->fail('订单未支付');
        if (!in_array($data['status'], [0, 1])) return app('json')->fail('订单状态错误');
        return app('json')->success(formToData($this->repository->sendProductForm($id, $data)));
    }

    /**
     * 发货
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function delivery($id)
    {
        $type = $this->request->param('delivery_type');
        $split = $this->request->params(['is_split', ['split', []]]);
        if (!$this->repository->merDeliveryExists($id, $this->request->merId()))
            return app('json')->fail('订单信息或状态错误');

        if (!$this->repository->hasRefund($id))
            return app('json')->fail('该订单存在退款申请，请先处理');

        switch ($type) {
            case OrderStatus::DELIVER_TYPE_DELIVERY:
                $data = $this->request->params(['delivery_type', 'delivery_name', 'delivery_id', 'remark',]);
                if (!$data['delivery_type'] || !$data['delivery_name'] || !$data['delivery_id'])
                    return app('json')->fail('填写配送信息');
                $ser = app()->make(DeliveryServiceRepository::class)->get($data['delivery_name']);
                $data['delivery_name'] = $ser['name'] ?? $data['delivery_name'];
                $method = 'delivery';
                break;
            case OrderStatus::DELIVER_TYPE_VIRTUAL: //虚拟发货
                $data = $this->request->params([
                    'delivery_type',
                    'remark',
                ]);
                $data['delivery_name'] = '';
                $data['delivery_id'] = '';
                $method = 'delivery';
                break;
            case OrderStatus::DELIVER_TYPE_DUMP: //电子面单
                if (!systemConfig('crmeb_serve_dump') || merchantConfig($this->request->merId(), 'mer_dump_switch') == 0)
                    return app('json')->fail('电子面单功能未开启');
                $data = $this->request->params(['delivery_type', 'delivery_name', 'from_name', 'from_tel', 'from_addr', 'temp_id', 'remark', ['is_cargo', 1],]);
                if (!$data['from_name'] || !$data['delivery_name'] || !$data['from_tel'] || !$data['from_addr'] || !$data['temp_id'])
                    return app('json')->fail('填写配送信息');
                $method = 'dump';
                break;
            case OrderStatus::DELIVER_TYPE_SAME_CITY: //同城配送
                if (systemConfig('delivery_status') != 1)
                    return app('json')->fail('未开启第三方配送');
                $data = $this->request->params(['delivery_type', 'station_id', 'mark', ['cargo_weight', 0], 'remark',]);
                if ($data['cargo_weight'] < 0) return app('json')->fail('包裹重量能为负数');
                if (!$data['station_id']) return app('json')->fail('请选择门店');
                $method = 'cityDelivery';
                break;
            case OrderStatus::DELIVER_TYPE_SHIP_MENT:
                $data = $this->request->params(['delivery_type', 'delivery_name', 'from_name', 'from_tel', 'from_addr', 'temp_id', 'remark', ['is_cargo', 1],['day_type', 0],'service_type','pickup_start_time','pickup_end_time','weight']);
                if(!isset($data['day_type']) || $data['day_type'] === ''){
                    return app('json')->fail('请选择取件日期');
                }
                $method = 'shoipment';
                break;
            default: //快递
                $data = $this->request->params(['delivery_type', 'delivery_name', 'delivery_id', 'remark',]);
                if (!$data['delivery_type'] || !$data['delivery_name'] || !$data['delivery_id'])
                    return app('json')->fail('填写配送信息');
                $method = 'delivery';
                break;
        }
        $res = $this->repository->runDelivery($id, $this->request->merId(), $data, $split, $method);
        return app('json')->success($type == OrderStatus::DELIVER_TYPE_SHIP_MENT ?'商家寄件订单创建成功' : '发货成功', $res);
    }

    /**
     *
     * @return \think\response\Json
     * @author Qinii
     * @day 7/26/21
     */
    public function batchDelivery()
    {
        $params = $this->request->params([
            'order_id',
            'delivery_id',
            'delivery_type',
            'delivery_name',
            'remark',
            ['select_type', 'select'],
            ['where', []],
        ]);
        if (!in_array($params['select_type'], ['all', 'select'])) return app('json')->fail('选择了类型错误');
        if (!in_array($params['delivery_type'], [2, 3, 4])) return app('json')->fail('发货类型错误');
        if ($params['delivery_type'] ==2 ) {
            $data = $this->request->params(['delivery_type', 'delivery_name', 'delivery_id', 'remark',]);
            if (!$data['delivery_type'] || !$data['delivery_name'] || !$data['delivery_id'])
                return app('json')->fail('填写配送信息');
            $ser = app()->make(DeliveryServiceRepository::class)->get($data['delivery_name']);
            $data['delivery_name'] = $ser['name'];
        }
        if ($params['delivery_type'] == 4) {
            $data = $this->request->params([
                'from_name',
                'from_tel',
                'from_addr',
                'temp_id',
                'remark',
                ['is_cargo', 1],
            ]);
            $params = array_merge($params, $data);
            if (!systemConfig('crmeb_serve_dump') || merchantConfig($this->request->merId(), 'mer_dump_switch') == 0)
                return app('json')->fail('电子面单功能未开启');
            if (!merchantConfig($this->request->merId(), 'mer_dump_type'))
                return app('json')->fail('通用打印机不支持批量打印');
        }
        if ($params['select_type'] == 'select' && !$params['order_id']) return app('json')->fail('需要订单ID');
        if ($params['select_type'] == 'all' && empty($params['where'])) return app('json')->fail('需要搜索条件');
        //$this->repository->batchDelivery($this->request->merId(),$params);
        Queue::push(BatchDeliveryJob::class, [
            'mer_id' => $this->request->merId(),
            'data' => $params
        ]);
        return app('json')->success('已开始批量发货，请稍后查看');
    }

    public function repeatDump($id)
    {
        if (!systemConfig('crmeb_serve_dump') || merchantConfig($this->request->merId(), 'mer_dump_switch') == 0)
            return app('json')->fail('电子面单功能未开启');
        $data = $this->repository->repeat_dump($id, $this->request->merId());
        return app('json')->success($data);
    }

    /**
     * 改价form
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-11
     */
    public function updateForm($id)
    {
        if (!$this->repository->merStatusExists($id, $this->request->merId()))
            return app('json')->fail('订单信息或状态错误');
        return app('json')->success(formToData($this->repository->form($id)));
    }

    /**
     * 改价
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-11
     */
    public function update($id)
    {
        $data = $this->request->params(['total_price', 'pay_postage']);
        if ($data['total_price'] < 0 || $data['pay_postage'] < 0)
            return app('json')->fail('金额不可未负数');
        if (!$this->repository->merStatusExists($id, $this->request->merId()))
            return app('json')->fail('订单信息或状态错误');
        $this->repository->eidt($id, $data);
        return app('json')->success('修改成功');
    }

    /**
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-11
     */
    public function detail($id)
    {
        $data = $this->repository->getOne($id, $this->request->merId());
        if (!$data) return app('json')->fail('数据不存在');
        return app('json')->success($data);
    }

    /**
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-11
     */
    public function status($id)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date', 'user_type']);
        $where['id'] = $id;
        if (!$this->repository->getOne($id, $this->request->merId()))
            return app('json')->fail('数据不存在');
        return app('json')->success($this->repository->getOrderStatus($where, $page, $limit));
    }

    /**
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-11
     */
    public function remarkForm($id)
    {
        return app('json')->success(formToData($this->repository->remarkForm($id)));
    }

    /**
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-11
     */
    public function remark($id)
    {
        if (!$this->repository->getOne($id, $this->request->merId()))
            return app('json')->fail('数据不存在');
        $data = $this->request->params(['remark']);
        $this->repository->update($id, $data);

        return app('json')->success('备注成功');
    }

    public function collectCargoForm($id)
    {
        return app('json')->success(formToData($this->repository->collectCargoForm($id)));
    }

    public function collectCargo($id)
    {
        if (!$this->repository->getOne($id, $this->request->merId()))
            return app('json')->fail('数据不存在');
        $data = $this->request->params(['real_name','user_phone','user_address']);
        $this->repository->update($id, $data);
        return app('json')->success('操作成功');
    }

    /**
     * 核销
     * @param $code
     * @author xaboy
     * @day 2020/8/15
     */
    public function verify($id)
    {
        $data = $this->request->params(['data', 'verify_code']);
        $merId = $this->request->merId();
        // 根据订单ID、商家ID、验证码和订单类型查询订单，并连带查询订单产品信息
        $order = $this->repository->getWhere(['order_id' => $id, 'mer_id' => $merId, 'verify_code' => $data['verify_code'], 'order_type' => 1], '*', ['orderProduct']);
        // 如果订单不存在，则抛出验证异常
        if (!$order)  return app('json')->fail('订单不存在');
        // 如果订单未支付，则抛出验证异常
        if (!$order->paid)  return app('json')->fail('订单未支付');
        // 如果订单已全部核销，则抛出验证异常
        if ($order['status'])  return app('json')->fail('订单已全部核销，请勿重复操作');
        $this->repository->verifyOrder($order, $data);
        return app('json')->success('订单核销成功');
    }

    /**
     * 根据订单编号获取订单详情
     *
     * @param string $code 订单编号
     * @return \Illuminate\Http\JsonResponse 返回订单详情或错误信息
     */
    public function verifyDetail($code)
    {
        // 通过订单编号查询订单
        $order = $this->repository->codeByDetail($code);
        // 如果订单不存在则返回错误信息
        if (!$order) return app('json')->fail('订单不存在');
        // 返回订单详情
        return app('json')->success($order);
    }


    /**
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-11
     */
    public function delete($id)
    {
        if (!$this->repository->userDelExists($id, $this->request->merId()))
            return app('json')->fail('订单信息或状态错误');
        $this->repository->merDelete($id);
        return app('json')->success('删除成功');
    }


    /**
     * 快递查询
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-25
     */
    public function express($id)
    {
        return app('json')->success($this->repository->express($id, $this->request->merId()));
    }

    /**
     *
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-07-30
     */
    public function reList($id)
    {
        [$page, $limit] = $this->getPage();
        $make = app()->make(MerchantReconciliationRepository::class);
        if (!$make->getWhereCount(['mer_id' => $this->request->merId(), 'reconciliation_id' => $id]))
            return app('json')->fail('数据不存在');
        $where = ['reconciliation_id' => $id, 'type' => 0];
        return app('json')->success($this->repository->reconList($where, $page, $limit));
    }

    /**
     * 导出文件
     * @author Qinii
     * @day 2020-07-30
     */
    public function excel()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['status', 'date', 'order_sn', 'order_type', 'username', 'keywords', 'take_order', 'order_id', 'activity_type', 'group_order_sn', 'store_name', 'filter_delivery', 'filter_product', 'pay_type', 'uid', 'nickname', 'real_name', 'phone']);
        $where['order_ids'] = $this->request->param('ids','');
        if ($where['pay_type'] != '') $where['pay_type'] = $this->repository::PAY_TYPE_FILTEER[$where['pay_type']];
        if ($where['take_order']) {
            $where['status'] = -1;
            $where['verify_date'] = $where['date'];
            unset($where['date']);
            unset($where['order_type']);
        }
        $where['mer_id'] = $this->request->merId();
        $data = app()->make(ExcelService::class)->order($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 打印小票
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-07-30
     */
    public function printer($id)
    {
        $merId = $this->request->merId();
        if (!$this->repository->getWhere(['order_id' => $id, 'mer_id' => $merId]))
            return app('json')->fail('数据不存在');
        $this->repository->batchPrinter($id, $merId,null);
        return app('json')->success('打印成功');
    }

    /**
     * 导出发货单
     * @return \think\response\Json
     * @author Qinii
     * @day 3/13/21
     */
    public function deliveryExport()
    {
        $where = $this->request->params(['username', 'date', 'activity_type', 'order_type', 'username', 'keywords', 'id']);
        $where['order_ids'] = $this->request->param('ids');
        $where['mer_id'] = $this->request->merId();
        $where['delivery_type'] = 0;
        $where['status'] = 0;
        $where['paid'] = 1;
        $make = app()->make(StoreOrderRepository::class);
        if (is_array($where['id'])) $where['order_ids'] = $where['id'];
        $count = $make->search($where)->find();
        if (!$count) return app('json')->fail('没有可导出数据');

        [$page, $limit] = $this->getPage();
        $data = app()->make(ExcelService::class)->delivery($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 获取指定节点的子节点列表
     *
     * @param int $id 节点ID
     * @return \think\response\Json 返回JSON格式的数据
     */
    public function childrenList($id)
    {
        // 调用repository层的childrenList方法获取子节点列表
        $data = $this->repository->childrenList($id, $this->request->merId());
        // 返回JSON格式的数据
        return app('json')->success($data);
    }

    /**
     * 将指定节点设置为下线状态
     *
     * @param int $id 节点ID
     * @return \think\response\Json 返回JSON格式的数据
     */
    public function offline($id)
    {
        // 调用repository层的offline方法将节点设置为下线状态
        $this->repository->offline($id, $this->request->merId());
        return app('json')->success('确认成功');
    }

    /**
     *  订单配货单
     * @return \think\response\Json
     * @author Qinii
     */
    public function note()
    {
        $where = $this->request->params(['status', 'date', 'order_sn', 'order_type', 'username', 'keywords', 'take_order', 'order_id', 'activity_type', 'group_order_sn', 'store_name', 'filter_delivery', 'filter_product', 'pay_type', 'uid', 'nickname', 'real_name', 'phone']);
        $where['order_ids'] = $this->request->param('ids','');
        if ($where['pay_type'] != '') $where['pay_type'] = $this->repository::PAY_TYPE_FILTEER[$where['pay_type']];
        if ($where['take_order']) {
            $where['status'] = -1;
            $where['verify_date'] = $where['date'];
            unset($where['date']);
            unset($where['order_type']);
        }
        $limit = $this->request->param('limit', 10);
        $merchant = $this->request->merchant()->toArray();
        // $merchant['qrcode'] = app()->make(MerchantRepository::class)->wxQrcode(intval($this->request->merId()));
        $data = $this->repository->note($where,$limit);
        return app('json')->success($data);
    }
    /**
     * 预约订单派单
     *
     * @param integer $id
     * @return json
     */
    public function reservationDispatch(int $id)
    {
        $params = $this->request->params(['staffs_id']);
        if (!$params['staffs_id']) {
            return app('json')->fail('请选择服务人员');
        }

        $res = $this->repository->reservationDispatch($id, $this->request->merId(), $params);
        if (!$res) {
            return app('json')->fail('派单失败');
        }

        return app('json')->success('派单成功');
    }
    /**
     * 预约订单改派
     *
     * @param integer $id
     * @return json
     */
    public function reservationUpdateDispatch(int $id)
    {
        $params = $this->request->params(['staffs_id']);
        if (!$params['staffs_id']) {
            return app('json')->fail('请选择服务人员');
        }

        $res = $this->repository->reservationUpdateDispatch($id, $this->request->merId(), $params);
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
    public function reservationReschedule(int $id)
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
        $res = $this->repository->reservationReschedule($id, $this->request->merId(), $params);
        if (!$res) {
            return app('json')->fail('改约失败');
        }

        return app('json')->success('改约成功');
    }
    /**
     * 单独修改预约时间
     *
     * @param integer $id
     * @return json
     */
    public function reservationTime(int $id)
    {
        $params = $this->request->params(
            [
                'reservation_date',
                'part_start',
                'part_end'
            ]
        );

        $validate = app()->make(OrderValidate::class);
        if (!$validate->sceneOnlyReservationTime($params)) {
            return app('json')->fail($validate->getError());
        }
        $res = $this->repository->updateReservationTime($id, $this->request->merId(), $params);
        if (!$res) {
            return app('json')->fail('修过失败');
        }

        return app('json')->success('修改成功');
    }
    /**
     * 预约订单核销
     *
     * @param integer $id
     * @return void
     */
    public function reservationVerify(int $id)
    {
        $res = $this->repository->reservationVerify($id, $this->request->merId());
        if (!$res) {
            return app('json')->fail('核销失败');
        }
        return app('json')->success('核销成功');
    }

    public function getPrice($id)
    {
        $data = $this->request->params([
            ['kuaidicom', ''],
            ['service_type', ''],
            ['send_address', ''],
        ]);
        $data = $this->repository->getPrice($id, $data);
        return app('json')->success($data);
    }

    public function getKuaidiComs()
    {
        $data = $this->repository->getKuaidiComs($this->request->merId());
        return app('json')->success($data);
    }

    public function cancelShipment($id)
    {
        $msg = $this->request->param('msg','');
        $orderInfo = $this->repository->get($id);
        if (!$orderInfo || $orderInfo['mer_id'] != $this->request->merId()) {
            throw new ValidateException('取消的订单不存在');
        }
        if (!$orderInfo->task_id || !$orderInfo->kuaidi_order_id) {
            throw new ValidateException('商家寄件订单信息不存在，无法取消');
        }
        if ($orderInfo->is_stock_up != 1) {
            throw new ValidateException('订单状态不正确，无法取消寄件');
        }

        $this->repository->cancelShipment($orderInfo,$this->request->merId(),$msg);
        return app('json')->success('商家寄件已取消');
    }

    public function shipmentList()
    {
        [$page,$limit] = $this->getPage();
        $list = $this->repository->shipmentList($this->request->merId(),$page,$limit);
        return app('json')->success($list);
    }
    /**
     * 同城配送商家配送派单
     *
     * @param integer $id
     * @return json
     */
    public function deliveryDispatch(int $id)
    {
        $params = $this->request->params(['service_id']);
        if (!$params['service_id']) {
            return app('json')->fail('请选择配送人员');
        }

        $res = app()->make(DeliveryOrderRepository::class)->merDispatch($id, $this->request->merId(), $params);
        if (!$res) {
            return app('json')->fail('派单失败');
        }

        return app('json')->success('派单成功');
    }
    /**
     * 同城配送商家配送改派
     *
     * @param integer $id
     * @return json
     */
    public function deliveryUpdateDispatch(int $id)
    {
        $params = $this->request->params(['service_id']);
        if (!$params['service_id']) {
            return app('json')->fail('请选择配送人员');
        }

        $res = app()->make(DeliveryOrderRepository::class)->merUpdateDispatch($id, $this->request->merId(), $params);
        if (!$res) {
            return app('json')->fail('改派失败');
        }

        return app('json')->success('改派成功');
    }
    /**
     * 同城配送商家配送确认
     *
     * @param integer $id
     * @return void
     */
    public function deliveryConfirm(int $id)
    {
        $res = app()->make(DeliveryOrderRepository::class)->confirm($id, $this->request->merId());
        if (!$res) {
            return app('json')->fail('确认失败');
        }
        return app('json')->success('确认成功');
    }

    public function deliveryOrderSync()
    {
        $orderIds = $this->request->param('order_ids');
        if(empty($orderIds)) {
            return app('json')->fail('order_ids 参数不能为空');
        }

        $res = $this->repository->deliveryOrderSync($orderIds, $this->request->merId());
        if (!$res) {
            return app('json')->fail('同步失败');
        }
        return app('json')->success('同步成功');
    }
}
