<?php

namespace app\common\dao\user;

use app\common\dao\BaseDao;
use app\common\model\user\UserProfile;

class UserProfileDao extends BaseDao
{
    protected function getModel(): string
    {
        return UserProfile::class;
    }

    public function getByUid(int $uid): ?UserProfile
    {
        return UserProfile::getInstance()->where('uid', $uid)->find();
    }

    public function upsert(int $uid, array $data): void
    {
        $profile = $this->getByUid($uid);
        if ($profile) {
            UserProfile::getDB()->where('uid', $uid)->update($data);
        } else {
            $data['uid'] = $uid;
            $this->create($data);
        }
    }
}
