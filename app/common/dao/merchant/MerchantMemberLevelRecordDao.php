<?php

namespace app\common\dao\merchant;

use app\common\dao\BaseDao;
use app\common\model\merchant\MerchantMemberLevelRecord;

class MerchantMemberLevelRecordDao extends BaseDao
{
    public function search(array $where = [])
    {
        return MerchantMemberLevelRecord::getDB()->where($where);
    }

    protected function getModel(): string
    {
        return MerchantMemberLevelRecord::class;
    }
}
