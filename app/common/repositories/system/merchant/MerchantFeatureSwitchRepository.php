<?php

namespace app\common\repositories\system\merchant;

use app\common\model\system\config\SystemConfigValue;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;

/**
 * 店铺功能开关：平台三选一 + 商家手动开关
 *
 * 平台模式（eb_system_config_value, mer_id=0）：
 *   0 任何店铺都不显示 / 1 初始不显示、商家可手动开启 / 2 全部店铺显示、商家可手动关闭
 * 商家开关：NULL 未手动设置（模式1=关、模式2=开） / 1 开 / 0 关；平台改模式不清商家的手动选择
 *
 * 读写都直连 eb_system_config_value，不走 systemConfig/merchantConfig 缓存
 */
class MerchantFeatureSwitchRepository extends BaseRepository
{
    const MODE_OFF = 0;
    const MODE_MER_OPT_IN = 1;
    const MODE_ALL_ON = 2;

    /** 开关分组：一个分组对应平台后台一个设置页 / 商家后台一张开关卡片 */
    const GROUP_ACTION = 'action';
    const GROUP_BLINDBOX = 'blindbox';
    const GROUP_WELFARE = 'welfare';

    /** 全部开关；默认全部显示，与上线前一致 */
    const FEATURES = [
        // 店铺详情页按钮
        'online_order' => [
            'group' => self::GROUP_ACTION,
            'name' => '线上下单',
            'mode_key' => 'nearby_online_order_mode',
            'mer_key' => 'nearby_online_order_switch',
            'default' => self::MODE_ALL_ON,
        ],
        'bill' => [
            'group' => self::GROUP_ACTION,
            'name' => '买单',
            'mode_key' => 'nearby_bill_mode',
            'mer_key' => 'nearby_bill_switch',
            'default' => self::MODE_ALL_ON,
        ],
        // 买单支付成功页「店老板请你免费开盲盒」入口
        'pay_blindbox' => [
            'group' => self::GROUP_BLINDBOX,
            'name' => '盲盒入口',
            'mode_key' => 'pay_blindbox_entry_mode',
            'mer_key' => 'pay_blindbox_entry_switch',
            'default' => self::MODE_ALL_ON,
        ],
        // 买单页公益商品（免单资格提示、公益商品卡片、「购物享免单」）
        'bill_welfare' => [
            'group' => self::GROUP_WELFARE,
            'name' => '公益商品',
            'mode_key' => 'bill_welfare_mode',
            'mer_key' => 'bill_welfare_switch',
            'default' => self::MODE_ALL_ON,
        ],
    ];

    public static function normalizeMode($value, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $mode = (int)$value;
        return in_array($mode, [self::MODE_OFF, self::MODE_MER_OPT_IN, self::MODE_ALL_ON], true) ? $mode : $default;
    }

    /**
     * 平台模式 + 商家开关 => 是否开启
     */
    public static function resolveSwitch(int $mode, $merValue): bool
    {
        if ($mode === self::MODE_OFF) {
            return false;
        }
        if ($merValue === null || $merValue === '') {
            return $mode === self::MODE_ALL_ON;
        }
        return (int)$merValue === 1;
    }

    /**
     * @param array $keyDefaults config_key => 默认模式
     * @return array config_key => 模式
     */
    public function readPlatformModes(array $keyDefaults): array
    {
        $values = $this->readValues(0, array_keys($keyDefaults));
        $modes = [];
        foreach ($keyDefaults as $key => $default) {
            $modes[$key] = self::normalizeMode($values[$key] ?? null, $default);
        }
        return $modes;
    }

    /**
     * @param array $keyModes config_key => 模式
     */
    public function writePlatformModes(array $keyModes): void
    {
        foreach ($keyModes as $key => $mode) {
            if (!in_array($mode, [self::MODE_OFF, self::MODE_MER_OPT_IN, self::MODE_ALL_ON], true)) {
                throw new ValidateException('开关选项无效');
            }
        }
        foreach ($keyModes as $key => $mode) {
            $this->writeValue(0, $key, (string)$mode);
        }
    }

