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

use think\facade\Cache;
use crmeb\services\pay\BasePay;
use crmeb\services\AlipayService;
use crmeb\services\pay\PayInterface;
use think\exception\ValidateException;

class Alipay extends BasePay implements PayInterface
{
    public function pay($type, $order,$openid)
    {
        $affect = $order['attach'];
        $order['openid'] = $openid;
        $service = AlipayService::instance();
        switch ($type){
            case 'alipay':
                $url = $service->create(
                    title: $order['body'],
                    orderId: $order['order_sn'],
                    totalAmount: (string)$order['pay_price'],
                    passbackParams: $affect,
                    quitUrl: $order['return_url'] ?? '',
                    siteUrl: $order['return_url'] ?? ''
                );
                $pay_key = md5($url);
                Cache::set('pay_key' . $pay_key, $url, 3600);
                return ['config' => $url, 'pay_key' => $pay_key];
                break;
            case 'alipayQr':
                $res = $service->create(
                    title: $order['body'],
                    orderId: $order['order_sn'],
                    totalAmount: (string)$order['pay_price'],
                    passbackParams: $affect,
                    isCode: true
                );
                $url = is_object($res) && isset($res->qrCode) ? $res->qrCode : $res;
                return ['config' => ["code_url" => $url, 'invalid' => time() + (15 * 60)]];
                break;
            case 'alipayBarCode':
                $config = $service->microPay(
                    authCode: $order['auth_code'],
                    title: $order['body'],
                    orderId: $order['order_sn'],
                    totalAmount: (string)$order['pay_price'],
                    passbackParams: $affect
                );
                $config['transaction_id'] = $config['payInfo']['trade_no'] ?? 0;
                break;
            case 'alipayApp':
                $config = $service->create(
                    title: $order['body'],
                    orderId: $order['order_sn'],
                    totalAmount: (string)$order['pay_price'],
                    passbackParams: $affect
                );
                break;
            default:
                throw new ValidateException('不存在的支付方式');
                break;
        }
        return compact('config');
    }

    public function handleNotify()
    {

    }


    public function payOrderRefund(string $outTradeNo, array $options = [])
    {
        $refundId = $options['refund_id'] ?? $options['refund_no'] ?? $outTradeNo;
        $config = AlipayService::instance()->refund($outTradeNo, (string)floatval($options['refund_price']), $refundId);
        return compact('config');
    }

    public function refund(string $outTradeNo, array $options = [])
    {
        return $this->payOrderRefund($outTradeNo, $options);
    }
}
