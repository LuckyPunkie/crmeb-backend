<?php

namespace app\common\dao\user;

use app\common\dao\BaseDao;
use app\common\model\user\UserFields;

class UserFieldsDao extends BaseDao
{
    protected function getModel(): string
    {
        return UserFields::class;
    }
}
