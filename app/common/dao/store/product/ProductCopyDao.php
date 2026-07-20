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
use app\common\model\store\product\ProductCopy as model;

class ProductCopyDao extends BaseDao
{
    protected function getModel(): string
    {
        return model::class;
    }

    /**
     * 根据条件搜索数据。
     *
     * 本函数用于根据提供的条件数组搜索相关数据。它支持两个主要的搜索条件：'mer_id' 和 'type'。
     * 'mer_id' 用于指定商家ID，'type' 用于指定数据类型。其中，'type' 条件支持 'copy' 类型的数据，
     * 这种情况下，搜索将包括 'taobao'、'jd' 和 'copy' 类型的数据。
     *
     * @param array $where 包含搜索条件的数组。可能包含 'mer_id' 和 'type' 键。
     * @return \Illuminate\Database\Eloquent\Builder|static 返回数据库查询构建器实例，用于进一步的查询操作或数据获取。
     */
    public function search(array $where)
    {
        // 获取模型对应的数据库实例。
        return $this->getModel()::getDB()
            // 当 'mer_id' 条件存在且不为空时，添加 'mer_id' 的查询条件。
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('mer_id', $where['mer_id']);
            })
            // 当 'type' 条件存在且不为空时，根据 'type' 的值添加相应的查询条件。
            ->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
                if ($where['type'] == 'copy') {
                    // 如果 'type' 为 'copy'，则查询 'type' 为 'taobao'、'jd' 或 'copy' 的数据。
                    $query->where('type', 'in', ['taobao', 'jd', 'copy']);
                } else {
                    // 否则，直接查询指定 'type' 的数据。
                    $query->where('type', $where['type']);
                }
            })
            // 按 'create_time' 降序排序。
            ->order('create_time DESC');
    }

    /**
     * 获取产品复制信息
     *
     * 本方法通过查询数据库，获取store_product_copy_id在398到467之间的产品复制信息。
     * 主要用于特定条件下的产品数据检索，以便于进一步的操作或展示。
     *
     * @return array 返回符合条件的产品复制信息数组。
     */
    public function get2()
    {
        // 根据条件查询产品复制信息，限定store_product_copy_id在398到467之间，并只返回info字段
        return $data =  model::where('store_product_copy_id','>',398)
            ->where('store_product_copy_id','<',467)->field('info')->select();
    }
}
