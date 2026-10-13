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

namespace app\common\dao\store\product;

use app\common\dao\BaseDao;
use app\common\model\store\product\StoreDiscounts;
use app\common\model\store\product\StoreDiscountProduct;
use app\common\repositories\store\product\ProductSkuRepository;
use think\facade\Db;

class StoreDiscountProductDao extends BaseDao
{
    protected function getModel(): string
    {
        return StoreDiscountProduct::class;
    }

    /**
     * 清除特定字段值对应的数据记录
     *
     * 本函数用于根据指定的字段值和该值对应的ID，从数据库中删除相应的记录。
     * 这是个通用函数，可以通过传入不同的字段名和ID值来删除不同表中的数据。
     *
     * @param mixed $id 需要删除的数据记录的ID值，可以是数字、字符串等
     * @param string $field 指定的字段名，用于查询和删除数据
     */
    public function clear($id,$field)
    {
        // 使用模型获取数据库实例，并构造删除语句，根据字段和ID删除数据
        $this->getModel()::getDB()->where($field, $id)->delete();
    }


}

