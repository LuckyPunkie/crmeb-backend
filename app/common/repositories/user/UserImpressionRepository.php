<?php

namespace app\common\repositories\user;

use app\common\dao\user\UserImpressionCommentDao;
use app\common\dao\user\UserImpressionDao;
use app\common\dao\user\UserImpressionLikeDao;
use app\common\model\system\Relevance;
use app\common\model\user\User;
use app\common\model\user\UserImpression;
use app\common\model\user\UserImpressionComment;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\RelevanceRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 印象墙
 *
 * 关系标签快照 relation_snapshot：
 *   0 陌生人 / 1 互关 / 2 墙主粉丝(对方关注墙主) / 3 墙主关注(墙主关注对方)
 *   4 墙主拉黑(墙主拉黑对方) / 5 拉黑墙主(对方拉黑墙主) / 6 互相拉黑
 *
 * @mixin UserImpressionDao
 */
class UserImpressionRepository extends BaseRepository
{
    const REL_STRANGER      = 0;
    const REL_MUTUAL_FOLLOW = 1;
    const REL_OWNER_FAN     = 2;
    const REL_OWNER_FOLLOW  = 3;
    const REL_OWNER_BLOCK   = 4;
    const REL_BLOCK_OWNER   = 5;
    const REL_MUTUAL_BLOCK  = 6;

    const REL_LABELS = [
        self::REL_STRANGER      => '陌生人',
        self::REL_MUTUAL_FOLLOW => '互相关注',
        self::REL_OWNER_FAN     => '墙主粉丝',
        self::REL_OWNER_FOLLOW  => '墙主关注',
        self::REL_OWNER_BLOCK   => '墙主已拉黑',
        self::REL_BLOCK_OWNER   => '已拉黑墙主',
        self::REL_MUTUAL_BLOCK  => '互相拉黑',
    ];

    /** @var UserImpressionCommentDao */
    protected $commentDao;
    /** @var UserImpressionLikeDao */
    protected $likeDao;

    public function __construct(UserImpressionDao $dao, UserImpressionCommentDao $commentDao, UserImpressionLikeDao $likeDao)
    {
        $this->dao = $dao;
        $this->commentDao = $commentDao;
        $this->likeDao = $likeDao;
    }

    /* ────────────── 开关 ────────────── */

    public function toggleWall(int $uid, int $enabled): void
    {
        Db::name('user')->where('uid', $uid)->update([
            'impression_wall_enabled' => $enabled ? 1 : 0,
        ]);
    }

    public function isWallEnabled(int $ownerUid): bool
    {
        return (int) Db::name('user')->where('uid', $ownerUid)->value('impression_wall_enabled') === 1;
    }

    /* ────────────── 关系标签计算 ────────────── */

    /**
     * 计算 `发布者(fromUid) 相对于 墙主(ownerUid)` 的关系标签快照。
     * 优先级：互相拉黑 > 墙主拉黑/被拉黑 > 互关 > 墙主粉丝/墙主关注 > 陌生人
     */
    public function calcRelationSnapshot(int $ownerUid, int $fromUid): int
    {
        if ($ownerUid == $fromUid) return self::REL_STRANGER;

        [$blockA, $blockB] = $this->readBlackPair($ownerUid, $fromUid);
        if ($blockA && $blockB) return self::REL_MUTUAL_BLOCK;
        if ($blockA) return self::REL_OWNER_BLOCK;
        if ($blockB) return self::REL_BLOCK_OWNER;

        $fansType   = RelevanceRepository::TYPE_COMMUNITY_FANS;
        $ownerToFrom = Relevance::where('left_id', $ownerUid)->where('right_id', $fromUid)->where('type', $fansType)->count() > 0;
        $fromToOwner = Relevance::where('left_id', $fromUid)->where('right_id', $ownerUid)->where('type', $fansType)->count() > 0;

        if ($ownerToFrom && $fromToOwner) return self::REL_MUTUAL_FOLLOW;
        if ($fromToOwner) return self::REL_OWNER_FAN;
        if ($ownerToFrom) return self::REL_OWNER_FOLLOW;
        return self::REL_STRANGER;
    }

