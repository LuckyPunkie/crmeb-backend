<?php

namespace crmeb\listens\pay;

use app\common\repositories\user\UserFindMeOrderRepository;
use crmeb\interfaces\ListenerInterface;
use think\facade\Log;

/**
 * 找人付费查询支付回调（订单号 FM 前缀）
 */
class FindMeOrderPaySuccessListen implements ListenerInterface
{
    public function handle($data): void
    {
        try {
            if (is_array($data) && isset($data[0]) && is_array($data[0])) {
                $notify = $data[0];
                if (isset($notify['out_trade_no']) && strpos($notify['out_trade_no'], UserFindMeOrderRepository::ORDER_PREFIX) === 0) {
                    $this->process($notify['out_trade_no']);
                    return;
                }
            }

            $orderSn = $data['order_sn'] ?? ($data['out_trade_no'] ?? '');
            if ($orderSn && strpos($orderSn, UserFindMeOrderRepository::ORDER_PREFIX) === 0) {
                $this->process($orderSn);
            }
        } catch (\Throwable $e) {
            Log::error('FindMeOrderPaySuccessListen: ' . $e->getMessage());
        }
    }

    protected function process(string $orderSn): void
    {
        app()->make(UserFindMeOrderRepository::class)->paySuccess($orderSn);
        Log::info('FindMeOrderPaySuccessListen success: ' . $orderSn);
    }
}
