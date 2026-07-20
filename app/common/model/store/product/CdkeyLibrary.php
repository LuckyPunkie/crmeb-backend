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


namespace app\common\model\store\product;

use app\common\model\BaseModel;

/**
 * Class CdkeyLibrary
 * app\common\model\store\cdkey
 *  卡密库
 */
class CdkeyLibrary extends BaseModel
{

    /**
     * 主键
     */
    public static function tablePk(): string
    {
        return 'id';
    }

    /**
     * @return string
     * @author Qinii
     */
    public static function tableName(): string
    {
        return 'cdkey_library';
    }

    public function product()
    {
        return $this->hasOne(Product::class, 'product_id', 'product_id');
    }

    public function attrValue()
    {
        return $this->hasOne(ProductAttrValue::class, 'value_id', 'product_attr_value_id');
    }


    public function searchIdAttr($query, $value)
    {
        return $query->where('id', $value);
    }

    public function searchStatusAttr($query, $value)
    {
        return $query->where('status', $value);
    }
}
