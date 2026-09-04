<?php

namespace app\common\repositories\taoke;

use app\common\dao\taoke\ServiceTabConfigDao;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;

class ServiceTabConfigRepository extends BaseRepository
{
    const TYPE_BUILTIN = 1;
    const TYPE_CUSTOM  = 2;

    public function __construct(ServiceTabConfigDao $dao)
    {
        $this->dao = $dao;
    }

    public function listAll(): array
    {
        return array_map([$this, 'format'], $this->dao->all());
    }

    public function listEnabled(): array
    {
        return array_map([$this, 'format'], $this->dao->enabled());
    }

    public function findCustomByKey(string $tabKey): ?array
    {
        $row = $this->dao->findByKey($tabKey);
        if (!$row) return null;
        $arr = is_array($row) ? $row : $row->toArray();
        if ((int)$arr['tab_type'] !== self::TYPE_CUSTOM) return null;
        return $this->format($arr);
    }

    public function saveConfig(array $data): array
    {
        $id     = (int)($data['id'] ?? 0);
        $name   = trim((string)($data['name'] ?? ''));
        $sort   = (int)($data['sort'] ?? 0);
        $status = (int)($data['status'] ?? 1) ? 1 : 0;
        $tabKey = trim((string)($data['tab_key'] ?? ''));
        $brands = $data['brands'] ?? [];

        if ($name === '') throw new ValidateException('请填写显示名');
        if (mb_strlen($name) > 20) throw new ValidateException('显示名不能超过20个字');

        $existingRaw = $id > 0 ? $this->dao->findById($id) : null;
        if ($id > 0 && !$existingRaw) throw new ValidateException('记录不存在');
        $existing = $existingRaw ? (is_array($existingRaw) ? $existingRaw : $existingRaw->toArray()) : null;

        $cleanBrands = $this->cleanBrands(is_array($brands) ? $brands : []);

        // 内置行：可改 name/status/sort/brands（brands 允许为空，为空则不显示筛选标签）
        if ($existing && (int)$existing['tab_type'] === self::TYPE_BUILTIN) {
            $this->dao->updateById($id, [
                'name'   => $name,
                'status' => $status,
                'sort'   => $sort,
                'brands' => json_encode(array_values($cleanBrands), JSON_UNESCAPED_UNICODE),
            ]);
            $row = $this->dao->findById($id);
            return $this->format(is_array($row) ? $row : $row->toArray());
        }

        // 自定义行：brands 必填
        if (empty($cleanBrands)) throw new ValidateException('请至少添加一个品牌');

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
        if ($this->dao->findByKey($newKey)) {
            throw new ValidateException('tab_key 已存在');
        }

        $newId = $this->dao->insert([
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

    public function deleteConfig(int $id): void
    {
        $rowRaw = $this->dao->findById($id);
        if (!$rowRaw) throw new ValidateException('记录不存在');
        $row = is_array($rowRaw) ? $rowRaw : $rowRaw->toArray();
        if ((int)$row['tab_type'] === self::TYPE_BUILTIN) {
            throw new ValidateException('内置平台不能删除，如需隐藏请关闭开关');
        }
        $this->dao->deleteById($id);
    }

    protected function cleanBrands(array $brands): array
    {
        $out = [];
        foreach ($brands as $b) {
            $b = trim((string)$b);
            if ($b === '') continue;
            if (mb_strlen($b) > 30) throw new ValidateException('品牌名称不能超过30个字');
            if (!in_array($b, $out, true)) $out[] = $b;
        }
        if (count($out) > 50) throw new ValidateException('品牌最多50个');
        return $out;
    }

    protected function format(array $row): array
    {
        $brands = $row['brands'] ?? null;
        if (is_string($brands)) $brands = json_decode($brands, true) ?: [];
        if (!is_array($brands)) $brands = [];
        return [
            'id'       => (int)$row['id'],
            'tab_key'  => (string)$row['tab_key'],
            'tab_type' => (int)$row['tab_type'],
            'name'     => (string)$row['name'],
            'brands'   => array_values($brands),
            'status'   => (int)$row['status'],
            'sort'     => (int)$row['sort'],
        ];
    }
}
