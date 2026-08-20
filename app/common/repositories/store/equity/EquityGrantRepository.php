<?php

namespace app\common\repositories\store\equity;

use app\common\dao\store\equity\EquityInvestOrderDao;
use app\common\dao\store\equity\EquityInvestRefundDao;
use app\common\dao\store\equity\EquityProjectDao;
use app\common\dao\store\equity\EquityShareholderDao;
use app\common\dao\store\equity\EquityTransactionDao;
use app\common\dao\store\equity\MerchantEquityConfigDao;
use app\common\model\store\equity\EquityInvestOrder;
use app\common\model\store\equity\EquityInvestRefund;
use app\common\model\store\equity\EquityProject;
use app\common\model\store\equity\EquityShareholder;
use app\common\model\store\equity\EquityTransaction;
use app\common\model\store\equity\MerchantEquityConfig;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;

/**
 * 消费送股核心：发放 / 扣减 / 达标换期 / 充值入股
 */
class EquityGrantRepository extends BaseRepository
{
    protected $configDao;
    protected $projectDao;
    protected $shareholderDao;
    protected $transactionDao;
    protected $investOrderDao;
    protected $investRefundDao;

    public function __construct(
        EquityProjectDao $dao,
        MerchantEquityConfigDao $configDao,
        EquityShareholderDao $shareholderDao,
        EquityTransactionDao $transactionDao,
        EquityInvestOrderDao $investOrderDao,
        EquityInvestRefundDao $investRefundDao
    ) {
        $this->dao = $dao;
        $this->projectDao = $dao;
        $this->configDao = $configDao;
        $this->shareholderDao = $shareholderDao;
        $this->transactionDao = $transactionDao;
        $this->investOrderDao = $investOrderDao;
        $this->investRefundDao = $investRefundDao;
    }

