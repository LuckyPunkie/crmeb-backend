<?php

namespace app\common\model\major_customer;

use app\common\model\BaseModel;

class MajorCustomerConfig extends BaseModel
{
    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'major_customer_config';
    }
}
