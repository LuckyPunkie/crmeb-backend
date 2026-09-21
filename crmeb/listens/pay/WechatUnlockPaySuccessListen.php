<?php

namespace crmeb\listens\pay;

use app\common\repositories\user\UserWechatUnlockRepository;
use crmeb\interfaces\ListenerInterface;
use think\facade\Log;

/**
 * 微信号解锁支付回调（订单号 WU 前缀）
 */
class WechatUnlockPaySuccessListen implements ListenerInterface
{
    public function handle($data): void
    {
        try {
            if (is_array($data) && isset($data[0]) && is_array($data[0])) {
                $notify = $data[0];
                if (isset($notify['out_trade_no']) && strpos($notify['out_trade_no'], UserWechatUnlockRepository::ORDER_PREFIX) === 0) {
                    $this->process($notify['out_trade_no']);
                    return;
                }
            }

            $orderSn = $data['order_sn'] ?? ($data['out_trade_no'] ?? '');
            if ($orderSn && strpos($orderSn, UserWechatUnlockRepository::ORDER_PREFIX) === 0) {
                $this->process($orderSn);
            }
        } catch (\Throwable $e) {
            Log::error('WechatUnlockPaySuccessListen: ' . $e->getMessage());
        }
    }

    protected function process(string $orderSn): void
    {
        app()->make(UserWechatUnlockRepository::class)->paySuccess($orderSn);
        Log::info('WechatUnlockPaySuccessListen success: ' . $orderSn);
    }
}
