<?php

namespace crmeb\listens;

use crmeb\interfaces\ListenerInterface;
use crmeb\services\SchemaCacheService;
use think\facade\Log;

/**
 * Swoole 启动时清理 ThinkORM 表结构缓存，避免 ALTER 加字段后新列被静默丢弃
 */
class ClearSchemaCacheListen implements ListenerInterface
{
    public function handle($event): void
    {
        try {
            $result = SchemaCacheService::clearAll();
            Log::info(sprintf(
                '[SchemaCache] swoole.init cleared redis=%d files=%d',
                $result['redis'],
                $result['files']
            ));
        } catch (\Throwable $e) {
            Log::error('[SchemaCache] swoole.init clear failed: ' . $e->getMessage());
        }
    }
}
