<?php

namespace crmeb\jobs;

use crmeb\interfaces\JobInterface;
use crmeb\services\wechat\MiniProgram;
use think\facade\Log;

/**
 * 小程序订单签收同步
 */
class MiniOrderConfirmJob implements JobInterface
{
    /**
     * @param mixed $job
     * @param array $data
     * @return mixed
     */
    public function fire($job, $data)
    {
        try {
            MiniProgram::notifyConfirmByTradeNo(
                (string)($data['out_trade_no'] ?? ''),
                (int)($data['received_time'] ?? 0),
                (string)($data['sub_mchid'] ?? '')
            );
        } catch (\Throwable $e) {
            Log::error('小程序订单签收同步失败：' . json_encode([
                'message' => $e->getMessage(),
                'out_trade_no' => (string)($data['out_trade_no'] ?? ''),
                'received_time' => (int)($data['received_time'] ?? 0),
                'sub_mchid' => (string)($data['sub_mchid'] ?? ''),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return $job->delete();
    }

    /**
     * @param array $data
     * @return void
     */
    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}
