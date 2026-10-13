<?php

namespace app\controller\api\merchant;

use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\merchant\ShoppingCreditsRepository;
use app\common\repositories\merchant\ShoppingCreditsPaymentRepository;
use app\common\repositories\merchant\ShoppingCreditsRefundRepository;
use crmeb\basic\BaseController;
use think\App;

/**
 * C端购物积分/购物金控制器：负责购物金充值套餐、购物金资产、充值下单、充值订单、收支记录、退款申请与退款订单等接口，继承自 BaseController
 */
class ShoppingCredits extends BaseController
{
    protected ShoppingCreditsRepository $repository;

    /**
     * 构造函数，初始化控制器
     * @param App $app 应用实例
     * @param ShoppingCreditsRepository $repository 购物金仓储
     */
    public function __construct(App $app, ShoppingCreditsRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * C端-充值套餐列表：获取指定店铺上架的购物金充值套餐列表
     * @param $merId 店铺ID
     * @return \think\response\Json
     */
    public function packages($merId)
    {
        return app('json')->success($this->repository->packageList((int)$merId, true));
    }

    /**
     * C端-获取充值套餐：根据请求中的套餐ID集合与店铺ID返回上架套餐，未传ID时返回全部
     * @return \think\response\Json
     */
    public function packagesByIds()
    {
        $ids = $this->request->param('ids', []);
        $merId = (int)$this->request->param('mer_id', 0);
        if (!$merId) {
            return app('json')->fail('缺少店铺参数');
        }
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)$ids))));
        $packageList = $ids
            ? $this->repository->packageListByIds($ids, $merId)
            : $this->repository->packageList($merId, true);

        $packages = [];
        foreach ($packageList as $package) {
            $packages[] = [
                'id' => (int)$package['id'],
                'recharge_amount' => (string)$package['recharge_amount'],
                'gift_amount' => (string)$package['gift_amount'],
                'rechargeAmount' => (string)$package['recharge_amount'],
                'giftAmount' => (string)$package['gift_amount'],
            ];
        }
        return app('json')->success($packages);
    }

    /**
     * C端-购物金资产：获取当前用户在指定店铺的购物金资产（充值、赠送余额及累计数据）
     * @param $merId 店铺ID
     * @return \think\response\Json
     */
    public function asset($merId)
    {
        return app('json')->success($this->repository->asset($this->request->uid(), (int)$merId));
    }

    /**
     * C端-购物金充值下单：根据套餐ID与支付方式创建充值订单并拉起支付
     * @return \think\response\Json
     */
    public function payPackage(ShoppingCreditsPaymentRepository $paymentRepository)
    {
        [$packageId, $payType, $returnUrl] = $this->request->params(['package_id', 'pay_type', ['return_url', '']], true);
        $payType = ($payType === 'pc') ? 'balance' : $payType;
        if (!in_array($payType, StoreOrderRepository::PAY_TYPE, true))
            return app('json')->fail('请选择正确的支付方式:'.$payType);

        $payment = $paymentRepository->createRechargeOrder(
            $this->request->uid(),
            (int)$packageId,
            (string)$payType,
            (string)$returnUrl,
            $this->request->isApp()
        );

        return app('json')->status($payment['status'], $payment['result']);
    }

    /**
     * C端-充值订单列表：分页获取当前用户在指定店铺的购物金充值订单
     * @return \think\response\Json
     */
    public function rechargeOrders(ShoppingCreditsPaymentRepository $paymentRepository)
    {
        [$page, $limit] = $this->getPage();
        [$start, $stop] = $this->request->params(['start', 'stop'], true);
        $date = $start && $stop
            ? date('Y/m/d', (int)$start) . '-' . date('Y/m/d', (int)$stop)
            : '';

        return app('json')->success($paymentRepository->userRechargeOrders(
            $this->request->uid(),
            (int)$this->request->param('mer_id'),
            $page,
            $limit,
            $date
        ));
    }

    /**
     * C端-购物金流水记录：分页获取当前用户在指定店铺的购物金流水
     * @return \think\response\Json
     */
    public function records()
    {
        [$page, $limit] = $this->getPage();
        [$start, $stop] = $this->request->params(['start', 'stop'], true);
        $date = $start && $stop
            ? date('Y/m/d', (int)$start) . '-' . date('Y/m/d', (int)$stop)
            : '';
        $recordType = (string)$this->request->param('record_type', 'expense');
        $recordType = in_array($recordType, ['expense', 'refund'], true) ? $recordType : 'expense';

        return app('json')->success($this->repository->userRecords(
            $this->request->uid(),
            (int)$this->request->param('mer_id'),
            $page,
            $limit,
            $recordType,
            $date
        ));
    }

    /**
     * C端-申请退款：对已支付的充值订单发起购物金退款申请
     * @return \think\response\Json
     */
    public function refundApply(ShoppingCreditsRefundRepository $refundRepository)
    {
        [$orderNo, $reason] = $this->request->params(['order_no', ['refund_reason', '']], true);
        $refundNo = $refundRepository->applyRefund($this->request->uid(), (string)$orderNo, (string)$reason);
        return app('json')->success('退款申请成功', ['refund_order_no' => $refundNo]);
    }

    /**
     * C端-退款订单列表：分页获取当前用户在指定店铺的购物金退款订单
     * @return \think\response\Json
     */
    public function refundOrders(ShoppingCreditsRefundRepository $refundRepository)
    {
        [$page, $limit] = $this->getPage();
        [$start, $stop] = $this->request->params(['start', 'stop'], true);
        $date = $start && $stop ? date('Y/m/d', $start) . '-' . date('Y/m/d', $stop) : '';
        return app('json')->success($refundRepository->userRefundOrders(
            $this->request->uid(),
            (int)$this->request->param('mer_id'),
            $page,
            $limit,
            $date
        ));
    }
}