    /**
     * 读取 (owner→from, from→owner) 是否拉黑
     */
    private function readBlackPair(int $ownerUid, int $fromUid): array
    {
        $a = min($ownerUid, $fromUid);
        $b = max($ownerUid, $fromUid);
        $row = Db::name('user_dialog')
            ->where('uid_a', $a)
            ->where('uid_b', $b)
            ->field('is_black_a,is_black_b')
            ->find();
        if (!$row) return [false, false];
        // is_black_a: uid_a 是否拉黑 uid_b
        if ($a == $ownerUid) {
            return [(bool) $row['is_black_a'], (bool) $row['is_black_b']];
        }
        return [(bool) $row['is_black_b'], (bool) $row['is_black_a']];
    }

    /* ────────────── 头部信息 ────────────── */

    public function summary(int $ownerUid): array
    {
        $owner = Db::name('user')
            ->where('uid', $ownerUid)
            ->field('uid,nickname,avatar,sex,impression_wall_enabled')
            ->find();
        if (!$owner) throw new ValidateException('用户不存在');

        return [
            'owner_uid'   => (int) $owner['uid'],
            'nickname'    => (string) $owner['nickname'],
            'avatar'      => (string) $owner['avatar'],
            'sex'         => (int) $owner['sex'],
            'wall_enabled' => (int) $owner['impression_wall_enabled'] === 1,
            'total'       => $this->dao->countByOwner($ownerUid),
            'participants' => $this->dao->distinctFromCount($ownerUid),
        ];
    }

    /* ────────────── 印象 CRUD ────────────── */

    public function createImpression(int $ownerUid, int $fromUid, string $content, string $image): array
    {
        if ($ownerUid == $fromUid) throw new ValidateException('不能给自己留印象');
        if (!$this->isWallEnabled($ownerUid)) throw new ValidateException('对方未开启印象墙');

        $len = mb_strlen($content);
        if ($len < 1 || $len > 500) throw new ValidateException('印象文字需在 1-500 字之间');

        $snapshot = $this->calcRelationSnapshot($ownerUid, $fromUid);

        $imp = UserImpression::create([
            'owner_uid' => $ownerUid,
            'from_uid'  => $fromUid,
            'content'   => $content,
            'image'     => $image,
            'relation_snapshot' => $snapshot,
        ]);

        // 通知墙主
        try {
            $preview = mb_substr($content, 0, 60);
            $payload = json_encode([
                'text' => $preview,
                'owner_uid' => $ownerUid,
                'impression_id' => $imp->impression_id,
                'image' => $image,
            ], JSON_UNESCAPED_UNICODE);
            app()->make(UserNotificationRepository::class)
                ->createAndPush($ownerUid, $fromUid, 'impression_new', '新印象', $payload, 'impression', (int) $imp->impression_id);
        } catch (\Throwable $e) {
        }

        // 重新读一次，确保 like_count/comment_count 等 DB 默认字段齐全
        $fresh = $this->dao->getRow((int) $imp->impression_id);
        return $this->formatImpression($fresh ? $fresh->toArray() : $imp->toArray(), $fromUid);
    }

    public function deleteImpression(int $impressionId, int $uid): void
    {
        $row = $this->dao->getRow($impressionId);
        if (!$row) throw new ValidateException('印象不存在');
        if ((int) $row['from_uid'] !== $uid) throw new ValidateException('只有发布者可以删除');

        Db::transaction(function () use ($row) {
            UserImpression::where('impression_id', $row['impression_id'])->update(['is_deleted' => 1]);
            UserImpressionComment::where('impression_id', $row['impression_id'])
                ->where('is_deleted', 0)
                ->update(['is_deleted' => 1]);
        });

        // 通知墙主
        try {
            $payload = json_encode([
                'owner_uid' => (int) $row['owner_uid'],
                'impression_id' => (int) $row['impression_id'],
            ], JSON_UNESCAPED_UNICODE);
            app()->make(UserNotificationRepository::class)
                ->createAndPush((int) $row['owner_uid'], $uid, 'impression_deleted', '印象已删除', $payload, 'impression', (int) $row['impression_id']);
        } catch (\Throwable $e) {
        }
    }

