<?php

namespace app\common\dao\community;

use app\common\dao\BaseDao;
use app\common\model\community\CommunityTopicRel;

class CommunityTopicRelDao extends BaseDao
{
    protected function getModel(): string
    {
        return CommunityTopicRel::class;
    }

    /**
     * 同步笔记话题关联（全量替换）
     * @param int $communityId
     * @param array $topicIds
     */
    public function sync(int $communityId, array $topicIds): void
    {
        $this->getModel()::getDB()->where('community_id', $communityId)->delete();
        $topicIds = array_values(array_unique(array_filter(array_map('intval', $topicIds))));
        if (!$topicIds) {
            return;
        }
        $rows = [];
        $now = date('Y-m-d H:i:s');
        foreach ($topicIds as $i => $topicId) {
            $rows[] = [
                'community_id' => $communityId,
                'topic_id' => $topicId,
                'sort' => $i,
                'create_time' => $now,
            ];
        }
        $this->getModel()::getDB()->insertAll($rows);
    }

    /**
     * 按话题取笔记 ID 列表
     */
    public function communityIdsByTopicIds(array $topicIds): array
    {
        $topicIds = array_values(array_unique(array_filter(array_map('intval', $topicIds))));
        if (!$topicIds) {
            return [];
        }
        return $this->getModel()::getDB()->whereIn('topic_id', $topicIds)->column('community_id') ?: [];
    }
}
