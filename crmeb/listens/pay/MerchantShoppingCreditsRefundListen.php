<?php

namespace crmeb\listens\pay;

use app\common\repositories\merchant\ShoppingCreditsRefundRepository;
use crmeb\interfaces\ListenerInterface;

/**
 * 支付回调监听器：监听购物金退款通知事件，处理退款回调数据
 */
class MerchantShoppingCreditsRefundListen implements ListenerInterface
{
    /**
     * 处理购物金退款通知：将消息转为数组后交由仓储层处理退款回调
     * @param mixed $message 退款通知消息，可为对象或数组
     */
    public function handle($message): void
    {
        $data = is_object($message) && method_exists($message, 'toArray')
            ? $message->toArray()
            : (array)$message;
        app()->make(ShoppingCreditsRefundRepository::class)->handleRefundNotify($data);
    }
}
