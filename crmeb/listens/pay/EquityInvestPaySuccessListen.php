<?php

namespace crmeb\listens\pay;

use app\common\repositories\store\equity\EquityGrantRepository;
use crmeb\interfaces\ListenerInterface;
use think\facade\Log;

/**
 * 充值入股支付成功
 * 事件: pay_success_equity_invest / pay.notify(EQ前缀)
 */
class EquityInvestPaySuccessListen implements ListenerInterface
{
    public function handle($data): void
    {
        try {
            // V3 pay.notify
            if (is_array($data) && isset($data[0]) && is_array($data[0])) {
                $notify = $data[0];
                if (isset($notify['out_trade_no']) && strpos($notify['out_trade_no'], 'EQ') === 0) {
                    $this->process($notify['out_trade_no'], 'weixin');
                    return;
                }
            }

            $orderSn = $data['order_sn'] ?? '';
            if ($orderSn && strpos($orderSn, 'EQ') === 0) {
                $this->process($orderSn, 'weixin');
            }
        } catch (\Throwable $e) {
            Log::error('EquityInvestPaySuccessListen: ' . $e->getMessage());
        }
    }

    protected function process(string $orderSn, string $payType): void
    {
        app()->make(EquityGrantRepository::class)->investPaySuccess($orderSn, $payType);
        Log::info('EquityInvestPaySuccessListen success: ' . $orderSn);
    }
}
