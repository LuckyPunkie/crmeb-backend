<?php

namespace app\common\dao\taoke;

use app\common\dao\BaseDao;
use app\common\model\taoke\TaobaoOrder;

/**
 * 淘宝订单DAO层
 */
class TaobaoOrderDao extends BaseDao
{
    /**
     * 获取模型
     * @return string
     */
    protected function getModel(): string
    {
        return TaobaoOrder::class;
    }

    /**
     * 搜索
     * @param array $where
     * @return \think\db\BaseQuery
     */
    public function search(array $where = [])
    {
        return TaobaoOrder::getDB()->withSearch(array_keys($where), $where);
    }

    /**
     * 根据交易号获取订单
     * @param string $tradeId
     * @return array|\think\Model|null
     */
    public function getByTradeId(string $tradeId)
    {
        return $this->search(['trade_id' => $tradeId])->find();
    }

    /**
     * 获取用户订单列表
     * @param int $uid
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getUserOrderList(int $uid, array $where = [], int $page = 1, int $limit = 20)
    {
        $where['uid'] = $uid;
        return $this->search($where)
            ->order('create_time DESC')
            ->page($page, $limit)
            ->select()
            ->toArray();
    }

    /**
     * 获取用户订单总数
     * @param int $uid
     * @param array $where
     * @return int
     */
    public function getUserOrderCount(int $uid, array $where = []): int
    {
        $where['uid'] = $uid;
        return $this->search($where)->count();
    }

    /**
     * 获取待结算订单列表
     * @param int $limit
     * @return array
     */
    public function getSettleOrders(int $limit = 500): array
    {
        return $this->search([
                'is_fanli' => 0,
                'order_status' => 2
            ])
            ->where('tk_settle_time', '>', 0)
            ->order('tk_settle_time ASC')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 更新订单状态
     * @param string $tradeId
     * @param array $data
     * @return bool
     */
    public function updateByTradeId(string $tradeId, array $data): bool
    {
        return $this->getModel()::where('trade_id', $tradeId)->update($data) !== false;
    }

    /**
     * 批量插入订单
     * @param array $orders
     * @return bool
     */
    public function insertAll(array $orders): bool
    {
        return $this->getModel()::insertAll($orders) !== false;
    }

    /**
     * 获取订单统计
     * @param int $uid
     * @return array
     */
    public function getOrderStats(int $uid): array
    {
        $stats = $this->search(['uid' => $uid])
            ->field(
                'COUNT(*) as total_count,' .
                'SUM(total_price) as total_amount,' .
                'SUM(commission_price) as total_commission,' .
                'SUM(CASE WHEN order_status = 1 THEN 1 ELSE 0 END) as paid_count,' .
                'SUM(CASE WHEN order_status = 2 THEN 1 ELSE 0 END) as settle_count'
            )
            ->find();

        return $stats ? $stats->toArray() : [];
    }
}
