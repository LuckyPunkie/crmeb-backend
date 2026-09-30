<?php
// +----------------------------------------------------------------------
// | uni-sec-check Sprint 1+2: UGC 内容安全审核门面
// | 小程序端（有 openid）走微信 msgSecCheck V2，其余场景（APP/H5，V1 未接入）
// | 及微信接口异常时兜底走本地敏感词过滤，第三方故障不阻断主流程。
// | 图片/视频审核（mediaSecCheck 异步）不在本次范围，见 eb_ugc_check_task。
// +----------------------------------------------------------------------

namespace crmeb\services\security;

use crmeb\services\security\driver\JoySafetyDriver;
use crmeb\services\security\driver\WechatDriver;
use crmeb\services\SensitiveWordFilter;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;

class ContentSecurityService
{
    // 微信官方场景枚举，仅这 4 个值；聊天/反馈等无对应场景的按语义就近映射
    const SCENE_PROFILE = 1; // 资料（昵称/签名/认证说明）
    const SCENE_COMMENT = 2; // 评论（商品评价/社区评论）
    const SCENE_FORUM = 3;   // 论坛（社区发帖/简历）
    const SCENE_SOCIAL = 4;  // 社交日志（聊天/反馈）

    const MEDIA_AUDIO = 1;
    const MEDIA_IMAGE = 2;

    // eb_ugc_check_task.status
    const TASK_PENDING = 0;
    const TASK_PASS = 1;
    const TASK_REJECT = 2;
    const TASK_REVIEW = 3;
    const TASK_TIMEOUT = 4; // 超时未回调，按"服务不可用放行"处理

    // 微信通常 5~30 分钟内回调，超过这个时长仍未回调视为服务不可用
    const MEDIA_CALLBACK_TIMEOUT = 2400;

    // 三级优先队列（文档 5.4）：P0 聊天 / P1 笔记 / P2 评论
    const QUEUE_CONNECTION = 'moderation';
    const QUEUE_HIGH = 'moderation:queue:high';
    const QUEUE_NORMAL = 'moderation:queue:normal';
    const QUEUE_LOW = 'moderation:queue:low';

    // 每小时审核服务失败次数达到该值时告警
    const FAILURE_ALERT_THRESHOLD = 20;

    public static function pushModeration(string $jobClass, array $data, string $queue = self::QUEUE_NORMAL): void
    {
        Queue::connection(self::QUEUE_CONNECTION)->push($jobClass, $data, $queue);
    }