    /**
     * 印象列表（含发布者信息、当前 uid 是否点赞、前 2 条一级评论预览）
     */
    public function listImpressions(int $ownerUid, int $viewerUid, int $page, int $limit): array
    {
        $list = $this->dao->listByOwner($ownerUid, $page, $limit);
        $rows = [];
        $ids  = [];
        $fromUids = [];
        foreach ($list as $item) {
            $r = $item->toArray();
            $ids[] = (int) $r['impression_id'];
            $fromUids[] = (int) $r['from_uid'];
            $rows[] = $r;
        }
        if (!$rows) {
            return ['list' => [], 'count' => $this->dao->countByOwner($ownerUid)];
        }

        $userMap  = $this->batchUsers(array_unique(array_filter($fromUids)));
        $likedSet = $viewerUid > 0 ? array_flip($this->likeDao->likedIds($viewerUid, 'impression', $ids)) : [];

        // 每条印象的前 2 条一级评论预览 + 每条评论的前 3 条楼中楼预览
        $previewMap = $this->previewCommentsForImpressions($ids, $viewerUid);

        $out = [];
        foreach ($rows as $r) {
            $r = $this->formatImpression($r, $viewerUid, $userMap, $likedSet);
            $r['comment_preview'] = $previewMap[$r['impression_id']] ?? ['top' => [], 'top_total' => 0];
            $out[] = $r;
        }
        return ['list' => $out, 'count' => $this->dao->countByOwner($ownerUid)];
    }

    private function formatImpression(array $r, int $viewerUid, array $userMap = null, array $likedSet = null): array
    {
        if ($userMap === null) {
            $userMap = $this->batchUsers([(int) $r['from_uid']]);
        }
        if ($likedSet === null) {
            $likedSet = $viewerUid > 0
                ? array_flip($this->likeDao->likedIds($viewerUid, 'impression', [(int) $r['impression_id']]))
                : [];
        }
        $u = $userMap[(int) $r['from_uid']] ?? null;
        $rel = (int) ($r['relation_snapshot'] ?? 0);
        return [
            'impression_id' => (int) $r['impression_id'],
            'owner_uid'     => (int) ($r['owner_uid'] ?? 0),
            'from_uid'      => (int) ($r['from_uid'] ?? 0),
            'from_nickname' => (string) ($u['nickname'] ?? ''),
            'from_avatar'   => (string) ($u['avatar'] ?? ''),
            'from_sex'      => (int) ($u['sex'] ?? 0),
            'from_profile'  => (string) ($u['profile_line'] ?? ''),
            'content'       => (string) ($r['content'] ?? ''),
            'image'         => (string) ($r['image'] ?? ''),
            'like_count'    => (int) ($r['like_count'] ?? 0),
            'comment_count' => (int) ($r['comment_count'] ?? 0),
            'liked'         => isset($likedSet[(int) $r['impression_id']]),
            'is_owner_view' => $viewerUid > 0 && $viewerUid == (int) ($r['owner_uid'] ?? 0),
            'can_delete'    => $viewerUid > 0 && $viewerUid == (int) ($r['from_uid'] ?? 0),
            'relation_key'  => $rel,
            'relation_label'=> self::REL_LABELS[$rel] ?? '',
            'is_wall_owner_reply' => (int) ($r['from_uid'] ?? 0) === (int) ($r['owner_uid'] ?? 0),
            'create_time'   => (string) ($r['create_time'] ?? ''),
        ];
    }

    /* ────────────── 评论 & 楼中楼 ────────────── */

