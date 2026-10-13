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
use app\common\model\store\product\ProductReservation as model;

class ProductReservationDao extends BaseDao
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
     * @return int 返回影响的行数，即被删除的属性记录数。
     */
    public function clear(int $productId)
    {
        // 初始化查询，根据产品ID查找属性记录
        return ($this->getModel())::where('product_id', $productId)->delete();
    }

    public function search(array $where)
    {
        $query = $this->getModel()::getDB()
            ->when(isset($where['product_id']) && $where['product_id'] !== '', function($query) use($where){
                $query->where('product_id', $where['product_id']);
            })
        ;
        $query->order('product_reservation_id ASC');
        return $query;
    }
}
