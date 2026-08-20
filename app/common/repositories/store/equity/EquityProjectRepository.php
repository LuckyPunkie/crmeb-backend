<?php

namespace app\common\repositories\store\equity;

use app\common\dao\store\equity\EquityProjectDao;
use app\common\model\store\equity\EquityDividend;
use app\common\model\store\equity\EquityDividendNotice;
use app\common\model\store\equity\EquityFinancialReport;
use app\common\model\store\equity\EquityInvestRefund;
use app\common\model\store\equity\EquityProject;
use app\common\model\store\equity\EquityShareholder;
use app\common\model\store\equity\EquityStaffPool;
use app\common\model\store\equity\EquityTransaction;
use app\common\model\store\equity\MerchantEquityConfig;
use app\common\model\system\merchant\Merchant;
use app\common\repositories\BaseRepository;
use app\common\model\user\User;
use app\common\repositories\user\UserBillRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 用户端 / 平台运营查询与运营操作
 */
class EquityProjectRepository extends BaseRepository
{
    public function __construct(EquityProjectDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 我的股份列表
     */
    public function myProjects(int $uid, array $params): array
    {
        $tab = $params['tab'] ?? 'all'; // all|raising|pending|operating
        $sort = $params['sort'] ?? 'consume_time';
        $order = strtolower($params['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $statusMap = [
            'raising' => EquityProject::STATUS_RAISING,
            'pending' => EquityProject::STATUS_PENDING,
            'operating' => EquityProject::STATUS_OPERATING,
        ];

        $query = EquityShareholder::getDB()->alias('s')
            ->join('equity_project p', 'p.id = s.project_id')
            ->where('s.uid', $uid)
            ->where('s.total_amount', '>', 0);

        if ($tab !== 'all' && isset($statusMap[$tab])) {
            $query->where('p.status', $statusMap[$tab]);
        }

        $orderSql = $this->buildSortSql($sort, $order);
        $list = $query->field('s.*,p.mer_id,p.new_store_id,p.round_no,p.target_amount,p.total_consumer_amount,p.total_equity,p.status,p.opened_at,p.reached_at,p.shareholder_count')
            ->orderRaw($orderSql)
            ->select()
            ->toArray();

        $result = [];
        foreach ($list as $row) {
            $result[] = $this->formatCard($row, $uid);
        }
        return $result;
    }

    protected function buildSortSql(string $sort, string $order): string
    {
        $dir = $order === 'asc' ? 'ASC' : 'DESC';
        switch ($sort) {
            case 'target_amount':
                return "p.target_amount {$dir}, s.last_consume_time DESC";
            case 'progress':
                return "(p.total_consumer_amount / NULLIF(p.target_amount,0)) {$dir}, s.last_consume_time DESC";
            case 'opened_at':
                // 未开业排最后
                return "(CASE WHEN p.status = 3 THEN 0 ELSE 1 END) ASC, p.opened_at {$dir}, s.last_consume_time DESC";
            case 'share_ratio':
                return "s.share_ratio {$dir}";
            case 'dividend':
                return "s.total_dividend_amount {$dir}";
            case 'turnover':
                return "s.last_consume_time {$dir}"; // 昨日营业额在 format 后前端可再排；列表内用消费时间兜底
            case 'consume_time':
            default:
                return "s.last_consume_time {$dir}";
        }
    }

    protected function formatCard(array $row, int $uid): array
    {
        $status = (int)$row['status'];
        $displayMerId = $status === EquityProject::STATUS_OPERATING && (int)$row['new_store_id'] > 0
            ? (int)$row['new_store_id']
            : (int)$row['mer_id'];
        $merchant = Merchant::getDB()->where('mer_id', $displayMerId)->find();

        $card = [
            'project_id' => (int)$row['project_id'],
            'mer_id' => (int)$row['mer_id'],
            'new_store_id' => (int)$row['new_store_id'],
            'round_no' => (int)$row['round_no'],
            'status' => $status,
            'status_text' => $this->statusText($status),
            'store_name' => ($merchant['mer_name'] ?? '店铺') . '（第' . $row['round_no'] . '期）',
            'store_avatar' => $merchant['mer_avatar'] ?? ($merchant['mer_banner'] ?? ''),
            'target_amount' => (float)$row['target_amount'],
            'total_consumer_amount' => (float)$row['total_consumer_amount'],
            'progress' => $this->progressPct($row['total_consumer_amount'], $row['target_amount']),
            'my_amount' => (float)$row['total_amount'],
            'my_share_ratio' => $this->formatRatio($row['share_ratio']),
            'my_share_ratio_raw' => (float)$row['share_ratio'],
            'last_dividend_amount' => (float)$row['last_dividend_amount'],
            'total_dividend_amount' => (float)$row['total_dividend_amount'],
            'yesterday_turnover' => 0,
            'last_consume_time' => (int)$row['last_consume_time'],
            'opened_at' => (int)$row['opened_at'],
            'pending_badge' => false,
            'raising_progress' => null,
        ];

        // 待开业卡片：展示新一轮筹集中进度
        if ($status === EquityProject::STATUS_PENDING) {
            $card['pending_badge'] = true;
            $raising = EquityProject::getDB()
                ->where('mer_id', $row['mer_id'])
                ->where('status', EquityProject::STATUS_RAISING)
                ->find();
            if ($raising) {
                $myRaising = EquityShareholder::getDB()
                    ->where('project_id', $raising['id'])
                    ->where('uid', $uid)
                    ->find();
                $card['raising_progress'] = [
                    'project_id' => (int)$raising['id'],
                    'target_amount' => (float)$raising['target_amount'],
                    'total_consumer_amount' => (float)$raising['total_consumer_amount'],
                    'progress' => $this->progressPct($raising['total_consumer_amount'], $raising['target_amount']),
                    'my_amount' => $myRaising ? (float)$myRaising['total_amount'] : 0,
                    'my_share_ratio' => $myRaising ? $this->formatRatio($myRaising['share_ratio']) : '约0.00%',
                ];
                $card['target_amount'] = (float)$raising['target_amount'];
                $card['total_consumer_amount'] = (float)$raising['total_consumer_amount'];
                $card['progress'] = $card['raising_progress']['progress'];
                $card['my_amount'] = $card['raising_progress']['my_amount'];
                $card['my_share_ratio'] = $card['raising_progress']['my_share_ratio'];
            }
        }

        if ($status === EquityProject::STATUS_OPERATING && (int)$row['new_store_id'] > 0) {
            $card['yesterday_turnover'] = $this->yesterdayTurnover((int)$row['new_store_id']);
        }

        return $card;
    }

    public function projectDetail(int $projectId, int $uid = 0): array
    {
        $project = EquityProject::getDB()->where('id', $projectId)->find();
        if (!$project) {
            throw new ValidateException('项目不存在');
        }
        $project = $project->toArray();
        $config = MerchantEquityConfig::getDB()->where('mer_id', $project['mer_id'])->find();
        $merchant = Merchant::getDB()->where('mer_id', $project['mer_id'])->find();
        $newMerchant = null;
        if ((int)$project['new_store_id'] > 0) {
            $newMerchant = Merchant::getDB()->where('mer_id', $project['new_store_id'])->find();
        }

        $my = null;
        if ($uid > 0) {
            $my = EquityShareholder::getDB()->where('project_id', $projectId)->where('uid', $uid)->find();
        }

        $raising = null;
        if ((int)$project['status'] === EquityProject::STATUS_PENDING || (int)$project['status'] === EquityProject::STATUS_OPERATING) {
            $raising = EquityProject::getDB()
                ->where('mer_id', $project['mer_id'])
                ->where('status', EquityProject::STATUS_RAISING)
                ->find();
        }

        $remain = max(0, round((float)$project['target_amount'] - (float)$project['total_consumer_amount'], 2));

        return [
            'project' => $project,
            'config' => $config ? $config->toArray() : null,
            'store' => $merchant ? [
                'mer_id' => $merchant['mer_id'],
                'mer_name' => $merchant['mer_name'],
                'mer_avatar' => $merchant['mer_avatar'] ?? '',
            ] : null,
            'new_store' => $newMerchant ? [
                'mer_id' => $newMerchant['mer_id'],
                'mer_name' => $newMerchant['mer_name'],
                'mer_avatar' => $newMerchant['mer_avatar'] ?? '',
            ] : null,
            'my' => $my ? [
                'total_amount' => (float)$my['total_amount'],
                'invest_amount' => (float)$my['invest_amount'],
                'share_ratio' => $this->formatRatio($my['share_ratio']),
                'share_ratio_raw' => (float)$my['share_ratio'],
                'last_dividend_amount' => (float)$my['last_dividend_amount'],
                'total_dividend_amount' => (float)$my['total_dividend_amount'],
            ] : [
                'total_amount' => 0,
                'invest_amount' => 0,
                'share_ratio' => '约0.00%',
                'share_ratio_raw' => 0,
            ],
            'remain_amount' => $remain,
            'progress' => $this->progressPct($project['total_consumer_amount'], $project['target_amount']),
            'can_invest' => (int)$project['status'] === EquityProject::STATUS_RAISING && $remain >= 0.01,
            'can_refund_invest' => (int)$project['status'] === EquityProject::STATUS_RAISING
                && $my && (float)$my['invest_amount'] >= 0.01,
            'raising_project' => $raising ? $this->projectDetailLite($raising->toArray(), $uid) : null,
            'disclaimer' => '股本金不等于实际股权，仅享有分红权，不参与经营决策。',
        ];
    }

    public function projectDetailLite(array $project, int $uid = 0): array
    {
        $my = null;
        if ($uid > 0) {
            $my = EquityShareholder::getDB()->where('project_id', $project['id'])->where('uid', $uid)->find();
        }
        $remain = max(0, round((float)$project['target_amount'] - (float)$project['total_consumer_amount'], 2));
        return [
            'project_id' => (int)$project['id'],
            'round_no' => (int)$project['round_no'],
            'status' => (int)$project['status'],
            'target_amount' => (float)$project['target_amount'],
            'total_consumer_amount' => (float)$project['total_consumer_amount'],
            'shareholder_count' => (int)$project['shareholder_count'],
            'progress' => $this->progressPct($project['total_consumer_amount'], $project['target_amount']),
            'remain_amount' => $remain,
            'my_amount' => $my ? (float)$my['total_amount'] : 0,
            'my_share_ratio' => $my ? $this->formatRatio($my['share_ratio']) : '约0.00%',
            'can_invest' => (int)$project['status'] === EquityProject::STATUS_RAISING && $remain >= 0.01,
        ];
    }

    /**
     * 店铺详情页入股模块（按老店 mer_id）
     */
    public function shopInvestModule(int $merId, int $uid = 0): array
    {
        $config = MerchantEquityConfig::getDB()->where('mer_id', $merId)->find();
        $raising = EquityProject::getDB()
            ->where('mer_id', $merId)
            ->where('status', EquityProject::STATUS_RAISING)
            ->find();
        // 已达成项目 = 待开业 + 营业中（筹集中不在此列）
        $fundedList = EquityProject::getDB()
            ->where('mer_id', $merId)
            ->whereIn('status', [EquityProject::STATUS_PENDING, EquityProject::STATUS_OPERATING])
            ->order('id', 'desc')
            ->select()
            ->toArray();

        $module = [
            'enabled' => $config ? (int)$config['enabled'] : 0,
            'configured' => (bool)$config,
            'consume_equity_percent' => $config ? (float)$config['consume_equity_percent'] : 0,
            'raising' => null,
            'pending_list' => [],
            'funded_list' => [],
            'my_transactions' => [],
            'disclaimer' => '股本金不等于实际股权，仅享有分红权，不参与经营决策。',
        ];

        if ($raising) {
            $module['raising'] = $this->projectDetail((int)$raising['id'], $uid);
        }

        $oldMer = Merchant::getDB()->where('mer_id', $merId)->find();
        $oldMerName = $oldMer['mer_name'] ?? '店铺';

        foreach ($fundedList as $p) {
            $status = (int)$p['status'];
            $newMerName = '';
            if (!empty($p['new_store_id'])) {
                $newMer = Merchant::getDB()->where('mer_id', (int)$p['new_store_id'])->find();
                $newMerName = $newMer['mer_name'] ?? '';
            }
            $displayName = $status === EquityProject::STATUS_OPERATING && $newMerName
                ? $newMerName
                : $oldMerName;
            $reachedAt = (int)($p['reached_at'] ?? 0);
            $item = [
                'project_id' => (int)$p['id'],
                'round_no' => (int)$p['round_no'],
                'status' => $status,
                'name' => $displayName . '（第' . (int)$p['round_no'] . '期）',
                'target_amount' => (float)$p['target_amount'],
                'total_consumer_amount' => (float)$p['total_consumer_amount'],
                'reached_at' => $reachedAt,
                'reached_at_text' => $reachedAt > 0 ? date('Y-m-d', $reachedAt) : '',
                'expected_open_at' => $p['expected_open_at'] ?: '待定',
                'new_store_id' => (int)($p['new_store_id'] ?? 0),
            ];
            $module['funded_list'][] = $item;
            // 兼容旧字段：仅待开业
            if ($status === EquityProject::STATUS_PENDING) {
                $module['pending_list'][] = $item;
            }
        }

        if ($uid > 0) {
            $allProjectIds = EquityProject::getDB()->where('mer_id', $merId)->column('id');
            if ($allProjectIds) {
                // 店铺维度：展示该老店下各期（筹集中/待开业/营业中）的入股记录，换期后不丢历史
                $module['my_transactions'] = EquityTransaction::getDB()
                    ->whereIn('project_id', $allProjectIds)
                    ->where('uid', $uid)
                    ->order('id', 'desc')
                    ->limit(50)
                    ->select()
                    ->toArray();
            }
        }

        return $module;
    }

    public function myTransactions(int $projectId, int $uid): array
    {
        return EquityTransaction::getDB()
            ->where('project_id', $projectId)
            ->where('uid', $uid)
            ->order('id', 'desc')
            ->select()
            ->toArray();
    }

    public function bindNewStore(int $projectId, int $newStoreId): array
    {
        return Db::transaction(function () use ($projectId, $newStoreId) {
            $project = EquityProject::getDB()->where('id', $projectId)->lock(true)->find();
            if (!$project || (int)$project['status'] !== EquityProject::STATUS_PENDING) {
                throw new ValidateException('仅待开业项目可绑定新店');
            }
            $mer = Merchant::getDB()->where('mer_id', $newStoreId)->find();
            if (!$mer) {
                throw new ValidateException('新店不存在');
            }
            EquityProject::getDB()->where('id', $projectId)->update([
                'new_store_id' => $newStoreId,
                'status' => EquityProject::STATUS_OPERATING,
                'opened_at' => time(),
            ]);
            return $this->projectDetail($projectId, 0);
        });
    }

    public function pendingStores(array $where, int $page, int $limit): array
    {
        // 待开业管理：展示待开业 + 已绑定营业中；可按 status 筛选
        $statusFilter = null;
        if (isset($where['status']) && $where['status'] !== '' && $where['status'] !== null) {
            $st = (int)$where['status'];
            if (in_array($st, [EquityProject::STATUS_PENDING, EquityProject::STATUS_OPERATING], true)) {
                $statusFilter = $st;
            }
        }

        $query = EquityProject::getDB();
        if ($statusFilter !== null) {
            $query->where('status', $statusFilter);
        } else {
            $query->whereIn('status', [
                EquityProject::STATUS_PENDING,
                EquityProject::STATUS_OPERATING,
            ]);
        }

        $count = $query->count();
        $list = $query->order('id', 'desc')->page($page, $limit)->select()->toArray();
        foreach ($list as &$row) {
            $mer = Merchant::getDB()->where('mer_id', $row['mer_id'])->find();
            $row['store_name'] = $mer['mer_name'] ?? '';
            $row['reached_at_text'] = !empty($row['reached_at']) ? date('Y-m-d H:i', (int)$row['reached_at']) : '';
            $row['opened_at_text'] = !empty($row['opened_at']) ? date('Y-m-d H:i', (int)$row['opened_at']) : '';
            $row['status_text'] = [
                EquityProject::STATUS_PENDING => '待开业',
                EquityProject::STATUS_OPERATING => '营业中',
            ][(int)$row['status']] ?? '';
            $newMer = null;
            if (!empty($row['new_store_id'])) {
                $newMer = Merchant::getDB()->where('mer_id', $row['new_store_id'])->find();
            }
            $row['new_store_name'] = $newMer['mer_name'] ?? '';
        }
        unset($row);
        return compact('count', 'list');
    }

    public function refundList(array $where, int $page, int $limit): array
    {
        $query = EquityInvestRefund::getDB()->alias('r');
        if (isset($where['status']) && $where['status'] !== '') {
            $query->where('r.status', (int)$where['status']);
        }
        $count = $query->count();
        $list = $query->order('r.id', 'desc')->page($page, $limit)->select()->toArray();
        return compact('count', 'list');
    }

    public function executeDividend(int $projectId, float $totalAmount, string $period): array
    {
        if ($totalAmount <= 0) {
            throw new ValidateException('分红金额必须大于0');
        }
        return Db::transaction(function () use ($projectId, $totalAmount, $period) {
            $project = EquityProject::getDB()->where('id', $projectId)->lock(true)->find();
            if (!$project || (int)$project['status'] !== EquityProject::STATUS_OPERATING) {
                throw new ValidateException('仅营业中项目可分红');
            }

            $batchNo = 'DV' . date('YmdHis') . mt_rand(1000, 9999);
            $consumerPool = round($totalAmount * 0.9, 2);
            $merchantPool = round($totalAmount * 0.05, 2);
            $platformPool = round($totalAmount * 0.03, 2);
            $staffPool = round($totalAmount * 0.02, 2);

            $shareholders = EquityShareholder::getDB()
                ->where('project_id', $projectId)
                ->where('total_amount', '>', 0)
                ->lock(true)
                ->select();

            $totalEquity = (string)$project['total_equity'];
            $paidConsumer = '0.00';
            foreach ($shareholders as $sh) {
                // 用户分红 = 总分红 × 个人占股比例（个人占比已是相对总股本，合计约90%）
                $amt = '0.00';
                if (bccomp($totalEquity, '0', 2) > 0) {
                    $amt = bcmul((string)$totalAmount, (string)$sh['share_ratio'], 2);
                }
                if (bccomp($amt, '0.01', 2) < 0) {
                    continue;
                }
                $this->creditUserWallet((int)$sh['uid'], (float)$amt, '消费送股分红', $projectId);
                EquityDividend::getDB()->insert([
                    'project_id' => $projectId,
                    'batch_no' => $batchNo,
                    'total_amount' => $totalAmount,
                    'uid' => $sh['uid'],
                    'amount' => $amt,
                    'role_type' => 1,
                    'period' => $period,
                    'status' => 2,
                ]);
                EquityShareholder::getDB()->where('id', $sh['id'])->update([
                    'last_dividend_amount' => $amt,
                    'total_dividend_amount' => bcadd((string)$sh['total_dividend_amount'], $amt, 2),
                ]);
                $paidConsumer = bcadd($paidConsumer, $amt, 2);
            }

            // 员工激励池
            $staffList = EquityStaffPool::getDB()->where('project_id', $projectId)->select();
            $staffPaid = '0.00';
            foreach ($staffList as $staff) {
                $ratio = bcdiv((string)$staff['pool_ratio'], '100', 6);
                $amt = bcmul((string)$staffPool, $ratio, 2);
                if (bccomp($amt, '0.01', 2) < 0) {
                    continue;
                }
                if ((int)$staff['staff_uid'] > 0) {
                    $this->creditUserWallet((int)$staff['staff_uid'], (float)$amt, '员工激励池分红', $projectId);
                }
                EquityDividend::getDB()->insert([
                    'project_id' => $projectId,
                    'batch_no' => $batchNo,
                    'total_amount' => $totalAmount,
                    'uid' => (int)$staff['staff_uid'],
                    'amount' => $amt,
                    'role_type' => 4,
                    'period' => $period,
                    'status' => 2,
                ]);
                $staffPaid = bcadd($staffPaid, $amt, 2);
            }

            // 原商家 / 平台记流水（不入用户余额，记 role）
            EquityDividend::getDB()->insert([
                'project_id' => $projectId,
                'batch_no' => $batchNo,
                'total_amount' => $totalAmount,
                'uid' => 0,
                'amount' => $merchantPool,
                'role_type' => 2,
                'period' => $period,
                'status' => 2,
            ]);
            EquityDividend::getDB()->insert([
                'project_id' => $projectId,
                'batch_no' => $batchNo,
                'total_amount' => $totalAmount,
                'uid' => 0,
                'amount' => $platformPool,
                'role_type' => 3,
                'period' => $period,
                'status' => 2,
            ]);
            $unallocatedStaff = bcsub((string)$staffPool, $staffPaid, 2);
            if (bccomp($unallocatedStaff, '0', 2) > 0) {
                EquityDividend::getDB()->insert([
                    'project_id' => $projectId,
                    'batch_no' => $batchNo,
                    'total_amount' => $totalAmount,
                    'uid' => 0,
                    'amount' => $unallocatedStaff,
                    'role_type' => 4,
                    'period' => $period . '-未分配',
                    'status' => 2,
                ]);
            }

            return [
                'batch_no' => $batchNo,
                'consumer_paid' => $paidConsumer,
                'merchant_pool' => $merchantPool,
                'platform_pool' => $platformPool,
                'staff_paid' => $staffPaid,
                'staff_unallocated' => $unallocatedStaff,
            ];
        });
    }

    protected function creditUserWallet(int $uid, float $amount, string $title, int $linkId): void
    {
        if ($uid <= 0 || $amount < 0.01) {
            return;
        }
        $user = User::getDB()->where('uid', $uid)->lock(true)->find();
        if (!$user) {
            return;
        }
        $balance = bcadd((string)$user['now_money'], (string)$amount, 2);
        User::getDB()->where('uid', $uid)->update(['now_money' => $balance]);
        app()->make(UserBillRepository::class)->incBill($uid, 'now_money', 'sys_inc_money', [
            'link_id' => $linkId,
            'status' => 1,
            'title' => $title,
            'number' => $amount,
            'mark' => $title . ' ¥' . $amount,
            'balance' => $balance,
        ]);
    }

    public function dividendNotices(int $projectId): array
    {
        return EquityDividendNotice::getDB()
            ->where('project_id', $projectId)
            ->where('status', EquityDividendNotice::STATUS_PUBLISHED)
            ->order('id', 'desc')
            ->select()
            ->toArray();
    }

    public function myDividends(int $uid, int $page, int $limit): array
    {
        $query = EquityDividend::getDB()->where('uid', $uid)->where('role_type', 1);
        $count = $query->count();
        $list = $query->order('id', 'desc')->page($page, $limit)->select()->toArray();
        return compact('count', 'list');
    }

    public function financialReport(int $projectId, int $uid, string $start, string $end): array
    {
        $project = EquityProject::getDB()->where('id', $projectId)->find();
        if (!$project || (int)$project['status'] !== EquityProject::STATUS_OPERATING) {
            throw new ValidateException('仅营业中项目可查看财报');
        }
        $sh = EquityShareholder::getDB()->where('project_id', $projectId)->where('uid', $uid)->find();
        if (!$sh || (float)$sh['total_amount'] <= 0) {
            throw new ValidateException('仅股东可查看');
        }

        $startTs = strtotime($start . ' 00:00:00');
        $endTs = strtotime($end . ' 23:59:59');
        if (!$startTs || !$endTs || $startTs > $endTs) {
            throw new ValidateException('日期范围无效');
        }
        if (($endTs - $startTs) > 366 * 86400) {
            throw new ValidateException('跨度不能超过366天');
        }

        $onlineIncome = 0;
        $orderCount = 0;
        $refundRate = 0;
        if ((int)$project['new_store_id'] > 0) {
            $merId = (int)$project['new_store_id'];
            $onlineIncome = (float)Db::name('store_order')
                ->where('mer_id', $merId)
                ->where('paid', 1)
                ->where('pay_time', '>=', $startTs)
                ->where('pay_time', '<=', $endTs)
                ->sum('pay_price');
            $orderCount = (int)Db::name('store_order')
                ->where('mer_id', $merId)
                ->where('paid', 1)
                ->where('pay_time', '>=', $startTs)
                ->where('pay_time', '<=', $endTs)
                ->count();
            // 简化退款率
            $refundCount = (int)Db::name('store_refund_order')
                ->alias('r')
                ->join('store_order o', 'o.order_id = r.order_id')
                ->where('o.mer_id', $merId)
                ->where('r.status', 3)
                ->where('r.create_time', '>=', date('Y-m-d H:i:s', $startTs))
                ->where('r.create_time', '<=', date('Y-m-d H:i:s', $endTs))
                ->count();
            $refundRate = $orderCount > 0 ? round($refundCount / $orderCount * 100, 2) : 0;
        }

        $manual = EquityFinancialReport::getDB()
            ->where('project_id', $projectId)
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->order('id', 'desc')
            ->select()
            ->toArray();

        $cashIncome = 0;
        $expense = [];
        $cost = [];
        $staffCount = 0;
        $staffWageTotal = 0;
        $staffWageAvg = 0;
        foreach ($manual as $m) {
            $cashIncome = bcadd((string)$cashIncome, (string)$m['cash_income'], 2);
            $expense = array_merge($expense, json_decode($m['expense_json'] ?: '[]', true) ?: []);
            $cost = array_merge($cost, json_decode($m['cost_json'] ?: '[]', true) ?: []);
            $staffCount = max($staffCount, (int)$m['staff_count']);
            $staffWageTotal = bcadd((string)$staffWageTotal, (string)$m['staff_wage_total'], 2);
            $staffWageAvg = (float)$m['staff_wage_avg'] ?: $staffWageAvg;
        }

        $totalIncome = bcadd((string)$onlineIncome, (string)$cashIncome, 2);
        $expenseSum = '0.00';
        foreach ($expense as $e) {
            $expenseSum = bcadd($expenseSum, (string)($e['amount'] ?? 0), 2);
        }
        $costSum = '0.00';
        foreach ($cost as $c) {
            $costSum = bcadd($costSum, (string)($c['amount'] ?? 0), 2);
        }
        $net = bcsub(bcsub($totalIncome, $expenseSum, 2), $costSum, 2);
        $avgOrder = $orderCount > 0 ? round((float)$onlineIncome / $orderCount, 2) : 0;

        return [
            'start_date' => $start,
            'end_date' => $end,
            'online_income' => (float)$onlineIncome,
            'cash_income' => (float)$cashIncome,
            'cash_income_note' => '现金收款为手工录入，仅月度/季度/年度更新',
            'total_income' => (float)$totalIncome,
            'expense_list' => $expense,
            'expense_total' => (float)$expenseSum,
            'cost_list' => $cost,
            'cost_total' => (float)$costSum,
            'gross_profit' => (float)bcsub($totalIncome, $costSum, 2),
            'net_profit' => (float)$net,
            'staff_count' => $staffCount,
            'staff_wage_total' => (float)$staffWageTotal,
            'staff_wage_avg' => (float)$staffWageAvg,
            'order_count' => $orderCount,
            'avg_order_price' => $avgOrder,
            'refund_rate' => $refundRate,
        ];
    }

    public function saveFinancialReport(int $projectId, array $data, int $adminId): array
    {
        $start = (string)($data['start_date'] ?? '');
        $end = (string)($data['end_date'] ?? '');
        if (!$start || !$end || strtotime($start) === false || strtotime($end) === false) {
            throw new ValidateException('请选择有效的开始/结束日期');
        }
        if (strtotime($start) > strtotime($end)) {
            throw new ValidateException('开始日期不能晚于结束日期');
        }

        $payload = [
            'project_id' => $projectId,
            'start_date' => $start,
            'end_date' => $end,
            'cash_income' => round((float)($data['cash_income'] ?? 0), 2),
            'expense_json' => json_encode($data['expense_list'] ?? [], JSON_UNESCAPED_UNICODE),
            'cost_json' => json_encode($data['cost_list'] ?? [], JSON_UNESCAPED_UNICODE),
            'staff_count' => (int)($data['staff_count'] ?? 0),
            'staff_wage_total' => round((float)($data['staff_wage_total'] ?? 0), 2),
            'staff_wage_avg' => round((float)($data['staff_wage_avg'] ?? 0), 2),
            'staff_wage_structure' => (string)($data['staff_wage_structure'] ?? ''),
            'remark' => (string)($data['remark'] ?? ''),
            'admin_id' => $adminId,
        ];

        // 同一项目 + 同一起止日期：覆盖保存，防止重复提交翻倍
        $exist = EquityFinancialReport::getDB()
            ->where('project_id', $projectId)
            ->where('start_date', $start)
            ->where('end_date', $end)
            ->order('id', 'asc')
            ->select()
            ->toArray();

        if ($exist) {
            $keepId = (int)$exist[0]['id'];
            EquityFinancialReport::getDB()->where('id', $keepId)->update($payload);
            $dupIds = array_column(array_slice($exist, 1), 'id');
            if ($dupIds) {
                EquityFinancialReport::getDB()->whereIn('id', $dupIds)->delete();
            }
            $id = $keepId;
        } else {
            $id = EquityFinancialReport::getDB()->insertGetId($payload);
        }

        return ['id' => $id, 'updated' => !empty($exist)];
    }

    public function financialReportList(array $where, int $page, int $limit): array
    {
        $query = EquityFinancialReport::getDB()->alias('f')
            ->leftJoin('equity_project p', 'p.id = f.project_id');
        if (!empty($where['project_id'])) {
            $query->where('f.project_id', (int)$where['project_id']);
        }
        $count = (clone $query)->count();
        $list = $query->field('f.*,p.round_no,p.mer_id,p.new_store_id,p.status as project_status')
            ->order('f.id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        foreach ($list as &$row) {
            $mer = Merchant::getDB()->where('mer_id', (int)$row['mer_id'])->find();
            $row['store_name'] = $mer['mer_name'] ?? '';
            if (!empty($row['new_store_id'])) {
                $newMer = Merchant::getDB()->where('mer_id', (int)$row['new_store_id'])->find();
                $row['new_store_name'] = $newMer['mer_name'] ?? '';
            } else {
                $row['new_store_name'] = '';
            }
            $expense = json_decode($row['expense_json'] ?: '[]', true) ?: [];
            $cost = json_decode($row['cost_json'] ?: '[]', true) ?: [];
            $row['expense_list'] = $expense;
            $row['cost_list'] = $cost;
            $expenseSum = 0;
            foreach ($expense as $e) {
                $expenseSum += (float)($e['amount'] ?? 0);
            }
            $costSum = 0;
            foreach ($cost as $c) {
                $costSum += (float)($c['amount'] ?? 0);
            }
            $row['expense_total'] = round($expenseSum, 2);
            $row['cost_total'] = round($costSum, 2);
            $row['create_time_text'] = $row['create_time'] ?? '';
        }
        unset($row);
        return compact('count', 'list');
    }

    public function deleteFinancialReport(int $id): void
    {
        $row = EquityFinancialReport::getDB()->where('id', $id)->find();
        if (!$row) {
            throw new ValidateException('记录不存在');
        }
        EquityFinancialReport::getDB()->where('id', $id)->delete();
    }

    public function saveStaffPool(int $projectId, array $list): void
    {
        $sum = 0;
        foreach ($list as $item) {
            $sum += (float)($item['pool_ratio'] ?? 0);
        }
        if ($sum > 100.0001) {
            throw new ValidateException('员工激励池占比合计不能超过100%');
        }
        Db::transaction(function () use ($projectId, $list) {
            EquityStaffPool::getDB()->where('project_id', $projectId)->delete();
            foreach ($list as $item) {
                EquityStaffPool::getDB()->insert([
                    'project_id' => $projectId,
                    'staff_name' => (string)($item['staff_name'] ?? ''),
                    'staff_uid' => (int)($item['staff_uid'] ?? 0),
                    'pool_ratio' => round((float)($item['pool_ratio'] ?? 0), 4),
                ]);
            }
        });
    }

    public function staffPoolList(int $projectId): array
    {
        return EquityStaffPool::getDB()->where('project_id', $projectId)->select()->toArray();
    }

    public function saveNotice(array $data, int $id = 0): array
    {
        $payload = [
            'project_id' => (int)$data['project_id'],
            'title' => (string)($data['title'] ?? ''),
            'period' => (string)($data['period'] ?? ''),
            'expected_date' => (string)($data['expected_date'] ?? ''),
            'expected_amount' => isset($data['expected_amount']) ? round((float)$data['expected_amount'], 2) : null,
            'content' => (string)($data['content'] ?? ''),
            'status' => (int)($data['status'] ?? EquityDividendNotice::STATUS_DRAFT),
        ];
        if ($payload['status'] === EquityDividendNotice::STATUS_PUBLISHED) {
            $payload['published_at'] = time();
        }
        if ($id > 0) {
            EquityDividendNotice::getDB()->where('id', $id)->update($payload);
        } else {
            $id = EquityDividendNotice::getDB()->insertGetId($payload);
        }
        return EquityDividendNotice::getDB()->where('id', $id)->find()->toArray();
    }

    public function withdrawNotice(int $id): void
    {
        EquityDividendNotice::getDB()->where('id', $id)->update([
            'status' => EquityDividendNotice::STATUS_WITHDRAWN,
        ]);
    }

    public function noticeList(array $where, int $page, int $limit): array
    {
        $query = EquityDividendNotice::getDB();
        if (!empty($where['project_id'])) {
            $query->where('project_id', (int)$where['project_id']);
        }
        $count = $query->count();
        $list = $query->order('id', 'desc')->page($page, $limit)->select()->toArray();
        return compact('count', 'list');
    }

    protected function progressPct($current, $target): float
    {
        $target = (float)$target;
        if ($target <= 0) {
            return 0;
        }
        return round(min(100, (float)$current / $target * 100), 2);
    }

    protected function formatRatio($ratio): string
    {
        return '约' . number_format((float)$ratio * 100, 2, '.', '') . '%';
    }

    protected function statusText(int $status): string
    {
        return [1 => '筹集中', 2 => '待开业', 3 => '营业中'][$status] ?? '';
    }

    protected function yesterdayTurnover(int $merId): float
    {
        $start = strtotime('yesterday');
        $end = $start + 86400 - 1;
        return round((float)Db::name('store_order')
            ->where('mer_id', $merId)
            ->where('paid', 1)
            ->where('pay_time', '>=', $start)
            ->where('pay_time', '<=', $end)
            ->sum('pay_price'), 2);
    }
}