    /**
     * 评论列表（一级正序 + 每条一级下前 N 条二级预览 + 更多）
     */
    public function listComments(int $impressionId, int $viewerUid, int $page, int $limit): array
    {
        $imp = UserImpression::where('impression_id', $impressionId)->where('is_deleted', 0)->find();
        if (!$imp) throw new ValidateException('印象不存在');

        $topRows = $this->commentDao->topLevelList($impressionId, $page, $limit);
        $topIds = [];
        $userIds = [];
        $out = [];
        foreach ($topRows as $item) {
            $r = $item->toArray();
            $topIds[] = (int) $r['comment_id'];
            $userIds[] = (int) $r['from_uid'];
            $out[] = $r;
        }
        if (!$out) {
            return [
                'list' => [],
                'top_total' => $this->commentDao->countTopLevel($impressionId),
            ];
        }

        // 楼中楼一次性拉全（前端负责收起/展开）
        $childRowsRaw = UserImpressionComment::whereIn('parent_id', $topIds)
            ->where('is_deleted', 0)
            ->order('create_time', 'asc')
            ->order('comment_id', 'asc')
            ->select();
        $childMap = [];
        foreach ($childRowsRaw as $c) {
            $ca = $c->toArray();
            $pid = (int) $ca['parent_id'];
            $childMap[$pid][] = $ca;
            $userIds[] = (int) $ca['from_uid'];
            if ((int) $ca['reply_to_uid']) $userIds[] = (int) $ca['reply_to_uid'];
        }

        $userMap = $this->batchUsers(array_values(array_unique(array_filter($userIds))));
        $ownerUid = (int) $imp['owner_uid'];

        $allCommentIds = $topIds;
        foreach ($childMap as $list) {
            foreach ($list as $c) $allCommentIds[] = (int) $c['comment_id'];
        }
        $likedSet = $viewerUid > 0 ? array_flip($this->likeDao->likedIds($viewerUid, 'comment', $allCommentIds)) : [];

        $result = [];
        foreach ($out as $r) {
            $formatted = $this->formatComment($r, $viewerUid, $ownerUid, $userMap, $likedSet);
            $children = $childMap[(int) $r['comment_id']] ?? [];
            $formatted['replies'] = array_map(function ($c) use ($viewerUid, $ownerUid, $userMap, $likedSet) {
                return $this->formatComment($c, $viewerUid, $ownerUid, $userMap, $likedSet);
            }, $children);
            $formatted['reply_count'] = count($children);
            $result[] = $formatted;
        }

        return [
            'list' => $result,
            'top_total' => $this->commentDao->countTopLevel($impressionId),
        ];
    }

    private function formatComment(array $r, int $viewerUid, int $ownerUid, array $userMap, array $likedSet): array
    {
        $u = $userMap[(int) ($r['from_uid'] ?? 0)] ?? null;
        $rTo = (int) ($r['reply_to_uid'] ?? 0);
        $toUser = $rTo > 0 ? ($userMap[$rTo] ?? null) : null;
        $rel = (int) ($r['relation_snapshot'] ?? 0);
        $images = [];
        if (!empty($r['images'])) {
            $parsed = json_decode((string) $r['images'], true);
            if (is_array($parsed)) $images = $parsed;
        }
        return [
            'comment_id'     => (int) ($r['comment_id'] ?? 0),
            'impression_id'  => (int) ($r['impression_id'] ?? 0),
            'parent_id'      => (int) ($r['parent_id'] ?? 0),
            'from_uid'       => (int) ($r['from_uid'] ?? 0),
            'from_nickname'  => (string) ($u['nickname'] ?? ''),
            'from_avatar'    => (string) ($u['avatar'] ?? ''),
            'from_sex'       => (int) ($u['sex'] ?? 0),
            'from_profile'   => (string) ($u['profile_line'] ?? ''),
            'is_wall_owner'  => (int) ($r['from_uid'] ?? 0) === $ownerUid,
            'reply_to_uid'   => $rTo,
            'reply_to_nickname' => (string) ($toUser['nickname'] ?? ''),
            'reply_to_is_wall_owner' => $rTo > 0 && $rTo === $ownerUid,
            'content'        => (string) ($r['content'] ?? ''),
            'images'         => $images,
            'like_count'     => (int) ($r['like_count'] ?? 0),
            'liked'          => isset($likedSet[(int) ($r['comment_id'] ?? 0)]),
            'relation_key'   => $rel,
            'relation_label' => self::REL_LABELS[$rel] ?? '',
            // PRD §5：本人可删除自己的评论；墙主可删除自己墙上的评论
            'can_delete'     => $viewerUid > 0 && ($viewerUid == (int) ($r['from_uid'] ?? 0) || $viewerUid == $ownerUid),
            'create_time'    => (string) ($r['create_time'] ?? ''),
        ];
    }

