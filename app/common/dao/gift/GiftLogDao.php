<?php

namespace app\common\dao\gift;

use app\common\dao\BaseDao;
use app\common\model\gift\GiftLog;

class GiftLogDao extends BaseDao
{
    protected function getModel(): string
    {
        return GiftLog::class;
    }

    public function addLog(int $giftId, string $action, int $operatorId, $before = null, $after = null): void
    {
        $this->create([
            'gift_id' => $giftId,
            'action' => $action,
            'operator_id' => $operatorId,
            'before_data' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : '',
            'after_data' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : '',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
