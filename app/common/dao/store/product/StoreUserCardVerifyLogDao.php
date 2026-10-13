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
use app\common\model\store\product\StoreUserCardVerifyLog;

class StoreUserCardVerifyLogDao extends BaseDao
{

    protected function getModel(): string
    {
        return StoreUserCardVerifyLog::class;
    }

    /**
     * 构建次卡/年卡核销记录查询。
     *
     * 核销日志通常按订单、用户卡、用户或商户维度查看；verify_type 可区分商户后台、
     * 服务人员和系统来源。DAO 只拼接条件，排序和分页留给 Repository/调用方决定。
     *
     * @param array $where 查询条件。
     */
    public function search(array $where)
    {
        return StoreUserCardVerifyLog::getDB()
            ->alias('L')
            ->join('store_user_card C', 'C.user_card_id = L.user_card_id', 'left')
            ->join('user U', 'U.uid = L.uid', 'left')
            ->join('merchant M', 'M.mer_id = L.mer_id', 'left')
            ->field('L.*,C.order_sn,C.product_name,U.nickname')
            ->when(isset($where['user_card_id']) && $where['user_card_id'] !== '', function ($query) use ($where) {
                $query->where('L.user_card_id', $where['user_card_id']);
            })
            ->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
                $query->where('L.uid', $where['uid']);
            })
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('L.mer_id', $where['mer_id']);
            })
            ->when(isset($where['is_trader']) && $where['is_trader'] !== '', function ($query) use ($where) {
                $query->where('M.is_trader', $where['is_trader']);
            })
            ->when(isset($where['verify_type']) && $where['verify_type'] !== '', function ($query) use ($where) {
                $query->where('L.verify_type', $where['verify_type']);
            })
            ->when(isset($where['order_id']) && $where['order_id'] !== '', function ($query) use ($where) {
                $query->where('L.order_id', $where['order_id']);
            })
            ->when(isset($where['order_sn']) && $where['order_sn'] !== '', function ($query) use ($where) {
                $query->whereLike('C.order_sn', "%{$where['order_sn']}%");
            })
            ->when(isset($where['product_name']) && $where['product_name'] !== '', function ($query) use ($where) {
                $query->whereLike('C.product_name', "%{$where['product_name']}%");
            })
            ->when(isset($where['nickname']) && $where['nickname'] !== '', function ($query) use ($where) {
                $query->whereLike('U.nickname', "%{$where['nickname']}%");
            })
            ->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['date'], 'L.create_time');
            })
            ;
    }
}
