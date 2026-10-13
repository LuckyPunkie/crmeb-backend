<?php

namespace app\common\dao\delivery;

use app\common\dao\BaseDao;
use app\common\model\delivery\DeliveryServiceApply;

class DeliveryServiceApplyDao extends BaseDao
{
    public const STATUS_REVIEW = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = -1;
    public const STATUS_ALL = 2;

    protected function getModel(): string
    {
        return DeliveryServiceApply::class;
    }

    public function search(array $where)
    {
        return DeliveryServiceApply::getDB()
            ->field('a.delivery_service_apply_id,a.uid,a.service_id,a.avatar,a.name,a.phone,a.status,a.audit_admin_id,a.audit_reason,a.audit_time,a.create_time,a.update_time,u.nickname AS nickname,u.phone AS user_phone')
            ->alias('a')
            ->join('user u', 'u.uid = a.uid', 'left')
            ->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
                $query->where('a.uid', $where['uid']);
            })->when(isset($where['nickname']) && $where['nickname'] !== '', function ($query) use ($where) {
                $query->whereLike('u.nickname', "%{$where['nickname']}%");
            })->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                $query->where('a.status', $where['status']);
            })->when(isset($where['name']) && $where['name'] !== '', function ($query) use ($where) {
                $query->whereLike('a.name', "%{$where['name']}%");
            })->when(isset($where['phone']) && $where['phone'] !== '', function ($query) use ($where) {
                $query->whereLike('a.phone', "%{$where['phone']}%");
            })->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->whereLike('a.delivery_service_apply_id|a.uid|a.name|a.phone|u.nickname', "%{$where['keyword']}%");
            })->when(isset($where['create_time']) && !empty($where['create_time']), function ($query) use ($where) {
                getModelTime($query, $where['create_time'], 'a.create_time');
            });
    }
}
