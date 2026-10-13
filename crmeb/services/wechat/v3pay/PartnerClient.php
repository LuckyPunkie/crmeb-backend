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
namespace crmeb\services\wechat\v3pay;

use crmeb\services\wechat\config\ServicePaymentConfig;
use crmeb\services\wechat\config\V3PaymentConfig;
use think\exception\ValidateException;

class PartnerClient extends BaseClient
{
    //退款
    const PARTNER_REFUNDS = 'v3/refund/domestic/refunds';
    //请求分账
    const PROFITSHARING_ORDERS = 'v3/profitsharing/orders';
    //解冻剩余资金
    const PROFITSHARING_ORDERS_UNFREZEE = 'v3/profitsharing/orders/unfreeze';
    //添加分账接收方
    const PROFITSHARING_RECEIVERS_ADD = 'v3/profitsharing/receivers/add';

    //查询分账接收方最大分账比例 /v3/profitsharing/merchant-configs/{sub_mchid}
    const PROFITSHARING_MERCHANT_CONFIGS = 'v3/profitsharing/merchant-configs/';
    //查询分账订单分账状态
    const PROFITSHARING_ORDERS_STATUS = 'v3/profitsharing/orders/';

    public function __construct(V3PaymentConfig $config, ServicePaymentConfig $serviceConfig)
    {
        parent::__construct($config, $serviceConfig);
        $this->isServicePay = true;
    }

    public function getRate($subMchid)
    {
        $res = $this->request(self::PROFITSHARING_MERCHANT_CONFIGS.$subMchid, 'GET',[]);
        if (isset($res['code'])) {
            throw new ValidateException('微信接口报错:' . $res['message']);
        }
        return $res;
    }

    public function profitsharingStatus($options)
    {
        $params = [
            'sub_mchid' => $options['sub_mchid'],
            'transaction_id'=> $options['transaction_id']
        ];
        $url = self::PROFITSHARING_ORDERS_STATUS.$options['out_order_no'].'?'.http_build_query($params);
        $res = $this->request($url, 'GET', []);
        if (isset($res['code'])) {
            throw new ValidateException('微信接口报错:' . $res['message']);
        }
        return $res;
    }

    public function receiversAdd($subMchid)
    {
        $params = [
            //特约商户号
            'sub_mchid' => $subMchid,
            //公众账号ID
            'appid' => $this->serviceConfig->wechatAppid,
            //接收方类型
            'type' => 'MERCHANT_ID',
            'name' => $this->serviceConfig->serviceName,
            //接收方账号
            'account' => $this->serviceConfig->mchId,
            //与分账方的关系类型
            'relation_type' => 'SERVICE_PROVIDER'
        ];
        $params = $this->processParams($params);
        $content = json_encode($params, JSON_UNESCAPED_UNICODE);
        $res = $this->request(self::PROFITSHARING_RECEIVERS_ADD, 'POST', ['body' => $content]);

        if (isset($res['code'])) {
            throw new ValidateException('微信接口报错:' . $res['message']);
        }

        return $res;
    }
    public function handleNotify($callback)
    {
        $request = request();
        $success = $request->post('event_type') === 'TRANSACTION.SUCCESS';
        $data = $this->decrypt($request->post('resource', []));

        $handleResult = call_user_func_array($callback, [json_decode($data, true), $success]);
        if (is_bool($handleResult) && $handleResult) {
            $response = [
                'code' => 'SUCCESS',
                'message' => 'OK',
            ];
        } else {
            $response = [
                'code' => 'FAIL',
                'message' => $handleResult,
            ];
        }

        return response($response, 200, [], 'json');
    }

