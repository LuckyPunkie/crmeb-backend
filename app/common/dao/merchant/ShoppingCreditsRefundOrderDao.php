<?php

namespace app\common\dao\merchant;

use app\common\dao\BaseDao;
use app\common\model\merchant\ShoppingCreditsRefundOrder;

class ShoppingCreditsRefundOrderDao extends BaseDao
{
    public function search(array $where = [])
    {
        return ShoppingCreditsRefundOrder::getDB()->where($where);
    }

    protected function getModel(): string
    {
        return ShoppingCreditsRefundOrder::class;
    }
}
