<?php

namespace app\common\model\taoke;

use app\common\model\BaseModel;

class ServiceBrandTab extends BaseModel
{
    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'service_brand_tab';
    }
}
