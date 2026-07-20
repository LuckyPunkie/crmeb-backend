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


use app\common\repositories\store\order\StoreOrderProductRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\order\StoreOrderStatusRepository;
use app\common\repositories\store\order\StoreRefundOrderRepository;
use app\common\repositories\store\shipping\ExpressRepository;
use app\common\repositories\store\order\StoreGroupOrderRepository;
use app\validate\api\BackGoodsValidate;
use app\validate\api\StoreRefundOrderValidate;
use crmeb\basic\BaseController;
use think\App;

/**
 * Class StoreRefundOrder
 * @package app\controller\api\store\order
 * @author xaboy
 * @day 2020/6/12
 */
class StoreRefundOrder extends BaseController
{
    /**
     * @var StoreRefundOrderRepository
     */
    protected $repository;

    /**
     * StoreRefundOrder constructor.
     * @param App $app
     * @param StoreRefundOrderRepository $repository
     */
    public function __construct(App $app, StoreRefundOrderRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * @param $id
     * @param StoreOrderRepository $orderRepository
     * @return mixed
     * @author xaboy
     * @day 2020/6/12
     */
    public function batchProduct($id, StoreOrderRepository $orderRepository)
    {
        return app('json')->success($orderRepository->refundProduct($id, $this->request->uid()));
    }

    /**
     * @param $id
     * @param StoreOrderProductRepository $orderProductRepository
     * @param StoreOrderRepository $storeOrderRepository
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author xaboy
     * @day 2020/9/2
     */
    public function product($id, StoreOrderProductRepository $orderProductRepository, StoreOrderRepository $storeOrderRepository)
    {
        $_id  = (string)$this->request->param('ids/s', '');
        $type = (string)$this->request->param('type/s', '');
        $ids  = explode(',', $_id);
        if (!$_id || !count($ids))
            return app('json')->fail('请选择退款商品');
        $uid   = $this->request->uid();
        $order = $storeOrderRepository->userOrder(intval($id), $uid);
        if (!$order)
            return app('json')->fail('订单状态有误');
        if (!$order->refund_status)
            return app('json')->fail('订单已过退款/退货期限');
        if ($order->status < 0) return app('json')->fail('订单已退款');
        if ($order->status == 10) return app('json')->fail('订单不支持退款');
        $product = $orderProductRepository->userRefundProducts($ids, $uid, intval($id));
        if (!$product)
            return app('json')->fail('商品不存在或已退款');
        if (count($product) != count($ids))
            return app('json')->fail('请选择正确的退款商品');
        if ($type == 2) {
            $total_refund_price = $this->repository->getRefundsTotalPrice($order, $product);
            $postage_price      = 0;
        } else {
            $data               = $this->repository->getRefundTotalPrice($order, $product);
            $total_refund_price = (float)$data['total_refund_price'];
            $postage_price      = (float)$data['postage_price'];
        }
        if ($order->status == 11) {
            $total_refund_price = $order->pay_price;
        }
        // 同城配送订单显示运费
        if($order->order_type == 2) {
            $postage_price = $order->total_postage;
        }
        $status        = (!$order->status || $order->status == 9) ? 0 : $order->status;
        $activity_type = $order->activity_type;
        return app('json')->success(compact('activity_type', 'total_refund_price', 'product', 'postage_price', 'status'));
    }
    /**
     * 计算退款金额
     *
     * @param StoreOrderRepository $storeOrderRepository 订单仓库
     * @return \think\response\Json
     */
    public function compute(StoreOrderRepository $storeOrderRepository)
    {
        $refund = $this->request->param('refund/a', []);
        $orderId = $this->request->param('order_id/d', 0);
        if(empty($refund) || !$orderId) {
            return app('json')->fail('参数错误');
        }
        $order = $storeOrderRepository->getSearch(['order_id' => $orderId])->find();
        // 判断订单是否存在
        if (!$order) return app('json')->fail('订单状态有误');
//        if (!$order->refund_status) return app('json')->fail('订单已过退款/退货期限');
        // 判断订单是否可退款
        if ($order->status < 0) return app('json')->fail('订单已退款');
        if ($order->status == 10) return app('json')->fail('订单不支持退款');
        list($totalRefundPrice,$isDisplayPostage) = $this->repository->compute($order, $refund);
        // 返回结果
        return app('json')->success(compact('totalRefundPrice','isDisplayPostage'));
    }

    /**
     * @param $id
     * @param StoreRefundOrderValidate $validate
     * @param StoreOrderRepository $orderRepository
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author xaboy
     * @day 2020/6/12
     */
    public function refund($id, StoreRefundOrderValidate $validate, StoreOrderRepository $orderRepository)
    {
        $data = $this->request->params(['type', 'refund_type', 'refund_price', 'num', 'ids', 'refund_message', 'mark', 'pics']);
        $validate->check($data);
        $ids  = explode(',', $data['ids']);
        $type = $data['type'];
        $num  = $data['num'];
        unset($data['num'], $data['ids'], $data['type']);
        if ($type == 1 && count($ids) > 1)
            return app('json')->fail('请选择正确的退款商品');
        $uid   = $this->request->uid();
        $order = $orderRepository->userOrder($id, $uid);
        if (!$order) return app('json')->fail('订单状态错误');
        if ($order->order_type == 2 && (isset($order->deliveryOrder) && in_array($order->deliveryOrder->status, [3,4]))) // 配送中、已完成
            return app('json')->fail('订单已配送，不支持退款');
        if (!$order->refund_status)
            return app('json')->fail('订单已过退款/退货期限');
        if ($order->status < 0) return app('json')->fail('订单已退款');
        if ($order->status == 10) return app('json')->fail('订单不支持退款');
        if ($order->is_virtual && $data['refund_type'] == 2) return app('json')->fail('订单不支持退款退货');
        if ($type == 1) {
            $refund = $this->repository->refund($order, (int)$ids[0], $num, $uid, $data);
        } else {
            $refund = $this->repository->refunds($order, $ids, $uid, $data);
        }
        return app('json')->success('提交申请成功', ['refund_order_id' => $refund->refund_order_id]);
    }

    /**
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author xaboy
     * @day 2020/6/12
     */
    public function lst()
    {
        $type = $this->request->param('type/d');
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->userList([
            'type'   => $type,
            'uid'    => $this->request->uid(),
            'is_del' => 0,
        ], $page, $limit));
    }

