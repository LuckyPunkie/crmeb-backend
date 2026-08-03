<?php
// +----------------------------------------------------------------------
// | AI 点餐 - 按商家计量计费
// +----------------------------------------------------------------------

namespace app\common\repositories\store\aiOrder;

use app\common\model\store\aiOrder\AiBalanceLog;
use app\common\model\store\aiOrder\AiOrderSession;
use app\common\model\system\merchant\Merchant;
use think\exception\ValidateException;
use think\facade\Db;

class AiOrderBillingRepository
{
    public function platformOpen(): bool
    {
        return (int)systemConfig('ai_order_open') === 1;
    }

    public function ratePer1k(): float
    {
        $v = systemConfig('ai_order_rate_per_1k');
        if ($v === '' || $v === null) {
            return (float)config('ai_order.default_rate_per_1k');
        }
        return max(0, (float)$v);
    }

    public function minBalance(): float
    {
        $v = systemConfig('ai_order_min_balance');
        if ($v === '' || $v === null) {
            return (float)config('ai_order.default_min_balance');
        }
        return max(0, (float)$v);
    }

    public function getBalance(int $merId): float
    {
        $row = Merchant::getDB()->where('mer_id', $merId)->field('ai_balance')->find();
        return $row ? round((float)$row['ai_balance'], 2) : 0.0;
    }

    public function assertCanStart(int $merId): void
    {
        if (!$this->platformOpen()) {
            throw new ValidateException('AI点餐暂未开放');
        }
        $balance = $this->getBalance($merId);
        if ($balance < $this->minBalance()) {
            throw new ValidateException('商家AI余额不足，暂无法通话');
        }
    }

    /**
     * 计算费用：ceil(tokens/1000) * rate
     */
    public function calcFee(int $usageTokens, ?float $rate = null): float
    {
        $rate = $rate === null ? $this->ratePer1k() : $rate;
        if ($usageTokens <= 0 || $rate <= 0) {
            return 0.0;
        }
        $units = (int)ceil($usageTokens / 1000);
        return round($units * $rate, 4);
    }

    /**
     * 会话结束扣费（幂等：同一 session_no + deduct 只扣一次）
     */
    public function deductForSession(AiOrderSession $session, int $usageTokens, int $usageSeconds = 0): array
    {
        $sessionNo = (string)$session['session_no'];
        $merId = (int)$session['mer_id'];
        if ($sessionNo === '' || $merId <= 0) {
            throw new ValidateException('会话无效');
        }
        if ((int)$session['deducted'] === 1) {
            return [
                'fee' => (float)$session['fee'],
                'balance' => $this->getBalance($merId),
                'duplicated' => true,
            ];
        }

        $rate = $this->ratePer1k();
        $fee = $this->calcFee($usageTokens, $rate);

        return Db::transaction(function () use ($session, $sessionNo, $merId, $usageTokens, $usageSeconds, $rate, $fee) {
            // 再次检查幂等
            $exists = AiBalanceLog::getDB()
                ->where('session_no', $sessionNo)
                ->where('type', AiBalanceLog::TYPE_DEDUCT)
                ->lock(true)
                ->find();
            if ($exists) {
                AiOrderSession::getDB()->where('id', $session['id'])->update([
                    'deducted' => 1,
                    'usage_tokens' => $usageTokens,
                    'usage_seconds' => $usageSeconds,
                    'fee' => (float)$exists['amount'] < 0 ? abs((float)$exists['amount']) : (float)$exists['amount'],
                    'rate' => $rate,
                ]);
                return [
                    'fee' => abs((float)$exists['amount']),
                    'balance' => $this->getBalance($merId),
                    'duplicated' => true,
                ];
            }

            $mer = Merchant::getDB()->where('mer_id', $merId)->lock(true)->find();
            if (!$mer) {
                throw new ValidateException('商户不存在');
            }
            $balance = round((float)$mer['ai_balance'], 2);
            $newBalance = round($balance - $fee, 2);
            // 允许扣成负数？1.0 不允许，余额不足则按实际余额扣光并标记失败备注
            if ($fee > 0 && $newBalance < 0) {
                if ($balance <= 0) {
                    $fee = 0;
                    $newBalance = $balance;
                } else {
                    $fee = $balance;
                    $newBalance = 0;
                }
            }

            if ($fee > 0) {
                Merchant::getDB()->where('mer_id', $merId)->update(['ai_balance' => $newBalance]);
            } else {
                $newBalance = $balance;
            }

            AiBalanceLog::getDB()->insert([
                'mer_id' => $merId,
                'type' => AiBalanceLog::TYPE_DEDUCT,
                'amount' => -$fee,
                'balance' => $newBalance,
                'session_no' => $sessionNo,
                'usage_tokens' => $usageTokens,
                'remark' => 'AI点餐通话扣费',
                'admin_id' => 0,
                'create_time' => date('Y-m-d H:i:s'),
            ]);

            AiOrderSession::getDB()->where('id', $session['id'])->update([
                'usage_tokens' => $usageTokens,
                'usage_seconds' => $usageSeconds,
                'fee' => $fee,
                'rate' => $rate,
                'deducted' => 1,
            ]);

            return [
                'fee' => $fee,
                'balance' => $newBalance,
                'duplicated' => false,
            ];
        });
    }

    /**
     * 平台代充 / 调账
     * @param float $amount 正数增加，负数扣减
     */
    public function adjustBalance(int $merId, float $amount, string $remark = '', int $adminId = 0, string $type = AiBalanceLog::TYPE_ADJUST): float
    {
        if ($merId <= 0) {
            throw new ValidateException('商户无效');
        }
        if (abs($amount) < 0.0001) {
            throw new ValidateException('金额无效');
        }

        return Db::transaction(function () use ($merId, $amount, $remark, $adminId, $type) {
            $mer = Merchant::getDB()->where('mer_id', $merId)->lock(true)->find();
            if (!$mer) {
                throw new ValidateException('商户不存在');
            }
            $balance = round((float)$mer['ai_balance'], 2);
            $newBalance = round($balance + $amount, 2);
            if ($newBalance < 0) {
                throw new ValidateException('调账后余额不能为负');
            }
            Merchant::getDB()->where('mer_id', $merId)->update(['ai_balance' => $newBalance]);
            AiBalanceLog::getDB()->insert([
                'mer_id' => $merId,
                'type' => $type,
                'amount' => $amount,
                'balance' => $newBalance,
                'session_no' => '',
                'usage_tokens' => 0,
                'remark' => $remark ?: ($amount > 0 ? '平台充值' : '平台调减'),
                'admin_id' => $adminId,
                'create_time' => date('Y-m-d H:i:s'),
            ]);
            return $newBalance;
        });
    }

    public function logs(int $merId, int $page = 1, int $limit = 20): array
    {
        $query = AiBalanceLog::getDB()->where('mer_id', $merId)->order('id DESC');
        $count = (clone $query)->count();
        $list = $query->page($page, $limit)->select()->toArray();
        return compact('count', 'list');
    }
}
