<?php

namespace crmeb\listens;

use app\common\repositories\merchant\ShoppingCreditsRefundRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;

/**
 * Retry shopping credits refunds that remain in the processing state.
 */
class RetryMerchantShoppingCreditsRefundListen extends TimerService implements ListenerInterface
{
    /**
     * 监听定时任务触发，每 5 分钟调用仓储层重试处理仍处于退款处理中的购物金退款单
     * @param mixed $event 事件参数（本监听中未直接使用，仅满足监听器接口定义）
     */
    public function handle($event): void
    {
        $this->tick(1000 * 60 * 5, function () {
            request()->clearCache();
            app()->make(ShoppingCreditsRefundRepository::class)->retryProcessingRefunds(100);
        });
    }
}
