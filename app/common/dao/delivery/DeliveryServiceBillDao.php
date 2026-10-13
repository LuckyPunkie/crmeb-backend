<?php

namespace app\common\dao\delivery;

use app\common\dao\BaseDao;
use app\common\model\delivery\DeliveryServiceBill;

class DeliveryServiceBillDao extends BaseDao
{
    protected function getModel(): string
    {
        return DeliveryServiceBill::class;
    }
}
