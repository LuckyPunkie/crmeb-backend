<?php
// +----------------------------------------------------------------------
// | 汇总某条业务内容的所有媒体检测任务，决定内容最终状态
// | 任一拒绝 → 拒绝；仍有待回调 → 等待；有疑似 → 留在人工审核；全部通过/超时放行 → 按原规则发布
// +----------------------------------------------------------------------

namespace crmeb\services\security;

use app\common\repositories\community\CommunityRepository;
use app\common\repositories\user\UserCertificationRepository;
use app\common\repositories\user\UserMessageRepository;
use think\facade\Db;
use think\facade\Log;

class MediaModerationResolver
{
    const VERDICT_PENDING = 'pending';
    const VERDICT_REJECT = 'reject';
    const VERDICT_REVIEW = 'review';
    const VERDICT_PASS = 'pass';

    public static function verdict(string $bizType, int $bizId): string
    {
        $statuses = array_map('intval', Db::name('ugc_check_task')
            ->where('biz_type', $bizType)->where('biz_id', $bizId)->column('status'));
        if (in_array(ContentSecurityService::TASK_REJECT, $statuses, true)) {
            return self::VERDICT_REJECT;
        }
        if (in_array(ContentSecurityService::TASK_PENDING, $statuses, true)) {
            return self::VERDICT_PENDING;
        }
        if (in_array(ContentSecurityService::TASK_REVIEW, $statuses, true)) {
            return self::VERDICT_REVIEW;
        }
        return self::VERDICT_PASS;
    }

    public static function resolve(string $bizType, int $bizId): void
    {
        $verdict = self::verdict($bizType, $bizId);
        if ($verdict === self::VERDICT_PENDING) {
            return;
        }
        switch ($bizType) {
            case 'community_post':
                self::resolveCommunityPost($bizId, $verdict);
                break;
            // 以下为"先发后审"的资料类图片：只在违规时处理，按被拒图片精确清除
            case 'goods_comment':
                if ($verdict === self::VERDICT_REJECT) {
                    self::resolveGoodsComment($bizId);
                }
                break;
            case 'avatar':
                if ($verdict === self::VERDICT_REJECT) {
                    self::resolveAvatar($bizId);
                }
                break;
            case 'profile_cover':
                if ($verdict === self::VERDICT_REJECT) {
                    self::resolveProfileCover($bizId);
                }
                break;
            case 'certification':
                if ($verdict === self::VERDICT_REJECT) {
                    $status = Db::name('user_certification')->where('id', $bizId)->value('status');
                    if ($status !== null && (int)$status !== 2) {
                        // 复用后台驳回逻辑：改状态、撤认证标签、刷新用户审核状态、通知用户
                        app()->make(UserCertificationRepository::class)->review($bizId, 2, '证件图片包含违规内容');
                    }
                }
                break;
            case 'chat_msg':
                // 私聊图片/语音先发后审：只处理违规撤回，疑似不撤回
                if ($verdict === self::VERDICT_REJECT) {
                    $fromUid = (int)Db::name('user_message')->where('message_id', $bizId)->value('from_uid');
                    if (app()->make(UserMessageRepository::class)->systemRecallMessage($bizId) && $fromUid) {
                        ContentSecurityService::notifyRecalled($fromUid);
                    }
                }
                break;
            default:
                Log::warning("媒体审核结果无对应处理：{$bizType}#{$bizId} => {$verdict}");
        }
    }

    /**
     * 该业务下被判违规的图片路径（去掉域名比较，兼容相对/绝对地址）
     * @return string[]
     */
    protected static function rejectedPaths(string $bizType, int $bizId): array
    {
        $urls = Db::name('ugc_check_task')->where('biz_type', $bizType)->where('biz_id', $bizId)
            ->where('status', ContentSecurityService::TASK_REJECT)->column('media_url');
        return array_values(array_unique(array_map([self::class, 'urlPath'], $urls)));
    }

    protected static function urlPath($url): string
    {
        $url = trim((string)$url);
        $path = parse_url($url, PHP_URL_PATH);
        return $path ? '/' . ltrim($path, '/') : $url;
    }

