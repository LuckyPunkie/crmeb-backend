<?php

namespace app\common\dao\user;

use app\common\dao\BaseDao;
use app\common\model\user\UserImpressionLike;

class UserImpressionLikeDao extends BaseDao
{
    protected function getModel(): string
    {
        return UserImpressionLike::class;
    }

    public function has(int $uid, string $targetType, int $targetId): bool
    {
        return UserImpressionLike::where('uid', $uid)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->count() > 0;
    }

    public function add(int $uid, string $targetType, int $targetId): void
    {
        try {
            UserImpressionLike::create([
                'uid' => $uid,
                'target_type' => $targetType,
                'target_id' => $targetId,
            ]);
        } catch (\Throwable $e) {
            // 唯一索引冲突时安全忽略（并发点赞）
        }
    }

    public function remove(int $uid, string $targetType, int $targetId): int
    {
        return (int) UserImpressionLike::where('uid', $uid)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->delete();
    }

    /**
     * 批量判断 uid 是否点赞了某组 targetIds
     */
    public function likedIds(int $uid, string $targetType, array $targetIds): array
    {
        if (!$targetIds) return [];
        return UserImpressionLike::where('uid', $uid)
            ->where('target_type', $targetType)
            ->whereIn('target_id', $targetIds)
            ->column('target_id');
    }
}
