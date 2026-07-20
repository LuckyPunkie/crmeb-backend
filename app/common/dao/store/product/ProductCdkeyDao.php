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
use app\common\model\store\product\ProductCdkey as model;

class ProductCdkeyDao extends BaseDao
{
    protected function getModel(): string
    {
        return model::class;
    }

    /**
     * 根据条件搜索数据。
     *
     * 该方法通过动态构建查询条件来搜索数据库。它支持多个条件参数，
     * 并且只有在参数存在时才会添加相应的查询条件。这提高了查询的灵活性，
     * 允许根据不同的需求构建不同的查询。
     *
     * @param array $where 查询条件，包含多个可能的字段及其值。
     * @return \yii\db\Query 查询对象，可用于进一步的查询操作或获取结果。
     */
    public function search($where)
    {
        // 获取数据库连接对象
        $query = $this->getModel()::getDb();

        // 动态添加查询条件基于$where数组中的键值对
        $query
            ->when(isset($where['cdkey_id']) && $where['cdkey_id'] !== '', function ($query) use ($where) {
                // 如果'cdkey_id'存在，添加到查询条件中
                $query->where('cdkey_id', $where['cdkey_id']);
            })
            ->when(isset($where['cdkey_ids']) && $where['cdkey_ids'] !== '', function ($query) use ($where) {
                // 如果'cdkey_id'存在，添加到查询条件中
                $query->whereIn('cdkey_id', $where['cdkey_ids']);
            })
            ->when(isset($where['library_id']) && $where['library_id'] !== '', function ($query) use ($where) {
                // 如果'library_id'存在，添加到查询条件中
                $query->where('library_id', $where['library_id']);
            })
            ->when(isset($where['product_id']) && $where['product_id'] !== '', function ($query) use ($where) {
                // 如果'product_id'存在，添加到查询条件中
                $query->where('product_id', $where['product_id']);
            })
            ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                // 如果'status'存在，添加到查询条件中
                $query->where('status', $where['status']);
            })
            ->when(isset($where['is_type']) && $where['is_type'] !== '', function ($query) use ($where) {
                // 如果'is_type'存在，添加到查询条件中
                $query->where('is_type', $where['is_type']);
            })
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                // 如果'mer_id'存在，添加到查询条件中
                $query->where('mer_id', $where['mer_id']);
            })
            ->when(isset($where['keys']) && $where['keys'] !== '', function ($query) use ($where) {
                // 如果'keys'存在，使用其中的值作为查询条件
                $query->whereIn('key', $where['keys']);
            });

        // 返回构建好的查询对象
        return $query;
    }


    /**
     * 清除指定产品的属性
     *
     * 本函数用于删除数据库中与指定产品ID相关联的所有属性记录。
     * 通过调用关联模型的where方法来筛选出特定产品ID的属性记录，然后调用delete方法进行删除。
     *
     * @param int $productId 产品的ID，用于指定要清除属性的产品。
     * @return int 返回受影响的行数，即被删除的属性记录数。
     */
    public function clearAttr(int $productId)
    {
        // 使用动态模型删除与指定产品ID相关的所有属性记录
        return ($this->getModel())::where('product_id',$productId)->where('is_type',0)->delete();
    }
}
