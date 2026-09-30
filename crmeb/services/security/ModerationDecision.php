<?php
// +----------------------------------------------------------------------
// | 内容审核阈值判定（《内容安全阈值确定版》4.1 / 4.2）
// | 阈值只在服务端判定、后台可配（社区配置），客户端只上报分数，改阈值无需发版
// +----------------------------------------------------------------------

namespace crmeb\services\security;

use think\facade\Log;

class ModerationDecision
{
    const PASS = 'pass';
    const REVIEW = 'review';
    const BLOCK = 'block';

    const DEFAULT_IMAGE_REVIEW = 0.85;
    const DEFAULT_IMAGE_BLOCK = 0.9;
    const DEFAULT_VIDEO_FRAME_REVIEW = 0.8;

    /**
     * 单张图片：< 放行线 公开；放行线 ~ 拦截线（含边界）仅作者可见 + 人工审核；> 拦截线 拦截
     */
    public static function image(float $score): string
    {
        [$review, $block] = self::imageThresholds();
        if ($score > $block) {
            return self::BLOCK;
        }
        if ($score < $review) {
            return self::PASS;
        }
        return self::REVIEW;
    }

    /**
     * 多张图片取最严重的结果
     * @param float[] $scores
     */
    public static function images(array $scores): string
    {
        $result = self::PASS;
        foreach ($scores as $score) {
            $one = self::image((float)$score);
            if ($one === self::BLOCK) {
                return self::BLOCK;
            }
            if ($one === self::REVIEW) {
                $result = self::REVIEW;
            }
        }
        return $result;
    }

    /**
     * 视频抽帧：采样稀疏，任一帧 > 复审线即进入复审，视频不自动拦截
     * @param float[] $frameScores
     */
    public static function videoFrames(array $frameScores): string
    {
        $threshold = self::videoFrameThreshold();
        foreach ($frameScores as $score) {
            if ((float)$score > $threshold) {
                return self::REVIEW;
            }
        }
        return self::PASS;
    }

    /**
     * @return array{0:float,1:float} [放行线, 拦截线]
     */
    public static function imageThresholds(): array
    {
        $review = self::readThreshold('image_review_threshold', self::DEFAULT_IMAGE_REVIEW);
        $block = self::readThreshold('image_block_threshold', self::DEFAULT_IMAGE_BLOCK);
        if ($review > $block) {
            Log::warning("审核阈值配置异常：放行线 {$review} 大于拦截线 {$block}，已回退默认值");
            return [self::DEFAULT_IMAGE_REVIEW, self::DEFAULT_IMAGE_BLOCK];
        }
        return [$review, $block];
    }

    public static function videoFrameThreshold(): float
    {
        return self::readThreshold('video_frame_review_threshold', self::DEFAULT_VIDEO_FRAME_REVIEW);
    }

    private static function readThreshold(string $key, float $default): float
    {
        try {
            $value = systemConfig($key);
        } catch (\Throwable $e) {
            return $default;
        }
        if ($value === null || $value === '' || !is_numeric($value)) {
            return $default;
        }
        $value = (float)$value;
        return ($value >= 0 && $value <= 1) ? $value : $default;
    }
}
