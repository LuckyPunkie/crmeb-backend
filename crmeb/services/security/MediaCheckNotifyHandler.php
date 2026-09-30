<?php
// +----------------------------------------------------------------------
// | 小程序消息推送：接收 media_check_async 异步检测结果（Event=wxa_media_check）
// +----------------------------------------------------------------------

namespace crmeb\services\security;

use think\facade\Log;

class MediaCheckNotifyHandler
{
    public function __invoke($message, \Closure $next)
    {
        $event = strtolower((string)($message['Event'] ?? ''));
        if ($event !== 'wxa_media_check') {
            return $next($message);
        }

        $traceId = (string)($message['trace_id'] ?? '');
        $result = $message['result'] ?? [];
        if (!is_array($result)) {
            $result = (array)$result;
        }
        $suggest = (string)($result['suggest'] ?? '');
        $label = (int)($result['label'] ?? 0);
        Log::info('media_check 回调: ' . json_encode(compact('traceId', 'suggest', 'label'), JSON_UNESCAPED_UNICODE));

        if ($traceId !== '' && $suggest !== '') {
            try {
                ContentSecurityService::onMediaResult($traceId, $suggest, $label);
            } catch (\Throwable $e) {
                Log::error('media_check 回调处理失败: ' . $e->getMessage());
            }
        }
        // 返回空值，EasyWeChat 会回复纯文本 success；返回字符串会被包装成文本回复消息
        return null;
    }
}