    protected static function resolveGoodsComment(int $replyId): void
    {
        $reply = Db::name('store_product_reply')->where('reply_id', $replyId)->field('uid,pics')->find();
        if (!$reply || (string)$reply['pics'] === '') {
            return;
        }
        $rejected = self::rejectedPaths('goods_comment', $replyId);
        $pics = array_filter(explode(',', (string)$reply['pics']));
        $remain = array_values(array_filter($pics, static function ($pic) use ($rejected) {
            return !in_array(self::urlPath($pic), $rejected, true);
        }));
        if (count($remain) !== count($pics)) {
            Db::name('store_product_reply')->where('reply_id', $replyId)->update(['pics' => implode(',', $remain)]);
            ContentSecurityService::notifyRejected((int)$reply['uid'], '评价图片');
        }
    }

    protected static function resolveAvatar(int $uid): void
    {
        $avatar = (string)Db::name('user')->where('uid', $uid)->value('avatar');
        if ($avatar === '' || !in_array(self::urlPath($avatar), self::rejectedPaths('avatar', $uid), true)) {
            return; // 已换成别的头像，不处理
        }
        Db::name('user')->where('uid', $uid)->update(['avatar' => (string)(systemConfig('user_default_avatar') ?: '')]);
        ContentSecurityService::notifyRejected($uid, '头像');
    }

    protected static function resolveProfileCover(int $uid): void
    {
        $fields = ['cover_info', 'cover_about', 'cover_hope', 'cover_hobby', 'hobby_photo_1', 'hobby_photo_2'];
        $row = Db::name('user_profile')->where('uid', $uid)->field($fields)->find();
        if (!$row) {
            return;
        }
        $rejected = self::rejectedPaths('profile_cover', $uid);
        $clear = [];
        foreach ($fields as $field) {
            $value = (string)($row[$field] ?? '');
            if ($value !== '' && in_array(self::urlPath($value), $rejected, true)) {
                $clear[$field] = '';
            }
        }
        if ($clear) {
            Db::name('user_profile')->where('uid', $uid)->update($clear);
            ContentSecurityService::notifyRejected($uid, '主页图片');
        }
    }

    /** 供 V2 提交失败时端侧降级结果汇总 */
    public static function applyCommunityPostVerdict(int $communityId, string $verdict): void
    {
        self::resolveCommunityPost($communityId, $verdict);
    }

    /**
     * 只处理仍在待审（status=0）的笔记，管理员已人工处理过的不覆盖
     */
    protected static function resolveCommunityPost(int $communityId, string $verdict): void
    {
        $post = Db::name('community')->where('community_id', $communityId)->find();
        if (!$post || (int)$post['is_del'] === 1 || (int)$post['status'] !== 0) {
            return;
        }
        $repository = app()->make(CommunityRepository::class);

        if ($verdict === self::VERDICT_REJECT) {
            $repository->setStatus($communityId, ['status' => -1, 'is_show' => 0, 'refusal' => '图片或视频包含违规内容']);
            ContentSecurityService::notifyRejected((int)$post['uid'], '笔记');
            return;
        }
        if ($verdict === self::VERDICT_REVIEW) {
            return; // 留在后台待审列表，由人工决定
        }

        // 媒体通过：文本也通过且后台开启了免审时才自动发布，否则仍走人工审核
        $textSuggest = Db::name('ugc_check_log')
            ->where('biz_type', 'community_post')->where('biz_id', $communityId)
            ->order('id', 'desc')->value('suggest');
        if ($textSuggest && $textSuggest !== 'pass') {
            return;
        }
        $auditFree = (string)$post['is_type'] === CommunityRepository::COMMUNIT_TYPE_VIDEO
            ? systemConfig('community_video_audit')
            : systemConfig('community_audit');
        if ((int)$auditFree !== 1) {
            return;
        }
        $repository->setStatus($communityId, ['status' => 1, 'is_show' => 1]);
    }
}
