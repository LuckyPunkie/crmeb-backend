<?php

namespace app\common\dao\system;

use app\common\dao\BaseDao;
use app\common\model\system\UploadMedia;

class UploadMediaDao extends BaseDao
{
    protected function getModel(): string
    {
        return UploadMedia::class;
    }

    public function getByKey(string $objectKey)
    {
        return UploadMedia::getDB()->where('object_key', $objectKey)->find();
    }

    public function getByTaskId(string $taskId)
    {
        return UploadMedia::getDB()->where('imm_task_id', $taskId)->find();
    }

    /** @return array object_key => play_key */
    public function playKeys(array $objectKeys): array
    {
        if (!$objectKeys) {
            return [];
        }
        return UploadMedia::getDB()->whereIn('object_key', $objectKeys)->column('play_key', 'object_key');
    }
}
