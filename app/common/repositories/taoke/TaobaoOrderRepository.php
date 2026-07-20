<?php

namespace app\common\repositories\taoke;

use app\common\dao\taoke\TaobaoOrderDao;
use app\common\dao\taoke\CommissionLogDao;
use app\common\dao\user\UserDao;
use app\common\repositories\BaseRepository;
use think\facade\Log;

/**
 * 淘宝订单处理Repository
 */
class TaobaoOrderRepository extends BaseRepository
{
    /**
     * @var TaobaoOrderDao
     */
    protected $dao;

    /**
     * @var CommissionLogDao
     */
    protected $commissionLogDao;

    /**
     * @var UserDao
     */
    protected $userDao;

    public function __construct(TaobaoOrderDao $dao, CommissionLogDao $commissionLogDao, UserDao $userDao)
    {
        $this->dao = $dao;
        $this->commissionLogDao = $commissionLogDao;
        $this->userDao = $userDao;
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
        return $this->dao->getUserOrderList($uid, $where, $page, $limit);
    }

    /**
     * 获取用户订单总数
     * @param int $uid
     * @param array $where
     * @return int
     */
    public function getUserOrderCount(int $uid, array $where = []): int
    {
        return $this->dao->getUserOrderCount($uid, $where);
    }

    /**
     * 获取用户订单统计
     * @param int $uid
     * @return array
     */
    public function getUserOrderStats(int $uid): array
    {
        return $this->dao->getOrderStats($uid);
    }
}
