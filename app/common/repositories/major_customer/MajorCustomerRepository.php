<?php

namespace app\common\repositories\major_customer;

use app\common\model\major_customer\MajorCustomerConfig;
use app\common\model\major_customer\MajorCustomerPlan;
use app\common\model\system\merchant\MerchantAdmin;
use app\common\model\user\User;
use app\common\repositories\BaseRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserRepository;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;

class MajorCustomerRepository extends BaseRepository
{
    public const ANONYMOUS_SUFFIX = '（此客户公司要求显示匿名）';

    protected function companyPoolList(): array
    {
        return [
            ['code' => 'A', 'name' => '甲公司企业'],
            ['code' => 'B', 'name' => '乙公司企业'],
            ['code' => 'C', 'name' => '丙公司企业'],
            ['code' => 'D', 'name' => '丁公司企业'],
            ['code' => 'E', 'name' => '戊公司企业'],
        ];
    }

    public function resolveMerIdByUid(int $uid): int
    {
        if ($uid <= 0) {
            return 0;
        }
        $user = User::getDB()->where('uid', $uid)->field('mer_id')->find();
        if ($user && (int)$user['mer_id'] > 0) {
            return (int)$user['mer_id'];
        }
        $merId = MerchantAdmin::getDB()
            ->where('uid', $uid)
            ->where('is_del', 0)
            ->where('status', 1)
            ->value('mer_id');
        return (int)$merId;
    }

    public function getConfigPayload(int $merId): array
    {
        $config = $this->getOrCreateConfigRow($merId);
        $plans = MajorCustomerPlan::getDB()
            ->where('mer_id', $merId)
            ->order('sort ASC,plan_id ASC')
            ->select()
            ->toArray();

        $prestore = $this->listPrestoreForMerchant($merId, 1, 50);

        return [
            'status' => (int)$config['status'],
            'share_status' => (int)$config['share_status'],
            'referrer_phone' => (string)$config['referrer_phone'],
            'referrer_phone_locked' => (string)$config['referrer_phone'] !== '',
            'referrer_fill_closed' => (int)($config['referrer_fill_closed'] ?? 0),
            'referrer_can_edit' => $this->referrerCanEdit($config),
            'referrer_uid' => (int)$config['referrer_uid'],
            'opened_at' => $config['opened_at'],
            'share_opened_at' => $config['share_opened_at'],
            'plans' => array_map(function ($p) {
                return [
                    'plan_id' => (int)$p['plan_id'],
                    'a' => (string)$p['amount_a'],
                    'b' => (string)$p['amount_b'],
                    'status' => (int)$p['status'],
                    'sort' => (int)$p['sort'],
                ];
            }, $plans),
            'prestore_list' => $prestore['list'] ?? [],
        ];
    }

    protected function getOrCreateConfigRow(int $merId): array
    {
        $row = MajorCustomerConfig::getDB()->where('mer_id', $merId)->find();
        if ($row) {
            return $row->toArray();
        }
        $now = date('Y-m-d H:i:s');
        MajorCustomerConfig::getDB()->insert([
            'mer_id' => $merId,
            'status' => 0,
            'share_status' => 0,
            'referrer_phone' => '',
            'referrer_uid' => 0,
            'first_batch_done' => 0,
            'referrer_fill_closed' => 0,
            'create_time' => $now,
            'update_time' => $now,
        ]);
        return MajorCustomerConfig::getDB()->where('mer_id', $merId)->find()->toArray();
    }

    protected function referrerCanEdit(array $config): bool
    {
        if ((string)($config['referrer_phone'] ?? '') !== '') {
            return false;
        }
        return (int)($config['referrer_fill_closed'] ?? 0) === 0;
    }

    /** 开通阶段未填推荐人时，保存方案/共享等操作后关闭补填窗口 */
    protected function maybeCloseReferrerFillWindow(int $merId): void
    {
        $config = MajorCustomerConfig::getDB()->where('mer_id', $merId)->find();
        if (!$config || (int)$config['referrer_fill_closed'] === 1) {
            return;
        }
        MajorCustomerConfig::getDB()->where('mer_id', $merId)->update([
            'referrer_fill_closed' => 1,
            'update_time' => date('Y-m-d H:i:s'),
        ]);
    }

    public function saveSwitch(int $merId, int $status): array
    {
        $status = $status ? 1 : 0;
        $config = $this->getOrCreateConfigRow($merId);
        $now = date('Y-m-d H:i:s');
        $update = ['status' => $status, 'update_time' => $now];
        if ($status === 1 && (int)$config['status'] === 0) {
            $update['referrer_fill_closed'] = 0;
        }
        if ($status === 1 && empty($config['opened_at'])) {
            $update['opened_at'] = $now;
            $this->scheduleFirstWeekRecharges($merId, $now);
        }
        if ($status === 0) {
            $this->cancelPendingRecharges($merId);
            $this->cancelPendingRebates($merId);
            Db::name('major_customer_virtual_account')
                ->where('mer_id', $merId)
                ->update(['status' => 0, 'update_time' => $now]);
        }
        MajorCustomerConfig::getDB()->where('mer_id', $merId)->update($update);
        return $this->getConfigPayload($merId);
    }

