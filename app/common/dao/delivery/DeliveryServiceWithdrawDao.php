<?php

namespace app\common\dao\delivery;

use app\common\dao\BaseDao;
use app\common\model\delivery\DeliveryServiceWithdraw;

class DeliveryServiceWithdrawDao extends BaseDao
{
    public function search(array $where)
    {
        return DeliveryServiceWithdraw::getDB()
            ->alias('w')
            ->field('w.*,ds.name as service_name,ds.phone as service_phone,ds.avatar as service_avatar,u.nickname')
            ->join('delivery_service ds', 'ds.service_id = w.service_id', 'left')
            ->join('user u', 'u.uid = w.uid', 'left')
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('w.mer_id', $where['mer_id']);
            })
            ->when(isset($where['service_type']) && $where['service_type'] !== '', function ($query) use ($where) {
                $query->where('w.service_type', $where['service_type']);
            })
            ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                $query->where('w.status', $where['status']);
            })
            ->when(isset($where['payment_method']) && $where['payment_method'] !== '', function ($query) use ($where) {
                $query->where('w.payment_method', $where['payment_method']);
            })
            ->when(isset($where['service_id']) && $where['service_id'] !== '', function ($query) use ($where) {
                $query->where('w.service_id', $where['service_id']);
            })
            ->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
                $query->where('w.uid', $where['uid']);
            })
            ->when(isset($where['nickname']) && $where['nickname'] !== '', function ($query) use ($where) {
                $query->whereLike('u.nickname', "%{$where['nickname']}%");
            })
            ->when(isset($where['phone']) && $where['phone'] !== '', function ($query) use ($where) {
                $query->whereLike('ds.phone', "%{$where['phone']}%");
            })
            ->when(isset($where['real_name']) && $where['real_name'] !== '', function ($query) use ($where) {
                $query->whereLike('ds.name', "%{$where['real_name']}%");
            })
            ->when(isset($where['withdraw_sn']) && $where['withdraw_sn'] !== '', function ($query) use ($where) {
                $query->whereLike('w.withdraw_sn', "%{$where['withdraw_sn']}%");
            })
            ->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->whereLike('w.withdraw_sn|ds.name|ds.phone|u.nickname', "%{$where['keyword']}%");
            })
            ->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['date'], 'w.create_time');
            })
            ->when(isset($where['create_time']) && !empty($where['create_time']), function ($query) use ($where) {
                getModelTime($query, $where['create_time'], 'w.create_time');
            });
    }

    protected function getModel(): string
    {
        return DeliveryServiceWithdraw::class;
    }
}
