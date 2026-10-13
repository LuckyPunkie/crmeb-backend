<?php

namespace app\common\dao\merchant;

use app\common\dao\BaseDao;
use app\common\model\merchant\ShoppingCreditsMerchantMoneyRecord;

class ShoppingCreditsMerchantMoneyRecordDao extends BaseDao
{
    public function search(array $where = [])
    {
        return ShoppingCreditsMerchantMoneyRecord::getDB()->where($where);
    }

    protected function getModel(): string
    {
        return ShoppingCreditsMerchantMoneyRecord::class;
    }
}
