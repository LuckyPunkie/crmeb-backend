<?php

namespace app\common\dao\animal_rescue;

use app\common\dao\BaseDao;
use app\common\model\animal_rescue\PostFundAudit;

class PostFundAuditDao extends BaseDao
{
    protected function getModel(): string
    {
        return PostFundAudit::class;
    }

    public function search(array $where)
    {
        return PostFundAudit::getDB()
            ->when(isset($where['status']) && $where['status'] !== '', function ($q) use ($where) {
                $q->where('status', $where['status']);
            })
            ->when(isset($where['post_id']) && $where['post_id'] !== '', function ($q) use ($where) {
                $q->where('post_id', $where['post_id']);
            })
            ->order('audit_id DESC');
    }
}