    /**
     * 每印象前 2 条一级评论 + 每条一级前 3 条二级预览
     */
    private function previewCommentsForImpressions(array $impressionIds, int $viewerUid): array
    {
        if (!$impressionIds) return [];
        $result = [];
        $preview_per_imp = 2;
        $preview_replies_per_top = 3;

        // 拉每印象最新 2 条一级
        $rows = Db::name('user_impression_comment')
            ->whereIn('impression_id', $impressionIds)
            ->where('parent_id', 0)
            ->where('is_deleted', 0)
            ->order('create_time', 'asc')
            ->order('comment_id', 'asc')
            ->select()
            ->toArray();

        // group + take head N
        $grouped = [];
        foreach ($rows as $r) {
            $iid = (int) $r['impression_id'];
            $grouped[$iid] = $grouped[$iid] ?? [];
            if (count($grouped[$iid]) < $preview_per_imp) {
                $grouped[$iid][] = $r;
            }
        }

        // 一级 comment 的 top_total
        $topTotalMap = [];
        foreach ($impressionIds as $iid) $topTotalMap[$iid] = 0;
        $stat = Db::name('user_impression_comment')
            ->whereIn('impression_id', $impressionIds)
            ->where('parent_id', 0)
            ->where('is_deleted', 0)
            ->field('impression_id, COUNT(*) as c')
            ->group('impression_id')
            ->select()
            ->toArray();
        foreach ($stat as $s) $topTotalMap[(int) $s['impression_id']] = (int) $s['c'];

        // 拉这些 top 的 replies
        $topIds = [];
        foreach ($grouped as $list) foreach ($list as $r) $topIds[] = (int) $r['comment_id'];
        $repliesMap = [];
        $replyTotalMap = [];
        if ($topIds) {
            $replyRows = Db::name('user_impression_comment')
                ->whereIn('parent_id', $topIds)
                ->where('is_deleted', 0)
                ->order('create_time', 'asc')
                ->order('comment_id', 'asc')
                ->select()
                ->toArray();
            foreach ($replyRows as $r) {
                $pid = (int) $r['parent_id'];
                $repliesMap[$pid] = $repliesMap[$pid] ?? [];
                $replyTotalMap[$pid] = ($replyTotalMap[$pid] ?? 0) + 1;
                if (count($repliesMap[$pid]) < $preview_replies_per_top) {
                    $repliesMap[$pid][] = $r;
                }
            }
        }

        // 收集所有需要的 user
        $userIds = [];
        foreach ($grouped as $list) foreach ($list as $r) {
            $userIds[] = (int) $r['from_uid'];
        }
        foreach ($repliesMap as $list) foreach ($list as $r) {
            $userIds[] = (int) $r['from_uid'];
            if ((int) $r['reply_to_uid']) $userIds[] = (int) $r['reply_to_uid'];
        }
        $userMap = $this->batchUsers(array_values(array_unique(array_filter($userIds))));

        // 拉 owner uid
        $ownerMap = [];
        $ownerRows = Db::name('user_impression')
            ->whereIn('impression_id', $impressionIds)
            ->column('owner_uid', 'impression_id');
        foreach ($ownerRows as $iid => $ouid) $ownerMap[(int) $iid] = (int) $ouid;

        // liked
        $allCids = array_merge($topIds, array_map(function ($r) { return (int) $r['comment_id']; }, array_merge(...array_values($repliesMap ?: [[]]))));
        $likedSet = $viewerUid > 0 && $allCids ? array_flip($this->likeDao->likedIds($viewerUid, 'comment', $allCids)) : [];

        foreach ($impressionIds as $iid) {
            $ownerUid = $ownerMap[$iid] ?? 0;
            $topList = [];
            foreach ($grouped[$iid] ?? [] as $r) {
                $formatted = $this->formatComment($r, $viewerUid, $ownerUid, $userMap, $likedSet);
                $pid = (int) $r['comment_id'];
                $formatted['replies'] = array_map(function ($x) use ($viewerUid, $ownerUid, $userMap, $likedSet) {
                    return $this->formatComment($x, $viewerUid, $ownerUid, $userMap, $likedSet);
                }, $repliesMap[$pid] ?? []);
                $formatted['reply_count'] = $replyTotalMap[$pid] ?? 0;
                $topList[] = $formatted;
            }
            $result[$iid] = ['top' => $topList, 'top_total' => $topTotalMap[$iid] ?? 0];
        }
        return $result;
    }