    // ---------------- 按分组读写 ----------------

    protected function features(?string $group = null): array
    {
        if ($group === null) {
            return self::FEATURES;
        }
        $list = array_filter(self::FEATURES, function ($f) use ($group) {
            return $f['group'] === $group;
        });
        if (!$list) {
            throw new ValidateException('参数错误');
        }
        return $list;
    }

    /**
     * 平台模式
     * @return array 如 ['online_order_mode' => 2, 'bill_mode' => 2]
     */
    public function getModes(?string $group = null): array
    {
        $features = $this->features($group);
        $modes = $this->readPlatformModes(array_column($features, 'default', 'mode_key'));
        $out = [];
        foreach ($features as $feature => $f) {
            $out[$feature . '_mode'] = $modes[$f['mode_key']];
        }
        return $out;
    }

    public function saveModes(string $group, array $data): array
    {
        $keyModes = [];
        foreach ($this->features($group) as $feature => $f) {
            if (isset($data[$feature . '_mode']) && $data[$feature . '_mode'] !== '') {
                $keyModes[$f['mode_key']] = (int)$data[$feature . '_mode'];
            }
        }
        $this->writePlatformModes($keyModes);
        return $this->getModes($group);
    }

    /**
     * 商家后台：各开关的平台模式、商家开关生效值
     */
    public function getMerSwitch(int $merId, ?string $group = null): array
    {
        $features = $this->features($group);
        $modes = $this->getModes($group);
        $merValues = $this->readValues($merId, array_column($features, 'mer_key'));
        $out = [];
        foreach ($features as $feature => $f) {
            $mode = $modes[$feature . '_mode'];
            $out[$feature] = [
                'name' => $f['name'],
                'mode' => $mode,
                'enabled' => self::resolveSwitch($mode, $merValues[$f['mer_key']] ?? null) ? 1 : 0,
            ];
        }
        return $out;
    }

    public function saveMerSwitch(int $merId, string $group, string $feature, int $value): array
    {
        $features = $this->features($group);
        if (!isset($features[$feature])) {
            throw new ValidateException('参数错误');
        }
        $f = $features[$feature];
        if ($this->getModes($group)[$feature . '_mode'] === self::MODE_OFF) {
            throw new ValidateException('平台已关闭' . $f['name']);
        }
        $this->writeValue($merId, $f['mer_key'], $value ? '1' : '0');
        return $this->getMerSwitch($merId, $group);
    }

    /**
     * 用户端：各开关是否显示（全部分组）
     * @return array ['online_order' => bool, 'bill' => bool, 'pay_blindbox' => bool, 'bill_welfare' => bool]
     */
    public function resolveVisible(int $merId): array
    {
        $out = [];
        foreach ($this->getMerSwitch($merId) as $feature => $row) {
            $out[$feature] = (bool)$row['enabled'];
        }
        return $out;
    }

    /**
     * 单个开关是否开启
     */
    public function isOn(int $merId, string $feature): bool
    {
        if (!isset(self::FEATURES[$feature])) {
            return false;
        }
        $f = self::FEATURES[$feature];
        $mode = $this->readPlatformModes([$f['mode_key'] => $f['default']])[$f['mode_key']];
        if ($mode === self::MODE_OFF) {
            return false;
        }
        $merValues = $this->readValues($merId, [$f['mer_key']]);
        return self::resolveSwitch($mode, $merValues[$f['mer_key']] ?? null);
    }

    // ---------------- eb_system_config_value 直读直写 ----------------

    protected function readValues(int $merId, array $keys): array
    {
        if (!$keys) {
            return [];
        }
        return SystemConfigValue::where('mer_id', $merId)
            ->whereIn('config_key', $keys)
            ->column('value', 'config_key');
    }

    protected function writeValue(int $merId, string $key, string $value): void
    {
        $row = SystemConfigValue::where('config_key', $key)->where('mer_id', $merId)->find();
        if ($row) {
            $row->value = $value;
            $row->save();
        } else {
            SystemConfigValue::create(['config_key' => $key, 'value' => $value, 'mer_id' => $merId]);
        }
    }
}
