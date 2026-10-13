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
use app\common\model\store\product\ProductGroupSku;

class ProductGroupSkuDao extends BaseDao
{
    public function getModel(): string
    {
        return ProductGroupSku::class;
    }

    /**
     * 清除指定产品组的数据
     *
     * 本函数用于根据给定的产品组ID，从数据库中删除相关的产品组数据。
     * 这是数据维护的一部分，可以帮助管理产品组的信息，避免过时或不必要的数据堆积。
     *
     * @param int $id 产品组的唯一标识符
     * @return int 返回删除的记录数
     */
    public function clear($id)
    {
        // 通过模型获取数据库实例，并使用where子句指定删除条件，然后执行删除操作
        return $this->getModel()::getDB()->where('product_group_id', $id)->delete();
    }


    /**
     * 增加商品组SKU的库存
     *
     * 该方法用于指定商品组中的某个唯一SKU增加库存。通过传入商品组ID和唯一标识符来定位特定的SKU，
     * 然后将库存数量增加指定的值。这种方法适用于需要精确控制商品库存的场景，例如在订单退款或商品回收时。
     *
     * @param int $product_group_id 商品组ID，用于定位商品组。
     * @param string $unique SKU的唯一标识符，用于在商品组中定位特定的SKU。
     * @param int $inc 库存需要增加的数量。这个值可以是正数，表示增加库存；也可以是负数，表示减少库存（虽然不符合常规的库存操作逻辑）。
     * @return bool 更新操作的结果。如果成功更新库存，则返回true；否则返回false。
     */
    public function incStock($product_group_id, $unique, $inc)
    {
        // 使用where子句定位到特定的SKU行，然后通过inc方法增加stock列的值，并通过update方法保存更改。
        return ProductGroupSku::getDB()->where('product_group_id', $product_group_id)->where('unique', $unique)->inc('stock', $inc)->update();
    }

    /**
     * 减少商品组SKU的库存
     *
     * 此方法用于更新指定商品组ID和唯一标识的SKU的库存数量。
     * 它通过查询数据库，找到对应的SKU记录，然后减少库存量，并执行更新操作。
     *
     * @param int $product_group_id 商品组ID，用于定位特定商品组。
     * @param string $unique SKU的唯一标识，用于唯一确定一个SKU。
     * @param int $inc 库存量减少的值，可以是正整数，表示库存减少的数量。
     *
     * @return int 返回更新操作影响的行数，用于确认更新是否成功。
     */
    public function descStock($product_group_id, $unique, $inc)
    {
        // 通过ProductGroupSku的数据库访问对象，构造更新语句，减少库存并执行更新操作。
        return ProductGroupSku::getDB()->where('product_group_id', $product_group_id)->where('unique', $unique)->dec('stock', $inc)->update();
    }


}
