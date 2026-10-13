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


use app\common\repositories\store\order\PresellOrderRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

class PresellOrder extends BaseController
{
    protected $repository;

    public function __construct(App $app, PresellOrderRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 处理支付请求
     *
     * 本函数旨在处理用户的支付操作，包括校验支付方式的有效性、检查订单状态和支付状态，
     * 以及根据不同的支付方式引导用户完成支付。如果订单无需支付或已支付，则直接标记支付成功。
     *
     * @param int $id 订单ID
     * @return mixed 返回支付结果，可能是支付成功的提示，也可能是支付网关的跳转指令
     */
    public function pay($id)
    {
        // 获取用户选择的支付方式
        $type = $this->request->param('type/s');

        // 校验支付方式是否有效
        if (!in_array($type, StoreOrderRepository::PAY_TYPE))
            return app('json')->fail('请选择正确的支付方式');

        // 根据用户ID和订单ID获取订单信息
        $order = $this->repository->userOrder($this->request->uid(), intval($id));

        // 检查订单是否存在
        if (!$order)
            throw new ValidateException('尾款订单不存在');

        // 检查订单是否已支付
        if ($order->paid)
            throw new ValidateException('已支付');

        // 检查订单是否失效
        if (!$order->status)
            throw new ValidateException('尾款订单以失效');

        // 检查支付时间是否有效
        if (strtotime($order->final_start_time) > time())
            throw new ValidateException('未到尾款支付时间');
        if (strtotime($order->final_end_time) < time())
            throw new ValidateException('已过尾款支付时间');

        // 更新订单支付方式
        $order->pay_type = array_search($type, StoreOrderRepository::PAY_TYPE);
        if ($type == 'offline')  {
            if (!systemConfig('offline_switch')) {
                return app('json')->fail('未开启线下支付功能');
            }
            if (!(($order->merchant['offline_switch']) ?? '')) {
                return app('json')->fail('该店铺未开启线下支付');
            }
            return app('json')->status('success', '线下支付，请告知收银员', ['order_id' => $order->presell_order_id]);
        }
        $order->save();
        // 如果订单金额为0，直接标记支付成功并返回
        if ($order['pay_price'] == 0) {
            $this->repository->paySuccess($order);
            return app('json')->status('success', '支付成功', ['order_id' => $order['presell_order_id']]);
        }
        // 尝试执行支付操作，可能会根据支付方式跳转到不同的支付网关
        try {
            return $this->repository->pay($type, $this->request->userInfo(), $order, $this->request->param('return_url'), $this->request->isApp());
        } catch (\Exception $e) {
            // 支付过程中发生异常，返回错误信息
            return app('json')->status('error', $e->getMessage(), ['order_id' => $order->presell_order_id]);
        }
    }
}
