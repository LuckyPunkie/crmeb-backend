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
        return UserCertification::getInstance()
            ->where('uid', $uid)
            ->order('create_time', 'desc')
            ->order('id', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 取某类型最新一条
     */
    public function getByUidType(int $uid, string $type): ?UserCertification
    {
        return UserCertification::getInstance()
            ->where('uid', $uid)
            ->where('type', $type)
            ->order('create_time', 'desc')
            ->order('id', 'desc')
            ->find();
    }

    /**
     * 提交/重提：
     * - 无记录：新建
     * - 最新一条为驳回(status=2)：新建（保留驳回历史）
     * - 其他：覆盖最新一条
     */
    public function upsert(int $uid, string $type, array $data): void
    {
        $record = $this->getByUidType($uid, $type);
        $now = time();
        if (!$record) {
            $data['uid'] = $uid;
            $data['type'] = $type;
            $data['create_time'] = $data['create_time'] ?? $now;
            $this->create($data);
            return;
        }
        // 驳回后重提：保留旧驳回记录，新开一条
        if ((int)$record->status === 2) {
            $data['uid'] = $uid;
            $data['type'] = $type;
            $data['create_time'] = $now;
            $this->create($data);
            return;
        }
        $data['update_time'] = $now;
        UserCertification::getDB()->where('id', (int)$record->id)->update($data);
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
        $count = (clone $query)->count();
        $list  = $query->page($page, $limit)
            ->order('create_time', 'desc')
            ->with(['user' => function ($q) {
                $q->field('uid,nickname,avatar,phone');
            }])
            ->select()
            ->toArray();
        return compact('count', 'list');
    }
}
