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

use app\common\dao\store\product\ProductSkuDao;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;
use think\facade\Db;

class ProductSkuRepository extends BaseRepository
{
    public function __construct(ProductSkuDao $dao)
    {
        $this->dao = $dao;
    }

    const ACTIVE_TYPE_DISCOUNTS = 10;

    /**
     * 保存活动产品SKU信息
     *
     * 该方法用于将活动产品的SKU数据存储到数据库中。它首先通过唯一标识符检索现有的SKU数据，
     * 然后根据活动ID、产品ID和活动产品ID将活动SKU的信息插入数据库。
     * 如果找不到对应的SKU数据，则抛出一个验证异常。
     *
     * @param int $id 活动ID
     * @param int $productId 产品ID
     * @param array $data SKU数据数组，每个元素包含唯一标识和活动价格
     * @param int $activeProductId 活动产品ID，默认为0
     * @throws ValidateException 如果找不到SKU数据，则抛出此异常
     */
    public function save(int $id, int $productId, array $data, $activeProductId = 0)
    {
        // 实例化产品属性值仓库
        $storeProductServices = app()->make(ProductAttrValueRepository::class);

        // 遍历数据数组，处理每个SKU的信息
        foreach ($data as $item) {
            // 根据唯一标识搜索SKU数据
            $skuData = $storeProductServices->search(['unique' => $item['unique']])->find();

            // 如果找不到SKU数据，则抛出异常
            if (!$skuData) throw new ValidateException('属性规格不存在');

            // 构建活动SKU的数据数组
            $activeSku[] = [
                'active_id'     => $id,
                'active_product_id' => $activeProductId,
                'product_id'    => $productId,
                'active_type'   => self::ACTIVE_TYPE_DISCOUNTS,
                'price'         => $skuData['price'],
                'active_price'  => $item['active_price'] ?? $skuData['price'],
                'unique'        => $item['unique'],
            ];
        }

        // 将所有活动SKU数据插入数据库
        $this->dao->insertAll($activeSku);
    }
}
