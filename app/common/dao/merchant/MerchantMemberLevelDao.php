<?php

namespace app\common\dao\merchant;

use app\common\dao\BaseDao;
use app\common\model\merchant\MerchantMemberLevel;

class MerchantMemberLevelDao extends BaseDao
{
    public function search(array $where = [])
    {
        return MerchantMemberLevel::getDB()->where($where);
    }

    protected function getModel(): string
    {
        return MerchantMemberLevel::class;
    }
}
