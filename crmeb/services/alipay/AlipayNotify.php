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


namespace crmeb\services\alipay;


use Payment\Contracts\IPayNotify;
use think\exception\ValidateException;
use think\facade\Log;

class AlipayNotify implements IPayNotify
{
    private $type;

    public function __construct($type)
    {
        $this->type = $type;
    }

    /**
     * 处理支付宝支付回调。
     *
     * 本函数负责接收并处理支付宝支付回调通知。它首先验证交易状态是否为成功或完成，
     * 如果验证失败，则抛出一个异常表明交易未支付。如果验证成功，它将触发一个事件来处理支付成功的逻辑。
     *
     * @param string $channel 支付渠道，此处固定为支付宝。
     * @param string $notifyType 通知类型，指示回调的具体类型。
     * @param string $notifyWay 通知方式，指示回调的通知途径。
     * @param array $notifyData 通知数据，包含支付宝返回的所有回调信息。
     * @return bool 返回处理结果，true表示处理成功，false表示处理失败。
     * @throws ValidateException 如果交易状态不是TRADE_SUCCESS或TRADE_FINISHED，则抛出此异常。
     */
    public function handle(string $channel, string $notifyType, string $notifyWay, array $notifyData)
    {
        // 记录回调数据到日志，用于后续的调试和审计。
        Log::info('支付宝支付回调 handle:' . var_export($notifyData, 1));

        // 验证交易状态是否为成功或完成，如果不是，则抛出异常。
        if (!in_array($notifyData['trade_status'], ['TRADE_SUCCESS', 'TRADE_FINISHED']))
            throw new ValidateException('未支付');
        try {
            // 触发支付成功事件，传入订单号和通知数据。
            Log::info('支付宝支付成功回调执行队列 handle:' . var_export([$this->type,$notifyData],1));
            event('pay_success_' . $this->type, ['order_sn' => $notifyData['out_trade_no'], 'data' => $notifyData]);
            return true;
        } catch (\Exception$e) {
            // 记录处理回调失败的原因到日志。
            Log::info('支付宝支付回调失败handle:' . $e->getMessage());
        }

        // 如果处理过程中发生异常或错误，则返回false。
        return false;
    }
}