    public function saveShareSwitch(int $merId, int $status): array
    {
        $config = $this->getOrCreateConfigRow($merId);
        if ((int)$config['status'] !== 1) {
            throw new ValidateException('请先开通大客户卡');
        }
        $status = $status ? 1 : 0;
        $now = date('Y-m-d H:i:s');
        $update = ['share_status' => $status, 'update_time' => $now];
        if ($status === 1 && empty($config['share_opened_at'])) {
            $update['share_opened_at'] = $now;
        }
        MajorCustomerConfig::getDB()->where('mer_id', $merId)->update($update);
        $this->maybeCloseReferrerFillWindow($merId);
        return $this->getConfigPayload($merId);
    }

    public function saveReferrerPhone(int $merId, string $phone): array
    {
        $phone = preg_replace('/\D/', '', $phone);
        if ($phone !== '' && strlen($phone) !== 11) {
            throw new ValidateException('请输入 11 位手机号');
        }
        $config = $this->getOrCreateConfigRow($merId);
        if ((int)$config['status'] !== 1) {
            throw new ValidateException('请先开通大客户卡');
        }
        if ((string)$config['referrer_phone'] !== '') {
            throw new ValidateException('推荐人手机号填写后不可修改');
        }
        if ((int)($config['referrer_fill_closed'] ?? 0) === 1) {
            throw new ValidateException('开通时未填写推荐人，不可补填');
        }
        $referrerUid = 0;
        if ($phone !== '') {
            $referrerUid = (int)User::getDB()->where('phone', $phone)->value('uid');
            if ($referrerUid <= 0) {
                $phone = '';
            }
        }
        MajorCustomerConfig::getDB()->where('mer_id', $merId)->update([
            'referrer_phone' => $phone,
            'referrer_uid' => $referrerUid,
            'referrer_fill_closed' => 1,
            'update_time' => date('Y-m-d H:i:s'),
        ]);
        return $this->getConfigPayload($merId);
    }

    public function savePlans(int $merId, array $plans): array
    {
        $config = $this->getOrCreateConfigRow($merId);
        if ((int)$config['status'] !== 1) {
            throw new ValidateException('请先开通大客户卡');
        }
        if (count($plans) > 10) {
            throw new ValidateException('最多可添加 10 组充值方案');
        }
        if (count($plans) < 1) {
            throw new ValidateException('至少保留 1 组充值方案');
        }

        $normalized = [];
        foreach ($plans as $idx => $item) {
            $a = round((float)($item['a'] ?? $item['amount_a'] ?? 0), 2);
            $b = round((float)($item['b'] ?? $item['amount_b'] ?? 0), 2);
            if ($a <= 0) {
                throw new ValidateException('充值金额 A 必须大于 0');
            }
            if ($b < 0) {
                throw new ValidateException('赠送金额 B 不能小于 0');
            }
            $normalized[] = [
                'plan_id' => (int)($item['plan_id'] ?? 0),
                'amount_a' => $a,
                'amount_b' => $b,
                'status' => isset($item['status']) ? ((int)$item['status'] ? 1 : 0) : 1,
                'sort' => (int)($item['sort'] ?? $idx),
            ];
        }

        $now = date('Y-m-d H:i:s');
        Db::transaction(function () use ($merId, $normalized, $now) {
            $existingIds = MajorCustomerPlan::getDB()->where('mer_id', $merId)->column('plan_id');
            $keepIds = [];
            foreach ($normalized as $row) {
                if ($row['plan_id'] > 0 && in_array($row['plan_id'], $existingIds, true)) {
                    MajorCustomerPlan::getDB()->where('plan_id', $row['plan_id'])->update([
                        'amount_a' => $row['amount_a'],
                        'amount_b' => $row['amount_b'],
                        'status' => $row['status'],
                        'sort' => $row['sort'],
                        'update_time' => $now,
                    ]);
                    $keepIds[] = $row['plan_id'];
                } else {
                    $id = MajorCustomerPlan::getDB()->insertGetId([
                        'mer_id' => $merId,
                        'amount_a' => $row['amount_a'],
                        'amount_b' => $row['amount_b'],
                        'status' => $row['status'],
                        'sort' => $row['sort'],
                        'create_time' => $now,
                        'update_time' => $now,
                    ]);
                    $keepIds[] = $id;
                }
            }
            if ($existingIds) {
                $deleteIds = array_diff($existingIds, $keepIds);
                if ($deleteIds) {
                    $used = Db::name('major_customer_virtual_recharge')
                        ->where('mer_id', $merId)
                        ->whereIn('plan_id', $deleteIds)
                        ->where('status', 1)
                        ->column('plan_id');
                    $used = array_unique(array_map('intval', (array)$used));
                    foreach ($deleteIds as $delId) {
                        if (in_array((int)$delId, $used, true)) {
                            MajorCustomerPlan::getDB()->where('plan_id', $delId)->update([
                                'status' => 0,
                                'update_time' => $now,
                            ]);
                        } else {
                            MajorCustomerPlan::getDB()->where('plan_id', $delId)->delete();
                        }
                    }
                }
            }
        });

        $config = MajorCustomerConfig::getDB()->where('mer_id', $merId)->find();
        if ($config && (int)$config['status'] === 1 && !(int)$config['first_batch_done']) {
            $pending = Db::name('major_customer_virtual_recharge')
                ->where('mer_id', $merId)
                ->where('trigger_type', 'first_week')
                ->whereIn('status', [0, 1])
                ->count();
            if ($pending <= 0) {
                $this->scheduleFirstWeekRecharges($merId, $config['opened_at'] ?: date('Y-m-d H:i:s'));
            }
        }

        $this->maybeCloseReferrerFillWindow($merId);
        return $this->getConfigPayload($merId);
    }

