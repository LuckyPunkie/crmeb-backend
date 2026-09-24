<?php

namespace app\common\repositories\taoke;

use app\common\dao\taoke\ServiceTabConfigDao;
use app\common\model\system\config\SystemConfigValue;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;
use think\facade\Cache;

class ServiceTabConfigRepository extends BaseRepository
{
    const TYPE_BUILTIN = 1;
    const TYPE_CUSTOM  = 2;

    const CHANNEL_LEGACY   = 'legacy';
    const CHANNEL_OFFICIAL = 'official';

    /** 服务页「逛网店」内置 Tab（跳转店铺街，非商品平台） */
    const TAB_KEY_SHOP_STREET = 'shop_street';

    public function __construct(ServiceTabConfigDao $dao)
    {
        $this->dao = $dao;
    }

    public function listAll(string $channel = self::CHANNEL_LEGACY): array
    {
        $channel = $this->normalizeChannel($channel);
        if ($channel === self::CHANNEL_OFFICIAL) {
            $this->ensureShopStreetTab($channel);
        }
        return array_map([$this, 'format'], $this->dao->all($channel));
    }

    public function listEnabled(string $channel = self::CHANNEL_LEGACY): array
    {
        $channel = $this->normalizeChannel($channel);
        if ($channel === self::CHANNEL_OFFICIAL) {
            $this->ensureShopStreetTab($channel);
        }
        return array_map([$this, 'format'], $this->dao->enabled($channel));
    }

    public function findCustomByKey(string $tabKey, string $channel = self::CHANNEL_LEGACY): ?array
    {
        $channel = $this->normalizeChannel($channel);
        $row = $this->dao->findByKey($tabKey, $channel);
        if (!$row) {
            return null;
        }
        $arr = is_array($row) ? $row : $row->toArray();
        if ((int) $arr['tab_type'] !== self::TYPE_CUSTOM) {
            return null;
        }
        return $this->format($arr);
    }

    public function saveConfig(array $data, string $channel = self::CHANNEL_LEGACY): array
    {
        $channel = $this->normalizeChannel($channel);
        $id     = (int) ($data['id'] ?? 0);
        $name   = trim((string) ($data['name'] ?? ''));
        $sort   = (int) ($data['sort'] ?? 0);
        $status = (int) ($data['status'] ?? 1) ? 1 : 0;
        $tabKey = trim((string) ($data['tab_key'] ?? ''));
        $brands = $data['brands'] ?? [];

        if ($name === '') {
            throw new ValidateException('请填写显示名');
        }
        if (mb_strlen($name) > 20) {
            throw new ValidateException('显示名不能超过20个字');
        }

        $existingRaw = $id > 0 ? $this->dao->findById($id) : null;
        if ($id > 0 && !$existingRaw) {
            throw new ValidateException('记录不存在');
        }
        $existing = $existingRaw ? (is_array($existingRaw) ? $existingRaw : $existingRaw->toArray()) : null;
        if ($existing && (string) ($existing['channel'] ?? self::CHANNEL_LEGACY) !== $channel) {
            throw new ValidateException('配置通道不匹配');
        }

        $cleanBrands = $this->cleanBrands(is_array($brands) ? $brands : []);

        if ($existing && (int) $existing['tab_type'] === self::TYPE_BUILTIN) {
            $this->dao->updateById($id, [
                'name'   => $name,
                'status' => $status,
                'sort'   => $sort,
                'brands' => json_encode(array_values($cleanBrands), JSON_UNESCAPED_UNICODE),
            ]);
            if ((string) ($existing['tab_key'] ?? '') === self::TAB_KEY_SHOP_STREET) {
                $this->syncShopStreetConfigValue($status);
            }
            $row = $this->dao->findById($id);
            return $this->format(is_array($row) ? $row : $row->toArray());
        }

        if (empty($cleanBrands)) {
            throw new ValidateException('请至少添加一个品牌');
        }

        if ($existing) {
            $this->dao->updateById($id, [
                'name'   => $name,
                'brands' => json_encode(array_values($cleanBrands), JSON_UNESCAPED_UNICODE),
                'status' => $status,
                'sort'   => $sort,
            ]);
            $row = $this->dao->findById($id);
            return $this->format(is_array($row) ? $row : $row->toArray());
        }

        $newKey = $tabKey !== '' ? $tabKey : ('custom_' . uniqid());
        if ($this->dao->findByKey($newKey, $channel)) {
            throw new ValidateException('tab_key 已存在');
        }

        $newId = $this->dao->insert([
            'channel'  => $channel,
            'tab_key'  => $newKey,
            'tab_type' => self::TYPE_CUSTOM,
            'name'     => $name,
            'brands'   => json_encode(array_values($cleanBrands), JSON_UNESCAPED_UNICODE),
            'status'   => $status,
            'sort'     => $sort,
        ]);
        $row = $this->dao->findById($newId);
        return $this->format(is_array($row) ? $row : $row->toArray());
    }

