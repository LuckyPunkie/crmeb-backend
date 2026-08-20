<?php

namespace app\common\repositories\store\equity;

use app\common\dao\store\equity\MerchantEquityConfigDao;
use app\common\model\store\equity\EquityProject;
use app\common\model\store\equity\EquityTransaction;
use app\common\model\store\equity\MerchantEquityConfig;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;
use think\facade\Db;

class EquityConfigRepository extends BaseRepository
{
    public function __construct(MerchantEquityConfigDao $dao)
    {
        $this->dao = $dao;
    }

    public function getConfig(int $merId): array
    {
        $row = MerchantEquityConfig::getDB()->where('mer_id', $merId)->find();
        if (!$row) {
            return [
                'mer_id' => $merId,
                'enabled' => 1,
                'consume_equity_percent' => '',
                'target_equity_amount' => '',
                'configured' => false,
            ];
        }
        $data = $row->toArray();
        $data['configured'] = true;
        return $data;
    }

    public function saveConfig(int $merId, array $data): array
    {
        $enabled = isset($data['enabled']) ? (int)$data['enabled'] : 1;
        $percent = isset($data['consume_equity_percent']) ? round((float)$data['consume_equity_percent'], 2) : 0;
        $target = isset($data['target_equity_amount']) ? (int)$data['target_equity_amount'] : 0;

        if ($percent <= 0 || $percent >= 100) {
            throw new ValidateException('消费送股百分比必须大于0且小于100');
        }
        if ($target <= 0) {
            throw new ValidateException('新店目标股本必须为正整数');
        }
        if ($target > 100000000) {
            throw new ValidateException('新店目标股本过大');
        }

        return Db::transaction(function () use ($merId, $enabled, $percent, $target) {
            $row = MerchantEquityConfig::getDB()->where('mer_id', $merId)->lock(true)->find();
            $payload = [
                'mer_id' => $merId,
                'enabled' => $enabled ? 1 : 0,
                'consume_equity_percent' => $percent,
                'target_equity_amount' => $target,
            ];
            if ($row) {
                MerchantEquityConfig::getDB()->where('id', $row['id'])->update($payload);
            } else {
                MerchantEquityConfig::getDB()->insert($payload);
            }

            /** @var EquityGrantRepository $grant */
            $grant = app()->make(EquityGrantRepository::class);
            $grant->completeIfTargetMet($merId, $target);

            return $this->getConfig($merId);
        });
    }

    public function getProgress(int $merId): array
    {
        $config = $this->getConfig($merId);
        $raising = EquityProject::getDB()
            ->where('mer_id', $merId)
            ->where('status', EquityProject::STATUS_RAISING)
            ->find();
        $pendingCount = EquityProject::getDB()
            ->where('mer_id', $merId)
            ->where('status', EquityProject::STATUS_PENDING)
            ->count();
        $operatingCount = EquityProject::getDB()
            ->where('mer_id', $merId)
            ->where('status', EquityProject::STATUS_OPERATING)
            ->count();

        return [
            'config' => $config,
            'raising' => $raising ? $raising->toArray() : null,
            'pending_count' => $pendingCount,
            'operating_count' => $operatingCount,
            'completed_count' => $pendingCount + $operatingCount,
        ];
    }

    public function recentTransactions(int $merId, int $limit = 20): array
    {
        $projectIds = EquityProject::getDB()->where('mer_id', $merId)->column('id');
        if (!$projectIds) {
            return [];
        }
        $list = EquityTransaction::getDB()
            ->whereIn('project_id', $projectIds)
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        foreach ($list as &$row) {
            $source = isset($row['source_amount']) ? (float)$row['source_amount'] : 0;
            if ($source <= 0) {
                $source = $this->resolveSourceAmount($row);
            }
            $row['source_amount'] = round($source, 2);
            $row['source_amount_label'] = $this->sourceAmountLabel((int)$row['type']);
        }
        unset($row);

        return $list;
    }

    protected function sourceAmountLabel(int $type): string
    {
        if ($type === EquityTransaction::TYPE_INVEST) {
            return '充值金额';
        }
        if ($type === EquityTransaction::TYPE_REFUND) {
            return '退款金额';
        }
        return '消费金额';
    }

    /**
     * 历史数据无 source_amount 时，按关联订单回填
     */
    protected function resolveSourceAmount(array $row): float
    {
        $orderId = (string)($row['order_id'] ?? '');
        $orderType = (string)($row['order_type'] ?? '');
        if ($orderId === '') {
            return 0;
        }

        try {
            if ($orderType === 'bill' || $orderType === 'bill_refund') {
                $pay = Db::name('nearby_shop_bill_order')->where('order_sn', $orderId)->value('pay_price');
                return $pay !== null ? (float)$pay : 0;
            }
            if ($orderType === 'order' || $orderType === 'order_refund') {
                $pay = Db::name('store_order')->where('order_id', $orderId)->value('pay_price');
                return $pay !== null ? (float)$pay : 0;
            }
            if ($orderType === 'invest' || $orderType === 'invest_refund') {
                if (strpos($orderId, 'refund_') === 0) {
                    $refundId = (int)substr($orderId, 7);
                    $pay = Db::name('equity_invest_refund')->where('id', $refundId)->value('refund_amount');
                    return $pay !== null ? (float)$pay : 0;
                }
                $pay = Db::name('equity_invest_order')->where('order_sn', $orderId)->value('amount');
                return $pay !== null ? (float)$pay : 0;
            }
        } catch (\Throwable $e) {
            return 0;
        }

        return 0;
    }
}
