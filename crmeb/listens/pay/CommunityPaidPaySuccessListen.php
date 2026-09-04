<?php

namespace crmeb\listens\pay;

use app\common\repositories\community\CommunityPaidContentRepository;
use crmeb\interfaces\ListenerInterface;
use think\facade\Log;

/**
 * 社区付费内容解锁支付回调（订单号 PO 前缀）
 */
class CommunityPaidPaySuccessListen implements ListenerInterface
{
    public function handle($data): void
    {
        try {
            if (is_array($data) && isset($data[0]) && is_array($data[0])) {
                $notify = $data[0];
                if (isset($notify['out_trade_no']) && strpos($notify['out_trade_no'], 'PO') === 0) {
                    $this->process($notify['out_trade_no']);
                    return;
                }
            }

            $orderSn = $data['order_sn'] ?? ($data['out_trade_no'] ?? '');
            if ($orderSn && strpos($orderSn, 'PO') === 0) {
                $this->process($orderSn);
            }
        } catch (\Throwable $e) {
            Log::error('CommunityPaidPaySuccessListen: ' . $e->getMessage());
        }
    }

    protected function process(string $orderSn): void
    {
        app()->make(CommunityPaidContentRepository::class)->paySuccess($orderSn);
        Log::info('CommunityPaidPaySuccessListen success: ' . $orderSn);
    }
}
