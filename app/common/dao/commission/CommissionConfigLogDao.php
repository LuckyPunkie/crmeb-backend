<?php

namespace app\common\dao\commission;

use app\common\dao\BaseDao;
use app\common\model\commission\CommissionConfigLog;

class CommissionConfigLogDao extends BaseDao
{
    protected function getModel(): string
    {
        return CommissionConfigLog::class;
    }

    public function addLog(array $data): void
    {
        CommissionConfigLog::create(array_merge($data, [
            'create_time' => date('Y-m-d H:i:s'),
        ]));
    }

    public function getRecentLogs(int $limit = 30): array
    {
        return CommissionConfigLog::getDB()
            ->order('create_time', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }
}