    /**
     * 消费送股：支付成功后发放
     */
    public function grantOnConsume(int $merId, int $uid, $payPrice, string $orderId, string $orderType = 'order'): bool
    {
        if ($merId <= 0 || $uid <= 0) {
            return false;
        }
        $payPrice = round((float)$payPrice, 2);
        if ($payPrice <= 0) {
            return false;
        }

        try {
            return Db::transaction(function () use ($merId, $uid, $payPrice, $orderId, $orderType) {
                // 幂等：同订单同类型只发一次
                $exists = EquityTransaction::getDB()
                    ->where('order_id', (string)$orderId)
                    ->where('type', EquityTransaction::TYPE_CONSUME)
                    ->where('order_type', $orderType)
                    ->lock(true)
                    ->find();
                if ($exists) {
                    return true;
                }

                $config = MerchantEquityConfig::getDB()->where('mer_id', $merId)->lock(true)->find();
                if (!$config || !(int)$config['enabled']) {
                    return false;
                }
                $percent = (float)$config['consume_equity_percent'];
                if ($percent <= 0 || $percent >= 100) {
                    return false;
                }

                $amount = round($payPrice * $percent / 100, 2);
                if ($amount < 0.01) {
                    return false;
                }

                $project = $this->getOrCreateRaisingProjectLocked($merId, (int)$config['target_equity_amount']);
                if (!$project) {
                    return false;
                }

                $remain = bcsub((string)$project['target_amount'], (string)$project['total_consumer_amount'], 2);
                if (bccomp($remain, '0', 2) <= 0) {
                    // 当前期已满，先换期再发
                    $this->checkAndRotateProject((int)$project['id'], $merId, (int)$config['target_equity_amount']);
                    $project = $this->getOrCreateRaisingProjectLocked($merId, (int)$config['target_equity_amount']);
                    if (!$project) {
                        return false;
                    }
                    $remain = bcsub((string)$project['target_amount'], (string)$project['total_consumer_amount'], 2);
                }
                if (bccomp($remain, '0', 2) <= 0) {
                    return false;
                }

                // PRD：拒绝超出部分；能填满当前期的部分照常发放并换期
                $grantAmount = $amount;
                if (bccomp((string)$amount, $remain, 2) > 0) {
                    $grantAmount = (float)$remain;
                    Log::info("Equity grant partial mer={$merId} full={$amount} grant={$grantAmount} remain={$remain}");
                }
                if ($grantAmount < 0.01) {
                    return false;
                }

                $this->applyAmountChange(
                    (int)$project['id'],
                    $uid,
                    $grantAmount,
                    EquityTransaction::TYPE_CONSUME,
                    (string)$orderId,
                    $orderType,
                    '消费送股',
                    false,
                    $payPrice
                );

                $this->checkAndRotateProject((int)$project['id'], $merId, (int)$config['target_equity_amount']);
                return true;
            });
        } catch (\Throwable $e) {
            Log::error('EquityGrant grantOnConsume: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 订单退款按比例扣减消费送股
     */
    public function clawbackOnOrderRefund(string $orderId, string $orderType, $refundPrice, $originPayPrice): bool
    {
        $refundPrice = round((float)$refundPrice, 2);
        $originPayPrice = round((float)$originPayPrice, 2);
        if ($refundPrice <= 0 || $originPayPrice <= 0) {
            return false;
        }

        try {
            return Db::transaction(function () use ($orderId, $orderType, $refundPrice, $originPayPrice) {
                $tx = EquityTransaction::getDB()
                    ->where('order_id', (string)$orderId)
                    ->where('type', EquityTransaction::TYPE_CONSUME)
                    ->where('order_type', $orderType)
                    ->lock(true)
                    ->find();
                if (!$tx) {
                    return false;
                }

                $ratio = bcdiv((string)$refundPrice, (string)$originPayPrice, 6);
                $deduct = round((float)bcmul((string)$tx['amount'], $ratio, 4), 2);
                if ($deduct < 0.01) {
                    return false;
                }

                // 已扣减合计
                $already = EquityTransaction::getDB()
                    ->where('order_id', (string)$orderId)
                    ->where('type', EquityTransaction::TYPE_REFUND)
                    ->where('order_type', $orderType . '_refund')
                    ->sum('amount');
                $alreadyAbs = abs((float)$already);
                $maxDeduct = round((float)$tx['amount'] - $alreadyAbs, 2);
                if ($maxDeduct < 0.01) {
                    return false;
                }
                if ($deduct > $maxDeduct) {
                    $deduct = $maxDeduct;
                }

                $this->applyAmountChange(
                    (int)$tx['project_id'],
                    (int)$tx['uid'],
                    -$deduct,
                    EquityTransaction::TYPE_REFUND,
                    (string)$orderId,
                    $orderType . '_refund',
                    '订单退款扣减股本金',
                    false,
                    $refundPrice
                );
                return true;
            });
        } catch (\Throwable $e) {
            Log::error('EquityGrant clawbackOnOrderRefund: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 充值入股支付成功入账
     */
    public function investPaySuccess(string $orderSn, string $payType = 'weixin'): bool
    {
        try {
            return Db::transaction(function () use ($orderSn, $payType) {
                $order = EquityInvestOrder::getDB()->where('order_sn', $orderSn)->lock(true)->find();
                if (!$order || (int)$order['paid'] === 1) {
                    return (bool)$order;
                }

                $affected = EquityInvestOrder::getDB()
                    ->where('id', $order['id'])
                    ->where('paid', 0)
                    ->update([
                        'paid' => 1,
                        'status' => 1,
                        'pay_type' => $payType,
                        'pay_time' => time(),
                    ]);
                if (!$affected) {
                    return true;
                }

                $project = EquityProject::getDB()->where('id', $order['project_id'])->lock(true)->find();
                if (!$project || (int)$project['status'] !== EquityProject::STATUS_RAISING) {
                    throw new ValidateException('项目已不可入股');
                }

                $remain = bcsub((string)$project['target_amount'], (string)$project['total_consumer_amount'], 2);
                if (bccomp((string)$order['amount'], $remain, 2) > 0) {
                    throw new ValidateException('入股金额超过剩余可筹额度');
                }

                $this->applyAmountChange(
                    (int)$project['id'],
                    (int)$order['uid'],
                    (float)$order['amount'],
                    EquityTransaction::TYPE_INVEST,
                    $orderSn,
                    'invest',
                    '充值入股',
                    true,
                    (float)$order['amount']
                );

                $config = MerchantEquityConfig::getDB()->where('mer_id', $order['mer_id'])->find();
                $target = $config ? (int)$config['target_equity_amount'] : (int)$project['target_amount'];
                $this->checkAndRotateProject((int)$project['id'], (int)$order['mer_id'], $target);
                return true;
            });
        } catch (\Throwable $e) {
            Log::error('EquityGrant investPaySuccess: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 创建充值入股待支付单
     */
    public function createInvestOrder(int $projectId, int $uid, $amount): array
    {
        $amount = round((float)$amount, 2);
        if ($amount <= 0) {
            throw new ValidateException('充值金额必须大于0');
        }

        return Db::transaction(function () use ($projectId, $uid, $amount) {
            $project = EquityProject::getDB()->where('id', $projectId)->lock(true)->find();
            if (!$project || (int)$project['status'] !== EquityProject::STATUS_RAISING) {
                throw new ValidateException('当前项目不可入股');
            }
            $remain = bcsub((string)$project['target_amount'], (string)$project['total_consumer_amount'], 2);
            if (bccomp((string)$amount, $remain, 2) > 0) {
                throw new ValidateException('入股金额超过剩余可筹额度');
            }

            $sn = $this->investOrderDao->makeOrderSn();
            $id = EquityInvestOrder::getDB()->insertGetId([
                'order_sn' => $sn,
                'project_id' => $projectId,
                'mer_id' => (int)$project['mer_id'],
                'uid' => $uid,
                'amount' => $amount,
                'paid' => 0,
                'status' => 0,
            ]);
            return [
                'id' => $id,
                'order_sn' => $sn,
                'amount' => $amount,
                'project_id' => $projectId,
                'mer_id' => (int)$project['mer_id'],
            ];
        });
    }

    /**
     * 申请充值入股全额退款（筹集中）
     */
    public function applyInvestRefund(int $projectId, int $uid, string $reason = ''): array
    {
        return Db::transaction(function () use ($projectId, $uid, $reason) {
            $project = EquityProject::getDB()->where('id', $projectId)->lock(true)->find();
            if (!$project || (int)$project['status'] !== EquityProject::STATUS_RAISING) {
                throw new ValidateException('仅筹集中项目可申请充值退款');
            }

            $shareholder = EquityShareholder::getDB()
                ->where('project_id', $projectId)
                ->where('uid', $uid)
                ->lock(true)
                ->find();
            $investAmount = $shareholder ? round((float)$shareholder['invest_amount'], 2) : 0;
            if ($investAmount < 0.01) {
                throw new ValidateException('无可退的充值入股金额');
            }

            $pending = EquityInvestRefund::getDB()
                ->where('project_id', $projectId)
                ->where('uid', $uid)
                ->where('status', EquityInvestRefund::STATUS_PENDING)
                ->find();
            if ($pending) {
                throw new ValidateException('已有待审核的退款申请');
            }

            $id = EquityInvestRefund::getDB()->insertGetId([
                'project_id' => $projectId,
                'uid' => $uid,
                'invest_order_id' => 0,
                'refund_amount' => $investAmount,
                'status' => EquityInvestRefund::STATUS_PENDING,
                'apply_reason' => mb_substr($reason, 0, 255),
            ]);
            return ['id' => $id, 'refund_amount' => $investAmount];
        });
    }

    /**
     * 平台审核充值退款
     */
    public function auditInvestRefund(int $refundId, bool $pass, int $adminId, string $auditReason = ''): bool
    {
        return Db::transaction(function () use ($refundId, $pass, $adminId, $auditReason) {
            $refund = EquityInvestRefund::getDB()->where('id', $refundId)->lock(true)->find();
            if (!$refund || (int)$refund['status'] !== EquityInvestRefund::STATUS_PENDING) {
                throw new ValidateException('退款申请不存在或已处理');
            }

            if (!$pass) {
                EquityInvestRefund::getDB()->where('id', $refundId)->update([
                    'status' => EquityInvestRefund::STATUS_REJECT,
                    'audit_reason' => mb_substr($auditReason, 0, 255),
                    'admin_id' => $adminId,
                    'audited_at' => time(),
                ]);
                return true;
            }

            $project = EquityProject::getDB()->where('id', $refund['project_id'])->lock(true)->find();
            if (!$project || (int)$project['status'] !== EquityProject::STATUS_RAISING) {
                EquityInvestRefund::getDB()->where('id', $refundId)->update([
                    'status' => EquityInvestRefund::STATUS_REJECT,
                    'audit_reason' => '项目已进入待开业，不可退款',
                    'admin_id' => $adminId,
                    'audited_at' => time(),
                ]);
                throw new ValidateException('项目已进入待开业，不可退款');
            }

            $amount = round((float)$refund['refund_amount'], 2);
            $this->applyAmountChange(
                (int)$project['id'],
                (int)$refund['uid'],
                -$amount,
                EquityTransaction::TYPE_REFUND,
                'refund_' . $refundId,
                'invest_refund',
                '充值入股退款',
                true,
                $amount
            );

            // 标记相关入股单已退
            EquityInvestOrder::getDB()
                ->where('project_id', $refund['project_id'])
                ->where('uid', $refund['uid'])
                ->where('paid', 1)
                ->where('refunded', 0)
                ->update(['refunded' => 1, 'status' => 2]);

            EquityInvestRefund::getDB()->where('id', $refundId)->update([
                'status' => EquityInvestRefund::STATUS_PASS,
                'audit_reason' => mb_substr($auditReason ?: '审核通过', 0, 255),
                'admin_id' => $adminId,
                'audited_at' => time(),
            ]);
            return true;
        });
    }

    /**
     * 修改目标后：若已达标则完成并开新一轮
     */
    public function completeIfTargetMet(int $merId, int $newTarget): void
    {
        Db::transaction(function () use ($merId, $newTarget) {
            $project = EquityProject::getDB()
                ->where('mer_id', $merId)
                ->where('status', EquityProject::STATUS_RAISING)
                ->lock(true)
                ->find();
            if (!$project) {
                $this->createRaisingProject($merId, $newTarget);
                return;
            }
            EquityProject::getDB()->where('id', $project['id'])->update([
                'target_amount' => $newTarget,
            ]);
            $this->checkAndRotateProject((int)$project['id'], $merId, $newTarget);
        });
    }

    public function getOrCreateRaisingProject(int $merId, int $targetAmount)
    {
        $project = EquityProject::getDB()
            ->where('mer_id', $merId)
            ->where('status', EquityProject::STATUS_RAISING)
            ->find();
        if ($project) {
            return $project;
        }
        return $this->createRaisingProject($merId, $targetAmount);
    }

    protected function getOrCreateRaisingProjectLocked(int $merId, int $targetAmount)
    {
        $project = EquityProject::getDB()
            ->where('mer_id', $merId)
            ->where('status', EquityProject::STATUS_RAISING)
            ->lock(true)
            ->find();
        if ($project) {
            if ($targetAmount > 0 && (int)$project['target_amount'] !== $targetAmount) {
                EquityProject::getDB()->where('id', $project['id'])->update(['target_amount' => $targetAmount]);
                $project['target_amount'] = $targetAmount;
            }
            return $project;
        }
        return $this->createRaisingProject($merId, $targetAmount);
    }

    public function createRaisingProject(int $merId, int $targetAmount)
    {
        if ($targetAmount <= 0) {
            return null;
        }
        $maxRound = (int)EquityProject::getDB()->where('mer_id', $merId)->max('round_no');
        $id = EquityProject::getDB()->insertGetId([
            'mer_id' => $merId,
            'new_store_id' => 0,
            'round_no' => $maxRound + 1,
            'target_amount' => $targetAmount,
            'total_consumer_amount' => 0,
            'total_equity' => 0,
            'shareholder_count' => 0,
            'status' => EquityProject::STATUS_RAISING,
        ]);
        return EquityProject::getDB()->where('id', $id)->find();
    }

    /**
     * 变动股本金并重算占比
     */
    protected function applyAmountChange(
        int $projectId,
        int $uid,
        float $amount,
        int $type,
        string $orderId,
        string $orderType,
        string $remark = '',
        bool $isInvest = false,
        float $sourceAmount = 0
    ): void {
        $project = EquityProject::getDB()->where('id', $projectId)->lock(true)->find();
        if (!$project) {
            throw new ValidateException('项目不存在');
        }

        $shareholder = EquityShareholder::getDB()
            ->where('project_id', $projectId)
            ->where('uid', $uid)
            ->lock(true)
            ->find();

        $oldPersonal = $shareholder ? (string)$shareholder['total_amount'] : '0.00';
        $newPersonal = bcadd($oldPersonal, (string)$amount, 2);
        if (bccomp($newPersonal, '0', 2) < 0) {
            $newPersonal = '0.00';
            $amount = (float)bcsub('0.00', $oldPersonal, 2);
        }

        $newPool = bcadd((string)$project['total_consumer_amount'], (string)$amount, 2);
        if (bccomp($newPool, '0', 2) < 0) {
            $newPool = '0.00';
        }
        $totalEquity = bccomp($newPool, '0', 2) > 0
            ? bcdiv($newPool, '0.9', 2)
            : '0.00';

        $shareRatio = '0.000000';
        if (bccomp($totalEquity, '0', 2) > 0) {
            $shareRatio = bcdiv($newPersonal, $totalEquity, 6);
        }

        $now = time();
        if ($shareholder) {
            $investAmount = (string)$shareholder['invest_amount'];
            if ($isInvest) {
                $investAmount = bcadd($investAmount, (string)$amount, 2);
                if (bccomp($investAmount, '0', 2) < 0) {
                    $investAmount = '0.00';
                }
            }
            EquityShareholder::getDB()->where('id', $shareholder['id'])->update([
                'total_amount' => $newPersonal,
                'invest_amount' => $investAmount,
                'share_ratio' => $shareRatio,
                'last_consume_time' => $amount > 0 ? $now : (int)$shareholder['last_consume_time'],
            ]);
        } else {
            EquityShareholder::getDB()->insert([
                'project_id' => $projectId,
                'uid' => $uid,
                'total_amount' => $newPersonal,
                'invest_amount' => $isInvest && $amount > 0 ? $amount : 0,
                'share_ratio' => $shareRatio,
                'last_consume_time' => $now,
            ]);
        }

        $count = EquityShareholder::getDB()
            ->where('project_id', $projectId)
            ->where('total_amount', '>', 0)
            ->count();

        EquityProject::getDB()->where('id', $projectId)->update([
            'total_consumer_amount' => $newPool,
            'total_equity' => $totalEquity,
            'shareholder_count' => $count,
        ]);

        EquityTransaction::getDB()->insert([
            'project_id' => $projectId,
            'uid' => $uid,
            'amount' => $amount,
            'balance' => $newPersonal,
            'type' => $type,
            'order_id' => $orderId,
            'order_type' => $orderType,
            'source_amount' => round(abs($sourceAmount), 2),
            'remark' => $remark,
        ]);

        $this->recalculateAllShareRatios($projectId, $totalEquity);
    }

    protected function recalculateAllShareRatios(int $projectId, string $totalEquity): void
    {
        $list = EquityShareholder::getDB()->where('project_id', $projectId)->select();
        foreach ($list as $row) {
            $ratio = '0.000000';
            if (bccomp($totalEquity, '0', 2) > 0) {
                $ratio = bcdiv((string)$row['total_amount'], $totalEquity, 6);
            }
            EquityShareholder::getDB()->where('id', $row['id'])->update(['share_ratio' => $ratio]);
        }
    }

    /**
     * 达标：筹集中 → 待开业，并创建新一轮筹集中
     */
    protected function checkAndRotateProject(int $projectId, int $merId, int $nextTarget): void
    {
        $project = EquityProject::getDB()->where('id', $projectId)->lock(true)->find();
        if (!$project || (int)$project['status'] !== EquityProject::STATUS_RAISING) {
            return;
        }
        if (bccomp((string)$project['total_consumer_amount'], (string)$project['target_amount'], 2) < 0) {
            return;
        }

        EquityProject::getDB()->where('id', $projectId)->update([
            'status' => EquityProject::STATUS_PENDING,
            'reached_at' => time(),
        ]);

        $target = $nextTarget > 0 ? $nextTarget : (int)$project['target_amount'];
        $this->createRaisingProject($merId, $target);
    }
}
