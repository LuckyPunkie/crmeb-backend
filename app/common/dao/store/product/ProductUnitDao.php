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
use app\common\model\store\product\ProductUnit;

class ProductUnitDao extends BaseDao
{

    /**
     * @return ProductUnit
     *
     * @date 2023/10/16
     * @author yyw
     */
    protected function getModel(): string
    {
        return ProductUnit::class;
    }

    /**
     * 搜索
     * @param array $where
     * @return \think\db\BaseQuery
     *
     * @date 2023/10/16
     * @author yyw
     */
    public function search(array $where = [])
    {
        return $this->getModel()::getDB()
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('mer_id', $where['mer_id']);
            })
            ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                $query->where('status', $where['status']);
            })
            ->when(isset($where['is_del']) && $where['is_del'] !== '', function ($query) use ($where) {
                $query->where('is_del', $where['is_del']);
            })
            ->when(isset($where['value']) && $where['value'] !== '', function ($query) use ($where) {
                $query->whereLike('value', "%{$where['value']}%");
            })
            ->when(isset($where['product_unit_id']) && $where['product_unit_id'] !== '', function ($query) use ($where) {
                $query->whereLike($this->getPk(), $where['product_unit_id']);
            })
            ->order(['sort' => 'DESC', 'create_time' => 'DESC']);
    }

}
