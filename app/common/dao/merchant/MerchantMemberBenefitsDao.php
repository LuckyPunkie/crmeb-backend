<?php

namespace app\common\dao\merchant;

use app\common\dao\BaseDao;
use app\common\model\merchant\MerchantMemberBenefits;

class MerchantMemberBenefitsDao extends BaseDao
{
    public function search(array $where = [])
    {
        return MerchantMemberBenefits::getDB()->where($where);
    }

    protected function getModel(): string
    {
        return MerchantMemberBenefits::class;
    }
}
