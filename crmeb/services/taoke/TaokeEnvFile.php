<?php

namespace crmeb\services\taoke;

/**
 * 直接读 .env 文件里的最新值。
 *
 * Swoole 常驻内存：config/env 只在启动时读一次。OAuth token 刷新后写回 .env，
 * 但各 worker 内存里仍是旧值（快手 refresh_token 刷新一次即作废），
 * 所以 token / 过期时间这类会被运行时改写的项要以文件为准。
 */
class TaokeEnvFile
{
    /**
     * @param list<string> $keys .env 里的原始键名，如 KUAISHOU_ACCESS_TOKEN
     * @return array<string, string> 只包含文件里存在的键
     */
    public static function read(array $keys): array
    {
        $envPath = app()->getRootPath() . '.env';
        $content = is_file($envPath) ? @file_get_contents($envPath) : false;
        if ($content === false || $content === '') {
            return [];
        }
        $out = [];
        foreach ($keys as $key) {
            if (preg_match('/^' . preg_quote($key, '/') . '\s*=\s*"?([^"\r\n]*)"?\s*$/m', $content, $m)) {
                $out[$key] = trim($m[1]);
            }
        }
        return $out;
    }
}
