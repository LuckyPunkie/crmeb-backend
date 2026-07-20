<?php

namespace app\common\dao\taoke;

use app\common\dao\BaseDao;
use app\common\model\taoke\PddOrder;

/**
 * 拼多多订单DAO
 */
class PddOrderDao extends BaseDao
{
    /**
     * 获取模型
     * @return string
     */
    protected function getModel(): string
    {
        return PddOrder::class;
    }

    /**
     * 搜索
     * @param array $where
     * @return \think\db\BaseQuery
     */
    public function search(array $where = [])
    {
        return PddOrder::getDB()->withSearch(array_keys($where), $where);
    }

    /**
     * 根据订单号获取订单
     * @param string $orderSn
     * @return mixed
     */
    public function getByOrderSn(string $orderSn)
    {
        return $this->search(['order_sn' => $orderSn])->find();
    }

    /**
     * 获取用户订单列表
     * @param int $uid
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getUserOrderList(int $uid, array $where = [], int $page = 1, int $limit = 20): array
    {
        $where['uid'] = $uid;
        return $this->search($where)
            ->order('create_time DESC')
            ->page($page, $limit)
            ->select()
            ->toArray();
    }

    /**
     * 统计用户订单数量
     * @param int $uid
     * @param array $where
     * @return int
     */
    public function countUserOrders(int $uid, array $where = []): int
    {
        $where['uid'] = $uid;
        return $this->search($where)->count();
    }
}
