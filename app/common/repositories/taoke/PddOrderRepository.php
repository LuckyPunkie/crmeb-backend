<?php

namespace app\common\repositories\taoke;

use app\common\dao\taoke\PddOrderDao;
use app\common\dao\taoke\CommissionLogDao;
use app\common\dao\user\UserDao;
use app\common\repositories\BaseRepository;
use think\facade\Log;

/**
 * 拼多多订单处理Repository
 */
class PddOrderRepository extends BaseRepository
{
    /**
     * @var PddOrderDao
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

    public function __construct(PddOrderDao $dao, CommissionLogDao $commissionLogDao, UserDao $userDao)
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
        return $this->dao->countUserOrders($uid, $where);
    }
}