    public function getPlatformConfig(): array
    {
        return [
            'major_customer_commission_rate' => $this->commissionRate(),
            'major_customer_balance_low_ratio' => (float)(systemConfig('major_customer_balance_low_ratio') ?: 0.1),
            'major_customer_balance_cap_multiple' => (float)(systemConfig('major_customer_balance_cap_multiple') ?: 5),
        ];
    }

    public function savePlatformConfig(array $data): array
    {
        $rate = isset($data['major_customer_commission_rate']) ? (float)$data['major_customer_commission_rate'] : $this->commissionRate();
        if ($rate > 1 && $rate <= 100) {
            $rate = $rate / 100;
        }
        if ($rate < 0 || $rate > 1) {
            throw new ValidateException('佣金比例需在 0~1 之间（或填 0~100 表示百分比）');
        }
        $low = isset($data['major_customer_balance_low_ratio']) ? (float)$data['major_customer_balance_low_ratio'] : 0.1;
        $mult = isset($data['major_customer_balance_cap_multiple']) ? (float)$data['major_customer_balance_cap_multiple'] : 5;
        if ($low <= 0 || $low > 1) {
            throw new ValidateException('余额接近 0 阈值比例需在 0~1 之间');
        }
        if ($mult <= 0 || $mult > 20) {
            throw new ValidateException('余额上限倍数需在 0~20 之间');
        }
        $this->writePlatformConfigValue('major_customer_commission_rate', (string)$rate);
        $this->writePlatformConfigValue('major_customer_balance_low_ratio', (string)$low);
        $this->writePlatformConfigValue('major_customer_balance_cap_multiple', (string)$mult);
        try {
            app()->make(\app\common\repositories\system\config\ConfigValueRepository::class)->syncConfig();
        } catch (\Throwable $e) {
        }
        return $this->getPlatformConfig();
    }

    protected function writePlatformConfigValue(string $key, string $value): void
    {
        $row = Db::name('system_config_value')->where('mer_id', 0)->where('config_key', $key)->find();
        if ($row) {
            Db::name('system_config_value')->where('config_value_id', $row['config_value_id'])->update(['value' => $value]);
            return;
        }
        Db::name('system_config_value')->insert([
            'mer_id' => 0,
            'config_key' => $key,
            'value' => $value,
        ]);
    }

    public function listAdminVirtualRecharges(int $page, int $limit, array $where = []): array
    {
        $query = Db::name('major_customer_virtual_recharge')->alias('r')
            ->leftJoin('major_customer_virtual_account a', 'r.account_id=a.account_id')
            ->leftJoin('merchant m', 'r.mer_id=m.mer_id')
            ->order('r.recharge_id DESC');
        if (!empty($where['mer_id'])) {
            $query->where('r.mer_id', (int)$where['mer_id']);
        }
        $count = (clone $query)->count();
        $list = $query->page($page, $limit)
            ->field('r.*,a.company_name,a.company_code,m.mer_name')
            ->select()
            ->toArray();
        return compact('count', 'list');
    }