    public function pay($type, array $order, string $appid = '')
    {
        if (!$appid) {
            $appid = $this->serviceConfig->wechatAppid;
        }
        $params = [
            //服务商APPID
            'sp_appid' => $appid,
            //服务商商户号
            'sp_mchid' => $this->serviceConfig->mchId,
            //子商户APPID sub_appid与sub_mchid有绑定关系
            //'sub_appid' => '',
            //子商户号
            'sub_mchid' => $order['sub_mchid'],
            //商品描述
            'description' => $order['body'],
            //商户订单号
            'out_trade_no' => $order['order_sn'],
            //结算信息
            'settle_info' => [
                //分账标识
                'profit_sharing' => true,
            ],
            //商户数据包
            'attach' => $order['attach'],

            //订单金额
            'amount' => [
                'total' => intval($order['pay_price'] * 100),
                'currency' => 'CNY',
            ],
            //场景信息
            'scene_info' => [
                //用户终端IP
                'payer_client_ip' => request()->ip(),
            ],
            //商户回调地址
            'notify_url' =>$this->serviceConfig->notifyUrl,
        ];

        if ($type === 'h5') {
            $params['scene_info']['h5_info'] = ['type' => $order['h5_type'] ?? 'Wap'];
        }

        // H5/Native/APP 下单文档无 payer；仅 JSAPI 需传 sp_openid（或 sub_openid）
        // 见 https://pay.weixin.qq.com/doc/v3/partner/4012738604
        if ($type === 'jsapi' && isset($order['openid'])) {
            $params['payer'] = [
                'sp_openid' => $order['openid'],
            ];
        }

        $content = json_encode($params, JSON_UNESCAPED_UNICODE);
        $res = $this->request('v3/pay/partner/transactions/' . $type, 'POST', ['body' => $content]);
        if (isset($res['code'])) {
            throw new ValidateException('微信接口报错:' . $res['message']);
        }
        return $res;
    }

    public function payApp(array $options)
    {
        $res = $this->pay('app', $options);
        return $this->configForAppPayment($res['prepay_id']);
    }

    /**
     * @param string $type 场景类型，枚举值： iOS：IOS移动应用； Android：安卓移动应用； Wap：WAP网站应用
     */
    public function payH5(array $options, $type = 'Wap')
    {
        $options['h5_type'] = $type;
        return $this->pay('h5', $options);
    }

    public function payJsapi($options, string $type = 'wechat')
    {
        $appid = $this->serviceConfig->{$type . 'Appid'};
        $res = $this->pay('jsapi', $options, $appid);
        return $this->configForJSSDKPayment($res['prepay_id'], $appid);
    }

    public function payNative(array $options)
    {
        return $this->pay('native', $options);
    }

    public function profitsharingOrder(array $order, bool $finish = false)
    {
        $params = [
            'sub_mchid' => $order['sub_mchid'],
            'transaction_id' => $order['transaction_id'],
            'out_order_no'   => $order['out_order_no'],
            'receivers' => [],
            'unfreeze_unsplit' => $finish,
        ];
        foreach ($order['receivers'] as $item) {
            $data = [
                'type'      => 'MERCHANT_ID',
                'account'   => $this->serviceConfig->mchId,
                'amount'    => intval($item['amount'] * 100),
                'description' => $item['body'] ?? $item['body'] ?? '',
            ];
            $params['receivers'][] = $data;
        }
        $content = json_encode($params);
        $res = $this->request(self::PROFITSHARING_ORDERS, 'POST', ['sign_body' => $content]);

        if (isset($res['code'])) {
            throw new ValidateException('微信接口报错:' . $res['message']);
        }
        return $res;
    }

    public function profitsharingFinishOrder(array $order)
    {
        $params = [
            'sub_mchid' => $order['sub_mchid'],
            'out_order_no' => $order['out_order_no'],
            'transaction_id' => $order['transaction_id'],
            'description' => $order['description']??'',
        ];
        $content = json_encode($params);
        $res = $this->request(self::PROFITSHARING_ORDERS_UNFREZEE, 'POST', ['sign_body' => $content]);
        if (isset($res['code'])) {
            throw new ValidateException('微信接口报错:' . $res['message']);
        }
        return $res;
    }

