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
// | 附近好店买单支付回调监听
// | 注册事件: pay.notify (V3支付), pay_success_bill / pay_success_bill_pay (V2支付)
// +----------------------------------------------------------------------

namespace crmeb\listens\pay;

use app\common\repositories\store\nearby\NearbyShopBillOrderRepository;
use crmeb\interfaces\ListenerInterface;
use think\facade\Log;

class NearbyBillPayNotifyListen implements ListenerInterface
{
    public function handle($data): void
    {
        // V3支付回调: pay.notify 事件 -> $data = [$notify, 'wechat']
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            $notify = $data[0];
            if (isset($notify['out_trade_no']) && strpos($notify['out_trade_no'], 'NB') === 0) {
                Log::info('NearbyBillPayNotifyListen (V3 pay.notify): ' . $notify['out_trade_no']);
                // 传空串保留下单时的支付方式（routine / weixinApp / weixin），后台按它区分哪个平台的游客
                $this->processPaySuccess($notify['out_trade_no'], '', $notify['transaction_id'] ?? '');
                return;
            }
        }

        // V2支付回调: pay_success_{attach} 事件 -> $data = ['order_sn' => ..., 'data' => ..., 'is_combine' => ...]
        if (isset($data['order_sn']) && strpos($data['order_sn'], 'NB') === 0) {
            $orderSn = $data['order_sn'];
            $transactionId = $data['data']['transaction_id'] ?? $data['data']['trade_no'] ?? '';
            Log::info('NearbyBillPayNotifyListen (V2): ' . $orderSn);
            // 传空串保留下单时的支付方式（以前统一写 weixin，支付宝单也被记成微信）
            $this->processPaySuccess($orderSn, '', $transactionId);
            // 支付宝回调带上了付款人（AlipayService::handleNotify 记进 eb_alipay_user），记到订单上，二期给游客单补归属
            if (!empty($data['data']['alipay_user_id'])) {
                try {
                    app()->make(NearbyShopBillOrderRepository::class)->setAlipayUser($orderSn, (int)$data['data']['alipay_user_id']);
                } catch (\Throwable $e) {
                    Log::error('NearbyBillPayNotifyListen setAlipayUser failed: ' . $orderSn . ' ' . $e->getMessage());
                }
            }
            return;
        }

        // 兼容pay_success_order事件（attach为'order'时，但order_sn以NB开头）
        if (isset($data['order_sn']) && strpos($data['order_sn'], 'NB') === 0) {
            $orderSn = $data['order_sn'];
            Log::info('NearbyBillPayNotifyListen (compat): ' . $orderSn);
            $this->processPaySuccess($orderSn, '', '');
            return;
        }
    }

    protected function processPaySuccess(string $orderSn, string $payType, string $transactionId): void
    {
        try {
            $repository = app()->make(NearbyShopBillOrderRepository::class);
            $repository->paySuccess($orderSn, $payType, $transactionId);
            Log::info('NearbyBillPayNotifyListen success: ' . $orderSn);
        } catch (\Exception $e) {
            Log::info('NearbyBillPayNotifyListen failed: ' . $e->getMessage());
        }
    }
}
