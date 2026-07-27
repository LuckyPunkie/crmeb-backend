<?php

namespace app\common\repositories\taoke;

use app\common\dao\taoke\ServiceBrandTabDao;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;

class ServiceBrandTabRepository extends BaseRepository
{
    public function __construct(ServiceBrandTabDao $dao)
    {
        $this->dao = $dao;
    }

    public function getConfig(): array
    {
        try {
            $row = $this->dao->getConfigRow();
        } catch (\Throwable $e) {
            return $this->emptyConfig();
        }
        if (!$row) {
            return $this->emptyConfig();
        }
        $brands = $row['brands'];
        if (is_string($brands)) {
            $brands = json_decode($brands, true) ?: [];
        }
        if (!is_array($brands)) {
            $brands = [];
        }
        $brands = array_values(array_filter(array_map(function ($item) {
            return trim((string)$item);
        }, $brands)));

        return [
            'name'   => (string)($row['name'] ?? ''),
            'brands' => $brands,
            'status' => (int)($row['status'] ?? 0),
        ];
    }

    public function getPublicConfig(): array
    {
        $config = $this->getConfig();
        if ($config['status'] != 1 || $config['name'] === '' || empty($config['brands'])) {
            return [
                'enabled' => false,
                'name'    => '',
                'brands'  => [],
            ];
        }
        return [
            'enabled' => true,
            'name'    => $config['name'],
            'brands'  => $config['brands'],
        ];
    }

    public function saveConfig(string $name, array $brands, int $status = 1): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new ValidateException('请填写类别名称');
        }
        if (mb_strlen($name) > 20) {
            throw new ValidateException('类别名称不能超过20个字');
        }

        $cleanBrands = [];
        foreach ($brands as $brand) {
            $brand = trim((string)$brand);
            if ($brand === '') {
                continue;
            }
            if (mb_strlen($brand) > 30) {
                throw new ValidateException('品牌名称不能超过30个字');
            }
            if (!in_array($brand, $cleanBrands, true)) {
                $cleanBrands[] = $brand;
            }
        }
        if (count($cleanBrands) > 50) {
            throw new ValidateException('品牌最多50个');
        }

        try {
            $this->dao->saveConfig($name, $cleanBrands, $status ? 1 : 0);
        } catch (\Throwable $e) {
            throw new ValidateException('保存失败，请确认数据表已创建: ' . $e->getMessage());
        }

        return $this->getConfig();
    }

    protected function emptyConfig(): array
    {
        return [
            'name'   => '',
            'brands' => [],
            'status' => 1,
        ];
    }
}
