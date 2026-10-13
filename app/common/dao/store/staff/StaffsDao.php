<?php

namespace app\common\dao\store\staff;

use app\common\dao\BaseDao;
use app\common\model\store\staff\Staffs;
use think\db\exception\DbException;

class StaffsDao extends BaseDao
{

    protected function getModel(): string
    {
        return Staffs::class;
    }

    /**
     * 检查指定商户ID和ID组合是否存在对应的记录。
     *
     * 本函数用于查询数据库中是否存在特定商户ID和ID组合的记录，
     * 其中ID通常代表某个实体的唯一标识，而mer_id则表示该实体所属的商户ID。
     * 函数通过计算符合条件的记录数量来判断记录是否存在，如果数量大于0，则表示存在。
     *
     * @param int $merId 商户ID，用于限定查询的商户范围。
     * @param int $id 需要查询的ID，用于指定具体的实体。
     * @return bool 如果存在符合条件的记录，则返回true，否则返回false。
     * @throws DbException
     */
    public function merExists(int $merId, int $id)
    {
        return Staffs::getDB()->where($this->getPk(), $id)->where('mer_id', $merId)->count($this->getPk()) > 0;
    }

    public function search(array $where)
    {
        return Staffs::getDB()
            ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                $query->where('status', $where['status']);
            })->when(isset($where['name']) && $where['name'] !== '', function ($query) use ($where) {
                $query->whereLike('name', "%{$where['name']}%");
            })->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('mer_id', $where['mer_id']);
            })->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
                $query->where('uid', $where['uid']);
            })->when(isset($where['phone']) && $where['phone'] !== '', function ($query) use ($where) {
                $query->whereLike('phone', "%{$where['phone']}%");
            })->when(isset($where['staff_id']) && $where['staff_id'] !== '', function ($query) use ($where) {
                $query->where('staffs_id', $where['staff_id']);
            })->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->where(function($query) use($where){
                    $query->whereLike('staffs_id|name|phone', "%{$where['keyword']}%")->whereOr('uid', $where['keyword']);
                });
            });
    }

    public function getOnlyTrashed(array $where)
    {
        return Staffs::getDB()->onlyTrashed()
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('mer_id', $where['mer_id']);
            })->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
                $query->where('uid', $where['uid']);
            });

    }

    public function statisticsSearch(array $where)
    {

        return Staffs::getDB()->alias('s')
            ->field('s.staffs_id,s.mer_id,s.uid,s.name,s.photo,s.phone,count(so.order_id) as orderCount, sum(sop.settlement_price) serviceFee, sum(sop.product_num) as productCount, sum(if(so.status = 1, 1, 0)) as pendingNum')
            ->join('store_order so', 'so.staffs_id = s.staffs_id', 'left')
            ->join('store_order_product sop', 'sop.order_id = so.order_id', 'left')
            ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                $query->where('s.status', $where['status']);
            })->when(isset($where['name']) && $where['name'] !== '', function ($query) use ($where) {
                $query->whereLike('s.name', "%{$where['name']}%");
            })->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('s.mer_id', $where['mer_id']);
            })->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
                $query->where('s.uid', $where['uid']);
            })->when(isset($where['phone']) && $where['phone'] !== '', function ($query) use ($where) {
                $query->whereLike('s.phone', "%{$where['phone']}%");
            })->when(isset($where['staff_id']) && $where['staff_id'] !== '', function ($query) use ($where) {
                $query->where('s.staffs_id', $where['staff_id']);
            })->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->whereLike('s.staffs_id|s.name|s.phone', "%{$where['keyword']}%");
            })->when(isset($where['service_date']) && !empty($where['service_date']), function ($query) use ($where) {
                $query->whereBetween('sop.reservation_date', $where['service_date']);
            })
            ->where('so.status', '<>', -1)
            ->where('so.paid', 1)
            ->where('so.is_del', 0)
            ->group('s.staffs_id');
    }
}
