<?php

namespace app\common\dao\user;

use app\common\dao\BaseDao;
use app\common\model\user\UserImpressionComment;

class UserImpressionCommentDao extends BaseDao
{
    protected function getModel(): string
    {
        return UserImpressionComment::class;
    }

    public function getRow(int $commentId)
    {
        return UserImpressionComment::where('comment_id', $commentId)
            ->where('is_deleted', 0)
            ->find();
    }

    /**
     * 一级评论：按时间正序
     */
    public function topLevelList(int $impressionId, int $page, int $limit)
    {
        return UserImpressionComment::where('impression_id', $impressionId)
            ->where('parent_id', 0)
            ->where('is_deleted', 0)
            ->order('create_time', 'asc')
            ->order('comment_id', 'asc')
            ->page($page, $limit)
            ->select();
    }

    /**
     * 二级楼中楼：按时间正序（同一楼下所有 replies 归到该楼）
     */
    public function repliesOfParent(int $parentId, int $limit = 500)
    {
        return UserImpressionComment::where('parent_id', $parentId)
            ->where('is_deleted', 0)
            ->order('create_time', 'asc')
            ->order('comment_id', 'asc')
            ->limit($limit)
            ->select();
    }

    public function countTopLevel(int $impressionId): int
    {
        return (int) UserImpressionComment::where('impression_id', $impressionId)
            ->where('parent_id', 0)
            ->where('is_deleted', 0)
            ->count();
    }

    /**
     * 该印象下所有评论（含楼中楼）总数，用于印象.comment_count 校准
     */
    public function countTotal(int $impressionId): int
    {
        return (int) UserImpressionComment::where('impression_id', $impressionId)
            ->where('is_deleted', 0)
            ->count();
    }

    public function updateLikeCount(int $commentId, int $delta): void
    {
        if ($delta > 0) {
            UserImpressionComment::where('comment_id', $commentId)->inc('like_count', $delta)->update();
        } elseif ($delta < 0) {
            UserImpressionComment::where('comment_id', $commentId)
                ->where('like_count', '>=', abs($delta))
                ->dec('like_count', abs($delta))->update();
        }
    }

    /**
     * 软删自身 + 若为一级则连同其下所有二级
     */
    public function softDelete(int $commentId, int $parentId): int
    {
        $cnt = 0;
        $cnt += (int) UserImpressionComment::where('comment_id', $commentId)->update(['is_deleted' => 1]);
        if ($parentId == 0) {
            $cnt += (int) UserImpressionComment::where('parent_id', $commentId)
                ->where('is_deleted', 0)
                ->update(['is_deleted' => 1]);
        }
        return $cnt;
    }
}
