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
use app\common\model\store\product\CdkeyLibrary;
use think\facade\Db;

/**
 * Class StoreServiceDao
 * @package app\common\dao\store\service
 * @author xaboy
 * 卡密库
 */
class CdkeyLibraryDao extends BaseDao
{

    /**
     * @return string
     * @author xaboy
     * @day 2020/5/29
     */
    protected function getModel(): string
    {
        return CdkeyLibrary::class;
    }

    /**
     * 根据条件搜索数据。
     *
     * 本函数用于根据提供的条件数组搜索数据库中的记录。它支持多个条件，
     * 包括mer_id（商户ID）、product_id（产品ID）和product_attr_value_id（产品属性值ID）。
     * 条件是可选的，只有在数组中存在且不为空时才会被应用。
     *
     * @param array $where 包含搜索条件的数组。
     * @param int $is_del 删除状态标志，默认为0表示未删除。
     * @return \Illuminate\Database\Query\Builder|static 返回查询构建器实例或静态调用的结果。
     */
    public function search(array $where, $is_del = 0)
    {
        // 获取数据库实例
        return $this->getModel()::getDB()->alias('CdkeyLibrary')
            // 当'mer_id'字段存在且不为空时，添加where条件
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('CdkeyLibrary.mer_id', $where['mer_id']);
            })
            // 当'product_id'字段存在且不为空时，添加where条件
            ->when(isset($where['product_id']) && $where['product_id'] !== '', function ($query) use ($where) {
                $query->where('product_id', $where['product_id']);
            })
            // 当'product_attr_value_id'字段存在且不为空时，添加where条件
            ->when(isset($where['product_attr_value_id']) && $where['product_attr_value_id'] !== '', function ($query) use ($where) {
                $query->where('product_attr_value_id', $where['product_attr_value_id']);
            })
            ->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->where('name', 'like', '%' . $where['keyword'] . '%');
            })
            ->when(isset($where['name']) && $where['name'] !== '', function ($query) use ($where) {
                $query->where('name', 'like', '%' . $where['name'] . '%');
            })
            ->when(isset($where['productName']) && $where['productName'] !== '', function ($query) use ($where) {
                $query->hasWhere('product', function ($query) use ($where) {
                    $query->where('store_name', 'like', '%' . $where['productName'] . '%');
                });
            })
            // 指定删除状态条件
            ->where('is_del', $is_del);
    }


    /**
     *  统计总 cdkey 数量
     * @param $id
     * @param $num
     * @return void
     * @author Qinii
     */
    public function incTotalNum($id, $num = 1)
    {
        $this->getModel()::getDB()->where('id', $id)->update([
            'total_num' => Db::raw('total_num+' . $num),
        ]);
    }

    /**
     * 减少指定ID的商品或资源的总数
     *
     * 此方法用于更新数据库中特定ID记录的总数量字段，将其减少指定的数量。
     * 主要应用于商品库存管理、资源下载计数等领域，通过减少总量来反映消耗或使用情况。
     *
     * @param int $id 需要更新数量的记录的ID
     * @param int $num 需要减少的数量，默认为1，表示减少一个单位
     */
    public function decTotalNum($id, $num = 1)
    {
        // 使用模型的数据库操作方法，根据ID更新记录的total_num字段
        // 通过Db::raw直接执行SQL表达式，实现数量的减少
        $this->getModel()::getDb()->where('id', $id)->update([
            'total_num' => Db::raw('total_num-' . $num),
        ]);
    }


    public function incUsedNum($id, $num = 1)
    {
        $this->getModel()::getDb()->where('id', $id)->update([
            'used_num' => Db::raw('used_num+' . $num),
        ]);
    }


}
