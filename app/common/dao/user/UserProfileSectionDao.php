<?php

namespace app\common\dao\user;

use app\common\dao\BaseDao;
use app\common\model\user\UserProfileSection;

class UserProfileSectionDao extends BaseDao
{
    protected function getModel(): string
    {
        return UserProfileSection::class;
    }

    public function getList(): array
    {
        return UserProfileSection::getDB()->order('sort ASC,id ASC')->select()->toArray();
    }

    public function existsKey(string $sectionKey): bool
    {
        return UserProfileSection::getDB()->where('section_key', $sectionKey)->count() > 0;
    }
}
