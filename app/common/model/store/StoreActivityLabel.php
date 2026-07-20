<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace app\common\model\store;

use app\common\model\BaseModel;

class StoreActivityLabel extends BaseModel
{
    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'store_activity_label';
    }

    public function cate()
    {
        return $this->hasOne(StoreActivityCate::class, 'id', 'label_cate');
    }


    public function searchIdAttr($query, $value)
    {
        $query->where('id', $value);
    }

    public function searchLabelNameAttr($query, $value)
    {
        $query->whereLike('label_name', "%{$value}%");
    }

    public function searchLabelCateAttr($query, $value)
    {
        $query->whereIn('label_cate', $value);
    }

    public function searchStatusAttr($query, $value)
    {
        $query->where('status', $value);
    }

    public function searchIsShowAttr($query, $value)
    {
        $query->where('is_show', $value);
    }

}
