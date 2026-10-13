<?php

namespace app\common\dao\user;

use app\common\dao\BaseDao;
use app\common\model\user\UserShoppingCredits;

class UserShoppingCreditsDao extends BaseDao
{
    public function search(array $where = [])
    {
        return UserShoppingCredits::getDB()->where($where);
    }

    protected function getModel(): string
    {
        return UserShoppingCredits::class;
    }
}
