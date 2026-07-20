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

namespace app\common\dao\circle;

use app\common\dao\BaseDao;
use app\common\model\circle\CircleBrokerageCheckout;

class CircleBrokerageCheckoutDao extends BaseDao
{
    const AUDIT_STATUS = [
        'REVIEW' => 0,    // 待审核，默认状态
        'APPROVER' => 1,  // 审核通过
        'REJECTED' => -1, // 审核拒绝
        'REVOKED' => -2   // 撤销
    ];

    const STATUS = [
        'NO_CREDITED' => 0, // 未到帐
        'CREDITED' => 1,    // 已到帐
    ];

    protected function getModel(): string
    {
        return CircleBrokerageCheckout::class;
    }

    public function search(array $where)
    {
        $query = ($this->getModel()::getDB());

        $query->when(isset($where['agent_id']) && $where['agent_id'] !== '', function ($query) use ($where) {
            $query->where('agent_id', $where['agent_id']);
        })->when(isset($where['agent_phone']) && $where['agent_phone'] !== '', function ($query) use ($where) {
            $query->where('agent_phone', $where['agent_phone']);
        })->when(isset($where['withdrawal_type']) && $where['withdrawal_type'] !== '', function ($query) use ($where) {
            $query->where('withdrawal_type', $where['withdrawal_type']);
        })->when(isset($where['audit_status']) && $where['audit_status'] !== '', function ($query) use ($where) {
            $query->where('audit_status', $where['audit_status']);
        })->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            $query->where('status', $where['status']);
        })->when(isset($where['withdrawal_sn']) && $where['withdrawal_sn'] !== '', function ($query) use ($where) {
            $query->where('withdrawal_sn', $where['withdrawal_sn']);
        })->when(isset($where['create_time']) && !empty($where['create_time']), function ($query) use ($where) {
            $where['create_time'][0] = $where['create_time'][0] . ' 00:00:00';
            $where['create_time'][1] = $where['create_time'][1] . ' 23:59:59';
            $query->whereBetween('create_time', $where['create_time']);
        })->when(isset($where['agent_name']) && $where['agent_name'] !== '', function ($query) use ($where) {
            $query->whereLike('agent_name', "%{$where['agent_name']}%");
        });

        $query->order('create_time DESC');

        return $query;
    }
}
