<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace app\common\repositories\user;

use app\common\dao\user\UserNotificationDao;
use app\common\model\system\Relevance;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\RelevanceRepository;
use crmeb\services\SwooleTaskService;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * @mixin UserNotificationDao
 */
class UserNotificationRepository extends BaseRepository
{
    public function __construct(UserNotificationDao $dao)
    {
        $this->dao = $dao;
    }

    public function getList(int $uid, int $page, int $limit, ?string $type = null): array
    {
        $list = $this->dao->getList($uid, $page, $limit, $type);
        $result = [];
        $fromUids = [];
        foreach ($list as $item) {
            $row = $item->toArray();
            if (!empty($row['from_uid'])) {
                $fromUids[] = (int)$row['from_uid'];
            }
            $result[] = $row;
        }
        $fromUids = array_values(array_unique(array_filter($fromUids)));
        $relationMap = $this->batchRelationLabels($uid, $fromUids);
        $userMap = $this->batchFromUsers($fromUids);

        foreach ($result as &$row) {
            $fromUid = (int)($row['from_uid'] ?? 0);
            $fromUser = $userMap[$fromUid] ?? null;
            // 兼容 with(fromUser) 不同命名
            if (!$fromUser) {
                $fromUser = $row['from_user'] ?? $row['fromUser'] ?? null;
            }
            $row['from_avatar'] = (string)($fromUser['avatar'] ?? '');
            $row['from_nickname'] = (string)($fromUser['nickname'] ?? '');
            unset($row['from_user'], $row['fromUser']);

            $parsed = $this->parseContent($row['content'] ?? '');
            $row['note_title'] = (string)($parsed['title'] ?? '');
            $row['note_image'] = (string)($parsed['image'] ?? '');
            $row['community_id'] = (int)($parsed['community_id'] ?? (
                (($row['target_type'] ?? '') === 'community') ? ($row['target_id'] ?? 0) : 0
            ));
            $row['reply_id'] = (int)($parsed['reply_id'] ?? (
                (($row['target_type'] ?? '') === 'community_reply') ? ($row['target_id'] ?? 0) : 0
            ));
            $row['reply_content'] = (string)($parsed['content'] ?? '');
            $row['content_text'] = (string)($parsed['text'] ?? $parsed['content'] ?? '');
            $row['company'] = (string)($parsed['company'] ?? '');
            $row['position'] = (string)($parsed['position'] ?? $parsed['job_title'] ?? '');
            $row['interview_time'] = (string)($parsed['interview_time'] ?? '');
            $row['apply_id'] = (int)($parsed['apply_id'] ?? (
                (($row['target_type'] ?? '') === 'recruit_apply') ? ($row['target_id'] ?? 0) : 0
            ));
            $row['jump_url'] = (string)($parsed['jump'] ?? '');
            $row['post_id'] = (int)($parsed['post_id'] ?? (
                (($row['target_type'] ?? '') === 'animal_rescue') ? ($row['target_id'] ?? 0) : 0
            ));
            $row['post_type'] = (int)($parsed['post_type'] ?? 1);

            $rel = $relationMap[$fromUid] ?? ['key' => '', 'label' => ''];
            $row['relation_key'] = $rel['key'];
            $row['relation_label'] = $rel['label'];

            if (($row['type'] ?? '') === 'follow') {
                if ($rel['key'] === 'mutual_follow') {
                    $row['follow_label'] = '互相关注';
                } elseif ($rel['key'] === 'following') {
                    $row['follow_label'] = '你的关注';
                } else {
                    $row['follow_label'] = '你的粉丝';
                }
            } else {
                $row['follow_label'] = '';
            }
        }
        unset($row);

        return [
            'count' => $this->dao->getCount($uid, $type),
            'list' => $result,
        ];
    }

    public function getDetail(int $notificationId, int $uid): array
    {
        $item = $this->dao->get($notificationId);
        if (!$item) {
            throw new ValidateException('通知不存在');
        }
        $row = is_array($item) ? $item : $item->toArray();
        if ((int)($row['uid'] ?? 0) !== $uid) {
            throw new ValidateException('通知不存在');
        }
        if (!(int)($row['is_read'] ?? 0)) {
            $this->dao->markRead($notificationId, $uid);
            $row['is_read'] = 1;
        }
        $parsed = $this->parseContent($row['content'] ?? '');
        if ($parsed) {
            $row['content_text'] = (string)($parsed['text'] ?? $parsed['content'] ?? '');
            if ($row['content_text'] === '' && isset($parsed['title'])) {
                $row['content_text'] = (string)$parsed['title'];
            }
        } else {
            $row['content_text'] = (string)($row['content'] ?? '');
        }
        return $row;
    }

    public function markRead(int $notificationId, int $uid): void
    {
        $this->dao->markRead($notificationId, $uid);
    }

    public function getUnreadCount(int $uid): int
    {
        return $this->dao->getUnreadCount($uid);
    }

    public function createAndPush(int $uid, int $fromUid, string $type, string $title, string $content = '', string $targetType = '', int $targetId = 0): void
    {
        if ($uid <= 0 || $uid == $fromUid) {
            return;
        }

        $data = [
            'uid' => $uid,
            'from_uid' => $fromUid,
            'type' => $type,
            'title' => $title,
            'content' => $content,
            'target_type' => $targetType,
            'target_id' => $targetId,
        ];
        $notification = $this->dao->create($data);

        try {
            $fromUser = $fromUid > 0
                ? Db::name('user')->field('uid,nickname,avatar')->where('uid', $fromUid)->find()
                : ['nickname' => '系统', 'avatar' => ''];

            SwooleTaskService::user($uid, [
                'type' => 'notification',
                'data' => [
                    'notification_id' => $notification->notification_id,
                    'type' => $type,
                    'title' => $title,
                    'from_uid' => $fromUid,
                    'from_nickname' => $fromUser['nickname'] ?? '',
                    'from_avatar' => $fromUser['avatar'] ?? '',
                    'create_time' => $notification->create_time,
                ],
            ]);
        } catch (\Throwable $e) {
            // 推送失败不影响通知入库
        }
    }

