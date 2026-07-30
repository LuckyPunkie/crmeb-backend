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

    /** 用户级审核状态 */
    public const REVIEW_NONE = 0;
    public const REVIEW_AI_PASS = 1;       // AI审核通过（排队人工）
    public const REVIEW_MANUAL_PASS = 3;   // 人工复审通过
    public const REVIEW_MANUAL_REJECT = 4; // 人工驳回

    /**
     * 提交认证（自动视为 AI 通过 status=1，打标签，进入人工队列）
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
        $this->markAiPassed($uid);
    }

    /**
     * AI 通过后写入用户级审核状态，进入人工队列
     */
    public function markAiPassed(int $uid): void
    {
        $user = Db::name('user')->where('uid', $uid)->field('uid')->find();
        if (!$user) {
            return;
        }
        // 提交/重提后视为 AI 通过，重新进入人工队列；加急需重新申请
        Db::name('user')->where('uid', $uid)->update([
            'profile_review_status' => self::REVIEW_AI_PASS,
            'profile_review_time' => time(),
            'profile_review_urgent' => 0,
            'profile_review_urgent_time' => 0,
        ]);
    }

    /**
     * 申请加急复审（无需登录，按目标 uid）
     */
    public function applyUrgent(int $uid): array
    {
        $user = Db::name('user')->where('uid', $uid)->whereNull('cancel_time')->find();
        if (!$user) {
            throw new \InvalidArgumentException('用户不存在');
        }
        $status = (int)($user['profile_review_status'] ?? 0);
        if ($status === self::REVIEW_MANUAL_PASS) {
            return $this->buildReviewDisplay($user);
        }
        if ($status === self::REVIEW_NONE) {
            throw new \InvalidArgumentException('该用户暂无可加急的审核资料');
        }
        if ((int)($user['profile_review_urgent'] ?? 0) === 1) {
            return $this->buildReviewDisplay($user);
        }
        Db::name('user')->where('uid', $uid)->update([
            'profile_review_urgent' => 1,
            'profile_review_urgent_time' => time(),
        ]);
        $user['profile_review_urgent'] = 1;
        return $this->buildReviewDisplay($user);
    }

    /**
     * C 端展示用审核状态
     */
    public function buildReviewDisplay(array $user): array
    {
        $status = (int)($user['profile_review_status'] ?? 0);
        $urgent = (int)($user['profile_review_urgent'] ?? 0);
        $label = '';
        $canApplyUrgent = false;
        if ($status === self::REVIEW_MANUAL_PASS) {
            $label = '人工复审';
        } elseif ($status === self::REVIEW_MANUAL_REJECT) {
            $label = '审核未通过';
        } elseif ($status === self::REVIEW_AI_PASS) {
            if ($urgent) {
                $label = '人工审核中';
            } else {
                $label = 'AI审核通过';
                $canApplyUrgent = true;
            }
        }
        return [
            'profile_review_status' => $status,
            'profile_review_urgent' => $urgent,
            'review_label' => $label,
            'can_apply_urgent' => $canApplyUrgent,
        ];
    }

    /**
     * 单条资质审核（兼容旧接口）
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
        $this->refreshUserReviewStatus((int)$record->uid);
    }

    /**
     * 以用户为单位审核：逐条更新资质结果，并汇总用户总状态
     * @param array $items [['id'=>1,'status'=>1,'remark'=>''], ...]
     */
    public function reviewByUser(int $uid, array $items): void
    {
        if (!$items) {
            throw new \InvalidArgumentException('请提交审核结果');
        }
        foreach ($items as $item) {
            $id = (int)($item['id'] ?? 0);
            $status = (int)($item['status'] ?? 0);
            $remark = (string)($item['remark'] ?? '');
            if (!$id || !in_array($status, [1, 2], true)) {
                continue;
            }
            $record = $this->dao->getById($id);
            if (!$record || (int)$record->uid !== $uid) {
                continue;
            }
            $this->dao->review($id, $status, $remark);
            if ($status === 1) {
                $this->applyLabel($uid, $record->type);
            } else {
                $this->removeLabel($uid, $record->type);
            }
        }
        $this->refreshUserReviewStatus($uid);
    }

    /**
     * 根据资质汇总刷新用户级审核状态：任一条驳回=未过；全部通过=人工复审
     */
    public function refreshUserReviewStatus(int $uid): void
    {
        $certs = $this->dao->getByUid($uid);
        if (!$certs) {
            return;
        }
        $hasReject = false;
        $allPass = true;
        foreach ($certs as $c) {
            $s = (int)($c['status'] ?? 0);
            if ($s === 2) {
                $hasReject = true;
                $allPass = false;
            } elseif ($s !== 1) {
                $allPass = false;
            }
        }
        if ($hasReject) {
            Db::name('user')->where('uid', $uid)->update([
                'profile_review_status' => self::REVIEW_MANUAL_REJECT,
                'profile_review_urgent' => 0,
            ]);
        } elseif ($allPass) {
            Db::name('user')->where('uid', $uid)->update([
                'profile_review_status' => self::REVIEW_MANUAL_PASS,
                'profile_review_urgent' => 0,
            ]);
        }
    }

    /**
     * 旧：按资质条列表
     */
    public function adminList(array $where, int $page, int $limit): array
    {
        return $this->dao->adminList($where, $page, $limit);
    }

    /**
     * 新：以用户为单位的审核列表
     */
    public function adminUserList(array $where, int $page, int $limit): array
    {
        $query = Db::name('user')->alias('u')
            ->whereNull('u.cancel_time')
            ->where('u.profile_review_status', '>', 0);

        if (!empty($where['uid'])) {
            $query->where('u.uid', (int)$where['uid']);
        }
        if (!empty($where['nickname'])) {
            $query->whereLike('u.nickname', '%' . $where['nickname'] . '%');
        }
        if (isset($where['profile_review_status']) && $where['profile_review_status'] !== '') {
            $query->where('u.profile_review_status', (int)$where['profile_review_status']);
        }
        if (isset($where['profile_review_urgent']) && $where['profile_review_urgent'] !== '') {
            $query->where('u.profile_review_urgent', (int)$where['profile_review_urgent']);
        }
        if (!empty($where['keyword'])) {
            $kw = $where['keyword'];
            $query->where(function ($q) use ($kw) {
                $q->whereLike('u.nickname|u.phone|u.account', '%' . $kw . '%')
                    ->whereOr('u.uid', $kw);
            });
        }

        $count = (clone $query)->count();
        $list = $query
            ->field('u.uid,u.nickname,u.avatar,u.phone,u.mer_id,u.user_type,u.bot_type,u.profile_review_status,u.profile_review_urgent,u.profile_review_urgent_time,u.profile_review_time,u.create_time')
            ->order('u.profile_review_urgent', 'desc')
            ->order('u.profile_review_urgent_time', 'desc')
            ->order('u.profile_review_time', 'asc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        foreach ($list as &$row) {
            $display = $this->buildReviewDisplay($row);
            $row = array_merge($row, $display);
            $row['cert_count'] = Db::name('user_certification')->where('uid', $row['uid'])->count();
        }
        unset($row);

        return compact('count', 'list');
    }

    /**
     * 用户审核详情：资料 + 全部资质
     */
    public function adminUserDetail(int $uid): array
    {
        $user = Db::name('user')->where('uid', $uid)->whereNull('cancel_time')->find();
        if (!$user) {
            throw new \InvalidArgumentException('用户不存在');
        }
        $profile = Db::name('user_profile')->where('uid', $uid)->find() ?: [];
        $certs = $this->dao->getByUid($uid);
        foreach ($certs as &$c) {
            $images = $c['images'] ?? '';
            if (is_string($images)) {
                $decoded = json_decode($images, true);
                $c['images'] = is_array($decoded) ? $decoded : ($images ? [$images] : []);
            }
        }
        unset($c);
        $display = $this->buildReviewDisplay($user);
        return [
            'user' => array_merge([
                'uid' => $user['uid'],
                'nickname' => $user['nickname'],
                'avatar' => $user['avatar'],
                'phone' => $user['phone'],
                'sex' => $user['sex'],
                'birthday' => $user['birthday'] ?? '',
                'real_name' => $user['real_name'] ?? '',
                'mer_id' => $user['mer_id'] ?? 0,
                'user_type' => $user['user_type'],
                'bot_type' => $user['bot_type'] ?? 0,
            ], $display),
            'profile' => $profile,
            'certifications' => $certs,
        ];
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
