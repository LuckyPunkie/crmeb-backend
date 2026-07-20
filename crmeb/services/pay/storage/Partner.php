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
namespace  crmeb\services\pay\storage;

use think\facade\Log;
use crmeb\services\pay\BasePay;
use crmeb\services\pay\PayInterface;
use think\exception\ValidateException;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\services\wechat\Payment;

class Partner extends BasePay implements PayInterface
{
    const DIVER_TYPE_WEIXIN = '1';
    //routine
    const DIVER_TYPE_ROUTINE = '0';

    public function pay($type, $order,$openid)
    {
        $order['openid'] = $openid;
        switch ($type){
            case 'h5':
                $config =  Payment::instance()->partnerClient()->payH5($order, 'Wap');
                break;
            case 'weixin':
                $config =  Payment::instance()->partnerClient()->payJsapi($order);
                break;
            case 'weixinQr':
                $config =  Payment::instance()->partnerClient()->payNative($order);
                break;
            case 'weixinApp':
                $config =  Payment::instance()->partnerClient()->payApp($order);
                break;
            case 'weixinBarCode':
                $config = Payment::microPay(
                    $order['authCode'],
                    $order['order_sn'],
                    $order['pay_price'],
                    $order['attach'],
                    $order['body'],
                    '',
                    $type,
                    $order['sub_mchid'] ?? ''
                );
                break;
            case 'routine':
                $config  = Payment::instance()->setAccessEnd(Payment::MINI)->partnerClient()->payJsapi($order,'routine' );
                break;
            default:
                throw new ValidateException('不存在的支付方式');
                break;
        }
        return compact('config');
    }

    public function handleNotify()
    {
        return Payment::instance()->partnerClient()->handleNotify(function ($notify, $successful) {
            if (!$successful) return false;
            response_log_write(
                ['message' => '微信服务商支付成功回调接口', 'request' => [], 'response' => $notify],
                'info'
            );
            if (isset($notify['combine_out_trade_no'])) {
                $is_combine = 1;
                $order_sn = $notify['combine_out_trade_no'];
            } else {
                $is_combine = 2;
                $order_sn = $notify['out_trade_no'];
            }
            try {
                event('pay_success_order',['order_sn' => $order_sn, 'data' => $notify, 'is_combine' => $is_combine]);
            } catch (\Exception $e) {
                response_log_write(
                    ['message' => '微信服务商支付成功回调失败', 'request' => [], 'response' => $e->getMessage()],
                    'info'
                );
                return false;
            }
            return true;
        });
    }


    public function profitsharingStatus($options)
    {
        return Payment::instance()->partnerClient()->profitsharingStatus($options);
    }

    public function profitsharing($order)
    {
        $config = Payment::instance()->partnerClient()->profitsharing($order);
        return compact('config');
    }

    public function payOrderRefund(string $outTradeNo, array $options = [])
    {
        $config = Payment::instance()->partnerClient()->refund($outTradeNo, $options);
        return compact('config');
    }

    public function profitsharingOrder($order, $finish)
    {
        $config = Payment::instance()->partnerClient()->profitsharingOrder($order,$finish);
        return compact('config');
    }

    public function profitsharingFinishOrder($order)
    {
        $config = Payment::instance()->partnerClient()->profitsharingFinishOrder($order);
        return compact('config');
    }

    public function refund(string $outTradeNo, array $options = [])
    {
        $config = Payment::instance()->partnerClient()->refund($outTradeNo,$options);
        return compact('config');
    }



}
