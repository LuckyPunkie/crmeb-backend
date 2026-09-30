<?php

namespace crmeb\listens;

use app\common\repositories\community\CommunityRedpacketRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;

/**
 * 红包求助到期结算：没发出去的钱自动退还发布者
 *
 * 2026-09-24：新建，配合 CommunityRedpacketRepository::settleExpiredRedpackets()。
 */
class SettleExpiredRedpacketListen extends TimerService implements ListenerInterface
{
    public function handle($event): void
    {
        $this->tick(1000 * 60 * 20, function () {
            request()->clearCache();
            app()->make(CommunityRedpacketRepository::class)->settleExpiredRedpackets();
        });
    }
}
