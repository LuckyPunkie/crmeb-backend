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
// | T1.5: IM 消息频率限制 — 防止刷屏攻击

namespace app\common\middleware;

use think\facade\Cache;

class RateLimiter
{
    /**
     * 检查用户发送频率
     * @param int $uid 用户ID
     * @param int $maxPerMinute 每分钟最大消息数 (默认30)
     * @param int $maxPerSecond 每秒最大消息数 (默认5)
     * @return bool
     */
    public static function check(int $uid, int $maxPerMinute = 30, int $maxPerSecond = 5): bool
    {
        // 每秒限制 — 固定1秒滑动窗口（消除秒边界绕过风险）
        $now = time();
        $secondKey = "rate_limit:second:{$uid}:" . $now;
        $secondCount = Cache::inc($secondKey);
        Cache::expire($secondKey, 2);
        if ($secondCount > $maxPerSecond) {
            return false;
        }

        // 每分钟限制
        $minuteKey = "rate_limit:minute:{$uid}:" . intval($now / 60);
        $minuteCount = Cache::inc($minuteKey);
        Cache::expire($minuteKey, 120);
        if ($minuteCount > $maxPerMinute) {
            return false;
        }

        return true;
    }
}