    public function refund(string $out_refund_no, array $options)
    {
        $params = [
            'sub_mchid' => $options['sub_mchid'],
            'out_trade_no' => $options['order_sn'],
            'out_refund_no' => $options['refund_order_sn'],
            'amount' => [
                'refund' => intval($options['refund_price'] * 100),
                'total' => intval($options['pay_price'] * 100),
                'currency' => 'CNY'
            ]
        ];

        if (isset($options['reason'])) {
            $params['reason'] = $options['reason'];
        }

        $content = json_encode($params);
        $res = $this->request(self::PARTNER_REFUNDS, 'POST', ['sign_body' => $content]);
        if (isset($res['code'])) {
            throw new ValidateException('微信接口报错:' . $res['message']);
        }
        return $res;
    }

    public function return($out_refund_no, $refund)
    {
        $params = [
            'sub_mchid' => $refund['sub_mchid'],
            'transaction_id' => $refund['transaction_id'],
            'out_refund_no' => $out_refund_no,
            //'notify_url' => '',
            'amount' => [
                'refund' => $refund['refund_price'] * 100,
                //原订单金额
                'total' => $refund['pay_price'] * 100,
                'currency' => 'CNY',
            ]
        ];

        $res = $this->request(self::PARTNER_REFUNDS , 'POST', ['sign_body' => json_encode($params)]);
        if (isset($res['code'])) {
            throw new ValidateException('微信接口报错:' . $res['message']);
        }
        return $res;
    }

    public function configForPayment($prepayId, $appid, $json = true)
    {
        $params = [
            'appId' => $appid,
            'timeStamp' => strval(time()),
            'nonceStr' => uniqid(),
            'package' => "prepay_id=$prepayId",
            'signType' => 'RSA',
        ];
        $message = $params['appId'] . "\n" .
            $params['timeStamp'] . "\n" .
            $params['nonceStr'] . "\n" .
            $params['package'] . "\n";
        openssl_sign($message, $raw_sign, $this->getPrivateKey(), 'sha256WithRSAEncryption');
        $sign = base64_encode($raw_sign);

        $params['paySign'] = $sign;

        return $json ? json_encode($params) : $params;
    }

    /**
     * Generate app payment parameters.
     *
     * @param string $prepayId
     *
     * @return array
     */
    public function configForAppPayment($prepayId)
    {
        $params = [
            'appid' => $this->serviceConfig->wechatAppid,
            'partnerid' => $this->serviceConfig->mchId,
            'prepayid' => $prepayId,
            'noncestr' => uniqid(),
            'timestamp' => time(),
            'package' => 'Sign=WXPay',
        ];
        $message = $params['appid'] . "\n" .
            $params['timestamp'] . "\n" .
            $params['noncestr'] . "\n" .
            $params['prepayid'] . "\n";
        openssl_sign($message, $raw_sign, $this->getPrivateKey(), 'sha256WithRSAEncryption');
        $sign = base64_encode($raw_sign);

        $params['sign'] = $sign;

        return $params;
    }

    public function configForJSSDKPayment($prepayId, $appid)
    {
        $config = $this->configForPayment($prepayId, $appid, false);

        $config['timestamp'] = $config['timeStamp'];
        unset($config['timeStamp']);

        return $config;
    }

    public function profitsharing($order)
    {
        $params = [
            //特约商户号
            'sub_mchid' => $order['sub_mchid'],
            //公众账号ID 可以填写这三种类型中的任意一种APPID，但请确保该appid与mchid有绑定关系
            'appid' => $this->serviceConfig->wechatAppid,
            //微信订单号
            'transaction_id' => $order['transaction_id'],
            //商户分账单号
            'out_order_no' => $order['order_sn'],
            //分账接收方列表
            'receivers' => [
                /**
                 * 分账接收方类型
                 * MERCHANT_ID：商户号
                 * PERSONAL_OPENID：个人openid
                 */
                'type' => 'MERCHANT_ID',
                //分账接收方帐号
                'account' => $this->serviceConfig->mchId,
                //分账金额，单位为分
                'amount' => intval($order['price'] * 100),
                'description' => '分给服务商',

            ],
            //是否解冻剩余未分资金
            //'unfreeze_unsplit' => true,
        ];
    }

}
