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

namespace app\common\model\user;

use app\common\model\BaseModel;
use app\common\model\store\product\Product;
use app\common\model\store\product\ProductAttrValue;

class UserBlindboxRecycle extends BaseModel
{

    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'user_blindbox_recycle';
    }

    /**
     * 关联盒柜记录
     */
    public function cabinet()
    {
        return $this->hasOne(UserBlindboxCabinet::class, 'id', 'cabinet_id');
    }

    /**
     * 关联商品
     */
    public function product()
    {
        return $this->hasOne(Product::class, 'product_id', 'product_id');
    }

    /**
     * 关联SKU属性值
     */
    public function attrValue()
    {
        return $this->hasOne(ProductAttrValue::class, 'value_id', 'attr_value_id');
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->hasOne(User::class, 'uid', 'uid');
    }
}
