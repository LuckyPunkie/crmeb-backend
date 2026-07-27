<?php

namespace app\common\model\community;

use app\common\model\BaseModel;

/**
 * 笔记-话题关联
 */
class CommunityTopicRel extends BaseModel
{
    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'community_topic_rel';
    }

    public function topic()
    {
        return $this->hasOne(CommunityTopic::class, 'topic_id', 'topic_id');
    }

    public function searchCommunityIdAttr($query, $value)
    {
        $query->where('community_id', $value);
    }

    public function searchTopicIdAttr($query, $value)
    {
        $query->where('topic_id', $value);
    }
}
