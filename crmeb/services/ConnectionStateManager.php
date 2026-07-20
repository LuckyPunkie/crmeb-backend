<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace crmeb\services;

use think\facade\Cache;

class ConnectionStateManager
{
    /**
     * 注册连接
     */
    public static function register(int $fd, int $uid, string $type, int $merId = 0, int $toUid = 0): void
    {
        $redis = self::redis();
        if (!$redis) {
            return;
        }

        $key = "im:conn:{$fd}";
        $redis->hMSet($key, [
            'uid'         => $uid,
            'mer_id'      => $merId,
            'to_uid'      => $toUid,
            'type'        => $type,
            'last_active' => time(),
        ]);
        $redis->expire($key, 7200);

        $redis->zAdd("im:user:{$uid}:conns", time(), $fd);
        $redis->expire("im:user:{$uid}:conns", 7200);
    }

    /**
     * 注销连接
     */
    public static function unregister(int $fd, int $uid): void
    {
        $redis = self::redis();
        if (!$redis) {
            return;
        }

        $redis->del("im:conn:{$fd}");
        $redis->zRem("im:user:{$uid}:conns", $fd);
    }

    /**
     * 获取用户所有活跃连接
     */
    public static function getUserConnections(int $uid): array
    {
        $redis = self::redis();
        if (!$redis) {
            return [];
        }

        $fds = $redis->zRange("im:user:{$uid}:conns", 0, -1) ?: [];
        $connections = [];
        foreach ($fds as $fd) {
            $conn = $redis->hGetAll("im:conn:{$fd}");
            if (!empty($conn)) {
                $conn['fd'] = $fd;
                $connections[] = $conn;
            }
        }
        return $connections;
    }

    /**
     * 根据 uid 和 merId 查找目标连接
     */
    public static function findUserConnectionsByMerId(int $uid, int $merId): array
    {
        return array_filter(
            self::getUserConnections($uid),
            function ($c) use ($merId) {
                return ($c['mer_id'] ?? 0) == $merId;
            }
        );
    }

    /**
     * 更新连接心跳时间
     */
    public static function heartbeat(int $fd): void
    {
        $redis = self::redis();
        if (!$redis) {
            return;
        }

        $key = "im:conn:{$fd}";
        if ($redis->hExists($key, 'uid')) {
            $redis->hSet($key, 'last_active', time());
            $redis->expire($key, 7200);
        }
    }

    /**
     * 获取连接信息
     */
    public static function getConnection(int $fd): ?array
    {
        $redis = self::redis();
        if (!$redis) {
            return null;
        }

        $conn = $redis->hGetAll("im:conn:{$fd}");
        return empty($conn) ? null : $conn;
    }

    private static function redis(): ?\Redis
    {
        try {
            $handler = Cache::store('redis')->handler();
            return $handler instanceof \Redis ? $handler : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
