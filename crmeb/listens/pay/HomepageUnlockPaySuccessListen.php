<?php

namespace crmeb\listens\pay;

use app\common\repositories\user\UserHomepageUnlockRepository;
use crmeb\interfaces\ListenerInterface;
use think\facade\Log;

/**
 * 主页解锁支付回调（订单号 HP 前缀）
 */
class HomepageUnlockPaySuccessListen implements ListenerInterface
{
    public function handle($data): void
    {
        try {
            if (is_array($data) && isset($data[0]) && is_array($data[0])) {
                $notify = $data[0];
                if (isset($notify['out_trade_no']) && strpos($notify['out_trade_no'], UserHomepageUnlockRepository::ORDER_PREFIX) === 0) {
                    $this->process($notify['out_trade_no']);
                    return;
                }
            }

            $orderSn = $data['order_sn'] ?? ($data['out_trade_no'] ?? '');
            if ($orderSn && strpos($orderSn, UserHomepageUnlockRepository::ORDER_PREFIX) === 0) {
                $this->process($orderSn);
            }
        } catch (\Throwable $e) {
            Log::error('HomepageUnlockPaySuccessListen: ' . $e->getMessage());
        }
    }

    protected function process(string $orderSn): void
    {
        app()->make(UserHomepageUnlockRepository::class)->paySuccess($orderSn);
        Log::info('HomepageUnlockPaySuccessListen success: ' . $orderSn);
    }
}
