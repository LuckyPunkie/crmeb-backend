<?php

namespace crmeb\listens;

use app\common\repositories\merchant\MerchantMemberRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;

/**
 * 定时任务监听器：每 5 分钟重试补发店铺会员入会和升级优惠券
 */
class RetryMerchantMemberLevelRewardListen extends TimerService implements ListenerInterface
{
    /**
     * 监听定时任务触发，每 5 分钟调用仓储层重试补发待处理的会员等级奖励优惠券
     * @param mixed $event 事件参数（本监听中未直接使用，仅满足监听器接口定义）
     */
    public function handle($event): void
    {
        $this->tick(1000 * 60 * 5, function () {
            request()->clearCache();
            app()->make(MerchantMemberRepository::class)->retryPendingLevelRewards(100);
        });
    }
}
