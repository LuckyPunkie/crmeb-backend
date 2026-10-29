<?php

namespace crmeb\listens;

use app\common\repositories\system\AppEntryRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;

/**
 * 每日好券：每小时重新转链已上架活动，刷新打开方式缓存（⑤⑥ 自动不下发，见 AppEntryRepository::jtkActHiddenOnPlatform）
 */
class RefreshJtkActOpenModeListen extends TimerService implements ListenerInterface
{
    public function handle($event): void
    {
        $this->tick(1000 * 60 * 60, function () {
            request()->clearCache();
            app()->make(AppEntryRepository::class)->refreshDailyCouponOpenModes();
        });
    }
}
