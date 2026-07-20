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
use app\common\model\circle\CircleAgent;

class CircleAgentDao extends BaseDao
{
    const STATUS = [
        'REVIEW' => 0,    // 待审核，默认状态
        'APPROVER' => 1,  // 审核通过
        'REJECTED' => -1, // 审核拒绝
        'REVOKED' => -2   // 撤销
    ];

    const TYPE = [
        "REGION" => 0, // 区域
        "MERCHANT" => 1, // 商户
    ];

    protected function getModel(): string
    {
        return CircleAgent::class;
    }

    public function search(array $where, $isCount = false)
    {
        if($isCount) {
            $query = ($this->getModel()::getDB())->alias('c')->join('user u', 'u.uid = c.uid', 'left');
        } else {
            $query = ($this->getModel()::getDB())->field('c.*,u.nickname')->alias('c')->join('user u', 'u.uid = c.uid', 'left');
        }
        $query->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
            $query->where('c.uid', $where['uid']);
        })->when(isset($where['name']) && $where['name'] !== '', function ($query) use ($where) {
            $query->whereLike('c.name', "%{$where['name']}%");
        })->when(isset($where['phone']) && $where['phone'] !== '', function ($query) use ($where) {
            $query->where('c.phone', $where['phone']);
        })->when(isset($where['create_time']) && !empty($where['create_time']), function ($query) use ($where) {
            $where['create_time'][0] = $where['create_time'][0] . ' 00:00:00';
            $where['create_time'][1] = $where['create_time'][1] . ' 23:59:59';
            $query->whereBetween('c.create_time', $where['create_time']);
        })->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            $query->where('c.status', $where['status']);
        })->when(isset($where['nickname']) && $where['nickname'] !== '', function ($query) use ($where) {
            $query->whereLike('u.nickname', "%{$where['nickname']}%");
        })->when(isset($where['user_phone']) && $where['user_phone'] !== '', function ($query) use ($where) {
            $query->where('u.phone', $where['user_phone']);
        })->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
            $query->where('c.type', $where['type']);
        })->when(isset($where['is_apply']) && $where['is_apply'] !== '', function ($query) use ($where) {
            $query->where('c.business_name','<>', '');
        })->when(isset($where['business_name']) && $where['business_name'] !== '', function ($query) use ($where) {
            $query->where('c.business_name', $where['business_name']);
        });

        $query->order('c.create_time DESC');

        return $query;
    }
}
