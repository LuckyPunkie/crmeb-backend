<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------
namespace app\common\repositories\delivery;

use app\common\model\delivery\DeliveryStation;
use think\facade\Db;
use think\exception\ValidateException;
use app\common\repositories\BaseRepository;
use app\common\dao\delivery\DeliveryConfigDao;
use app\common\repositories\system\config\ConfigValueRepository;

class DeliveryConfigRepository extends BaseRepository
{
    use DeliveryTrait;

    public function __construct(DeliveryConfigDao $dao)
    {
        $this->dao = $dao;
    }

    protected function getDao()
    {
        return $this->dao;
    }

    public function update(int $id, int $merId, array $basicSettingsParams, array $deliveryParams)
    {
        // 基本信息保存
        $configValueRepository = app()->make(ConfigValueRepository::class);
        $configValueRepository->setFormData($basicSettingsParams, $merId);
        // 配送信息
        $deliveryParams['mer_id'] = $merId;
        $deliveryParams['status'] = 1;
        $deliveryParams['distance_premium_config'] = json_encode($deliveryParams['distance_premium_config']);
        $deliveryParams['weight_premium_config'] = json_encode($deliveryParams['weight_premium_config']);


        return Db::transaction(function () use (
            $id,
            $merId,
            $deliveryParams,
            $basicSettingsParams
        ) {
            $this->getDao()->saveConfig($id, $deliveryParams);

            $mer_delivery_type = $basicSettingsParams['mer_delivery_type'];
            app()->make(DeliveryStationRepository::class)->search([
                'mer_id' => $merId,
                'status' => 1
            ])->where('type', '<>', DeliveryStation::TYPE_PLATFORM)
                ->whereNotIn('type', $mer_delivery_type)
                ->update(['status'=> 0]);
            app()->make(DeliveryStationRepository::class)->search([
                'mer_id' => $merId,
                'status' => 0
            ])->where('type', '<>', DeliveryStation::TYPE_PLATFORM)
                ->whereIn('type', $mer_delivery_type)
                ->update(['status' => 1]);
        });

        return $this->getDao()->saveConfig($id, $deliveryParams);
    }

    public function updatePlatform(int $id, array $basicSettingsParams, array $deliveryParams)
    {
        $deliveryConfig = $this->dao->getDeliveryConfig(0);
        if ($id && (!$deliveryConfig || (int)$deliveryConfig->delivery_config_id !== $id)) {
            throw new ValidateException('平台配送设置记录不存在!');
        }

        app()->make(ConfigValueRepository::class)->setFormData($basicSettingsParams, 0);

        $deliveryParams['mer_id'] = 0;
        $deliveryParams['status'] = 1;
        $deliveryParams['distance_premium_config'] = json_encode($deliveryParams['distance_premium_config']);
        $deliveryParams['weight_premium_config'] = json_encode($deliveryParams['weight_premium_config']);
        $deliveryParams['night_premium_config'] = json_encode($deliveryParams['night_premium_config']);

        return $this->getDao()->saveConfig($id, $deliveryParams);
    }

    public function deliverySettings(int $merId, array $keys = [])
    {
        if (empty($keys)) {
            $keys = [
                'mer_delivery_type',
                'mer_delivery_order_status'
            ];
        }
        // 基本信息
        $basicSettings = merchantConfig($merId, $keys);
        // 配送信息
        $deliverySettings = $this->dao->getDeliveryConfig($merId);

        return compact('basicSettings', 'deliverySettings');
    }

    public function platformSettings()
    {
        $flowSettings = [
            [
                'field' => 'platform_delivery_grab_status',
                'title' => '配送员抢单',
                'description' => '开启后，配送员可以进行抢单操作；关闭则只能由平台指派配送员进行接单。',
                'disabled' => false,
            ],
            [
                'field' => 'platform_delivery_accept_status',
                'title' => '配送员接单',
                'description' => '开启后，平台指派单需配送员进行接单操作，接单后状态流转为骑手已接单；关闭则平台派单配送员自动接单。',
                'disabled' => false,
            ],
            [
                'field' => 'platform_delivery_pickup_status',
                'title' => '到达提货点',
                'description' => '开启后，配送员到达提货点需进行到达提货点操作，操作后状态流转为已到提货点；关闭则无需此流程。',
                'disabled' => false,
            ],
            [
                'field' => 'platform_delivery_start_status',
                'title' => '开始送货',
                'description' => '开启后，配送员拿到货品需进行开始送货操作，操作后状态流转为骑手送货中；关闭则无需此流程。',
                'disabled' => false,
            ],
            [
                'field' => 'platform_delivery_finish_status',
                'title' => '确认送达',
                'description' => '开启后，配送员送货完成需进行确认送达操作，操作后状态流转为确认送达；此流程不可关闭。',
                'disabled' => true,
            ],
        ];
        $flowSwitches = array_column($flowSettings, 'field');
        $basicSettings = systemConfig(array_merge([
            'platform_delivery_status',
            'platform_delivery_background',
            'platform_delivery_form',
        ], $flowSwitches));
        foreach ($flowSettings as &$setting) {
            $key = $setting['field'];
            $basicSettings[$key] = isset($basicSettings[$key]) ? (int)$basicSettings[$key] : 1;
            $setting['value'] = $basicSettings[$key];
        }
        $basicSettings['platform_delivery_finish_status'] = 1;
        unset($setting);

        $deliverySettings = $this->dao->getDeliveryConfig(0);

        return compact('basicSettings', 'deliverySettings', 'flowSettings');
    }
    /**
     * 配送费用计算
     *
     * @param integer $takeId
     * @param float $totalWeight
     * @param array $deliverySettings
     * @param array|string $address
     * @param int|null $stationMerId
     * @param string|null $deliveryTime 用户预约配送时间段（取开始时间计算夜间加价）
     * @return float
     */
    public function deliveryFee(int $takeId, float $totalWeight, array $deliverySettings, $address, ?int $stationMerId = null, ?string $deliveryTime = null): float
    {
        $stationMerId = $stationMerId ?? (int)$deliverySettings['mer_id'];
        // 是否开启溢价叠加,未开启溢价按基础运费计算
        $fee = $deliverySettings['base_shipping_fee'];
        if ((int)($deliverySettings['is_premium_stack_enabled'] ?? 0) === 1) {
            $distanceConfig = $deliverySettings['distance_premium_config'] ?? [];
            $weightConfig = $deliverySettings['weight_premium_config'] ?? [];
            $distanceStackFee = $this->hasDistancePremiumConfig($distanceConfig)
                ? $this->distanceStackFee($takeId, $stationMerId, $distanceConfig, $address)
                : 0;
            $weightStackFee = $this->hasWeightPremiumConfig($weightConfig)
                ? $this->weightStackFee($totalWeight, $weightConfig)
                : 0;
            $stackFee = bcadd((string)$distanceStackFee, (string)$weightStackFee, 2);
            $fee = bcadd((string)$fee, $stackFee, 2);
        }

        // 夜间加价按用户预约配送时间段的开始时间判断，而不是按服务器当前时间判断。
        $nightFee = $this->nightStackFee($deliverySettings['night_premium_config'] ?? [], $deliveryTime);
        if ($nightFee > 0) {
            $fee = bcadd($fee, $nightFee, 2);
        }

        return $fee;
    }

