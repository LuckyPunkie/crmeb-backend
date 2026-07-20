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
// | T3.1: 跨 Worker Redis Pub/Sub 消息路由

namespace crmeb\services;

use app\webscoket\Manager;
use Swoole\Timer;
use think\facade\Cache;
use think\facade\Log;
use think\swoole\Manager as SwooleManager;
use think\swoole\Websocket;

class CrossWorkerRouter
{
    /**
     * 路由消息到目标用户所在的 Worker
     * @param int $targetUid 目标用户 uid
     * @param string $action 动作类型
     * @param array $data 消息数据
     * @return bool
     */
    public static function routeToUser(int $targetUid, string $action, array $data): bool
    {
        // 先尝试本地推送（同 Worker 内零延迟）
        $localFds = Manager::userFd(Manager::USER_TYPE['user'] ?? 0, $targetUid);

        if (!empty($localFds)) {
            return self::pushToLocal($localFds, $data);
        }

        // 跨 Worker：通过 Redis Pub/Sub 广播
        $channel = "im:worker:user:{$targetUid}";
        try {
            if (self::redisPublish($channel, json_encode([
                'action'     => $action,
                'target_uid' => $targetUid,
                'data'       => $data,
            ]))) {
                return true;
            }
        } catch (\Throwable $e) {
            Log::error('CrossWorkerRouter publish failed: ' . $e->getMessage());
        }

        // Redis Pub/Sub 不可用时的 fallback：Redis List
        try {
            $redis = self::redis();
            if ($redis) {
                $redis->lPush('im:cross_worker:queue', json_encode([
                    'action'     => $action,
                    'target_uid' => $targetUid,
                    'data'       => $data,
                ]));
            }
        } catch (\Throwable $e) {
            Log::error('CrossWorkerRouter list push failed: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * 推送到特定 fd（跨 Worker 使用 Redis）
     * @param int $fd
     * @param string $data JSON payload
     * @return bool
     */
    public static function routeToFd(int $fd, string $data): bool
    {
        try {
            if (self::redisPublish("im:worker:fd:{$fd}", $data)) {
                return true;
            }
        } catch (\Throwable $e) {
            Log::error('CrossWorkerRouter routeToFd failed: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * 本地推送（同 Worker 内部）
     * @param array $fds
     * @param array $data
     * @return bool
     */
    private static function pushToLocal(array $fds, array $data): bool
    {
        $payload = json_encode($data);
        $ws = app()->make(Websocket::class);

        foreach ($fds as $fd) {
            try {
                $info = Cache::get('_ws_f_' . $fd);
                if (is_array($info) && !empty($info['sender'])) {
                    $ws->to($info['sender'])->push($payload);
                }
            } catch (\Throwable $e) {
                Log::warning("CrossWorkerRouter push to fd {$fd} failed: " . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * 初始化跨 Worker 消息订阅（WorkerStart 中调用）
     * 使用 Swoole Timer 轮询方式，兼容性更好
     */
    public static function subscribe(): void
    {
        /** @var SwooleManager $manager */
        $manager = app()->make(SwooleManager::class);

        Timer::tick(200, function () use ($manager) {
            $manager->runWithBarrier([$manager, 'runInSandbox'], function () {
                self::pollCrossWorkerQueue();
            });
        });
    }

    private static function pollCrossWorkerQueue(): void
    {
        try {
            $redis = self::redis();
            if (!$redis) {
                return;
            }

            $msg = $redis->rPop('im:cross_worker:queue');
            if (!$msg) {
                return;
            }

            $data = json_decode($msg, true);
            if (!$data || !isset($data['target_uid'])) {
                return;
            }

            $targetFds = Manager::userFd(
                Manager::USER_TYPE['user'] ?? 0,
                $data['target_uid']
            );

            if (!empty($targetFds)) {
                self::pushToLocal($targetFds, ['type' => 'chat', 'data' => $data['data']]);
            }
        } catch (\Throwable $e) {
            Log::error('CrossWorkerRouter tick error: ' . $e->getMessage());
        }
    }

    private static function redisPublish(string $channel, string $payload): bool
    {
        $redis = self::redis();
        if (!$redis) {
            return false;
        }

        return $redis->publish($channel, $payload) !== false;
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