    public function deleteConfig(int $id, string $channel = self::CHANNEL_LEGACY): void
    {
        $channel = $this->normalizeChannel($channel);
        $rowRaw = $this->dao->findById($id);
        if (!$rowRaw) {
            throw new ValidateException('记录不存在');
        }
        $row = is_array($rowRaw) ? $rowRaw : $rowRaw->toArray();
        if ((string) ($row['channel'] ?? self::CHANNEL_LEGACY) !== $channel) {
            throw new ValidateException('配置通道不匹配');
        }
        if ((int) $row['tab_type'] === self::TYPE_BUILTIN) {
            throw new ValidateException('内置平台不能删除，如需隐藏请关闭开关');
        }
        $this->dao->deleteById($id);
    }

    protected function normalizeChannel(string $channel): string
    {
        return $channel === self::CHANNEL_OFFICIAL ? self::CHANNEL_OFFICIAL : self::CHANNEL_LEGACY;
    }

    /** official 通道保证存在「逛网店」一行，与 eb_system_config shop_street_switch 对齐 */
    public function ensureShopStreetTab(string $channel = self::CHANNEL_OFFICIAL): void
    {
        $channel = $this->normalizeChannel($channel);
        if ($channel !== self::CHANNEL_OFFICIAL) {
            return;
        }
        if ($this->dao->findByKey(self::TAB_KEY_SHOP_STREET, $channel)) {
            return;
        }
        $status = (int) systemConfigNoCache('shop_street_switch') ? 1 : 0;
        $this->dao->insert([
            'channel'  => $channel,
            'tab_key'  => self::TAB_KEY_SHOP_STREET,
            'tab_type' => self::TYPE_BUILTIN,
            'name'     => '逛网店',
            'brands'   => json_encode([], JSON_UNESCAPED_UNICODE),
            'status'   => $status,
            'sort'     => 50,
        ]);
    }

    /** 表格开关与 App /api/config 共用 shop_street_switch */
    public function syncShopStreetConfigValue(int $status): void
    {
        $value = $status ? 1 : 0;
        $row = SystemConfigValue::where('config_key', 'shop_street_switch')
            ->where('mer_id', 0)
            ->find();
        if ($row) {
            $row->value = (string) $value;
            $row->save();
        } else {
            SystemConfigValue::create([
                'config_key' => 'shop_street_switch',
                'value'      => (string) $value,
                'mer_id'     => 0,
            ]);
        }
        Cache::delete('get_api_config');
    }

    public function setShopStreetEnabled(int $status): void
    {
        $this->ensureShopStreetTab(self::CHANNEL_OFFICIAL);
        $rowRaw = $this->dao->findByKey(self::TAB_KEY_SHOP_STREET, self::CHANNEL_OFFICIAL);
        if (!$rowRaw) {
            return;
        }
        $row = is_array($rowRaw) ? $rowRaw : $rowRaw->toArray();
        $status = $status ? 1 : 0;
        $this->dao->updateById((int) $row['id'], ['status' => $status]);
        $this->syncShopStreetConfigValue($status);
    }

    protected function cleanBrands(array $brands): array
    {
        $out = [];
        foreach ($brands as $b) {
            $b = trim((string) $b);
            if ($b === '') {
                continue;
            }
            if (mb_strlen($b) > 30) {
                throw new ValidateException('品牌名称不能超过30个字');
            }
            if (!in_array($b, $out, true)) {
                $out[] = $b;
            }
        }
        if (count($out) > 50) {
            throw new ValidateException('品牌最多50个');
        }
        return $out;
    }

    protected function format(array $row): array
    {
        $brands = $row['brands'] ?? null;
        if (is_string($brands)) {
            $brands = json_decode($brands, true) ?: [];
        }
        if (!is_array($brands)) {
            $brands = [];
        }
        return [
            'id'       => (int) $row['id'],
            'channel'  => (string) ($row['channel'] ?? self::CHANNEL_LEGACY),
            'tab_key'  => (string) $row['tab_key'],
            'tab_type' => (int) $row['tab_type'],
            'name'     => (string) $row['name'],
            'brands'   => array_values($brands),
            'status'   => (int) $row['status'],
            'sort'     => (int) $row['sort'],
        ];
    }
}