    protected function hasDistancePremiumConfig($config): bool
    {
        return is_array($config)
            && !empty($config['level_first']['lt_distance'])
            && !empty($config['level_last']['gt_distance'])
            && !empty($config['level_last']['add_distance'])
            && !empty($config['level_last']['add_amount']);
    }

    protected function hasWeightPremiumConfig($config): bool
    {
        if (!is_array($config)) {
            return false;
        }

        $firstWeight = $config['level_first']['lt_weight'] ?? null;
        if (!is_numeric($firstWeight)) {
            return false;
        }

        $last = $config['level_last'] ?? [];
        if (is_array($last)
            && is_numeric($last['gt_weight'] ?? null)
            && is_numeric($last['add_weight'] ?? null)
            && is_numeric($last['add_amount'] ?? null)
            && (float)$last['add_weight'] > 0
            && (float)$last['add_amount'] > 0
        ) {
            return true;
        }

        foreach ((array)($config['level_stairs'] ?? []) as $level) {
            if (!is_array($level)
                || !is_numeric($level['end_weight'] ?? null)
                || !is_numeric($level['add_weight'] ?? null)
                || !is_numeric($level['add_amount'] ?? null)
                || (float)$level['add_weight'] <= 0
                || (float)$level['add_amount'] <= 0
            ) {
                continue;
            }
            return true;
        }

        return false;
    }

    /**
     * 计算夜间配送加价。配置时间支持跨午夜，例如 09:00 至次日 03:00。
     */
    protected function nightStackFee($config, ?string $deliveryTime): float
    {
        if (!$deliveryTime || !is_array($config) || !$config) {
            return 0;
        }

        // 兼容配置保存为单条对象或多条规则列表的情况。
        $rules = isset($config[0]) && is_array($config[0]) ? $config : [$config];
        $selectedStart = $this->parseDeliveryMinute($deliveryTime);
        if ($selectedStart === null) {
            return 0;
        }

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            // type=1 表示不加价，type=2 才启用夜间额外费用。
            if (array_key_exists('type', $rule) && (int)$rule['type'] !== 2) {
                continue;
            }
            $enabled = $rule['status'] ?? $rule['enabled'] ?? $rule['switch'] ?? $rule['is_enabled'] ?? null;
            if ($enabled !== null && !(int)$enabled) {
                continue;
            }

            $start = $this->parseDeliveryMinute(
                $rule['start_time'] ?? $rule['start'] ?? ($rule['time']['start'] ?? null)
            );
            $end = $this->parseDeliveryMinute(
                $rule['end_time'] ?? $rule['end'] ?? ($rule['time']['end'] ?? null)
            );
            $amount = $rule['add_amount']
                ?? $rule['night_fee']
                ?? $rule['premium_fee']
                ?? $rule['amount']
                ?? 0;
            if ($start === null || !is_numeric($amount)) {
                continue;
            }

            $inNightPeriod = $end === null
                ? $selectedStart >= $start
                : ($start <= $end
                    ? $selectedStart >= $start && $selectedStart <= $end
                    : $selectedStart >= $start || $selectedStart <= $end);
            if ($inNightPeriod) {
                return max(0, (float)$amount);
            }
        }

        return 0;
    }

    /**
     * 提取 HH:MM 字符串并转换为当天分钟数。
     */
    protected function parseDeliveryMinute($value): ?int
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        if (!preg_match('/(\d{1,2}):(\d{2})/', (string)$value, $matches)) {
            return null;
        }
        $hour = (int)$matches[1];
        $minute = (int)$matches[2];
        return $hour < 24 && $minute < 60 ? $hour * 60 + $minute : null;
    }
}
