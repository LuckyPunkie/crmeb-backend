<?php

namespace app\common\dao\taoke;

use app\common\dao\BaseDao;
use app\common\model\taoke\ServiceTabConfig;

class ServiceTabConfigDao extends BaseDao
{
    protected function getModel(): string
    {
        return ServiceTabConfig::class;
    }

    public function all(): array
    {
        return ServiceTabConfig::getDB()->order('sort DESC, id ASC')->select()->toArray();
    }

    public function enabled(): array
    {
        return ServiceTabConfig::getDB()->where('status', 1)->order('sort DESC, id ASC')->select()->toArray();
    }

    public function findByKey(string $key)
    {
        return ServiceTabConfig::getDB()->where('tab_key', $key)->find();
    }

    public function findById(int $id)
    {
        return ServiceTabConfig::getDB()->where('id', $id)->find();
    }

    public function updateById(int $id, array $data): void
    {
        ServiceTabConfig::getDB()->where('id', $id)->update($data);
    }

    public function insert(array $data): int
    {
        return (int)ServiceTabConfig::getDB()->insertGetId($data);
    }

    public function deleteById(int $id): void
    {
        ServiceTabConfig::getDB()->where('id', $id)->delete();
    }
}
