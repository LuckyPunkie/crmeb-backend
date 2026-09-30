<?php

namespace crmeb\listens;

use crmeb\interfaces\ListenerInterface;
use crmeb\services\security\ContentSecurityService;
use crmeb\services\TimerService;

/**
 * 媒体审核超过 40 分钟仍未收到微信回调，按"服务不可用放行并记录日志"处理，避免内容永久卡在待审
 */
class ReleaseStaleMediaCheckListen extends TimerService implements ListenerInterface
{
    public function handle($event): void
    {
        $this->tick(1000 * 60 * 5, function () {
            request()->clearCache();
            ContentSecurityService::releaseStaleTasks();
        });
    }
}