    public function listPrestoreForMerchant(int $merId, int $page = 1, int $limit = 20): array
    {
        $query = Db::name('major_customer_prestore')->alias('p')
            ->leftJoin('major_customer_virtual_account a', 'p.account_id=a.account_id')
            ->where('p.mer_id', $merId)
            ->order('p.deposit_date DESC,p.id DESC');

        $count = (clone $query)->count();
        $list = $query->page($page, $limit)->field('p.*,a.company_code')->select()->toArray();

        return [
            'count' => $count,
            'list' => array_map(function ($row) {
                $code = (string)($row['company_code'] ?: '某');
                return [
                    'name' => ($code ?: '某') . '公司' . self::ANONYMOUS_SUFFIX,
                    'date' => $row['deposit_date'] ?: '',
                    'deposit' => (float)$row['latest_deposit'],
                    'balance' => (float)$row['period_balance'],
                    'update_time' => $row['update_time'] ?? '',
                ];
            }, $list),
        ];
    }

    public function getCommissionSummary(int $uid): array
    {
        $total = (float)Db::name('major_customer_settlement')
            ->where('referrer_uid', $uid)
            ->sum('referrer_y');
        $withdrawn = (float)Db::name('user_extract')
            ->where('uid', $uid)
            ->where('status', 1)
            ->sum('extract_price');
        $available = (float)User::getDB()->where('uid', $uid)->value('brokerage_price');
        return [
            'total' => round($total, 2),
            'available' => round($available, 2),
            'withdrawn' => round($withdrawn, 2),
        ];
    }

    public function getCommissionList(int $uid, int $page, int $limit): array
    {
        $query = Db::name('major_customer_settlement')->alias('s')
            ->leftJoin('merchant m', 's.mer_id=m.mer_id')
            ->where('s.referrer_uid', $uid)
            ->where('s.referrer_y', '>', 0)
            ->order('s.id DESC');
        $count = (clone $query)->count();
        $list = $query->page($page, $limit)
            ->field('s.*,m.mer_name as merchant_name')
            ->select()
            ->toArray();

        return [
            'count' => $count,
            'list' => array_map(function ($row) {
                return [
                    'id' => (int)$row['id'],
                    'merchant_name' => (string)($row['merchant_name'] ?? ''),
                    'order_sn' => (string)$row['order_sn'],
                    'amount' => (float)$row['referrer_y'],
                    'status' => 'available',
                    'arrival_time' => $row['settled_at'] ?? '',
                    'withdraw_status' => '',
                ];
            }, $list),
        ];
    }

    public function getRebateList(int $uid, int $page, int $limit): array
    {
        $query = Db::name('major_customer_rebate')
            ->where('uid', $uid)
            ->order('id DESC');
        $count = (clone $query)->count();
        $list = $query->page($page, $limit)->select()->toArray();
        return compact('count', 'list');
    }

    /** 买单支付成功：结算 D/Y + 排返现 + 扣虚拟余额 */
    public function onBillOrderPaid(array $billOrder): ?array
    {
        $merId = (int)($billOrder['mer_id'] ?? 0);
        $uid = (int)($billOrder['uid'] ?? 0);
        $billId = (int)($billOrder['id'] ?? 0);
        $orderSn = (string)($billOrder['order_sn'] ?? '');
        $C = round((float)($billOrder['pay_price'] ?? 0), 2);
        if ($merId <= 0 || $billId <= 0 || $C <= 0 || $uid <= 0) {
            return null;
        }
        if (Db::name('major_customer_settlement')->where('bill_order_id', $billId)->find()) {
            return null;
        }

        $config = MajorCustomerConfig::getDB()->where('mer_id', $merId)->find();
        if (!$config || (int)$config['status'] !== 1 || (int)$config['share_status'] !== 1) {
            return null;
        }
        if (!$this->isShareEffective($config)) {
            return null;
        }

        $planCtx = $this->resolveSettlementPlan($merId);
        if (!$planCtx) {
            return null;
        }
        $A = (float)$planCtx['amount_a'];
        $B = (float)$planCtx['amount_b'];
        $hasReferrer = (int)$config['referrer_uid'] > 0;
        $X = $this->commissionRate();
        [$D, $Y] = $this->calcSettlement($C, $A, $B, $X, $hasReferrer);
        if ($D <= 0) {
            return null;
        }

        $den = $A + $B;
        $rebate = $den > 0 ? $this->truncateMoney(($C / $den) * $B) : 0;

        $now = date('Y-m-d H:i:s');
        Db::transaction(function () use ($merId, $uid, $billId, $orderSn, $C, $planCtx, $A, $B, $X, $D, $Y, $config, $rebate, $now) {
            Db::name('major_customer_settlement')->insert([
                'mer_id' => $merId,
                'uid' => $uid,
                'bill_order_id' => $billId,
                'order_sn' => $orderSn,
                'pay_amount_c' => $C,
                'plan_id' => (int)$planCtx['plan_id'],
                'amount_a' => $A,
                'amount_b' => $B,
                'commission_x' => $X,
                'merchant_d' => $D,
                'referrer_y' => $Y,
                'referrer_uid' => (int)$config['referrer_uid'],
                'virtual_account_id' => (int)$planCtx['account_id'],
                'status' => 1,
                'settled_at' => $now,
                'create_time' => $now,
            ]);

            Db::name('major_customer_virtual_account')
                ->where('account_id', (int)$planCtx['account_id'])
                ->dec('balance', $C)
                ->inc('total_consume', $C)
                ->update(['update_time' => $now]);

            if ($Y > 0 && (int)$config['referrer_uid'] > 0) {
                $this->creditReferrerCommission((int)$config['referrer_uid'], $Y, $billId, $merId);
            }

            if ($rebate > 0) {
                $scheduledAt = $this->randomRebateTimeWithinWeek();
                Db::name('major_customer_rebate')->insert([
                    'mer_id' => $merId,
                    'uid' => $uid,
                    'bill_order_id' => $billId,
                    'order_sn' => $orderSn,
                    'original_amount' => $C,
                    'rebate_amount' => $rebate,
                    'status' => 0,
                    'scheduled_at' => $scheduledAt,
                    'create_time' => $now,
                ]);
            }

            $this->syncPrestoreRow($merId, (int)$planCtx['account_id']);
            $this->maybeScheduleLowBalanceRecharge($merId);
        });

        return [
            'merchant_d' => $D,
            'referrer_y' => $Y,
            'rebate_amount' => $rebate,
        ];
    }

