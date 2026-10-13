<?php

namespace crmeb\listens;

use app\common\repositories\system\operate\OperateLogRepository;
use crmeb\interfaces\ListenerInterface;
use think\facade\Log;

class CreateOperateLogListen implements ListenerInterface
{
    public function handle($event): void
    {
        try {
            app()->make(OperateLogRepository::class)->recordLog($event['category'], $event['data'], $event['mer_id'] ?? 0);
        } catch (\Throwable $throwable) {
            Log::error('操作日志记录失败：' . json_encode([
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ], JSON_UNESCAPED_UNICODE));
        }

    }
}
