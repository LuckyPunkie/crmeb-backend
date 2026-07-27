<?php

namespace app\common\dao\taoke;

use app\common\dao\BaseDao;
use app\common\model\taoke\ServiceBrandTab;

class ServiceBrandTabDao extends BaseDao
{
    protected function getModel(): string
    {
        return ServiceBrandTab::class;
    }

    public function getConfigRow(): ?ServiceBrandTab
    {
        return ServiceBrandTab::getDB()->order('id', 'asc')->find();
    }

    public function saveConfig(string $name, array $brands, int $status = 1): ServiceBrandTab
    {
        $row = $this->getConfigRow();
        $data = [
            'name'        => $name,
            'brands'      => json_encode(array_values($brands), JSON_UNESCAPED_UNICODE),
            'status'      => $status,
            'update_time' => date('Y-m-d H:i:s'),
        ];
        if ($row) {
            ServiceBrandTab::getDB()->where('id', $row['id'])->update($data);
            return $this->getConfigRow();
        }
        $data['create_time'] = date('Y-m-d H:i:s');
        $id = ServiceBrandTab::getDB()->insertGetId($data);
        return ServiceBrandTab::getDB()->where('id', $id)->find();
    }
}