    /**
     * 展开某条一级评论下的全部楼中楼
     * @return array{list: array, total: int}
     */
    public function listRepliesOfParent(int $parentId, int $viewerUid): array
    {
        $parent = UserImpressionComment::where('comment_id', $parentId)
            ->where('is_deleted', 0)
            ->find();
        if (!$parent) throw new ValidateException('评论不存在');
        if ((int) $parent['parent_id'] !== 0) throw new ValidateException('该评论不是一级评论');

        $rows = $this->commentDao->repliesOfParent($parentId, 500);
        $items = [];
        $userIds = [];
        foreach ($rows as $r) {
            $ra = $r->toArray();
            $items[] = $ra;
            $userIds[] = (int) $ra['from_uid'];
            if ((int) $ra['reply_to_uid']) $userIds[] = (int) $ra['reply_to_uid'];
        }
        $userMap = $this->batchUsers(array_values(array_unique(array_filter($userIds))));
        $ownerUid = (int) $parent['owner_uid'];
        $ids = array_map(function ($x) { return (int) $x['comment_id']; }, $items);
        $likedSet = $viewerUid > 0 && $ids ? array_flip($this->likeDao->likedIds($viewerUid, 'comment', $ids)) : [];

        $out = [];
        foreach ($items as $it) {
            $out[] = $this->formatComment($it, $viewerUid, $ownerUid, $userMap, $likedSet);
        }
        return ['list' => $out, 'total' => count($out)];
    }

