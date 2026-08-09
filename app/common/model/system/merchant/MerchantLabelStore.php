<?php

namespace app\common\model\system\merchant;

use app\common\model\BaseModel;

class MerchantLabelStore extends BaseModel
{
    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'merchant_label_store';
    }
}
