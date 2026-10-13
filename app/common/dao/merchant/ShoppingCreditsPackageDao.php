<?php

namespace app\common\dao\merchant;

use app\common\dao\BaseDao;
use app\common\model\merchant\ShoppingCreditsPackage;

class ShoppingCreditsPackageDao extends BaseDao
{
    public function search(array $where = [])
    {
        return ShoppingCreditsPackage::getDB()->where($where);
    }

    protected function getModel(): string
    {
        return ShoppingCreditsPackage::class;
    }
}
