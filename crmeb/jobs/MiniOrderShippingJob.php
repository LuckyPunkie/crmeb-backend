<?php

namespace crmeb\jobs;

use crmeb\interfaces\JobInterface;
use crmeb\services\wechat\MiniProgram;

/**
 * 小程序订单处理
 */
class MiniOrderShippingJob implements JobInterface
{
    /**
     * @param string $out_trade_no
     * @param int $logistics_type
     * @param array $shipping_list
     * @param string $payer_openid
     * @param string $path
     * @param int $delivery_mode
     * @param bool $is_all_delivered
     * @param string $sub_mchid
     * @return void
     */
    public function fire($job, $data)
    {
        try {
            MiniProgram::shippingByTradeNo($data['out_trade_no'], $data['logistics_type'], $data['shipping_list'], $data['payer_openid'], $data['path'], $data['delivery_mode'] ?? 1, $data['is_all_delivered'] ?? true, $data['sub_mchid'] ?? '');
        } catch (\Throwable $e) {
            \think\facade\Log::error('小程序订单处理失败：' . json_encode([
                'message' => $e->getMessage(),
                'out_trade_no' => (string)($data['out_trade_no'] ?? ''),
                'logistics_type' => $data['logistics_type'] ?? '',
                'path' => $data['path'] ?? '',
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        return $job->delete();
    }

    /**
     * 队列任务执行失败时回调（预留，当前未实现处理逻辑）
     * @param $data 任务数据
     * @return void
     */
    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}
