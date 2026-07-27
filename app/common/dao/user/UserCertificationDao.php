<?php

namespace app\common\dao\user;

use app\common\dao\BaseDao;
use app\common\model\user\UserCertification;

class UserCertificationDao extends BaseDao
{
    protected function getModel(): string
    {
        return UserCertification::class;
    }

    public function getByUid(int $uid): array
    {
        return UserCertification::getInstance()->where('uid', $uid)->select()->toArray();
    }

    public function getByUidType(int $uid, string $type): ?UserCertification
    {
        return UserCertification::getInstance()->where('uid', $uid)->where('type', $type)->find();
    }

    public function upsert(int $uid, string $type, array $data): void
    {
        $record = $this->getByUidType($uid, $type);
        if ($record) {
            UserCertification::getDB()->where('uid', $uid)->where('type', $type)->update($data);
        } else {
            $data['uid']  = $uid;
            $data['type'] = $type;
            $this->create($data);
        }
    }

    public function getById(int $id): ?UserCertification
    {
        return UserCertification::getInstance()->where('id', $id)->find();
    }

    public function review(int $id, int $status, string $remark): void
    {
        UserCertification::getDB()->where('id', $id)->update([
            'status' => $status,
            'remark' => $remark,
        ]);
    }

    public function adminList(array $where, int $page, int $limit): array
    {
        $query = UserCertification::getInstance();
        if (!empty($where['uid'])) {
            $query = $query->where('uid', $where['uid']);
        }
        if (!empty($where['type'])) {
            $query = $query->where('type', $where['type']);
        }
        if (isset($where['status']) && $where['status'] !== '') {
            $query = $query->where('status', $where['status']);
        }
        $count = $query->count();
        $list  = $query->page($page, $limit)->order('create_time', 'desc')->select()->toArray();
        return compact('count', 'list');
    }
}
