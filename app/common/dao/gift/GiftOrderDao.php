<?php

namespace app\common\dao\gift;

use app\common\dao\BaseDao;
use app\common\model\gift\GiftOrder;

class GiftOrderDao extends BaseDao
{
    protected function getModel(): string
    {
        return GiftOrder::class;
    }

    public function search(array $where)
    {
        return GiftOrder::getDB()
            ->when(isset($where['order_no']) && $where['order_no'] !== '', function ($query) use ($where) {
                $query->where('order_no', $where['order_no']);
            })
            ->when(isset($where['sender_id']) && $where['sender_id'] !== '', function ($query) use ($where) {
                $query->where('sender_id', $where['sender_id']);
            })
            ->when(isset($where['receiver_id']) && $where['receiver_id'] !== '', function ($query) use ($where) {
                $query->where('receiver_id', $where['receiver_id']);
            })
            ->when(isset($where['pay_status']) && $where['pay_status'] !== '' && $where['pay_status'] !== null, function ($query) use ($where) {
                $query->where('pay_status', (int)$where['pay_status']);
            })
            ->when(isset($where['scene_type']) && $where['scene_type'] !== '', function ($query) use ($where) {
                $query->where('scene_type', $where['scene_type']);
            })
            ->when(isset($where['gift_id']) && $where['gift_id'] !== '', function ($query) use ($where) {
                $query->where('gift_id', (int)$where['gift_id']);
            });
    }

    public function createOrderNo(int $uid): string
    {
        return 'GF' . date('YmdHis') . $uid . rand(100, 999);
    }
}
