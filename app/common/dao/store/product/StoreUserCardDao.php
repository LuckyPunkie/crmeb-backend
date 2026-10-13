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
use app\common\model\store\product\StoreUserCard;

class StoreUserCardDao extends BaseDao
{

    protected function getModel(): string
    {
        return StoreUserCard::class;
    }

    /**
     * 构建用户次卡/年卡查询。
     *
     * 用户卡是支付后发放给用户的权益快照，常按用户、商户、订单、订单商品、
     * 卡号、核销码和状态查询。DAO 只负责拼接基础条件，具体的排序、分页、
     * 关联加载和业务状态判断放在 Repository 层处理。
     *
     * @param array $where 查询条件。
     */
    public function search(array $where)
    {
        return StoreUserCard::getDB()
            ->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
                $query->where('uid', $where['uid']);
            })
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('mer_id', $where['mer_id']);
            })
            ->when(isset($where['order_id']) && $where['order_id'] !== '', function ($query) use ($where) {
                $query->where('order_id', $where['order_id']);
            })
            ->when(isset($where['order_product_id']) && $where['order_product_id'] !== '', function ($query) use ($where) {
                $query->where('order_product_id', $where['order_product_id']);
            })
            ->when(isset($where['verify_code']) && $where['verify_code'] !== '', function ($query) use ($where) {
                $query->where('verify_code', $where['verify_code']);
            })
            ->when(isset($where['card_no']) && $where['card_no'] !== '', function ($query) use ($where) {
                $query->where('card_no', $where['card_no']);
            })
            ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                is_array($where['status'])
                    ? $query->whereIn('status', $where['status'])
                    : $query->where('status', $where['status']);
            });
    }

    /**
     * 按核销码锁定用户卡。
     *
     * 次卡/年卡核销会扣减剩余次数并写入核销流水，必须在事务内通过
     * for update 锁住同一张卡，避免两个核销请求同时读到相同 remaining_count。
     * 商户 ID 必须一起参与条件，防止跨商户使用核销码。
     *
     * @param string $verifyCode 用户卡核销码。
     * @param int $merId 商户 ID。
     */
    public function lockByVerifyCode(string $verifyCode, int $merId)
    {
        return StoreUserCard::getDB()
            ->where('verify_code', $verifyCode)
            ->where('mer_id', $merId)
            ->lock(true)
            ->find();
    }
}
