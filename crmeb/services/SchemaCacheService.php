<?php
// +----------------------------------------------------------------------
// | 清理 ThinkORM 表结构（fields）缓存
// | ALTER TABLE 加字段后若不清理，INSERT/UPDATE 会静默丢弃新字段
// +----------------------------------------------------------------------

namespace crmeb\services;

use think\facade\Config;
use think\facade\Log;

class SchemaCacheService
{
    /**
     * Redis 中 schema key 匹配模式
     * 例：merchant_127.0.0.1_3306|cermb.eb_user
     */
    public static function redisPattern(): string
    {
        $prefix = (string)env('CACHE_PREFIX', Config::get('cache.stores.redis.prefix', 'merchant_'));
        $host = (string)Config::get('database.connections.mysql.hostname', '127.0.0.1');
        $port = (string)Config::get('database.connections.mysql.hostport', '3306');
        $database = (string)Config::get('database.connections.mysql.database', 'cermb');
        return $prefix . $host . '_' . $port . '|' . $database . '.*';
    }

    /**
     * 清理全部表结构缓存（Redis + runtime/schema 文件）
     * @return array{redis:int, files:int}
     */
    public static function clearAll(): array
    {
        $redisDeleted = self::clearRedisSchema();
        $filesDeleted = self::clearSchemaFiles();
        return ['redis' => $redisDeleted, 'files' => $filesDeleted];
    }

    /**
     * 清理指定表 schema（不含库前缀时自动加 eb_）
     */
    public static function clearTable(string $table): int
    {
        $table = trim($table);
        if ($table === '') {
            return 0;
        }
        $prefix = (string)Config::get('database.connections.mysql.prefix', 'eb_');
        if ($prefix && strpos($table, $prefix) !== 0) {
            $table = $prefix . $table;
        }

        $cachePrefix = (string)env('CACHE_PREFIX', Config::get('cache.stores.redis.prefix', 'merchant_'));
        $host = (string)Config::get('database.connections.mysql.hostname', '127.0.0.1');
        $port = (string)Config::get('database.connections.mysql.hostport', '3306');
        $database = (string)Config::get('database.connections.mysql.database', 'cermb');
        $key = $cachePrefix . $host . '_' . $port . '|' . $database . '.' . $table;

        try {
            $redis = app()->make(RedisCacheService::class)->handler();
            return (int)$redis->del($key);
        } catch (\Throwable $e) {
            Log::error('[SchemaCache] clearTable failed: ' . $e->getMessage());
            return 0;
        }
    }

    protected static function clearRedisSchema(): int
    {
        $deleted = 0;
        try {
            $redis = app()->make(RedisCacheService::class)->handler();
            $pattern = self::redisPattern();
            // 兼容 phpredis / predis
            $keys = [];
            if (method_exists($redis, 'keys')) {
                $keys = $redis->keys($pattern) ?: [];
            }
            if (!$keys && method_exists($redis, 'scan')) {
                $it = null;
                do {
                    $batch = $redis->scan($it, $pattern, 200);
                    if ($batch) {
                        $keys = array_merge($keys, $batch);
                    }
                } while ($it > 0);
            }
            $keys = array_values(array_unique(array_filter($keys)));
            if ($keys) {
                // 分批删除，避免一次参数过多
                foreach (array_chunk($keys, 100) as $chunk) {
                    $deleted += (int)call_user_func_array([$redis, 'del'], $chunk);
                }
            }
        } catch (\Throwable $e) {
            Log::error('[SchemaCache] clearRedisSchema failed: ' . $e->getMessage());
        }
        return $deleted;
    }

    protected static function clearSchemaFiles(): int
    {
        $deleted = 0;
        $path = (string)Config::get('database.connections.mysql.schema_cache_path', '');
        if ($path === '' || !is_dir($path)) {
            $path = runtime_path() . 'schema' . DIRECTORY_SEPARATOR;
        }
        if (!is_dir($path)) {
            return 0;
        }
        foreach (glob(rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file) && @unlink($file)) {
                $deleted++;
            }
        }
        return $deleted;
    }
}
