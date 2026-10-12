<?php

namespace app\common\repositories\store\equity;

use app\common\dao\store\equity\MerchantEquityConfigDao;
use app\common\model\store\equity\EquityProject;
use app\common\model\store\equity\EquityTransaction;
use app\common\model\store\equity\MerchantEquityConfig;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\merchant\MerchantFeatureSwitchRepository as Switches;
use think\exception\ValidateException;
use think\facade\Db;

class EquityConfigRepository extends BaseRepository
{
    /** 平台总开关三选一，含义见 MerchantFeatureSwitchRepository */
    const MODE_OFF = Switches::MODE_OFF;
    const MODE_MER_OPT_IN = Switches::MODE_MER_OPT_IN;
    const MODE_ALL_ON = Switches::MODE_ALL_ON;

    /** eb_system_config_value(mer_id=0) 直读直写，不走 systemConfig 缓存 */
    const KEY_CONSUME_MODE = 'equity_consume_mode';
    const KEY_INVEST_MODE = 'equity_invest_mode';

    /** 未设置时的默认值：与上线前行为一致（配置过的商家显示、充值入股对所有显示的店铺开放） */
    const DEFAULT_CONSUME_MODE = self::MODE_MER_OPT_IN;
    const DEFAULT_INVEST_MODE = self::MODE_ALL_ON;

    public function __construct(MerchantEquityConfigDao $dao)
    {
        $this->dao = $dao;
    }

    protected function switches(): Switches
    {
        return app()->make(Switches::class);
    }

    public function getPlatformModes(): array
    {
        $modes = $this->switches()->readPlatformModes([
            self::KEY_CONSUME_MODE => self::DEFAULT_CONSUME_MODE,
            self::KEY_INVEST_MODE => self::DEFAULT_INVEST_MODE,
        ]);
        return [
            'consume_mode' => $modes[self::KEY_CONSUME_MODE],
            'invest_mode' => $modes[self::KEY_INVEST_MODE],
        ];
    }

    public function savePlatformModes(int $consumeMode, int $investMode): array
    {
        $this->switches()->writePlatformModes([
            self::KEY_CONSUME_MODE => $consumeMode,
            self::KEY_INVEST_MODE => $investMode,
        ]);
        return $this->getPlatformModes();
    }

    /**
     * 平台模式 + 商家开关 => 是否开启
     * 商家开关 NULL = 从未手动设置：模式1 视为关、模式2 视为开
     */
    protected function resolveSwitch(int $mode, $merValue): bool
    {
        return Switches::resolveSwitch($mode, $merValue);
    }

    /**
     * 店铺消费送股 / 充值买股的生效状态
     *  - consume_visible：店铺详情页是否显示消费送股模块
     *  - invest_visible：消费送股显示的前提下，是否显示充值买股
     *  - rate_configured：是否已设置送股比例与目标股本；未设置时模块可显示但数据不变（不送股、不可充值）
     *
     * @param array|null $config eb_merchant_equity_config 行；不传则按 mer_id 查询
     */
    public function resolveState(int $merId, ?array $config = null, ?array $modes = null): array
    {
        if ($config === null) {
            $row = MerchantEquityConfig::getDB()->where('mer_id', $merId)->find();
            $config = $row ? $row->toArray() : [];
        }
        $modes = $modes ?: $this->getPlatformModes();
        $consumeVisible = $this->resolveSwitch($modes['consume_mode'], $config['enabled'] ?? null);
        $investVisible = $consumeVisible && $this->resolveSwitch($modes['invest_mode'], $config['invest_enabled'] ?? null);
        $rateConfigured = (float)($config['consume_equity_percent'] ?? 0) > 0
            && (int)($config['target_equity_amount'] ?? 0) > 0;

        return [
            'consume_mode' => $modes['consume_mode'],
            'invest_mode' => $modes['invest_mode'],
            'consume_visible' => $consumeVisible,
            'invest_visible' => $investVisible,
            'rate_configured' => $rateConfigured,
        ];
    }

    public function getConfig(int $merId): array
    {
        $row = MerchantEquityConfig::getDB()->where('mer_id', $merId)->find();
        $data = $row ? $row->toArray() : [];
        $state = $this->resolveState($merId, $data);
        $rateConfigured = $state['rate_configured'];

        return [
            'mer_id' => $merId,
            // 商家开关以生效值回显（未手动设置时按平台默认）
            'enabled' => $state['consume_visible'] ? 1 : 0,
            'invest_enabled' => $this->resolveSwitch($state['invest_mode'], $data['invest_enabled'] ?? null) ? 1 : 0,
            'consume_equity_percent' => $rateConfigured ? $data['consume_equity_percent'] : '',
            'target_equity_amount' => $rateConfigured ? $data['target_equity_amount'] : '',
            'configured' => $rateConfigured,
            'consume_mode' => $state['consume_mode'],
            'invest_mode' => $state['invest_mode'],
            'consume_visible' => $state['consume_visible'],
            'invest_visible' => $state['invest_visible'],
        ];
    }

    /**
     * 商家手动开关（显式写 0/1，之后不再跟随平台默认）
     *
     * @param string $type consume|invest
     */
    public function saveSwitch(int $merId, string $type, int $value): array
    {
        $column = $type === 'invest' ? 'invest_enabled' : 'enabled';
        $modes = $this->getPlatformModes();
        $mode = $type === 'invest' ? $modes['invest_mode'] : $modes['consume_mode'];
        if ($mode === self::MODE_OFF) {
            throw new ValidateException($type === 'invest' ? '平台已关闭充值买股' : '平台已关闭消费送股');
        }
        $value = $value ? 1 : 0;

        Db::transaction(function () use ($merId, $column, $value) {
            $row = MerchantEquityConfig::getDB()->where('mer_id', $merId)->lock(true)->find();
            if ($row) {
                MerchantEquityConfig::getDB()->where('id', $row['id'])->update([$column => $value]);
            } else {
                MerchantEquityConfig::getDB()->insert(['mer_id' => $merId, $column => $value]);
            }
        });

        return $this->getConfig($merId);
    }

    public function saveConfig(int $merId, array $data): array
    {
        // 新版商家后台开关走 saveSwitch；旧版页面仍随表单提交 enabled，兼容保留
        $enabled = isset($data['enabled']) && $data['enabled'] !== '' ? ((int)$data['enabled'] ? 1 : 0) : null;
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
                'consume_equity_percent' => $percent,
                'target_equity_amount' => $target,
            ];
            if ($enabled !== null) {
                $payload['enabled'] = $enabled;
            }
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
