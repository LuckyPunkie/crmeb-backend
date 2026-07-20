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

namespace app\common\dao\community;

use app\common\dao\BaseDao;
use app\common\model\community\CommunityPaidOrder;

class CommunityPaidOrderDao extends BaseDao
{
    protected function getModel(): string
    {
        return CommunityPaidOrder::class;
    }

    public function search(array $where)
    {
        return CommunityPaidOrder::getDB()
            ->when(isset($where['order_no']) && $where['order_no'] !== '', function ($query) use ($where) {
                $query->where('order_no', $where['order_no']);
            })
            ->when(isset($where['paid_content_id']) && $where['paid_content_id'] !== '', function ($query) use ($where) {
                $query->where('paid_content_id', $where['paid_content_id']);
            })
            ->when(isset($where['community_id']) && $where['community_id'] !== '', function ($query) use ($where) {
                $query->where('community_id', $where['community_id']);
            })
            ->when(isset($where['buyer_uid']) && $where['buyer_uid'] !== '', function ($query) use ($where) {
                $query->where('buyer_uid', $where['buyer_uid']);
            })
            ->when(isset($where['seller_uid']) && $where['seller_uid'] !== '', function ($query) use ($where) {
                $query->where('seller_uid', $where['seller_uid']);
            })
            ->when(isset($where['pay_status']) && $where['pay_status'] !== '', function ($query) use ($where) {
                $query->where('pay_status', $where['pay_status']);
            });
    }
}