    /**
     * 私聊"先发后审"开关（后台 社区配置 → 私聊先发后审）；关闭时所有文本私聊都先审后发
     */
    public static function chatAsyncEnabled(): bool
    {
        try {
            return (int)systemConfig('ugc_chat_async_check_status') === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 审核服务调用失败计数，每小时超过阈值告警一次（暂无告警通道，写 alert 日志）
     */
    public static function recordFailure(string $bizType, string $message): void
    {
        try {
            $redis = Cache::store('redis')->handler();
            $key = 'moderation:fail:' . date('YmdH');
            $count = (int)$redis->incr($key);
            if ($count === 1) {
                $redis->expire($key, 7200);
            }
            if ($count === self::FAILURE_ALERT_THRESHOLD) {
                Log::alert("内容审核服务本小时已失败 {$count} 次（最近一次 {$bizType}: {$message}），请检查微信接口/网络");
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * 文本内容安全检测
     *
     * @param string $content 待检测文本
     * @param int $scene 场景值，用 self::SCENE_* 常量
     * @param string $bizType 业务标识，如 nickname/community_post/chat_msg
     * @param int $bizId 关联的业务主键，没有则传 0
     * @param int $uid 提交内容的用户 id
     * @param string|null $openid 用户小程序 openid，为空则视为 APP/H5（本次走本地兜底）
     * @param bool $hardBlock 判定不通过时是否抛异常硬拦截
     * @return array{pass:bool,driver:string,suggest:string,label:int,trace_id:string}
     * @throws ValidateException 当 $hardBlock=true 且判定不通过
     */
    public static function checkText(
        string $content,
        int $scene,
        string $bizType,
        int $bizId,
        int $uid,
        ?string $openid,
        bool $hardBlock = true
    ): array {
        $content = trim($content);
        if ($content === '') {
            return ['pass' => true, 'driver' => 'skip', 'suggest' => 'pass', 'label' => 100, 'trace_id' => '', 'log_id' => 0];
        }

        $result = ['pass' => true, 'suggest' => 'pass', 'label' => 100, 'trace_id' => ''];
        $driver = '';
        $failed = false;

        // 文档第一节：优先小程序 V2（需 openid）→ 不可用时降级 JoySafety（后台配置后启用）→ 本地敏感词兜底
        if (!empty($openid)) {
            try {
                $result = array_merge($result, WechatDriver::checkTextV2($content, $scene, $openid));
                $driver = 'wechat_v2';
            } catch (\Throwable $e) {
                $failed = true;
                Log::error('ContentSecurityService 微信审核失败(' . $bizType . '): ' . $e->getMessage());
                self::recordFailure($bizType, $e->getMessage());
            }
        }
        if ($driver === '' && JoySafetyDriver::enabled()) {
            try {
                $result = array_merge($result, JoySafetyDriver::checkText($content, $uid));
                $driver = 'joysafety';
            } catch (\Throwable $e) {
                $failed = true;
                Log::error('ContentSecurityService JoySafety 失败(' . $bizType . '): ' . $e->getMessage());
                self::recordFailure($bizType, $e->getMessage());
            }
        }
        if ($driver === '') {
            // 第三方都不可用时不阻断主流程，用本地敏感词兜底
            $hit = SensitiveWordFilter::contains($content);
            $result['pass'] = !$hit;
            $result['suggest'] = $hit ? 'risky' : 'pass';
            $driver = $failed ? 'local_fallback' : 'local';
        }

        $logId = self::log($uid, $bizType, $bizId, $scene, $driver, $content, $result);

        if (!$result['pass'] && $hardBlock) {
            throw new ValidateException('内容包含敏感信息，请修改后重试');
        }

        return array_merge($result, ['driver' => $driver, 'log_id' => $logId]);
    }

    /**
     * 先审后建的场景（发帖/评论）在业务记录创建后回填审核日志的 biz_id
     */
    public static function bindBizId(int $logId, int $bizId): void
    {
        if ($logId <= 0 || $bizId <= 0) {
            return;
        }
        try {
            Db::name('ugc_check_log')->where('id', $logId)->update(['biz_id' => $bizId]);
        } catch (\Throwable $e) {
            Log::error('ugc_check_log 回填 biz_id 失败: ' . $e->getMessage());
        }
    }

    /**
     * 内容未通过审核时站内通知发布者，通知失败不影响主流程
     */
    public static function notifyRejected(int $uid, string $what): void
    {
        try {
            app()->make(\app\common\repositories\user\UserNotificationRepository::class)
                ->createSystemMessage($uid, '内容审核未通过', '你发布的' . $what . '包含违规信息，未能公开展示，如有疑问请联系客服。');
        } catch (\Throwable $e) {
            Log::error('审核未通过通知失败: ' . $e->getMessage());
        }
    }

    /**
     * 图片/视频异步审核总开关（后台 社区配置 → 图片视频异步审核）。
     * 需先在微信公众平台配置消息推送，否则回调收不到，只能等超时放行。
     */
    public static function mediaCheckEnabled(): bool
    {
        try {
            return (int)systemConfig('ugc_media_check_status') === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 资料类图片"先发后审"：内容已保存，这里投递异步检测（低优先级），违规时由 MediaModerationResolver 清除。
     * 仅小程序用户（有 openid）且后台开启图片审核时生效；入队失败不影响主流程。
     * @param bool $resetOld 业务 id 会复用（如认证按 uid+类型覆盖）时清掉旧任务，避免旧结果影响新提交
     */
    public static function dispatchMediaCheck(string $bizType, int $bizId, int $uid, ?string $openid, array $images, int $scene, bool $resetOld = false): void
    {
        $images = array_values(array_filter(array_map(static function ($v) {
            return is_string($v) ? trim($v) : '';
        }, $images)));
        if (!$images || $bizId <= 0 || empty($openid) || !self::mediaCheckEnabled()) {
            return;
        }
        try {
            if ($resetOld) {
                Db::name('ugc_check_task')->where('biz_type', $bizType)->where('biz_id', $bizId)->delete();
            }
            self::pushModeration(\crmeb\jobs\MediaCheckSubmitJob::class, [
                'biz_type' => $bizType,
                'biz_id' => $bizId,
                'uid' => $uid,
                'openid' => (string)$openid,
                'scene' => $scene,
                'images' => $images,
                'video' => '',
            ], self::QUEUE_LOW);
        } catch (\Throwable $e) {
            Log::error("媒体审核入队失败({$bizType}#{$bizId}): " . $e->getMessage());
            self::recordFailure($bizType, $e->getMessage());
        }
    }

    /**
     * 逐个提交媒体异步检测并登记任务，单个失败不影响其余
     * @return int 成功提交的数量
     */
    public static function submitMedia(array $urls, int $mediaType, int $scene, string $bizType, int $bizId, int $uid, string $openid): int
    {
        $count = 0;
        foreach (array_values(array_unique(array_filter($urls))) as $url) {
            try {
                $traceId = WechatDriver::submitMediaAsync($url, $mediaType, $scene, $openid);
                Db::name('ugc_check_task')->insert([
                    'trace_id' => $traceId,
                    'uid' => $uid,
                    'biz_type' => $bizType,
                    'biz_id' => $bizId,
                    'media_url' => mb_substr($url, 0, 500),
                    'status' => self::TASK_PENDING,
                    'create_time' => time(),
                    'update_time' => time(),
                ]);
                $count++;
            } catch (\Throwable $e) {
                Log::error("媒体审核提交失败({$bizType}#{$bizId}) {$url}: " . $e->getMessage());
                self::recordFailure($bizType, $e->getMessage());
            }
        }
        return $count;
    }

    /**
     * 微信推送的异步检测结果
     */
    public static function onMediaResult(string $traceId, string $suggest, int $label): void
    {
        $task = Db::name('ugc_check_task')->where('trace_id', $traceId)->find();
        if (!$task) {
            Log::warning('media_check 回调找不到任务 trace_id=' . $traceId);
            return;
        }
        if ((int)$task['status'] !== self::TASK_PENDING) {
            return; // 重复推送或已超时放行
        }
        $status = $suggest === 'pass' ? self::TASK_PASS : ($suggest === 'risky' ? self::TASK_REJECT : self::TASK_REVIEW);
        Db::name('ugc_check_task')->where('id', $task['id'])->update(['status' => $status, 'update_time' => time()]);
        self::log((int)$task['uid'], $task['biz_type'] . '_media', (int)$task['biz_id'], 0, 'wechat_v2_media', (string)$task['media_url'], [
            'pass' => $suggest === 'pass',
            'suggest' => $suggest,
            'label' => $label,
            'trace_id' => $traceId,
        ]);
        MediaModerationResolver::resolve((string)$task['biz_type'], (int)$task['biz_id']);
    }

    /**
     * 超时未回调的任务按"服务不可用放行并记录日志"处理（文档 5.4）
     * @return int 放行的任务数
     */
    public static function releaseStaleTasks(int $timeout = self::MEDIA_CALLBACK_TIMEOUT): int
    {
        $stale = Db::name('ugc_check_task')
            ->where('status', self::TASK_PENDING)
            ->where('create_time', '<', time() - $timeout)
            ->field('id,biz_type,biz_id')
            ->limit(200)
            ->select()
            ->toArray();
        if (!$stale) {
            return 0;
        }
        Db::name('ugc_check_task')->whereIn('id', array_column($stale, 'id'))
            ->update(['status' => self::TASK_TIMEOUT, 'update_time' => time()]);
        Log::alert('内容审核回调超时，已按服务不可用放行 ' . count($stale) . ' 个媒体任务，请检查小程序消息推送配置');
        $bizs = [];
        foreach ($stale as $row) {
            $bizs[$row['biz_type'] . '#' . $row['biz_id']] = [$row['biz_type'], (int)$row['biz_id']];
        }
        foreach ($bizs as [$bizType, $bizId]) {
            try {
                MediaModerationResolver::resolve($bizType, $bizId);
            } catch (\Throwable $e) {
                Log::error("超时放行处理失败({$bizType}#{$bizId}): " . $e->getMessage());
            }
        }
        return count($stale);
    }

    /**
     * App 端侧 NSFW 分数判定（文档 3.2/3.3/4.1/4.2）：阈值在服务端、后台可配，客户端只上报分数
     * @param float[] $imageScores 每张图片的分数
     * @param float[] $frameScores 视频各抽帧的分数
     * @return array{verdict:string,log_id:int} verdict 为 ModerationDecision::PASS/REVIEW/BLOCK
     */
    public static function judgeAppMediaScores(array $imageScores, array $frameScores, string $bizType, int $uid): array
    {
        $verdict = $imageScores ? ModerationDecision::images($imageScores) : ModerationDecision::PASS;
        if ($verdict !== ModerationDecision::BLOCK && $frameScores
            && ModerationDecision::videoFrames($frameScores) === ModerationDecision::REVIEW) {
            $verdict = ModerationDecision::REVIEW;
        }
        $all = array_merge($imageScores, $frameScores);
        $suggest = [ModerationDecision::PASS => 'pass', ModerationDecision::REVIEW => 'review', ModerationDecision::BLOCK => 'risky'][$verdict];
        $logId = self::log($uid, $bizType . '_media', 0, 0, 'app_nsfw',
            json_encode(['images' => $imageScores, 'frames' => $frameScores]), [
                'pass' => $verdict === ModerationDecision::PASS,
                'suggest' => $suggest,
                'label' => $all ? (int)round(max($all) * 1000) : 0,
                'trace_id' => '',
            ]);
        return ['verdict' => $verdict, 'log_id' => $logId];
    }

    /**
     * 私聊消息因违规被系统撤回时通知发送者
     */
    public static function notifyRecalled(int $uid): void
    {
        try {
            app()->make(\app\common\repositories\user\UserNotificationRepository::class)
                ->createSystemMessage($uid, '消息已被撤回', '你发送的一条私聊消息包含违规信息，已被系统撤回。');
        } catch (\Throwable $e) {
            Log::error('消息撤回通知失败: ' . $e->getMessage());
        }
    }

    private static function log(int $uid, string $bizType, int $bizId, int $scene, string $driver, string $content, array $result): int
    {
        try {
            return (int)Db::name('ugc_check_log')->insertGetId([
                'uid' => $uid,
                'biz_type' => $bizType,
                'biz_id' => $bizId,
                'scene' => $scene,
                'driver' => $driver,
                'content_snapshot' => mb_substr($content, 0, 2000),
                'is_pass' => $result['pass'] ? 1 : 0,
                'suggest' => $result['suggest'] ?? 'pass',
                'label' => (int)($result['label'] ?? 100),
                'trace_id' => (string)($result['trace_id'] ?? ''),
                'create_time' => time(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ugc_check_log 写入失败: ' . $e->getMessage());
            return 0;
        }
    }
}
