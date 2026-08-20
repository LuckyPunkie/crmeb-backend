<?php

namespace app\common\dao\store\equity;

use app\common\dao\BaseDao;
use app\common\model\store\equity\EquityInvestOrder;

class EquityInvestOrderDao extends BaseDao
{
    protected function getModel(): string
    {
        return EquityInvestOrder::class;
    }

    public function makeOrderSn(): string
    {
        return 'EQ' . date('YmdHis') . substr(implode('', array_map(function () {
            return mt_rand(0, 9);
        }, range(1, 6))), 0, 6);
    }
}
