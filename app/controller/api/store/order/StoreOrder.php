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


namespace app\controller\api\store\order;


use think\App;
use crmeb\basic\BaseController;
use crmeb\services\LockService;
use think\exception\ValidateException;
use app\validate\api\UserReceiptValidate;
use app\common\repositories\store\order\StoreCartRepository;
use app\common\repositories\delivery\DeliveryOrderRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\order\StoreGroupOrderRepository;
use app\common\repositories\store\order\StoreOrderCreateRepository;
use app\common\repositories\store\order\StoreOrderReceiptRepository;
use app\common\repositories\delivery\DeliveryConfigRepository;
use app\common\repositories\delivery\DeliveryStationRepository;

/**
 * Class StoreOrder
 * @package app\controller\api\store\order
 * @author xaboy
 * @day 2020/6/10
 */
class StoreOrder extends BaseController
{
    /**
     * @var StoreOrderRepository
     */
    protected $repository;

    /**
     * StoreOrder constructor.
     * @param App $app
     * @param StoreOrderRepository $repository
     */
    public function __construct(App $app, StoreOrderRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function v2CheckOrder(StoreCartRepository $cartRepository, StoreOrderCreateRepository $orderCreateRepository)
    {
        $cartId = (array)$this->request->param('cart_id', []);
        $addressId = (int)$this->request->param('address_id');
        $couponIds = (array)$this->request->param('use_coupon', []);
        $takes = (array)$this->request->param('takes', []);
        $city_takes = (array)$this->request->param('city_takes', []);
        $useIntegral = (bool)$this->request->param('use_integral', false);
        $user = $this->request->userInfo();
        $uid = $user->uid;
        if (!($count = count($cartId)) || $count != count($cartRepository->validIntersection($cartId, $uid)))
            return app('json')->fail('数据无效');
        $orderInfo = $orderCreateRepository->v2CartIdByOrderInfo(
            $user,
            $cartId,
            $takes,
            $couponIds,
            $useIntegral,
            $addressId,
            $city_takes
        );

        return app('json')->success($orderInfo);
    }

    public function v2CreateOrder(StoreCartRepository $cartRepository, StoreOrderCreateRepository $orderCreateRepository)
    {
        $cartId = (array)$this->request->param('cart_id', []);
        $addressId = (int)$this->request->param('address_id');
        $couponIds = (array)$this->request->param('use_coupon', []);
        $takes = (array)$this->request->param('takes', []);
        $useIntegral = (bool)$this->request->param('use_integral', false);
        $receipt_data = (array)$this->request->param('receipt_data', []);
        $extend = (array)$this->request->param('extend', []);
        $mark = (array)$this->request->param('mark', []);
        $payType = $this->request->param('pay_type');
        $key = (string)$this->request->param('key');
        $post = (array)$this->request->param('post');
        if(!$key){
            return app('json')->fail('订单操作超时,请刷新页面');
        }
        $payType = ($payType === 'pc') ? 'balance' : $payType;
        if (!in_array($payType, StoreOrderRepository::PAY_TYPE, true))
            return app('json')->fail('请选择正确的支付方式');

        $validate = app()->make(UserReceiptValidate::class);
        foreach ($receipt_data as $receipt) {
            if (!is_array($receipt)) throw new ValidateException('发票信息有误');
            $validate->check($receipt);
        }

        $uid = $this->request->uid();
        if (!($count = count($cartId)) || $count != count($cartRepository->validIntersection($cartId, $uid)))
            return app('json')->fail('已生成订单，请勿重复提交～');
        $groupOrder = app()->make(LockService::class)->exec('order.create', function () use ($key,
            $orderCreateRepository, $receipt_data, $mark, $extend, $cartId, $payType, $takes, $couponIds, $useIntegral, $addressId, $post) {
            return $orderCreateRepository->v2CreateOrder($key, array_search($payType, StoreOrderRepository::PAY_TYPE)
                , $this->request->userInfo(), $cartId, $extend, $mark, $receipt_data, $takes, $couponIds,
                $useIntegral, $addressId, $post);
        });
        //全部改成创建订单，下一步调用支付
        try{
            $orderList = $this->repository->getSearch([])->where('group_order_id',$groupOrder->group_order_id)->select();
            foreach ($orderList as $item ) {
                $this->repository->autoPrinter($item->order_id, $item->mer_id, 2);
            }
        }catch (\Exception $e) {

        }

        return app('json')->success(['order_id' => $groupOrder->group_order_id]);
        /**
         * 以下是立即支付，返回支付或者跳转信息
         */
        //try {
        //    return $this->repository->pay($payType, $this->request->userInfo(), $groupOrder, $this->request->param('return_url'), $this->request->isApp());
        //} catch (\Exception $e) {
        //    return app('json')->status('error', $e->getMessage(), ['order_id' => $groupOrder->group_order_id]);
        //}
    }

    /**
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author xaboy
     * @day 2020/6/10
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where['status'] = $this->request->param('status');
        $where['search'] = $this->request->param('store_name');
        $where['uid'] = $this->request->uid();
        if($where['status'] != -2){
            $where['paid'] = 1;
        }
        $data = $this->repository->getList($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * @param $id
     * @return mixed
     * @author xaboy
     * @day 2020/6/10
     */
    public function detail($id)
    {
        $order = $this->repository->getDetail((int)$id, $this->request->uid());
        if (!$order)
            return app('json')->fail('订单不存在');
        return app('json')->success($order->toArray());
    }

    /**
     * @return mixed
     * @author xaboy
     * @day 2020/6/10
     */
    public function number()
    {
        return app('json')->success(['orderPrice' => $this->request->userInfo()->pay_price] + $this->repository->userOrderNumber($this->request->uid()));
    }

    /**
     * @param StoreGroupOrderRepository $groupOrderRepository
     * @return mixed
     * @author xaboy
     * @day 2020/6/10
     */
    public function groupOrderList(StoreGroupOrderRepository $groupOrderRepository)
    {
        [$page, $limit] = $this->getPage();
        $list = $groupOrderRepository->getList(['uid' => $this->request->uid(), 'paid' => 0], $page, $limit);
        return app('json')->success($list);
    }

    /**
     * @param $id
     * @param StoreGroupOrderRepository $groupOrderRepository
     * @return mixed
     * @author xaboy
     * @day 2020/6/10
     */
    public function groupOrderDetail($id, StoreGroupOrderRepository $groupOrderRepository)
    {
        $groupOrder = $groupOrderRepository->detail($this->request->uid(), (int)$id);
        if (!$groupOrder)
            return app('json')->fail('订单不存在');
        else
            return app('json')->success($groupOrder->append(['cancel_time', 'cancel_unix'])->toArray());
    }

    /**
     *  订单状态查询
     * @param $id
     * @param StoreGroupOrderRepository $groupOrderRepository
     * @return \think\response\Json
     * @author Qinii
     */
    public function groupOrderStatus($id, StoreGroupOrderRepository $groupOrderRepository)
    {
        $groupOrder = $groupOrderRepository->status($this->request->uid(), intval($id));
        if (!$groupOrder)
            return app('json')->fail('订单不存在');
        $activity_type = 0;
        $activity_id = 0;
        foreach ($groupOrder->orderList as $order) {
            $activity_type = max($order->activity_type, $activity_type);
            if ($order->activity_type == 4 && $groupOrder->paid) {
                $order->append(['orderProduct']);
                $activity_id = $order->orderProduct[0]['activity_id'];
            }
        }
        $groupOrder->activity_type = $activity_type;
        $groupOrder->activity_id = $activity_id;
        return app('json')->success($groupOrder->toArray());
    }

    /**
     * @param $id
     * @param StoreGroupOrderRepository $groupOrderRepository
     * @return mixed
     * @author xaboy
     * @day 2020/6/10
     */
    public function cancelGroupOrder($id, StoreGroupOrderRepository $groupOrderRepository)
    {
        $groupOrderRepository->cancel((int)$id, $this->request->uid());
        return app('json')->success('取消成功');
    }

    /**
     *  订单付款操作
     * @param $id
     * @param StoreGroupOrderRepository $groupOrderRepository
     * @return mixed|\think\response\Json
     * @author Qinii
     */
    public function groupOrderPay($id, StoreGroupOrderRepository $groupOrderRepository)
    {
        //TODO 佣金结算,佣金退回,物流查询
        $type = $this->request->param('type');
        $is_points = $this->request->param('is_points',0);
        if (!in_array($type, StoreOrderRepository::PAY_TYPE))
            return app('json')->fail('请选择正确的支付方式');
        $groupOrder = $groupOrderRepository->detail($this->request->uid(), (int)$id, false);
        if (!$groupOrder)
            return app('json')->fail('订单不存在或已支付');

        $this->repository->changePayType($groupOrder, array_search($type, StoreOrderRepository::PAY_TYPE));

        if ($groupOrder['pay_price'] == 0) {
            $this->repository->paySuccess($groupOrder);
            return app('json')->status('success', '支付成功', ['order_id' => $groupOrder['group_order_id']]);
        }
        if ($type == 'offline')  {
            if (count($groupOrder['orderList']) > 1) {
                return app('json')->fail('线下支付仅支持同店铺商品');
            }
            if (!systemConfig('offline_switch')) {
                return app('json')->fail('未开启线下支付功能');
            }
            if (!(($groupOrder['orderList'][0]->merchant['offline_switch']) ?? '')) {
                return app('json')->fail('该店铺未开启线下支付');
            }
            return app('json')->status('success', '线下支付，请告知收银员', ['order_id' => $groupOrder['group_order_id']]);
        }

        try {
            return $this->repository->pay($type, $this->request->userInfo(), $groupOrder, $this->request->param('return_url'), $this->request->isApp());
        } catch (\Exception $e) {
            return app('json')->status('error', $e->getMessage(), ['order_id' => $groupOrder->group_order_id]);
        }
    }

    public function take($id)
    {
        $this->repository->takeOrder($id, $this->request->userInfo(), true);
        return app('json')->success('确认收货成功');
    }

    public function express($id)
    {
        $order = $this->repository->getWhere(['order_id' => $id, 'is_del' => 0]);
        if (!$order)
            return app('json')->fail('订单不存在');
        if (!$order->delivery_type || !$order->delivery_id)
            return app('json')->fail('订单未发货');
        $express = $this->repository->express($id,null);
        $order->append(['orderProduct']);
        return app('json')->success(compact('express', 'order'));
    }

    public function verifyCode($id)
    {
        $order = $this->repository->getWhere(['order_id' => $id, 'uid' => $this->request->uid(), 'is_del' => 0]);
        if (!$order)return app('json')->fail('订单状态有误');
        return app('json')->success(['qrcode' => $this->repository->wxQrcode($id, $order)]);
    }

    public function del($id)
    {
        $this->repository->userDel($id, $this->request->uid());
        return app('json')->success('删除成功');
    }

    public function createReceipt($id)
    {
        $data = $this->request->params(['receipt_type' , 'receipt_title' , 'duty_paragraph', 'receipt_title_type', 'bank_name', 'bank_code', 'address','tel', 'email']);
        $order = $this->repository->getWhere(['order_id' => $id, 'uid' => $this->request->uid(), 'is_del' => 0]);
        if (!$order) return app('json')->fail('订单不属于您或不存在');
        app()->make(StoreOrderReceiptRepository::class)->add($data, $order);
        return app('json')->success('操作成功');
    }

    public function getOrderDelivery($id, DeliveryOrderRepository $orderRepository)
    {
        $res = $orderRepository->show($id, $this->request->uid());
        return app('json')->success($res);
    }

    public function getCashierOrder($id)
    {
        $data = $this->repository->payConfig($id, $this->request->uid());
        return app('json')->success($data);
    }

    public function payConfig()
    {
        $id = $this->request->param('id',0);
        $type = $this->request->param('type',0);
        if ($type) {
            $data = $this->repository->payConfigPresell($this->request->uid(), $id);
        } else {
            $data = $this->repository->payConfig($id, $this->request->uid());
        }
        return app('json')->success($data);
    }

    public function cancelOrder($id)
    {
        $order = $this->repository->getSearch(['uid' => $this->request->uid()])->where('order_id',$id)->find();
        if (!$order) return app('json')->fail('订单状态有误');
        //        if (!$order->refund_status) return app('json')->fail('订单已过退款/退货期限');
        if ($order->status < 0) return app('json')->fail('订单已退款');
        if ($order->status == 10 || !$order->is_cancel) return app('json')->fail('订单不支持退款');
        if ($order->is_virtual !== 4) return app('json')->fail('订单不支持退款');
        $this->repository->cancelOrder($order);
        return app('json')->success('订单已取消');
    }
    /**
     * 配送配置
     *
     * @return void
     */
    public function deliveryConfig()
    {
        $merId = $this->request->param('mer_id');
        if(!$merId) {
            return app('json')->fail('缺少商户id');
        }

        $setings = app()->make(DeliveryConfigRepository::class)->deliverySettings($merId);
        return app('json')->success($setings);
    }
    /**
     * 配送站点列表
     *
     * @return void
     */
    public function deliveryStationList()
    {
        $params = $this->request->params([['switch_city', 0], ['switch_take', 0], ['status', 1], 'mer_id', 'address_id', 'name_and_address_search', 'longitude', 'latitude']);
        if(!$params['mer_id']) {
            return app('json')->fail('缺少商户id');
        }
        // 同城配送需要收货地址id
        if($params['switch_city'] && !$params['address_id']) {
            return app('json')->fail('缺少收货地址id');
        }
        // 到店自提需要定位信息
        // if($params['switch_take'] && (!$params['longitude'] || !$params['latitude'])) {
        //     return app('json')->fail('缺少定位信息');
        // }

        $params['uid'] = $this->request->uid();
        $data = app()->make(DeliveryStationRepository::class)->getListSortByDistance($params);

        if(empty($data)) {
            return app('json')->fail('该商家暂未设置提货点，请切换其他配送方式');
        }

        return app('json')->success($data);
    }
    /**
     * 第三方同城配送人员位置
     *
     * @param int $id
     * @return void
     */
    public function deliveryTrack(int $id)
    {
        if(!$id) {
            return app('json')->fail('参数错误');
        }

        return app('json')->success($this->repository->getDeliveryTrack($id));
    }
}
