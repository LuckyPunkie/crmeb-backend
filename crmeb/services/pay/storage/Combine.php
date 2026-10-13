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

class Combine extends BasePay implements PayInterface
{

    public function pay($type, $order, $openid)
    {
        $order['openid'] = $openid;
        switch ($type) {
            case 'h5':
                $config = Payment::instance()->CombineClient()->payH5($order, 'Wap');
                break;
            case 'weixin':
                $config = Payment::instance()->CombineClient()->payJs($openid, $order);
                break;
            case 'weixinQr':
                $config = Payment::instance()->CombineClient()->payNative($order);
                break;
            case 'weixinApp':
                $config = Payment::instance()->CombineClient()->payApp($order);
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
                $config = Payment::instance()->setAccessEnd(Payment::MINI)->CombineClient()->payJs($openid, $order);
                break;
            default:
                throw new ValidateException('不存在的支付方式');
        }
        return compact('config');
    }

    public function payOrderRefund(string $outTradeNo, array $options = [])
    {
        $config = Payment::instance()->CombineClient()->payOrderRefund($outTradeNo, $options);
        return compact('config');
    }

    public function refund(string $outTradeNo, array $options = [])
    {
        $config = Payment::instance()->CombineClient()->payOrderRefund($outTradeNo, $options);
        return compact('config');
    }

    public function profitsharingOrder($order, $finish)
    {
        $config = Payment::instance()->CombineClient()->profitsharingOrder($order, $finish);
        return compact('config');
    }

    public function profitsharingFinishOrder($order)
    {
        $config = Payment::instance()->CombineClient()->profitsharingFinishOrder($order);
        return compact('config');
    }

    public function handleNotify()
    {

    }

}