    /**
     * @param $id
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author xaboy
     * @day 2020/6/12
     */
    public function detail($id, StoreOrderStatusRepository $orderStatusRepository)
    {
        $refund = $this->repository->userDetail(intval($id), $this->request->uid());
        if (!$refund)
            return app('json')->fail('退款单不存在');
        $daysToAdd            = (int)systemConfig('platform_intervention_days');
        $intervention         = $orderStatusRepository->existsWhere(['order_id' => $id, 'change_type' => [$orderStatusRepository::CHANGE_REFUND_PLATFORM_AGREE, $orderStatusRepository::CHANGE_REFUND_PLATFORM_REFUSE]]);
        $refund->platform_msg = isset($refund->platform) ? $refund->platform['change_message'] : '';
        $refund->platform     = systemConfig('platform_intervention') && $refund->status == -1 && time() <= strtotime("{$refund->status_time} +{$daysToAdd} days") && !$intervention;
        return app('json')->success($refund->toArray());
    }

    /**
     * @param $id
     * @return mixed
     * @throws \think\db\exception\DbException
     * @author xaboy
     * @day 2020/6/12
     */
    public function del($id)
    {
        $this->repository->userDel(intval($id), $this->request->uid());
        return app('json')->success('删除成功');
    }

    public function back_goods($id, BackGoodsValidate $validate, ExpressRepository $expressRepository)
    {
        $data = $this->request->params(['delivery_type', 'delivery_id', 'delivery_phone', 'delivery_mark', 'delivery_pics']);
        $validate->check($data);
        if (!$expressRepository->merFieldExists('name', $data['delivery_type'], null, null, true))
            return app('json')->fail('不支持该快递公司');
        $this->repository->backGoods($this->request->uid(), $id, $data);
        return app('json')->success('提交成功');
    }

    public function express($id)
    {
        if (!$refund = $this->repository->getWhere(['status' => 2, 'refund_order_id' => $id]))
            return app('json')->fail('退款单不存在');
        $express = $this->repository->express($id);
        return app('json')->success(compact('refund', 'express'));
    }

    public function cancel($id)
    {
        $this->repository->cancel($id, $this->request->userInfo());
        return app('json')->success('取消成功');
    }

    /**
     * 用户申请平台介入
     * @param $refund_order_id
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function platformIntervene($id)
    {
        $this->repository->applyPlatformIntervene($id, $this->request->uid());
        return app('json')->success('申请成功');
    }
}
