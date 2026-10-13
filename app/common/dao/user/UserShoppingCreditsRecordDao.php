<?php

namespace app\common\dao\user;

use app\common\dao\BaseDao;
use app\common\model\user\UserShoppingCreditsRecord;

class UserShoppingCreditsRecordDao extends BaseDao
{
    public function search(array $where = [])
    {
        return UserShoppingCreditsRecord::getDB()->where($where);
    }

    protected function getModel(): string
    {
        return UserShoppingCreditsRecord::class;
    }
}
