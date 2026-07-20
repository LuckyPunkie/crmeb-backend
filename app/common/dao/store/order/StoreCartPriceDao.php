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


namespace app\common\dao\store\order;


use app\common\dao\BaseDao;
use app\common\model\store\order\StoreCartPrice;
use think\model\Relation;

class StoreCartPriceDao extends BaseDao
{
    protected function getModel(): string
    {
        return StoreCartPrice::class;
    }

    public function getCartPriceInfo(int $cartId)
    {
        return $this->getWhere(['cart_id' => $cartId]);
    }

    public function add(int $cartId, array $data, $isBatch = false)
    {
        $cartPriceData = $isBatch ? $data : $this->getCartPriceData($cartId, $data);
        return $this->create($cartPriceData);
    }

    public function edit(int $id, int $cartId, array $data, $isBatch = false)
    {
        $cartPriceData = $isBatch ? $data : $this->getCartPriceData($cartId, $data);
        return $this->update($id, $cartPriceData);
    }

    private function getCartPriceData(int $cartId, array $data) : array
    {
        $newPrice = $data['new_price'] ?? 0;
        if($data['type'] == 1){ // 立减
            $newPrice = bcsub($data['old_price'], $data['reduce_price'], 2);
        }
        if($data['type'] == 2){ // 折扣
            $newPrice = bcmul($data['old_price'], $data['discount_rate']/100, 2);
        }

        $storeCartPriceData['cart_id'] = $cartId;
        $storeCartPriceData['old_price'] = $data['old_price'];
        $storeCartPriceData['type'] = $data['type'];
        $storeCartPriceData['reduce_price'] = $data['reduce_price'] ?? 0;
        $storeCartPriceData['discount_rate'] = $data['discount_rate'] ?? 0;
        $storeCartPriceData['new_price'] = $newPrice;
        $storeCartPriceData['is_batch'] = 0;
        $storeCartPriceData['update_time'] = date('Y-m-d H:i:s', time());

        return $storeCartPriceData;
    }
}
