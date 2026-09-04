<?php

namespace app\common\model\gift;

use app\common\model\BaseModel;

class Gift extends BaseModel
{
    public static function tablePk(): string
    {
        return 'gift_id';
    }

    public static function tableName(): string
    {
        return 'gift';
    }

    public function searchGiftNameAttr($query, $value)
    {
        if ($value !== '') {
            $query->whereLike('gift_name', '%' . $value . '%');
        }
    }

    public function searchStatusAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', (int)$value);
        }
    }
}
