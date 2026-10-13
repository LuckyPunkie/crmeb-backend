<?php

namespace crmeb\listens\pay;

use app\common\repositories\merchant\ShoppingCreditsPaymentRepository;
use crmeb\interfaces\ListenerInterface;

/**
 * 支付回调监听器：监听购物金充值支付成功事件，处理充值成功回调数据
 */
class MerchantShoppingCreditsSuccessListen implements ListenerInterface
{
    /**
     * 处理购物金充值支付成功回调，交由仓储层完成充值入账
     * @param mixed $data 支付成功回调数据
     */
    public function handle($data): void
    {
        app()->make(ShoppingCreditsPaymentRepository::class)->rechargePaySuccess($data);
    }
}
