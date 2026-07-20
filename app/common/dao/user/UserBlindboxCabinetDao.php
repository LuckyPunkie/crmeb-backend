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

namespace app\common\dao\user;

use app\common\dao\BaseDao;
use app\common\model\user\UserBlindboxCabinet;
use app\common\model\BaseModel;

class UserBlindboxCabinetDao extends BaseDao
{

    protected function getModel(): string
    {
        return UserBlindboxCabinet::class;
    }

    /**
     * 搜索条件
     * @param array $where
     * @return BaseModel
     */
    public function search(array $where)
    {
        return UserBlindboxCabinet::getDB()->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
            $query->where('uid', $where['uid']);
        })->when(isset($where['product_id']) && $where['product_id'] !== '', function ($query) use ($where) {
            $query->where('product_id', $where['product_id']);
        })->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            $query->where('status', $where['status']);
        })->when(isset($where['day']), function ($query) use ($where) {
            getModelTime($query, $where, 'create_time');
        });
    }

    /**
     * 获取用户盒柜中某商品某SKU的已有记录（用于累加数量）
     * @param int $uid
     * @param int $productId
     * @param int $attrValueId
     * @return BaseModel|null
     */
    public function getByUserProductSku(int $uid, int $productId, int $attrValueId)
    {
        return UserBlindboxCabinet::getDB()
            ->where('uid', $uid)
            ->where('product_id', $productId)
            ->where('attr_value_id', $attrValueId)
            ->where('status', 1)
            ->find();
    }
}
