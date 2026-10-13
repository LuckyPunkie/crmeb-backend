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
use app\common\model\store\product\ProductSku;
use think\facade\Db;

class ProductSkuDao extends BaseDao
{
    protected function getModel(): string
    {
        return ProductSku::class;
    }

    /**
     * 清除指定活动ID和类型的相关数据
     *
     * 此方法通过指定的活动ID和类型，从数据库中删除相关记录。
     * 主要用于清理或撤销特定活动的影响，确保数据库中不再存在相关的活跃记录。
     *
     * @param int $id 活动ID，用于定位特定活动的记录。
     * @param int $type 活动类型，与活动ID配合使用，精确筛选需要删除的记录。
     */
    public function clear(int $id, int $type)
    {
        // 使用模型获取数据库实例，并构造删除条件，根据活动ID和类型删除相关记录
        $this->getModel()::getDB()->where('active_id', $id)->where('active_type', $type)->delete();
    }


    /**
     * 减少商品库存
     *
     * 该方法用于根据活动ID和唯一标识符减少商品的库存量。
     * 它通过查询数据库中匹配给定活动ID和唯一标识符的记录，然后更新这些记录的库存字段。
     * 更新库存的方式是将当前库存值减去传入的减少量。
     *
     * @param int $active_id 活动ID，用于定位特定活动的商品库存。
     * @param string $unique 唯一标识符，用于进一步唯一确定库存记录。
     * @param int $desc 库存减少的数量，这是一个正整数，表示要从当前库存中减去的数量。
     * @return bool 返回更新操作的结果，成功为true，失败为false。
     */
    public function descStock(int $active_id, string $unique, int $desc)
    {
        // 使用模型获取数据库实例，并通过where语句指定更新条件，然后更新库存字段
        return $this->getModel()::getDB()->where('active_id', $active_id)->where('unique', $unique)->update([
            'stock' => Db::raw('stock-' . $desc) // 使用数据库原生表达式减少库存
        ]);
    }


    /**
     * 增加库存函数
     * 该函数用于根据活动ID和唯一标识符增加指定商品的库存。
     * 主要用于在库存管理系统中，对特定商品的库存进行手动或自动的增加操作。
     *
     * @param int $active_id 活动ID，用于唯一标识某个商品或活动。
     * @param string $unique 唯一标识符，进一步确保操作的准确性，可以是商品的SKU或其他唯一码。
     * @param int $desc 库存增加的数量，是一个整数，可以为负数，但通常为正数。
     * @return bool 更新操作的结果，成功返回true，失败返回false。
     */
    public function incStock(int $active_id, string $unique, int $desc)
    {
        // 使用模型获取数据库实例，并通过where语句指定更新条件，然后更新库存字段
        return $this->getModel()::getDB()->where('active_id', $active_id)->where('unique', $unique)->update([
            'stock' => Db::raw('stock+' . $desc) // 直接对库存字段进行数学运算，以增加库存
        ]);
    }
}