    public function createComment(int $impressionId, int $fromUid, array $data): array
    {
        $imp = UserImpression::where('impression_id', $impressionId)->where('is_deleted', 0)->find();
        if (!$imp) throw new ValidateException('印象不存在');

        $content = trim((string) ($data['content'] ?? ''));
        $len = mb_strlen($content);
        if ($len < 1 || $len > 300) throw new ValidateException('评论文字需在 1-300 字之间');

        $images = $data['images'] ?? [];
        if (!is_array($images)) $images = [];
        if (count($images) > 9) throw new ValidateException('图片最多 9 张');

        $parentId = (int) ($data['parent_id'] ?? 0);
        $replyToUid = (int) ($data['reply_to_uid'] ?? 0);
        $replyToCommentId = (int) ($data['reply_to_comment_id'] ?? 0);

        // 楼中楼归并规则：超出二级归入所回复的顶层
        if ($parentId > 0) {
            $parent = UserImpressionComment::where('comment_id', $parentId)
                ->where('is_deleted', 0)
                ->find();
            if (!$parent) throw new ValidateException('回复的评论不存在');
            if ((int) $parent['impression_id'] !== $impressionId) throw new ValidateException('评论与印象不匹配');
            // 若 parent 本身是二级，归并到它的顶层 parent
            if ((int) $parent['parent_id'] > 0) {
                $replyToCommentId = $replyToCommentId ?: (int) $parent['comment_id'];
                $replyToUid = $replyToUid ?: (int) $parent['from_uid'];
                $parentId = (int) $parent['parent_id'];
            }
        }

        $snapshot = $this->calcRelationSnapshot((int) $imp['owner_uid'], $fromUid);

        $created = null;
        Db::transaction(function () use ($imp, $fromUid, $content, $images, $parentId, $replyToUid, $replyToCommentId, $snapshot, &$created) {
            $created = UserImpressionComment::create([
                'impression_id' => (int) $imp['impression_id'],
                'owner_uid'     => (int) $imp['owner_uid'],
                'parent_id'     => $parentId,
                'reply_to_uid'  => $replyToUid,
                'reply_to_comment_id' => $replyToCommentId,
                'from_uid'      => $fromUid,
                'content'       => $content,
                'images'        => $images ? json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'relation_snapshot' => $snapshot,
            ]);
            $this->dao->incCommentCount((int) $imp['impression_id']);
        });

        // 通知：一级 → 通知印象发布者；二级 → 通知被回复人；两者叠加时去重
        try {
            $notifiedUids = [];
            $notify = app()->make(UserNotificationRepository::class);
            $payload = json_encode([
                'owner_uid'     => (int) $imp['owner_uid'],
                'impression_id' => (int) $imp['impression_id'],
                'comment_id'    => (int) $created->comment_id,
                'text'          => mb_substr($content, 0, 60),
            ], JSON_UNESCAPED_UNICODE);

            if ($parentId === 0) {
                // 顶层评论 → 通知印象发布者
                $target = (int) $imp['from_uid'];
                if ($target > 0 && $target !== $fromUid) {
                    $notify->createAndPush($target, $fromUid, 'impression_comment', '你的印象被评论', $payload, 'impression', (int) $imp['impression_id']);
                    $notifiedUids[$target] = true;
                }
            } else {
                // 二级：通知被回复人
                if ($replyToUid > 0 && $replyToUid !== $fromUid && !isset($notifiedUids[$replyToUid])) {
                    $notify->createAndPush($replyToUid, $fromUid, 'impression_comment_reply', '你的评论有新回复', $payload, 'impression_comment', (int) $created->comment_id);
                    $notifiedUids[$replyToUid] = true;
                }
                // 同时通知一级评论作者（如果不同人）
                $topAuthor = (int) Db::name('user_impression_comment')->where('comment_id', $parentId)->value('from_uid');
                if ($topAuthor > 0 && $topAuthor !== $fromUid && !isset($notifiedUids[$topAuthor])) {
                    $notify->createAndPush($topAuthor, $fromUid, 'impression_comment_reply', '你的评论有新回复', $payload, 'impression_comment', $parentId);
                    $notifiedUids[$topAuthor] = true;
                }
            }
        } catch (\Throwable $e) {
        }

        $userMap = $this->batchUsers(array_filter([$fromUid, $replyToUid]));
        $fresh = $this->commentDao->getRow((int) $created->comment_id);
        return $this->formatComment($fresh ? $fresh->toArray() : $created->toArray(), $fromUid, (int) $imp['owner_uid'], $userMap, []);
    }

    public function deleteComment(int $commentId, int $uid): void
    {
        $row = $this->commentDao->getRow($commentId);
        if (!$row) throw new ValidateException('评论不存在');
        $ownerUid = (int) $row['owner_uid'];
        $fromUid  = (int) $row['from_uid'];
        // PRD §5：本人可删除自己的评论；墙主可删除自己墙上的评论
        if ($uid !== $ownerUid && $uid !== $fromUid) throw new ValidateException('无权删除该评论');

        Db::transaction(function () use ($row) {
            $affected = $this->commentDao->softDelete((int) $row['comment_id'], (int) $row['parent_id']);
            // 更新 impression.comment_count（校准为实际值）
            $realTotal = $this->commentDao->countTotal((int) $row['impression_id']);
            UserImpression::where('impression_id', $row['impression_id'])->update(['comment_count' => $realTotal]);
        });
    }

    /* ────────────── 点赞 ────────────── */