    public function merchantLockAmountForBill(array $billOrder, ?array $settlement): float
    {
        $amount = round((float)($billOrder['pay_price'] ?? 0), 2);
        if ($settlement && isset($settlement['merchant_d'])) {
            return (float)$settlement['merchant_d'];
        }
        return $amount;
    }

    protected function creditReferrerCommission(int $referrerUid, float $Y, int $billId, int $merId): void
    {
        /** @var UserRepository $userRepo */
        $userRepo = app()->make(UserRepository::class);
        $user = $userRepo->get($referrerUid);
        if (!$user) {
            return;
        }
        $user->brokerage_price = bcadd((string)$user->brokerage_price, (string)$Y, 2);
        $user->save();
        app()->make(UserBillRepository::class)->incBill($referrerUid, 'brokerage', 'major_customer_referrer', [
            'link_id' => $billId,
            'status' => 1,
            'title' => '大客户卡推荐佣金',
            'number' => $Y,
            'mark' => '商户ID:' . $merId . ' 买单推荐佣金',
            'balance' => $user->brokerage_price,
        ]);
    }

    protected function isShareEffective($config): bool
    {
        $opened = $config['share_opened_at'] ?? $config['opened_at'] ?? null;
        if (!$opened) {
            return false;
        }
        return strtotime($opened) <= time() - 7 * 86400;
    }

    protected function resolveSettlementPlan(int $merId): ?array
    {
        $recharge = Db::name('major_customer_virtual_recharge')
            ->where('mer_id', $merId)
            ->where('status', 1)
            ->order('executed_at DESC,recharge_id DESC')
            ->find();
        if (!$recharge) {
            return null;
        }
        $account = Db::name('major_customer_virtual_account')
            ->where('account_id', (int)$recharge['account_id'])
            ->where('status', 1)
            ->find();
        if (!$account || (float)$account['balance'] <= 0) {
            return null;
        }
        return [
            'plan_id' => (int)$recharge['plan_id'],
            'amount_a' => (float)$recharge['amount_a'],
            'amount_b' => (float)$recharge['amount_b'],
            'account_id' => (int)$recharge['account_id'],
        ];
    }

    public function commissionRate(): float
    {
        $raw = systemConfig('major_customer_commission_rate');
        if ($raw === '' || $raw === null) {
            return 0.05;
        }
        $x = (float)$raw;
        if ($x > 1) {
            $x = $x / 100;
        }
        return max(0, min(1, $x));
    }

    public function calcSettlement(float $C, float $A, float $B, float $X, bool $hasReferrer): array
    {
        if ($C <= 0 || $A <= 0) {
            return [0.0, 0.0];
        }
        if (!$hasReferrer) {
            $den = $A + $B;
            if ($den <= 0) {
                return [0.0, 0.0];
            }
            $D = $this->truncateMoney(($C / $den) * $A);
            return [$D, 0.0];
        }
        $den = $A + $B * (1 - $X);
        if ($den <= 0) {
            return [0.0, 0.0];
        }
        $D = $this->truncateMoney(($C / $den) * $A);
        $inner = ($A / $den) * ($A + $B) - $A;
        $Y = $this->truncateMoney(($C / $den) * $inner);
        return [$D, max(0, $Y)];
    }

    public function truncateMoney(float $value): float
    {
        if ($value <= 0) {
            return 0.0;
        }
        return floor($value * 100) / 100;
    }

