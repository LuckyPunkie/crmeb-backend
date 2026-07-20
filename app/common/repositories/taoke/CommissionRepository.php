<?php

namespace app\common\repositories\taoke;

use app\common\dao\taoke\CommissionLogDao;
use app\common\dao\user\UserDao;
use app\common\repositories\BaseRepository;
use think\facade\Db;
use think\facade\Log;

/**
 * 分佣计算Repository
 */
class CommissionRepository extends BaseRepository
{
    /**
     * @var CommissionLogDao
     */
    protected $dao;

    /**
     * @var UserDao
     */
    protected $userDao;

    public function __construct(CommissionLogDao $dao, UserDao $userDao)
    {
        $this->dao = $dao;
        $this->userDao = $userDao;
    }

    /**
     * 计算并保存分佣记录
     * @param int $orderId 订单ID
     * @param string $orderType 订单类型：tb/jd/pdd
     * @param int $uid 用户ID
     * @param float $money 佣金总额
     * @param int $isShare 是否分享订单：0=否,1=是
     * @param bool $isUpdate 是否更新订单（重新计算分佣）
     * @return bool
     */
    public function calculate(int $orderId, string $orderType, int $uid, float $money, int $isShare = 0, bool $isUpdate = false): bool
    {
        try {
            Db::startTrans();

            // 1. 获取分佣配置
            $config = config('taoke.commission');
            $kengdieFee = $config['kengdie_fee'] ?? 0;
            $maxLevel = $config['max_level'] ?? 2;

            // 2. 计算实际应分佣金（扣除坑位费）
            $moneyReal = $money * (100 - $kengdieFee) / 100;

            // 3. 获取用户信息
            $user = $this->userDao->get($uid);
            if (!$user) {
                Db::rollback();
                return false;
            }

            // 4. 如果是更新订单，先删除原有的分佣记录
            if ($isUpdate) {
                $this->dao->search([
                    'order_id' => $orderId,
                    'order_type' => $orderType
                ])->delete();
            }

            // 5. 递归计算分佣
            $this->calcLevel($orderId, $orderType, $user, $moneyReal, $isShare, 0, $maxLevel);

            Db::commit();
            return true;

        } catch (\Exception $e) {
            Db::rollback();
            Log::error('计算分佣失败', [
                'order_id' => $orderId,
                'order_type' => $orderType,
                'uid' => $uid,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 递归计算分佣
     * @param int $orderId 订单ID
     * @param string $orderType 订单类型
     * @param \app\common\model\user\User $user 用户
     * @param float $money 佣金总额
     * @param int $isShare 是否分享订单
     * @param int $level 当前层级（0=自己）
     * @param int $maxLevel 最高层级
     */
    protected function calcLevel(int $orderId, string $orderType, $user, float $money, int $isShare, int $level, int $maxLevel)
    {
        // 超过最大层级或用户不存在，停止递归
        if ($level > $maxLevel || !$user) {
            return;
        }

        // 1. 确定分佣比例
        $config = config('taoke.commission');

        if ($level == 0) {
            // 自己
            if ($isShare == 1) {
                // 分享订单
                $rate = $config['share_rate'] ?? 30;
            } else {
                // 自购订单
                $rate = $config['self_rate'] ?? 50;
            }
        } elseif ($level == 1) {
            // 一级上级
            $rate = $config['level1_rate'] ?? 20;
        } else {
            // 二级上级
            $rate = $config['level2_rate'] ?? 10;
        }

        // 2. 计算分佣金额
        $commissionMoney = $money * $rate / 100;

        // 3. 插入分佣日志
        $this->dao->create([
            'order_id' => $orderId,
            'order_type' => $orderType,
            'uid' => $user->uid,
            'parent_uid' => $user->spread_uid ?? 0,
            'level' => $level,
            'is_share' => $isShare,
            'commission_total' => $money,
            'commission_rate' => $rate,
            'commission_money' => $commissionMoney,
            'status' => 0,  // 预估
            'create_time' => time()
        ]);

        // 4. 递归处理上级
        if (!empty($user->spread_uid) && $user->spread_uid > 0) {
            $parentUser = $this->userDao->get($user->spread_uid);
            $this->calcLevel($orderId, $orderType, $parentUser, $money, $isShare, $level + 1, $maxLevel);
        }
    }

    /**
     * 结算佣金（定时任务调用）
     * @return bool
     */
    public function settle(): bool
    {
        try {
            // 1. 获取结算配置
            $config = config('taoke.settle');
            $settleType = $config['type'] ?? 1;

            // 2. 查询待结算的佣金日志
            $query = $this->dao->search(['status' => 0]);

            if ($settleType == 0) {
                // 每月固定日期结算
                $settleDate = $config['date'] ?? 15;
                if (date('d') != $settleDate) {
                    return true;  // 未到结算日期
                }
                $query->whereTime('create_time', 'last month');
            } else {
                // 订单结算后N天
                $days = $config['days'] ?? 7;
                $query->whereTime('create_time', '<=', time() - 86400 * $days);
            }

            $logs = $query->limit(500)->select();

            if ($logs->isEmpty()) {
                return true;
            }

            // 3. 批量处理结算
            foreach ($logs as $log) {
                $log->status = 1;
                $log->settle_time = time();
                $log->save();
            }

            return true;

        } catch (\Exception $e) {
            Log::error('结算佣金失败', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 处理单个佣金结算（队列消费者调用）
     * @param int $logId
     * @return bool
     */
    public function processSettle(int $logId): bool
    {
        try {
            Db::startTrans();

            // 1. 获取佣金日志
            $log = $this->dao->get($logId);
            if (!$log || $log->status != 1) {
                Db::rollback();
                return false;
            }

            // 2. 更新用户佣金余额
            $user = $this->userDao->get($log->uid);
            if (!$user) {
                Db::rollback();
                return false;
            }

            $beforeBalance = floatval($user->brokerage_price ?? 0);
            $afterBalance = $beforeBalance + $log->commission_money;

            $user->brokerage_price = $afterBalance;
            $user->save();

            // 3. 记录余额变动日志
            Db::name('user_money_log')->insert([
                'uid' => $log->uid,
                'type' => 'commission',
                'money' => $log->commission_money,
                'before' => $beforeBalance,
                'after' => $afterBalance,
                'mark' => "订单佣金收入：{$log->commission_money}元",
                'create_time' => time()
            ]);

            Db::commit();
            return true;

        } catch (\Exception $e) {
            Db::rollback();
            Log::error('处理佣金结算失败', [
                'log_id' => $logId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取用户佣金统计
     * @param int $uid
     * @return array
     */
    public function getUserCommissionStats(int $uid): array
    {
        $stats = $this->dao->getUserCommissionStats($uid);

        // 补充自购比例默认值
        if (!isset($stats['self_rate'])) {
            $stats['self_rate'] = config('taoke.commission.self_rate', 50);
        }

        return $stats;
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
        return $this->dao->getUserCommissionList($uid, $where, $page, $limit);
    }

    /**
     * 获取用户佣金总数
     * @param int $uid
     * @param array $where
     * @return int
     */
    public function getUserCommissionCount(int $uid, array $where = []): int
    {
        return $this->dao->getUserCommissionCount($uid, $where);
    }

    /**
     * 获取用户余额
     * @param int $uid
     * @return float
     */
    public function getUserBalance(int $uid): float
    {
        $user = $this->userDao->get($uid);
        return $user ? floatval($user->brokerage_price ?? 0) : 0;
    }

    /**
     * 获取提现列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getWithdrawList(array $where, int $page, int $limit): array
    {
        $query = Db::name('user_extract');

        if (isset($where['keyword']) && $where['keyword']) {
            $query->whereLike('uid|extract_sn', '%' . $where['keyword'] . '%');
        }

        if (isset($where['status']) && $where['status'] !== '') {
            $query->where('status', $where['status']);
        }

        if (isset($where['type']) && $where['type']) {
            $query->where('extract_type', $where['type']);
        }

        if (isset($where['uid']) && $where['uid']) {
            $query->where('uid', $where['uid']);
        }

        $count = $query->count();
        $list = $query->page($page, $limit)->order('id DESC')->select()->toArray();

        return [
            'count' => $count,
            'list' => $list,
            'page' => $page,
            'limit' => $limit
        ];
    }
}
