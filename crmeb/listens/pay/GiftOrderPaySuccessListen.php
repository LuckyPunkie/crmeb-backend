<?php

namespace crmeb\listens\pay;

use app\common\repositories\gift\GiftRepository;
use crmeb\interfaces\ListenerInterface;
use think\facade\Log;

class GiftOrderPaySuccessListen implements ListenerInterface
{
    public function handle($data): void
    {
        try {
            if (is_array($data) && isset($data[0]) && is_array($data[0])) {
                $notify = $data[0];
                if (isset($notify['out_trade_no']) && strpos($notify['out_trade_no'], 'GF') === 0) {
                    $this->process($notify['out_trade_no']);
                    return;
                }
            }

            $orderSn = $data['order_sn'] ?? ($data['out_trade_no'] ?? '');
            if ($orderSn && strpos($orderSn, 'GF') === 0) {
                $this->process($orderSn);
            }
        } catch (\Throwable $e) {
            Log::error('GiftOrderPaySuccessListen: ' . $e->getMessage());
        }
    }

    protected function process(string $orderSn): void
    {
        app()->make(GiftRepository::class)->paySuccess($orderSn);
        Log::info('GiftOrderPaySuccessListen success: ' . $orderSn);
    }
}