    protected function randomRebateTimeWithinWeek(): string
    {
        $offset = random_int(3600, 7 * 86400 - 3600);
        return date('Y-m-d H:i:s', time() + $offset);
    }

    public function runPendingVirtualRecharges(): int
    {
        $now = date('Y-m-d H:i:s');
        $rows = Db::name('major_customer_virtual_recharge')
            ->where('status', 0)
            ->where('scheduled_at', '<=', $now)
            ->limit(200)
            ->select()
            ->toArray();
        $done = 0;
        foreach ($rows as $row) {
            try {
                if ($this->executeVirtualRecharge($row)) {
                    $done++;
                }
            } catch (\Throwable $e) {
                Log::error('major_customer recharge failed id=' . $row['recharge_id'] . ' ' . $e->getMessage());
                Db::name('major_customer_virtual_recharge')->where('recharge_id', $row['recharge_id'])->update(['status' => 2]);
            }
        }
        return $done;
    }

    public function runDueRebates(): int
    {
        $now = date('Y-m-d H:i:s');
        $rows = Db::name('major_customer_rebate')
            ->where('status', 0)
            ->where('scheduled_at', '<=', $now)
            ->limit(200)
            ->select()
            ->toArray();
        $done = 0;
        foreach ($rows as $row) {
            try {
                $this->payRebate($row);
                $done++;
            } catch (\Throwable $e) {
                Log::error('major_customer rebate failed id=' . $row['id'] . ' ' . $e->getMessage());
                Db::name('major_customer_rebate')->where('id', $row['id'])->update(['status' => 2]);
            }
        }
        return $done;
    }

    public function runWeeklyPrestoreSnapshot(): int
    {
        $merIds = MajorCustomerConfig::getDB()->where('status', 1)->column('mer_id');
        $count = 0;
        foreach ($merIds as $merId) {
            $accounts = Db::name('major_customer_virtual_account')
                ->where('mer_id', (int)$merId)
                ->where('status', 1)
                ->select()
                ->toArray();
            foreach ($accounts as $acc) {
                $this->syncPrestoreRow((int)$merId, (int)$acc['account_id'], true);
                $count++;
            }
        }
        return $count;
    }

    protected function payRebate(array $row): void
    {
        $merId = (int)($row['mer_id'] ?? 0);
        if ($merId > 0) {
            $config = MajorCustomerConfig::getDB()->where('mer_id', $merId)->find();
            if (!$config || (int)$config['status'] !== 1 || (int)$config['share_status'] !== 1) {
                Db::name('major_customer_rebate')->where('id', $row['id'])->update(['status' => 3]);
                return;
            }
        }

        $uid = (int)$row['uid'];
        $amount = round((float)$row['rebate_amount'], 2);
        if ($uid <= 0 || $amount <= 0) {
            Db::name('major_customer_rebate')->where('id', $row['id'])->update(['status' => 1, 'paid_at' => date('Y-m-d H:i:s')]);
            return;
        }
        Db::transaction(function () use ($uid, $amount, $row) {
            $userRepo = app()->make(UserRepository::class);
            $user = $userRepo->get($uid);
            $user->now_money = bcadd((string)$user->now_money, (string)$amount, 2);
            $user->save();
            app()->make(UserBillRepository::class)->incBill($uid, 'now_money', 'major_customer_rebate', [
                'link_id' => (int)$row['bill_order_id'],
                'status' => 1,
                'title' => '大客户卡折扣返还',
                'number' => $amount,
                'mark' => '订单 ' . ($row['order_sn'] ?? '') . ' 共享折扣返还',
                'balance' => $user->now_money,
            ]);
            Db::name('major_customer_rebate')->where('id', $row['id'])->update([
                'status' => 1,
                'paid_at' => date('Y-m-d H:i:s'),
            ]);
        });
    }

