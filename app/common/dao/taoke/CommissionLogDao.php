<?php

namespace app\common\dao\taoke;

use app\common\dao\BaseDao;
use app\common\model\taoke\CommissionLog;

/**
 * 佣金日志DAO层
 */
class CommissionLogDao extends BaseDao
{
    /**
     * 获取模型
     * @return string
     */
    protected function getModel(): string
    {
        return CommissionLog::class;
    }

    /**
     * 搜索
     * @param array $where
     * @return \think\db\BaseQuery
     */
    public function search(array $where = [])
    {
        return CommissionLog::getDB()->withSearch(array_keys($where), $where);
    }

    /**
     * 获取用户佣金列表
     * @param int $uid
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getUserCommissionList(int $uid, array $where = [], int $page = 1, int $limit = 20): array
    {
        $where['uid'] = $uid;
        return $this->search($where)
            ->order('create_time DESC')
            ->page($page, $limit)
            ->select()
            ->toArray();
    }

    /**
     * 获取用户佣金总数
     * @param int $uid
     * @param array $where
     * @return int
     */
    public function getUserCommissionCount(int $uid, array $where = []): int
    {
        $where['uid'] = $uid;
        return $this->search($where)->count();
    }

    /**
     * 获取用户佣金总额
     * @param int $uid
     * @param int $status
     * @return float
     */
    public function getUserCommissionTotal(int $uid, int $status = 1): float
    {
        return (float)$this->search([
                'uid' => $uid,
                'status' => $status
            ])
            ->sum('commission_money');
    }

    /**
     * 获取待结算佣金日志
     * @param int $limit
     * @return array
     */
    public function getUnsettleLogs(int $limit = 500): array
    {
        return $this->search(['status' => 0])
            ->order('create_time ASC')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 批量更新状态
     * @param array $ids
     * @param int $status
     * @return bool
     */
    public function batchUpdateStatus(array $ids, int $status): bool
    {
        return $this->getModel()::whereIn('id', $ids)->update([
                'status' => $status,
                'settle_time' => time()
            ]) !== false;
    }

    /**
     * 获取订单的分佣日志
     * @param int $orderId
     * @param string $orderType
     * @return array
     */
    public function getOrderCommissionLogs(int $orderId, string $orderType): array
    {
        return $this->search([
                'order_id' => $orderId,
                'order_type' => $orderType
            ])
            ->order('level ASC')
            ->select()
            ->toArray();
    }

    /**
     * 统计用户的累计佣金
     * @param int $uid
     * @return array
     */
    public function getUserCommissionStats(int $uid): array
    {
        $stats = $this->search(['uid' => $uid])
            ->field(
                'SUM(CASE WHEN status = 0 THEN commission_money ELSE 0 END) as unsettled,' .
                'SUM(CASE WHEN status = 1 THEN commission_money ELSE 0 END) as settled,' .
                'SUM(CASE WHEN status = 2 THEN commission_money ELSE 0 END) as invalid,' .
                'SUM(commission_money) as total'
            )
            ->find();

        return $stats ? $stats->toArray() : [];
    }
}
