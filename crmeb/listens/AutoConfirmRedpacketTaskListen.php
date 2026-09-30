<?php

namespace crmeb\listens;

use app\common\repositories\community\CommunityRedpacketRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;

/**
 * 红包任务超过 72 小时未审核，系统自动确认发放
 *
 * 2026-09-24：新建。之前"审核通过"这一步压根没有真正把钱转给领取者，这里连同
 * CommunityRedpacketRepository::payoutTask() 一起补上，此监听器只负责按时间扫描、触发结算。
 */
class AutoConfirmRedpacketTaskListen extends TimerService implements ListenerInterface
{
    public function handle($event): void
    {
        $this->tick(1000 * 60 * 20, function () {
            request()->clearCache();
            app()->make(CommunityRedpacketRepository::class)->autoConfirmTimeoutTasks();
        });
    }
}