    protected function executeVirtualRecharge(array $task): bool
    {
        $merId = (int)$task['mer_id'];
        $config = MajorCustomerConfig::getDB()->where('mer_id', $merId)->find();
        if (!$config || (int)$config['status'] !== 1) {
            Db::name('major_customer_virtual_recharge')->where('recharge_id', $task['recharge_id'])->update(['status' => 3]);
            return false;
        }
        if ($task['trigger_type'] === 'first_week' && (int)$config['share_status'] === 0) {
            // 未开共享：首批完成后不再充值 — 单条任务仍执行
        }
        if ($task['trigger_type'] === 'balance_low' && (int)$config['share_status'] !== 1) {
            Db::name('major_customer_virtual_recharge')->where('recharge_id', $task['recharge_id'])->update(['status' => 3]);
            return false;
        }

        $plan = MajorCustomerPlan::getDB()->where('plan_id', (int)$task['plan_id'])->find();
        if (!$plan || (int)$plan['status'] !== 1) {
            Db::name('major_customer_virtual_recharge')->where('recharge_id', $task['recharge_id'])->update(['status' => 2]);
            return false;
        }

        $A = (float)$task['amount_a'];
        $B = (float)$task['amount_b'];
        $cap = $this->balanceCap($merId);
        $accountId = (int)$task['account_id'];
        if ($accountId <= 0) {
            $accountId = $this->pickOrCreateVirtualAccount($merId);
        }
        $account = Db::name('major_customer_virtual_account')->where('account_id', $accountId)->find();
        $newBalance = round((float)$account['balance'] + $A + $B, 2);
        if ($cap > 0 && $newBalance > $cap) {
            $room = $cap - (float)$account['balance'];
            if ($room <= 0) {
                Db::name('major_customer_virtual_recharge')->where('recharge_id', $task['recharge_id'])->update(['status' => 3]);
                return false;
            }
            $scale = $room / ($A + $B);
            $A = $this->truncateMoney($A * $scale);
            $B = $this->truncateMoney($B * $scale);
            $newBalance = round((float)$account['balance'] + $A + $B, 2);
        }

        $now = date('Y-m-d H:i:s');
        Db::name('major_customer_virtual_account')->where('account_id', $accountId)->update([
            'balance' => $newBalance,
            'total_recharge_a' => round((float)$account['total_recharge_a'] + $A, 2),
            'total_gift_b' => round((float)$account['total_gift_b'] + $B, 2),
            'update_time' => $now,
        ]);
        Db::name('major_customer_virtual_recharge')->where('recharge_id', $task['recharge_id'])->update([
            'account_id' => $accountId,
            'amount_a' => $A,
            'amount_b' => $B,
            'deposit_amount' => $A,
            'balance_after' => $newBalance,
            'status' => 1,
            'executed_at' => $now,
            'week_batch' => date('o-W'),
        ]);
        $this->syncPrestoreRow($merId, $accountId, true);

        if ($task['trigger_type'] === 'first_week') {
            $pendingFirst = Db::name('major_customer_virtual_recharge')
                ->where('mer_id', $merId)
                ->where('trigger_type', 'first_week')
                ->where('status', 0)
                ->count();
            if ($pendingFirst <= 0) {
                MajorCustomerConfig::getDB()->where('mer_id', $merId)->update(['first_batch_done' => 1]);
            }
        }
        return true;
    }

    protected function syncPrestoreRow(int $merId, int $accountId, bool $forceWeekBatch = false): void
    {
        $account = Db::name('major_customer_virtual_account')->where('account_id', $accountId)->find();
        if (!$account) {
            return;
        }
        $latest = Db::name('major_customer_virtual_recharge')
            ->where('account_id', $accountId)
            ->where('status', 1)
            ->order('executed_at DESC,recharge_id DESC')
            ->find();
        $deposit = $latest ? (float)$latest['deposit_amount'] : 0;
        $date = $latest && $latest['executed_at'] ? date('Y-m-d', strtotime($latest['executed_at'])) : date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $batch = date('o-W');
        $exists = Db::name('major_customer_prestore')
            ->where('mer_id', $merId)
            ->where('account_id', $accountId)
            ->find();
        $payload = [
            'latest_deposit' => $deposit,
            'period_balance' => (float)$account['balance'],
            'balance_cap' => $this->balanceCap($merId),
            'deposit_date' => $date,
            'week_batch' => $batch,
            'update_time' => $now,
        ];
        if ($exists) {
            Db::name('major_customer_prestore')->where('id', $exists['id'])->update($payload);
        } else {
            $payload['mer_id'] = $merId;
            $payload['account_id'] = $accountId;
            Db::name('major_customer_prestore')->insert($payload);
        }
    }

    protected function balanceCap(int $merId): float
    {
        $multiple = (float)(systemConfig('major_customer_balance_cap_multiple') ?: 5);
        $plans = $this->enabledPlans($merId);
        if (!$plans) {
            return 0;
        }
        $maxSum = 0;
        foreach ($plans as $p) {
            $maxSum = max($maxSum, (float)$p['amount_a'] + (float)$p['amount_b']);
        }
        return round($maxSum * $multiple, 2);
    }

    protected function pickOrCreateVirtualAccount(int $merId): int
    {
        $existing = Db::name('major_customer_virtual_account')
            ->where('mer_id', $merId)
            ->where('status', 1)
            ->order('account_id ASC')
            ->find();
        if ($existing) {
            return (int)$existing['account_id'];
        }
        $poolList = $this->companyPoolList();
        $pool = $poolList[array_rand($poolList)];
        $now = date('Y-m-d H:i:s');
        return (int)Db::name('major_customer_virtual_account')->insertGetId([
            'mer_id' => $merId,
            'company_code' => $pool['code'],
            'company_name' => $pool['name'],
            'balance' => 0,
            'status' => 1,
            'create_time' => $now,
            'update_time' => $now,
        ]);
    }

