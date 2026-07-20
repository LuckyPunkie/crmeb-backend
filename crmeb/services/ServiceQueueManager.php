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
// | T3.4: 客服排队管理

namespace crmeb\services;

use app\common\model\store\service\ServiceQueue;
use app\common\repositories\store\service\StoreServiceRepository;
use think\facade\Cache;

class ServiceQueueManager
{
    /**
     * 用户加入排队
     * @param int $uid 用户ID
     * @param int $merId 商户ID
     * @return array [position, estimated_wait]
     */
    public static function enqueue(int $uid, int $merId): array
    {
        $queueKey = "im:service_queue:{$merId}";

        // 检查是否已在队列中
        $existing = ServiceQueue::where('uid', $uid)
            ->where('mer_id', $merId)
            ->where('status', ServiceQueue::STATUS_WAITING)
            ->find();

        if ($existing) {
            $position = (int)Cache::zRank($queueKey, $uid) + 1;
            return ['position' => $position, 'estimated_wait' => $position * 30, 'queued_at' => $existing->created_at];
        }

        // 加入 Redis 有序队列
        $score = microtime(true);
        Cache::zAdd($queueKey, $score, $uid);

        // 持久化到数据库
        ServiceQueue::create([
            'uid'       => $uid,
            'mer_id'    => $merId,
            'status'    => ServiceQueue::STATUS_WAITING,
            'position'  => 0,
        ]);

        $position = (int)Cache::zCard($queueKey);
        return ['position' => $position, 'estimated_wait' => $position * 30];
    }

    /**
     * 客服空闲时自动分配队列中的下一位用户
     * @param int $merId 商户ID
     * @param int $serviceId 客服ID
     * @return array|null 分配到的用户信息
     */
    public static function assignNext(int $merId, int $serviceId): ?array
    {
        $queueKey = "im:service_queue:{$merId}";
        $uids = Cache::zRange($queueKey, 0, 0);

        if (empty($uids)) {
            return null;
        }

        $uid = (int)$uids[0];
        Cache::zRem($queueKey, $uid);

        // 更新数据库状态
        ServiceQueue::where('uid', $uid)
            ->where('mer_id', $merId)
            ->where('status', ServiceQueue::STATUS_WAITING)
            ->update([
                'status'     => ServiceQueue::STATUS_ASSIGNED,
                'service_id' => $serviceId,
            ]);

        return ['uid' => $uid, 'mer_id' => $merId, 'service_id' => $serviceId];
    }

    /**
     * 用户取消排队
     * @param int $uid
     * @param int $merId
     */
    public static function cancel(int $uid, int $merId): void
    {
        $queueKey = "im:service_queue:{$merId}";
        Cache::zRem($queueKey, $uid);

        ServiceQueue::where('uid', $uid)
            ->where('mer_id', $merId)
            ->where('status', ServiceQueue::STATUS_WAITING)
            ->update(['status' => ServiceQueue::STATUS_CANCELLED]);
    }

    /**
     * 获取排队长度
     * @param int $merId
     * @return int
     */
    public static function getQueueLength(int $merId): int
    {
        return (int)Cache::zCard("im:service_queue:{$merId}");
    }

    /**
     * 获取用户排队位置
     * @param int $uid
     * @param int $merId
     * @return int 0表示不在队列中
     */
    public static function getUserPosition(int $uid, int $merId): int
    {
        $rank = Cache::zRank("im:service_queue:{$merId}", $uid);
        if ($rank === false) return 0;
        return (int)$rank + 1;
    }

    /**
     * 检查客服是否有空位
     * @param int $merId
     * @return bool
     */
    public static function hasAvailableService(int $merId): bool
    {
        $repo = app()->make(StoreServiceRepository::class);
        $onlineCount = $repo->search([
            'mer_id' => $merId,
            'status' => 1,
            'is_open' => 1,
            'is_del' => 0,
        ])->count();

        return $onlineCount > 0;
    }
}
