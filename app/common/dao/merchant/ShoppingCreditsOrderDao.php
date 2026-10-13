<?php

namespace app\common\dao\merchant;

use app\common\dao\BaseDao;
use app\common\model\merchant\ShoppingCreditsOrder;

class ShoppingCreditsOrderDao extends BaseDao
{
    public function search(array $where = [])
    {
        return ShoppingCreditsOrder::getDB()->where($where);
    }

    protected function getModel(): string
    {
        return ShoppingCreditsOrder::class;
    }
}
