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

namespace app\common\repositories\store\product;

use think\facade\Db;
use think\exception\ValidateException;
use app\common\repositories\BaseRepository;
use app\common\dao\store\product\ProductAttrValueDao as dao;

/**
 * Class ProductAttrValueRepository
 * @package app\common\repositories\store\product
 * @mixin dao
 */
class ProductAttrValueRepository extends BaseRepository
{

    protected $dao;

    /**
     * ProductRepository constructor.
     * @param dao $dao
     */
    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 计算产品的最低价格
     *
     * 此方法通过调用DAO层，查询指定产品ID的最低价格。
     * 它用于获取某个产品系列中的最低价格，以便进行价格比较或展示。
     *
     * @param int $id 产品ID，用于指定要查询价格的产品。
     * @return int 返回查询到的最低价格。
     */
    public function priceCount(int $id)
    {
        // 调用DAO层方法，获取指定产品ID的最低价格
        return min($this->dao->getFieldColumnt('product_id',$id,'price'));
    }

    /**
     * 计算指定产品ID的库存总数
     *
     * 本函数通过调用DAO层方法，获取指定产品ID的库存总数量。
     * 主要用于库存管理模块，对单一产品ID的库存进行统计。
     *
     * @param int $id 产品ID，用于指定需要查询库存的产品。
     * @return int 返回指定产品ID的库存总数量。
     */
    public function stockCount(int $id)
    {
        // 调用DAO方法，获取指定产品ID的库存总和
        return  $this->dao->getFieldSum('product_id',$id,'stock');
    }

    /**
     * 检查指定商户是否存在指定的唯一标识。
     *
     * 本函数通过调用DAO层的方法来查询指定商户是否已存在特定的唯一标识值。
     * 这对于确保数据的唯一性和完整性非常关键，例如在注册新商户或更新商户信息时。
     *
     * @param int|null $merId 商户ID，如果为null，则表示检查全局唯一性。
     * @param string $value 要检查的唯一标识值。
     * @return bool 如果指定的唯一标识已存在，则返回true；否则返回false。
     */
    public function merUniqueExists(?int $merId, string $value)
    {
        // 调用DAO方法来查询指定商户是否存在指定的唯一标识值。
        return $this->dao->merFieldExists($merId, 'unique', $value);
    }

    /**
     * 根据唯一标识获取选项
     *
     * 本函数通过调用DAO层，查询具有特定唯一标识的记录。它不接受任何参数来指定表名，
     * 因为查询的表名在DAO层内部已经定义或通过其他方式确定。$unique 参数用于指定要查询的唯一标识。
     *
     * @param string $unique 唯一标识，用于查询特定记录。
     * @return array|false 返回符合查询条件的记录数组，如果未找到则返回false。
     */
    public function getOptionByUnique($unique, $product_id = 0)
    {
        // 调用DAO方法，查询具有指定唯一标识的记录
        if($product_id) {
            return  $this->dao->getFieldExists(null,'unique',$unique)->where('product_id', $product_id)->find();
        }
        return  $this->dao->getFieldExists(null,'unique',$unique)->find();
    }

    public function deleteReservation($productId)
    {
        return $this->dao->deleteReservation($productId);
    }

    public function batchSetReservationProductStock($productId, array $params)
    {
        $product = app()->make(ProductRepository::class)->getWhere(['product_id' => $productId, 'type' => ProductRepository::DEFINE_TYPE_RESERVATION]);
        if (!$product || $product['type'] != ProductRepository::DEFINE_TYPE_RESERVATION) {
            throw new ValidateException('商品不存在或非预约商品，请检查');
        }

        try {
            $result =  Db::transaction(function () use ($params, $product) {
                foreach ($params['stockValue'] as $item) {
                    $info = $this->dao->get($item['value_id']);
                    if (!$info) {
                        throw new ValidateException('规格不存在,请检查');
                    }
                    // 变更attr_value_reservation表的库存
                    app()->make(ProductAttrValueReservationRepository::class)->updateAttrReservations($item['reservation']);
                    // 变更attr_value表的库存
                    $info->update(['stock' => array_sum(array_column($item['reservation'], 'stock'))], ['value_id' => $item['value_id']]);
                }
                // 变更product表的库存
                $product->update(['stock' => $this->stockCount($info['product_id'])], ['product_id' => $info['product_id']]);

                return true;
            });
        } catch (\Exception $exception) {
            throw new ValidateException('规格设置失败: ' . $exception->getMessage());
        }

        return $result;
    }
}
