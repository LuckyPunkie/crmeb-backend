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

namespace app\common\dao\animal_rescue;

use app\common\dao\BaseDao;
use app\common\model\animal_rescue\AdoptionDeposit;

/**
 * 领养保证金 DAO
 * Class AdoptionDepositDao
 * @package app\common\dao\animal_rescue
 */
class AdoptionDepositDao extends BaseDao
{
    /**
     * @return string
     */
    protected function getModel(): string
    {
        return AdoptionDeposit::class;
    }

    /**
     * 搜索保证金记录
     * @param array $where
     * @return \think\db\BaseQuery
     */
    public function search(array $where)
    {
        $query = AdoptionDeposit::getDB();
        $query->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
            $query->where('uid', $where['uid']);
        })
        ->when(isset($where['post_id']) && $where['post_id'] !== '', function ($query) use ($where) {
            $query->where('post_id', $where['post_id']);
        })
        ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            $query->where('status', $where['status']);
        })
        ->when(isset($where['application_id']) && $where['application_id'] !== '', function ($query) use ($where) {
            $query->where('application_id', $where['application_id']);
        })
        ->when(isset($where['order_sn']) && $where['order_sn'] !== '', function ($query) use ($where) {
            $query->where('order_sn', $where['order_sn']);
        });
        $query->order('create_time DESC');
        return $query;
    }

    /**
     * 获取需要解冻的保证金列表
     * @return \think\Collection
     */
    public function getThawList()
    {
        return $this->getModel()::getDB()
            ->where('status', AdoptionDeposit::STATUS_FROZEN)
            ->where('thaw_time', '<=', date('Y-m-d H:i:s'))
            ->select();
    }

    /**
     * 即将到期（未解冻）的保证金，用于到期提醒
     * @param int $days
     * @return \think\Collection
     */
    public function getExpiringSoonList(int $days = 7)
    {
        $now = date('Y-m-d H:i:s');
        $end = date('Y-m-d H:i:s', strtotime('+' . max(1, $days) . ' days'));
        return $this->getModel()::getDB()
            ->where('status', AdoptionDeposit::STATUS_FROZEN)
            ->where('thaw_time', '>', $now)
            ->where('thaw_time', '<=', $end)
            ->select();
    }
}
