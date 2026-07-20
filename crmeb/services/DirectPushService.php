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
// | T2.3: 同进程直接推送 — 跳过 Redis 队列，减少推送延迟

namespace crmeb\services;

use app\webscoket\Manager;
use think\facade\Cache;
use think\facade\Log;
use think\swoole\Websocket;

class DirectPushService
{
    /**
     * 直接推送到指定用户的所有 fd（同 Worker 内）
     * @param array $params ['uid', 'data', 'except', 'msg_id']
     * @return bool 是否成功推送
     */
    public static function pushToUser(array $params): bool
    {
        $uid = $params['uid'] ?? 0;
        $data = $params['data'] ?? [];
        $except = $params['except'] ?? [];
        $msgId = $params['msg_id'] ?? null;

        if (!$uid || empty($data)) {
            return false;
        }

        $payload = json_encode([
            'type'   => 'chat',
            'data'   => $data,
            'msg_id' => $msgId,
        ]);

        // M3 修复: 统一使用 ConnectionStateManager 获取连接列表
        $connections = ConnectionStateManager::getUserConnections($uid);

        // 兼容旧式 m_chat/u_chat Redis SET（暂时双读）
        if (empty($connections)) {
            $mChatFds = Cache::sMembers('m_chat' . $uid) ?: [];
            $uChatFds = Cache::sMembers('u_chat' . $uid) ?: [];

            $allFds = [];
            foreach ($mChatFds as $item) {
                $parts = explode('/', $item);
                if (!empty($parts[0])) $allFds[] = (int)$parts[0];
            }
            foreach ($uChatFds as $item) {
                $parts = explode('/', $item);
                if (!empty($parts[0])) $allFds[] = (int)$parts[0];
            }
            $allFds = array_unique($allFds);
        } else {
            $allFds = array_column($connections, 'fd');
        }

        if (empty($allFds)) {
            return false;
        }

        $pushed = false;
        $ws = app()->make(Websocket::class);

        foreach ($allFds as $fd) {
            if (in_array($fd, $except) || in_array((string)$fd, array_map('strval', $except))) {
                continue;
            }
            try {
                // 通过 Cache 获取 sender 信息验证 fd 有效性
                $info = Cache::get('_ws_f_' . $fd);
                if (!is_array($info) || empty($info['sender'])) {
                    continue;
                }
                $ws->to($info['sender'])->push($payload);
                $pushed = true;
            } catch (\Throwable $e) {
                Log::info('DirectPush failed for fd ' . $fd . ': ' . $e->getMessage());
            }
        }

        return $pushed;
    }

    /**
     * 推送到指定 fd（直接推送，用于同 Worker）
     * @param int $fd
     * @param array $data
     * @param int|null $msgId
     * @return bool
     */
    public static function pushToFd(int $fd, array $data, ?int $msgId = null): bool
    {
        try {
            $payload = json_encode([
                'type'   => 'chat',
                'data'   => $data,
                'msg_id' => $msgId,
            ]);

            $info = Cache::get('_ws_f_' . $fd);
            if (!is_array($info) || empty($info['sender'])) {
                return false;
            }

            app()->make(Websocket::class)->to($info['sender'])->push($payload);
            return true;
        } catch (\Throwable $e) {
            Log::info('DirectPush pushToFd failed for fd ' . $fd . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Fallback: 回退到 Swoole Task Queue
     */
    public static function fallbackToQueue(array $params): void
    {
        $type = $params['type'] ?? 'chat_to_user';
        switch ($type) {
            case 'chat_to_user':
                SwooleTaskService::chatToUser($params);
                break;
            case 'chat_to_service':
                SwooleTaskService::chatToService($params);
                break;
            default:
                SwooleTaskService::chatToUser($params);
        }
    }
}
