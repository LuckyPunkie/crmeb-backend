<?php

namespace app\common\dao\user;

use app\common\dao\BaseDao;
use app\common\model\user\UserProfileField;

class UserProfileFieldDao extends BaseDao
{
    protected function getModel(): string
    {
        return UserProfileField::class;
    }

    public function getList(array $where = []): array
    {
        $query = UserProfileField::getDB()->order('sort ASC,id ASC');
        if (isset($where['is_show']) && $where['is_show'] !== '') {
            $query->where('is_show', (int)$where['is_show']);
        }
        if (isset($where['status']) && $where['status'] !== '') {
            $query->where('status', (int)$where['status']);
        }
        return $query->select()->toArray();
    }

    /**
     * 已上架：个人资料编辑页可用
     */
    public function getEnabledList(): array
    {
        return UserProfileField::getDB()
            ->where('status', 1)
            ->order('sort ASC,id ASC')
            ->select()
            ->toArray();
    }

    /**
     * 已上架且主页展示：个人主页「个人信息」网格
     */
    public function getDisplayList(): array
    {
        return UserProfileField::getDB()
            ->where('status', 1)
            ->where('is_show', 1)
            ->order('sort ASC,id ASC')
            ->select()
            ->toArray();
    }

    /** @deprecated 使用 getEnabledList */
    public function getVisibleList(): array
    {
        return $this->getEnabledList();
    }

    public function findByKey(string $fieldKey): ?array
    {
        $row = UserProfileField::getDB()->where('field_key', $fieldKey)->find();
        return $row ? $row->toArray() : null;
    }

    public function existsKey(string $fieldKey, int $excludeId = 0): bool
    {
        $query = UserProfileField::getDB()->where('field_key', $fieldKey);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        return $query->count() > 0;
    }
}