    /**
     * 平台系统消息（单用户）
     */
    public function createSystemMessage(int $uid, string $title, string $content): void
    {
        $payload = is_array(json_decode($content, true))
            ? $content
            : json_encode(['text' => $content], JSON_UNESCAPED_UNICODE);
        $this->createAndPush($uid, 0, 'system', $title, $payload, 'system', 0);
    }

    /**
     * 平台系统消息广播（uids 为空则全体用户分批）
     */
    public function broadcastSystemMessage(string $title, string $content, array $uids = []): int
    {
        $count = 0;
        if ($uids) {
            foreach ($uids as $uid) {
                $this->createSystemMessage((int)$uid, $title, $content);
                $count++;
            }
            return $count;
        }

        Db::name('user')->where('status', 1)->field('uid')->chunk(200, function ($users) use ($title, $content, &$count) {
            foreach ($users as $user) {
                $this->createSystemMessage((int)$user['uid'], $title, $content);
                $count++;
            }
        });
        return $count;
    }

    public function getUnreadTotal(int $uid): array
    {
        $dialogUnread = (int)Db::name('user_dialog')->where('uid_a', $uid)->sum('unread_a')
            + (int)Db::name('user_dialog')->where('uid_b', $uid)->sum('unread_b');

        $serviceUnread = (int)Db::name('store_service_user')->where('uid', $uid)->sum('user_unread');
        $notificationUnread = $this->getUnreadCount($uid);
        $total = $dialogUnread + $serviceUnread + $notificationUnread;

        return [
            'total' => $total,
            'dialog_unread' => $dialogUnread,
            'service_unread' => $serviceUnread,
            'notification_unread' => $notificationUnread,
        ];
    }

    private function parseContent($content): array
    {
        if (is_array($content)) {
            return $content;
        }
        if (!is_string($content) || $content === '') {
            return [];
        }
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<int, array{uid:int,nickname:string,avatar:string}>
     */
    private function batchFromUsers(array $fromUids): array
    {
        $map = [];
        if (!$fromUids) {
            return $map;
        }
        $rows = Db::name('user')->field('uid,nickname,avatar')->whereIn('uid', $fromUids)->select()->toArray();
        foreach ($rows as $r) {
            $map[(int)$r['uid']] = $r;
        }
        return $map;
    }

    /**
     * @return array<int, array{key:string,label:string}>
     */
    private function batchRelationLabels(int $uid, array $fromUids): array
    {
        $map = [];
        if (!$fromUids) {
            return $map;
        }
        $fansType = RelevanceRepository::TYPE_COMMUNITY_FANS;
        $iFollow = Relevance::where('left_id', $uid)
            ->whereIn('right_id', $fromUids)
            ->where('type', $fansType)
            ->column('right_id');
        $followMe = Relevance::whereIn('left_id', $fromUids)
            ->where('right_id', $uid)
            ->where('type', $fansType)
            ->column('left_id');
        $iFollowSet = array_flip(array_map('intval', $iFollow ?: []));
        $followMeSet = array_flip(array_map('intval', $followMe ?: []));

        foreach ($fromUids as $fromUid) {
            $a = isset($iFollowSet[$fromUid]);
            $b = isset($followMeSet[$fromUid]);
            if ($a && $b) {
                $map[$fromUid] = ['key' => 'mutual_follow', 'label' => '互相关注'];
            } elseif ($a) {
                $map[$fromUid] = ['key' => 'following', 'label' => '你的关注'];
            } elseif ($b) {
                $map[$fromUid] = ['key' => 'follower', 'label' => '你的粉丝'];
            } else {
                $map[$fromUid] = ['key' => '', 'label' => ''];
            }
        }
        return $map;
    }

    /**
     * 组装笔记通知 content JSON
     */
    public static function buildNoteContent(array $extra = []): string
    {
        return json_encode($extra, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 取用户最新一条笔记的首图/标题
     */
    public static function latestNoteBrief(int $uid): array
    {
        $row = Db::name('community')
            ->where('uid', $uid)
            ->where('is_del', 0)
            ->where('status', 1)
            ->where('is_show', 1)
            ->field('community_id,title,image')
            ->order('community_id', 'desc')
            ->find();
        if (!$row) {
            return ['community_id' => 0, 'title' => '', 'image' => ''];
        }
        $image = (string)($row['image'] ?? '');
        $first = $image !== '' ? explode(',', $image)[0] : '';
        return [
            'community_id' => (int)$row['community_id'],
            'title' => (string)($row['title'] ?? ''),
            'image' => $first,
        ];
    }

    public static function noteBriefById(int $communityId): array
    {
        $row = Db::name('community')
            ->where('community_id', $communityId)
            ->field('community_id,title,image,uid')
            ->find();
        if (!$row) {
            return ['community_id' => 0, 'title' => '', 'image' => '', 'uid' => 0];
        }
        $image = (string)($row['image'] ?? '');
        $first = $image !== '' ? explode(',', $image)[0] : '';
        return [
            'community_id' => (int)$row['community_id'],
            'title' => (string)($row['title'] ?? ''),
            'image' => $first,
            'uid' => (int)($row['uid'] ?? 0),
        ];
    }
}