    /**
     * @return array{liked:bool,count:int}
     */
    public function toggleLike(int $uid, string $targetType, int $targetId): array
    {
        if (!in_array($targetType, ['impression', 'comment'], true)) throw new ValidateException('参数错误');

        if ($targetType === 'impression') {
            $target = UserImpression::where('impression_id', $targetId)->where('is_deleted', 0)->find();
            if (!$target) throw new ValidateException('印象不存在');
            $ownerUid = (int) $target['owner_uid'];
            $authorUid = (int) $target['from_uid'];
        } else {
            $target = UserImpressionComment::where('comment_id', $targetId)->where('is_deleted', 0)->find();
            if (!$target) throw new ValidateException('评论不存在');
            $ownerUid = (int) $target['owner_uid'];
            $authorUid = (int) $target['from_uid'];
        }

        $liked = false;
        $count = 0;
        Db::transaction(function () use ($uid, $targetType, $targetId, &$liked, &$count) {
            if ($this->likeDao->has($uid, $targetType, $targetId)) {
                $this->likeDao->remove($uid, $targetType, $targetId);
                if ($targetType === 'impression') {
                    $this->dao->updateLikeCount($targetId, -1);
                    $count = (int) UserImpression::where('impression_id', $targetId)->value('like_count');
                } else {
                    $this->commentDao->updateLikeCount($targetId, -1);
                    $count = (int) UserImpressionComment::where('comment_id', $targetId)->value('like_count');
                }
                $liked = false;
            } else {
                $this->likeDao->add($uid, $targetType, $targetId);
                if ($targetType === 'impression') {
                    $this->dao->updateLikeCount($targetId, 1);
                    $count = (int) UserImpression::where('impression_id', $targetId)->value('like_count');
                } else {
                    $this->commentDao->updateLikeCount($targetId, 1);
                    $count = (int) UserImpressionComment::where('comment_id', $targetId)->value('like_count');
                }
                $liked = true;
            }
        });

        // 通知作者
        if ($liked && $authorUid > 0 && $authorUid !== $uid) {
            try {
                $payload = json_encode([
                    'owner_uid'   => $ownerUid,
                    'target_type' => $targetType,
                    'target_id'   => $targetId,
                ], JSON_UNESCAPED_UNICODE);
                app()->make(UserNotificationRepository::class)
                    ->createAndPush($authorUid, $uid, 'impression_like', '你收到一个赞', $payload, $targetType === 'impression' ? 'impression' : 'impression_comment', $targetId);
            } catch (\Throwable $e) {
            }
        }

        return ['liked' => $liked, 'count' => $count];
    }

    /* ────────────── 用户信息批量 ────────────── */

    /**
     * @return array<int, array{nickname:string,avatar:string,sex:int,profile_line:string}>
     */
    public function batchUsers(array $uids): array
    {
        if (!$uids) return [];
        $users = User::whereIn('uid', $uids)->field('uid,nickname,avatar,sex')->select()->toArray();

        $profiles = Db::name('user_profile')
            ->whereIn('uid', $uids)
            ->field('uid,birth_month,education,height')
            ->select()
            ->toArray();
        $profileMap = [];
        foreach ($profiles as $p) $profileMap[(int) $p['uid']] = $p;

        $eduMap = ['', '高中', '大专', '本科', '硕士', '博士及以上', '中专', '小学', '初中'];
        $map = [];
        foreach ($users as $u) {
            $uid = (int) $u['uid'];
            $p = $profileMap[$uid] ?? [];
            $parts = [];
            $bm = (string) ($p['birth_month'] ?? '');
            if ($bm && preg_match('/^(\d{4})/', $bm, $m)) {
                $yy = substr($m[1], 2, 2);
                $parts[] = $yy . '年';
            }
            $edu = (int) ($p['education'] ?? 0);
            if ($edu > 0 && isset($eduMap[$edu])) $parts[] = $eduMap[$edu];
            $height = (int) ($p['height'] ?? 0);
            if ($height > 0) $parts[] = $height . 'cm';

            $map[$uid] = [
                'nickname' => (string) $u['nickname'],
                'avatar'   => (string) $u['avatar'],
                'sex'      => (int) $u['sex'],
                'profile_line' => implode(' · ', $parts),
            ];
        }
        return $map;
    }
}
