<?php

namespace crmeb\listens\pay;

use app\controller\api\store\nearby\NearbyVoucher;
use crmeb\interfaces\ListenerInterface;
use think\facade\Log;

/**
 * 代金券购买支付回调监听
 * 注册事件: pay_success_coupon_buy (V2), pay.notify 中 CV 前缀订单 (V3)
 */
class NearbyCouponBuyPaySuccessListen implements ListenerInterface
{
    public function handle($data): void
    {
        // V3 回调: pay.notify 事件
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            $notify = $data[0];
            if (isset($notify['out_trade_no']) && strpos($notify['out_trade_no'], 'CV') === 0) {
                Log::info('NearbyCouponBuyPaySuccessListen V3: ' . $notify['out_trade_no']);
                $this->process($notify['out_trade_no'], 'weixin');
                return;
            }
        }

        // V2 回调: pay_success_coupon_buy 事件
        if (isset($data['order_sn']) && strpos($data['order_sn'], 'CV') === 0) {
            Log::info('NearbyCouponBuyPaySuccessListen V2: ' . $data['order_sn']);
            $this->process($data['order_sn'], 'weixin');
        }
    }

    protected function process(string $orderSn, string $payType): void
    {
        try {
            /** @var NearbyVoucher $controller */
            $controller = app()->make(NearbyVoucher::class);
            $controller->doPaySuccess($orderSn, $payType);
            Log::info('NearbyCouponBuyPaySuccessListen success: ' . $orderSn);
        } catch (\Throwable $e) {
            Log::error('NearbyCouponBuyPaySuccessListen failed: ' . $e->getMessage());
        }
    }
}
