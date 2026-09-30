<?php
// +----------------------------------------------------------------------
// | 异步提交媒体内容审核：视频抽帧 + 逐张提交 media_check_async
// | 放在队列里执行，避免发布接口等待 ffmpeg 和多次微信调用
// +----------------------------------------------------------------------

namespace crmeb\jobs;

use crmeb\interfaces\JobInterface;
use crmeb\services\security\ContentSecurityService;
use crmeb\services\security\MediaModerationResolver;
use crmeb\services\security\ModerationDecision;
use crmeb\services\VideoCoverService;
use think\facade\Log;

class MediaCheckSubmitJob implements JobInterface
{
    /**
     * @param array{biz_type:string,biz_id:int,uid:int,openid:string,scene:int,images:string[],video:string,audio?:string[]} $data
     */
    public function fire($job, $data)
    {
        $bizType = (string)($data['biz_type'] ?? '');
        $bizId = (int)($data['biz_id'] ?? 0);
        try {
            $urls = array_map([$this, 'absoluteUrl'], (array)($data['images'] ?? []));
            if (!empty($data['video'])) {
                $urls = array_merge($urls, VideoCoverService::extractFrames((string)$data['video']));
            }
            $scene = (int)($data['scene'] ?? ContentSecurityService::SCENE_FORUM);
            $uid = (int)($data['uid'] ?? 0);
            $openid = (string)($data['openid'] ?? '');
            $submitted = ContentSecurityService::submitMedia($urls, ContentSecurityService::MEDIA_IMAGE, $scene, $bizType, $bizId, $uid, $openid);
            $audio = array_map([$this, 'absoluteUrl'], (array)($data['audio'] ?? []));
            if ($audio) {
                $submitted += ContentSecurityService::submitMedia($audio, ContentSecurityService::MEDIA_AUDIO, $scene, $bizType, $bizId, $uid, $openid);
            }
            if ($submitted === 0) {
                $fallbackImages = self::normalizeScores($data['fallback_image_scores'] ?? []);
                $fallbackFrames = self::normalizeScores($data['fallback_frame_scores'] ?? []);
                if ($bizType === 'community_post' && ($fallbackImages || $fallbackFrames)) {
                    Log::warning("微信媒体 V2 全部提交失败，改用 App 端侧分数降级：community_post#{$bizId}");
                    $judge = ContentSecurityService::judgeAppMediaScores(
                        $fallbackImages,
                        $fallbackFrames,
                        'community_post',
                        (int)($data['uid'] ?? 0)
                    );
                    ContentSecurityService::bindBizId((int)$judge['log_id'], $bizId);
                    $verdict = MediaModerationResolver::VERDICT_PASS;
                    if ($judge['verdict'] === ModerationDecision::BLOCK) {
                        $verdict = MediaModerationResolver::VERDICT_REJECT;
                    } elseif ($judge['verdict'] === ModerationDecision::REVIEW) {
                        $verdict = MediaModerationResolver::VERDICT_REVIEW;
                    }
                    MediaModerationResolver::applyCommunityPostVerdict($bizId, $verdict);
                } else {
                    Log::alert("媒体审核全部提交失败，按服务不可用放行：{$bizType}#{$bizId}");
                    MediaModerationResolver::resolve($bizType, $bizId);
                }
            }
        } catch (\Throwable $e) {
            Log::error("媒体审核任务异常({$bizType}#{$bizId}): " . $e->getMessage());
            try {
                MediaModerationResolver::resolve($bizType, $bizId);
            } catch (\Throwable $ignore) {
            }
        }
        $job->delete();
    }

    public function failed($data)
    {
    }

    /** @param mixed $raw */
    protected static function normalizeScores($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : explode(',', $raw);
        }
        $scores = [];
        foreach ((array)$raw as $v) {
            if (is_numeric($v) && (float)$v >= 0 && (float)$v <= 1) {
                $scores[] = (float)$v;
            }
        }
        return array_slice($scores, 0, 50);
    }

    protected function absoluteUrl($url): string
    {
        $url = trim((string)$url);
        if ($url === '' || preg_match('#^https?://#i', $url)) {
            return $url;
        }
        return rtrim((string)systemConfig('site_url'), '/') . '/' . ltrim($url, '/');
    }
}
