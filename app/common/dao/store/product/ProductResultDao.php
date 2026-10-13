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
use app\common\model\store\product\ProductResult;

class ProductResultDao extends BaseDao
{
    protected function getModel(): string
    {
        return ProductResult::class;
    }

    public function search(array $where)
    {
        $query = $this->getModel()::getDB();
        $query->when(isset($where['product_id']) && $where['product_id'] !== '',function($query) use($where){
            $query->where('product_id', $where['product_id']);
        });
        $query->when(isset($where['type']) && $where['type'] !== '',function($query) use($where){
            $query->where('type', $where['type']);
        });
        $query->when(isset($where['id']) && $where['id'] !== '',function($query) use($where){
            $query->where('id', $where['id']);
        });
        return $query;
    }
}

