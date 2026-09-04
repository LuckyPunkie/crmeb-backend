<?php

namespace app\common\dao\gift;

use app\common\dao\BaseDao;
use app\common\model\gift\Gift;

class GiftDao extends BaseDao
{
    protected function getModel(): string
    {
        return Gift::class;
    }

    public function search(array $where)
    {
        return Gift::getDB()
            ->when(isset($where['gift_name']) && $where['gift_name'] !== '', function ($query) use ($where) {
                $query->whereLike('gift_name', '%' . $where['gift_name'] . '%');
            })
            ->when(isset($where['status']) && $where['status'] !== '' && $where['status'] !== null, function ($query) use ($where) {
                $query->where('status', (int)$where['status']);
            });
    }
}
