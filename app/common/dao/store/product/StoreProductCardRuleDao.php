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
use app\common\model\store\product\StoreProductCardRule;

class StoreProductCardRuleDao extends BaseDao
{

    protected function getModel(): string
    {
        return StoreProductCardRule::class;
    }

    /**
     * 构建商品次卡/年卡规则查询。
     *
     * 规则按商户、商品和 SKU 绑定。value_id 是真实规格主键，unique 用于兼容
     * 下单快照和部分前端回传场景；status/is_del 用来控制规则是否可继续售卖。
     * DAO 只拼接基础查询条件，具体匹配、更新和软删除策略放在 Repository 层。
     *
     * @param array $where 查询条件。
     */
    public function search(array $where)
    {
        return StoreProductCardRule::getDB()
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('mer_id', $where['mer_id']);
            })
            ->when(isset($where['product_id']) && $where['product_id'] !== '', function ($query) use ($where) {
                $query->where('product_id', $where['product_id']);
            })
            ->when(isset($where['value_id']) && $where['value_id'] !== '', function ($query) use ($where) {
                $query->where('value_id', $where['value_id']);
            })
            ->when(isset($where['unique']) && $where['unique'] !== '', function ($query) use ($where) {
                $query->where('unique', $where['unique']);
            })
            ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                $query->where('status', $where['status']);
            })
            ->when(isset($where['is_del']) && $where['is_del'] !== '', function ($query) use ($where) {
                $query->where('is_del', $where['is_del']);
            });
    }

    /**
     * 软删除商品下的次卡/年卡规则。
     *
     * 规则可能被历史订单、已发用户卡引用，因此不能物理删除。
     * 商品删除或重置规则时只标记 is_del，历史权益仍可通过用户卡快照继续展示/核销。
     *
     * @param int $productId 商品 ID。
     */
    public function clearByProduct(int $productId)
    {
        return StoreProductCardRule::getDB()
            ->where('product_id', $productId)
            ->update(['is_del' => StoreProductCardRule::IS_DEL_YES]);
    }
}
