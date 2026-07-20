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

use crmeb\services\pay\BasePay;
use crmeb\services\pay\PayInterface;
use think\exception\ValidateException;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\services\wechat\Payment;

class Weixin extends BasePay implements PayInterface
{

    public function pay($type, $order, $openid)
    {
        switch ($type){
            case 'h5':
                 //开启了v3支付
                if (Payment::instance()->isV3PAy()) {
                    $config = Payment::instance()->payClient()->h5Pay(
                        outTradeNo: $order['order_sn'],
                        total: $order['pay_price'],
                        description: $order['body'],
                        attach: $order['attach']
                    );
                } else {
                    $config = Payment::instance()->paymentOrder(
                        openid: $openid,
                        out_trade_no: $order['order_sn'],
                        total_fee: $order['pay_price'],
                        attach: $order['attach'],
                        body: $order['body'],
                        trade_type:'MWEB'
                    );
                }
                break;
            case 'weixin':
                 //开启了v3支付
                if (Payment::instance()->isV3PAy()) {
                    $config = Payment::instance()->payClient()->jsapiPay(
                        openid: $openid,
                        outTradeNo: $order['order_sn'],
                        total: $order['pay_price'],
                        description: $order['body'],
                        attach: $order['attach']
                    );
                } else {
                    $config =  Payment::instance()->jsPay(
                        openid: $openid,
                        out_trade_no: $order['order_sn'],
                        total_fee: $order['pay_price'],
                        attach: $order['attach'],
                        body: $order['body']
                    );
                }
                break;
            case 'weixinQr':
                $config = Payment::instance()->nativePay(
                    openid: null,
                    out_trade_no: $order['order_sn'],
                    total_fee: $order['pay_price'],
                    attach: $order['attach'],
                    body: $order['body']
                );
                break;
            case 'weixinApp':
                if (Payment::instance()->isV3PAy()) {
                    $config = Payment::instance()->payClient()->appPay(
                        outTradeNo:$order['order_sn'],
                        total:$order['pay_price'],
                        description:$order['body'],
                        attach:$order['attach']
                    );
                } else {
                    $config = Payment::instance()->appPay(
                        openid: $openid,
                        out_trade_no: $order['order_sn'],
                        total_fee: $order['pay_price'],
                        attach: $order['attach'],
                        body: $order['body']
                    );
                }
                break;
            case 'weixinBarCode':
                $config = Payment::instance()->microPay(
                    authCode: $order['authCode'],
                    outTradeNo: $order['order_sn'],
                    totalFee: $order['pay_price'],
                    attach: $order['attach'],
                    body: $order['body']
                );
                break;
            case 'routine':
                $services = Payment::instance()->setAccessEnd(Payment::MINI);
                //判断有没有打开小程序支付
                if ($services->isMiniPay) {
                    $config = $services->miniPay(
                        openid: $openid,
                        out_trade_no: $order['order_sn'],
                        total_fee: $order['pay_price'],
                        attach: $order['attach'],
                        body: $order['body']
                    );
                } else {
                    //开启了v3支付
                    if ($services->isV3Pay()) {
                        $config = $services->payClient()->miniprogPay(
                            openid: $openid,
                            outTradeNo: $order['order_sn'],
                            total: $order['pay_price'],
                            attach: $order['attach'],
                            description: $order['body'],
                        );
                    } else {
                        $config = $services->jsPay(
                            openid: $openid,
                            out_trade_no: $order['order_sn'],
                            total_fee: $order['pay_price'],
                            attach: $order['attach'],
                            body: $order['body']
                        );
                    }
                }
                break;
            default:
                throw new ValidateException('不存在的支付方式');
                break;
        }
        return compact('config');
    }

    public function payOrderRefund(string $outTradeNo, array $options = [], $type = 0)
    {
        $service = Payment::instance();
        if ($type == 2) {
            $service->setAccessEnd('mini');
        }
        $config = $service->payOrderRefund(orderNo:$outTradeNo, opt:$options);
        return compact('config');
    }

    public function refund(string $outTradeNo, array $options = [])
    {
        $config = Payment::instance()->payOrderRefund(orderNo:$outTradeNo, opt:$options);
        return compact('config');
    }

    public function handleNotify()
    {

    }

}
