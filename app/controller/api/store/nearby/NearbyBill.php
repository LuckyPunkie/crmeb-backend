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

namespace app\controller\api\store\nearby;

use think\App;
use crmeb\basic\BaseController;
use crmeb\services\pay\Pay;
use crmeb\services\PayService;
use app\common\repositories\store\nearby\NearbyShopBillOrderRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\validate\api\nearby\NearbyBillValidate;

/**
 * 附近好店买单 - C端API控制器
 */
class NearbyBill extends BaseController
{
    protected $repository;
    protected $merchantRepository;

    public function __construct(App $app, NearbyShopBillOrderRepository $repository, MerchantRepository $merchantRepository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->merchantRepository = $merchantRepository;
    }

    /**
     * 创建买单订单
     * POST /api/nearby/bill/create
     */
    public function create(NearbyBillValidate $validate)
    {
        $data = $this->request->params([
            'mer_id',
            'pay_price',
            'pay_type',
            'coupon_id',
        ]);

        $validate->check($data);

        // 校验商户是否存在且在附近好店展示
        $merchant = $this->merchantRepository->get((int)$data['mer_id']);
        if (!$merchant || $merchant['is_del'] || $merchant['status'] != 1) {
            return app('json')->fail('商家不存在或已下架');
        }

        // 创建订单
        $data['uid'] = $this->request->uid();
        $order = $this->repository->createBillOrder($data);

        // 调起支付
        try {
            $payType = $data['pay_type'];
            $payService = new PayService($payType, [
                'order_sn' => $order['order_sn'],
                'pay_price' => (float)$data['pay_price'],
                'attach' => 'bill_pay',
            ], 'bill');

            $user = $this->request->userInfo();
            $config = $payService->pay($user);

            return app('json')->success([
                'order_sn' => $order['order_sn'],
                'pay_price' => $order['pay_price'],
                'config' => $config,
            ]);
        } catch (\Exception $e) {
            return app('json')->fail('支付配置失败: ' . $e->getMessage());
        }
    }

    /**
     * 重新调起支付
     * POST /api/nearby/bill/pay/:order_sn
     */
    public function pay($orderSn)
    {
        $order = $this->repository->getWhere(['order_sn' => $orderSn]);
        if (!$order) {
            return app('json')->fail('订单不存在');
        }

        if ($order['paid'] == 1) {
            return app('json')->fail('订单已支付');
        }

        if ($order['uid'] != $this->request->uid()) {
            return app('json')->fail('无权操作该订单');
        }

        try {
            $payService = new PayService($order['pay_type'], [
                'order_sn' => $order['order_sn'],
                'pay_price' => (float)$order['pay_price'],
                'attach' => 'bill_pay',
            ], 'bill');

            $user = $this->request->userInfo();
            $config = $payService->pay($user);

            return app('json')->success([
                'order_sn' => $order['order_sn'],
                'pay_price' => $order['pay_price'],
                'config' => $config,
            ]);
        } catch (\Exception $e) {
            return app('json')->fail('支付配置失败: ' . $e->getMessage());
        }
    }
}
