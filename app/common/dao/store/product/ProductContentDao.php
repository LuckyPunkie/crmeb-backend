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
use app\common\model\store\product\ProductContent as model;

class ProductContentDao extends BaseDao
{
    protected function getModel(): string
    {
        return model::class;
    }

    /**
     * 清除指定产品的属性
     *
     * 此方法用于根据产品ID和可选的类型参数，从数据库中删除相应的属性记录。
     * 如果提供了类型参数，则只会删除与该类型匹配的属性记录。
     *
     * @param int $productId 产品的ID，用于确定要清除属性的产品。
     * @param int|null $type 属性的类型ID，可选参数。如果指定了类型，则只会删除指定类型的属性。
     * @return int 返回影响的行数，即被删除的属性记录数。
     */
    public function clearAttr(int $productId, ?int $type)
    {
        // 初始化查询，根据产品ID查找属性记录
        $query = ($this->getModel())::where('product_id', $productId);

        // 如果提供了类型参数，则进一步限制查询条件为指定的类型
        if (!is_null($type)) $query->where('type', $type);

        // 执行删除操作并返回删除的记录数
        return $query->delete();
    }


}
