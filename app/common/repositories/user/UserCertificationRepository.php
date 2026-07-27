<?php

namespace app\common\repositories\user;

use app\common\dao\user\UserCertificationDao as dao;
use app\common\repositories\BaseRepository;
use think\facade\Db;

class UserCertificationRepository extends BaseRepository
{
    protected $dao;

    // 认证类型 → 标签名称（与后台用户标签名称一致）
    private const LABEL_MAP = [
        'education' => '学历认证',
        'work'      => '工作认证',
        'income'    => '收入认证',
        'car'       => '车产认证',
        'house'     => '房产认证',
    ];

    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    public function getByUid(int $uid): array
    {
        return $this->dao->getByUid($uid);
    }

    /**
     * 提交认证（本期自动通过 status=1，同时打标签）
     */
    public function save(int $uid, string $type, string $description, array $images): void
    {
        $allowed = ['education', 'work', 'income', 'car', 'house'];
        if (!in_array($type, $allowed)) {
            throw new \InvalidArgumentException('认证类型不合法');
        }
        $this->dao->upsert($uid, $type, [
            'description' => $description,
            'images'      => json_encode($images, JSON_UNESCAPED_UNICODE),
            'status'      => 1,
            'remark'      => '',
        ]);
        $this->applyLabel($uid, $type);
    }

    /**
     * 管理员审核：status=1 通过（打标签），status=2 拒绝（移除标签）
     */
    public function review(int $id, int $status, string $remark = ''): void
    {
        $record = $this->dao->getById($id);
        if (!$record) return;

        $this->dao->review($id, $status, $remark);

        if ($status === 1) {
            $this->applyLabel((int)$record->uid, $record->type);
        } else {
            $this->removeLabel((int)$record->uid, $record->type);
        }
    }

    public function adminList(array $where, int $page, int $limit): array
    {
        return $this->dao->adminList($where, $page, $limit);
    }

    /**
     * 根据标签名称找到 label_id，追加到 eb_user.label_id
     */
    private function applyLabel(int $uid, string $type): void
    {
        $labelName = self::LABEL_MAP[$type] ?? null;
        if (!$labelName) return;

        $labelId = Db::name('user_label')
            ->where('label_name', $labelName)
            ->value('label_id');
        if (!$labelId) return;

        $current = Db::name('user')->where('uid', $uid)->value('label_id');
        $ids = $current ? array_filter(explode(',', $current), 'strlen') : [];
        if (!in_array((string)$labelId, $ids)) {
            $ids[] = (string)$labelId;
            Db::name('user')->where('uid', $uid)->update(['label_id' => implode(',', $ids)]);
        }
    }

    /**
     * 从 eb_user.label_id 中移除对应标签
     */
    private function removeLabel(int $uid, string $type): void
    {
        $labelName = self::LABEL_MAP[$type] ?? null;
        if (!$labelName) return;

        $labelId = Db::name('user_label')
            ->where('label_name', $labelName)
            ->value('label_id');
        if (!$labelId) return;

        $current = Db::name('user')->where('uid', $uid)->value('label_id');
        if (!$current) return;

        $ids = array_filter(
            explode(',', $current),
            static function ($id) use ($labelId) { return $id !== (string)$labelId; }
        );
        Db::name('user')->where('uid', $uid)->update(['label_id' => implode(',', $ids)]);
    }
}
