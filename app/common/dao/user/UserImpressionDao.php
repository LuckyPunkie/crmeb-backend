<?php

namespace app\common\dao\user;

use app\common\dao\BaseDao;
use app\common\model\user\UserImpression;

class UserImpressionDao extends BaseDao
{
    protected function getModel(): string
    {
        return UserImpression::class;
    }

    public function listByOwner(int $ownerUid, int $page, int $limit)
    {
        return UserImpression::where('owner_uid', $ownerUid)
            ->where('is_deleted', 0)
            ->order('create_time', 'desc')
            ->order('impression_id', 'desc')
            ->page($page, $limit)
            ->select();
    }

    public function countByOwner(int $ownerUid): int
    {
        return (int) UserImpression::where('owner_uid', $ownerUid)
            ->where('is_deleted', 0)
            ->count();
    }

    public function distinctFromCount(int $ownerUid): int
    {
        return (int) UserImpression::where('owner_uid', $ownerUid)
            ->where('is_deleted', 0)
            ->distinct(true)
            ->count('from_uid');
    }

    public function getRow(int $impressionId)
    {
        return UserImpression::where('impression_id', $impressionId)
            ->where('is_deleted', 0)
            ->find();
    }

    public function incCommentCount(int $impressionId, int $step = 1): void
    {
        UserImpression::where('impression_id', $impressionId)->inc('comment_count', $step)->update();
    }

    public function decCommentCount(int $impressionId, int $step = 1): void
    {
        UserImpression::where('impression_id', $impressionId)
            ->where('comment_count', '>=', $step)
            ->dec('comment_count', $step)->update();
    }

    public function updateLikeCount(int $impressionId, int $delta): void
    {
        if ($delta > 0) {
            UserImpression::where('impression_id', $impressionId)->inc('like_count', $delta)->update();
        } elseif ($delta < 0) {
            UserImpression::where('impression_id', $impressionId)
                ->where('like_count', '>=', abs($delta))
                ->dec('like_count', abs($delta))->update();
        }
    }
}