    protected function scheduleFirstWeekRecharges(int $merId, string $openedAt): void
    {
        $plans = $this->enabledPlans($merId);
        if (!$plans) {
            return;
        }
        $count = random_int(1, 5);
        $deadline = strtotime($openedAt) + 7 * 86400;
        for ($i = 0; $i < $count; $i++) {
            $plan = $plans[array_rand($plans)];
            $accountId = $this->pickOrCreateVirtualAccount($merId);
            $scheduled = $this->randomBusinessDatetime(strtotime($openedAt), min(time() + 86400, $deadline));
            Db::name('major_customer_virtual_recharge')->insert([
                'mer_id' => $merId,
                'account_id' => $accountId,
                'plan_id' => (int)$plan['plan_id'],
                'amount_a' => (float)$plan['amount_a'],
                'amount_b' => (float)$plan['amount_b'],
                'deposit_amount' => (float)$plan['amount_a'],
                'trigger_type' => 'first_week',
                'status' => 0,
                'scheduled_at' => $scheduled,
                'create_time' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    protected function maybeScheduleLowBalanceRecharge(int $merId): void
    {
        $config = MajorCustomerConfig::getDB()->where('mer_id', $merId)->find();
        if (!$config || (int)$config['status'] !== 1 || (int)$config['share_status'] !== 1) {
            return;
        }
        $plans = $this->enabledPlans($merId);
        if (!$plans) {
            return;
        }
        $minPlan = $plans[0];
        foreach ($plans as $p) {
            $sum = (float)$p['amount_a'] + (float)$p['amount_b'];
            $minSum = (float)$minPlan['amount_a'] + (float)$minPlan['amount_b'];
            if ($sum < $minSum) {
                $minPlan = $p;
            }
        }
        $thresholdRatio = (float)(systemConfig('major_customer_balance_low_ratio') ?: 0.1);
        $threshold = max(1, round(((float)$minPlan['amount_a'] + (float)$minPlan['amount_b']) * $thresholdRatio, 2));
        $totalBalance = (float)Db::name('major_customer_virtual_account')
            ->where('mer_id', $merId)
            ->where('status', 1)
            ->sum('balance');
        if ($totalBalance > $threshold) {
            return;
        }
        $pending = Db::name('major_customer_virtual_recharge')
            ->where('mer_id', $merId)
            ->where('trigger_type', 'balance_low')
            ->where('status', 0)
            ->count();
        if ($pending > 0) {
            return;
        }
        $count = random_int(1, 5);
        for ($i = 0; $i < $count; $i++) {
            $plan = $plans[array_rand($plans)];
            Db::name('major_customer_virtual_recharge')->insert([
                'mer_id' => $merId,
                'account_id' => $this->pickOrCreateVirtualAccount($merId),
                'plan_id' => (int)$plan['plan_id'],
                'amount_a' => (float)$plan['amount_a'],
                'amount_b' => (float)$plan['amount_b'],
                'deposit_amount' => (float)$plan['amount_a'],
                'trigger_type' => 'balance_low',
                'status' => 0,
                'scheduled_at' => $this->randomBusinessDatetime(time(), time() + 5 * 86400),
                'create_time' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    protected function enabledPlans(int $merId): array
    {
        return MajorCustomerPlan::getDB()
            ->where('mer_id', $merId)
            ->where('status', 1)
            ->order('sort ASC,plan_id ASC')
            ->select()
            ->toArray();
    }

    protected function cancelPendingRecharges(int $merId): void
    {
        Db::name('major_customer_virtual_recharge')
            ->where('mer_id', $merId)
            ->where('status', 0)
            ->update(['status' => 3]);
    }

    /** 关闭大客户卡：取消待返还的共享折扣（已返还的不回滚） */
    protected function cancelPendingRebates(int $merId): void
    {
        Db::name('major_customer_rebate')
            ->where('mer_id', $merId)
            ->where('status', 0)
            ->update(['status' => 3]);
    }

    protected function randomBusinessDatetime(int $fromTs, int $toTs): string
    {
        if ($toTs <= $fromTs) {
            $toTs = $fromTs + 3600;
        }
        for ($try = 0; $try < 30; $try++) {
            $ts = random_int($fromTs, $toTs);
            $w = (int)date('N', $ts);
            $h = (int)date('G', $ts);
            if ($w >= 1 && $w <= 5 && $h >= 9 && $h < 18) {
                return date('Y-m-d H:i:s', $ts);
            }
        }
        return date('Y-m-d H:i:s', $fromTs + 3600);
    }
}
