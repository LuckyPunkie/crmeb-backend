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
use app\common\model\store\product\ProductAssistSku;
use think\facade\Db;

class ProductAssistSkuDao extends BaseDao
{
    protected function getModel(): string
    {
        return ProductAssistSku::class;
    }

    /**
     * 清除关联数据
     *
     * 本函数用于根据指定的ID删除数据库中与产品辅助表相关的记录。
     * 它不返回任何值，而是通过调用数据库操作来直接影响数据库。
     *
     * @param int $id 需要删除的数据的ID。这个ID用于在数据库查询中定位特定的记录。
     */
    public function clear($id)
    {
        // 通过模型获取数据库实例，并使用where子句指定product_assist_id为$id，然后执行删除操作。
        $this->getModel()::getDB()->where('product_assist_id',$id)->delete();
    }


    /**
     * 减少商品辅助项的库存
     *
     * 本函数用于更新数据库中特定商品辅助项的库存数量。
     * 它通过传入的商品辅助项ID、唯一标识和减少的数量来定位特定的库存记录，
     * 并将库存数量减少指定的数值。
     *
     * @param int $product_assist_id 商品辅助项ID，用于定位特定的商品辅助项。
     * @param string $unique 唯一标识，与商品辅助项ID配合使用，确保更新操作的准确性。
     * @param int $desc 库存减少的数量，一个正整数，表示库存将减少的量。
     * @return bool 更新操作的结果，成功返回true，失败返回false。
     */
    public function descStock(int $product_assist_id, string $unique, int $desc)
    {
        // 使用模型的数据库操作方法，通过指定的条件更新库存字段
        // 使用Db::raw处理库存的数学运算，确保库存是减去指定的数量
        return $this->getModel()::getDB()->where('product_assist_id', $product_assist_id)->where('unique', $unique)->update([
            'stock' => Db::raw('stock-' . $desc)
        ]);
    }


    /**
     * 增加辅助商品库存
     *
     * 本函数用于更新指定辅助商品的库存数量。通过传入辅助商品ID和唯一标识符，
     * 以及要增加的库存数量，来实现库存的动态调整。此功能特别适用于需要对商品
     * 库存进行精确控制的场景，例如库存管理系统的后台操作。
     *
     * @param int $product_assist_id 辅助商品的唯一标识ID，用于定位特定的商品。
     * @param string $unique 商品的唯一标识字符串，用于进一步确保操作的准确性。
     * @param int $desc 要增加的库存数量，以整数形式表示。
     * @return bool 更新操作的结果，成功返回true，失败返回false。
     */
    public function incStock(int $product_assist_id, string $unique, int $desc)
    {
        // 使用模型获取数据库实例，并通过where子句指定更新条件，然后更新库存字段
        return $this->getModel()::getDB()->where('product_assist_id', $product_assist_id)->where('unique', $unique)->update([
            'stock' => Db::raw('stock+' . $desc) // 直接对库存字段进行数学运算，以增加库存
        ]);
    }

}

