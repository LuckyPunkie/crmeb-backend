<?php

namespace app\common\model\major_customer;

use app\common\model\BaseModel;

class MajorCustomerPlan extends BaseModel
{
    public static function tablePk(): string
    {
        return 'plan_id';
    }

    public static function tableName(): string
    {
        return 'major_customer_plan';
    }
}
