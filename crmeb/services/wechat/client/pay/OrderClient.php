<?php
/**
 *  +----------------------------------------------------------------------
 *  | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
 *  +----------------------------------------------------------------------
 *  | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
 *  +----------------------------------------------------------------------
 *  | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
 *  +----------------------------------------------------------------------
 *  | Author: CRMEB Team <admin@crmeb.com>
 *  +----------------------------------------------------------------------
 */

namespace crmeb\services\wechat\client\pay;

use crmeb\services\wechat\util\Helper;
use crmeb\services\wechat\util\XML;
use EasyWeChat\Kernel\HttpClient\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use think\helper\Arr;
use EasyWeChat\Pay\Client;
use EasyWeChat\Kernel\Contracts\Config;

/**
 * 创建订单
 * Class OrderClient
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/26
 * @package crmeb\services\wechat\client\pay
 */
class OrderClient
{

    /**
     * OrderClient constructor.
     * @param Client $api
     * @param Config $config
     */
    public function __construct(protected Client $api, protected Config $config)
    {
    }

    /**
     * 小程序创建订单
     * @param array $params
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function createorder(array $params): ResponseInterface|Response
    {
        $data = [
            'openid' => $params['openid'],//支付者的openid
            'combine_trade_no' => $params['out_trade_no'],//商家合单支付总交易单号
            'expire_time' => time() + 7000,
            'sub_orders' => [
                [
                    'mchid' => $this->config->get('mch_id'),
                    'amount' => (int)$params['total_fee'],
                    'trade_no' => $params['out_trade_no'],
                    'description' => $params['body'],
                ]
            ],
        ];
        return $this->api->postJson('shop/pay/createorder', $data);
    }

    /**
     * 小程序退款
     * @param array $params
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function refundorder(array $params): ResponseInterface|Response
    {
        $data = [
            'openid' => $params['openid'],
            'mchid' => $this->config->get('mch_id'),
            'trade_no' => $params['trade_no'],
            'transaction_id' => $params['transaction_id'],
            'refund_no' => $params['refund_no'],
            'total_amount' => (int)$params['total_amount'],
            'refund_amount' => (int)$params['refund_amount'],
        ];
        return $this->api->postJson('shop/pay/refundorder', $data);
    }

	/**
	 * 付款码支付
	 * @param array $params
	 * @param string $appId
	 * @return ResponseInterface
	 * @throws TransportExceptionInterface
	 */
    public function pay(array $params, string $appId = ''): ResponseInterface
    {
        $params['appid'] = $appId ?: $this->config->get('app_id');
		$params['mch_id'] = $this->config->get('mch_id');
		$params['notify_url'] = $params['notify_url'] ?? $this->config->get('notify_url');
		if (empty($params['spbill_create_ip'])) {
			$params['spbill_create_ip'] = request()->ip();
		}

        return $this->api->request('POST', 'pay/micropay', ['body' => $params]);
    }

    /**
     * 下单
     * @param array $params
     * @return ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function unify(array $params): ResponseInterface
    {
        if (empty($params['spbill_create_ip'])) {
            $params['spbill_create_ip'] = request()->ip();
        }

        $params['notify_url'] = $params['notify_url'] ?? $this->config->get('notify_url');

        return $this->request('pay/unifiedorder', $params);
    }

    /**
     * 发起请求
     * @param string $endpoint
     * @param array $params
     * @param string $method
     * @param array $options
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    protected function request(string $endpoint, array $params = [], string $method = 'post', array $options = []): ResponseInterface
    {
        $base = [
            'appid' => $this->config->get('app_id'),
            'mch_id' => $this->config->get('mch_id'),
            'nonce_str' => uniqid(),
        ];

        $params = array_filter(array_filter(array_merge($base, [], $params)), 'strlen');

        $secretKey = $this->config->get('v2_secret_key');

        $encryptMethod = Helper::getEncryptMethod(Arr::get($params, 'sign_type', 'MD5'), $secretKey);

        $params['sign'] = Helper::generateSign($params, $secretKey, $encryptMethod);

        $options = array_merge([
            'body' => XML::build($params),
        ], $options);

        return $this->api->request(strtoupper($method), $endpoint, $options);
    }

    /**
     * 商户订单号退款
     * @param string $number
     * @param string $refundNumber
     * @param int $totalFee
     * @param int $refundFee
     * @param array $optional
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function byOutTradeNumber(string $number, string $refundNumber, int $totalFee, int $refundFee, array $optional = []): Response
    {
        return $this->refund($refundNumber, $totalFee, $refundFee, array_merge($optional, ['out_trade_no' => $number]));
    }

    /**
     * 支付回执单号退款
     * @param string $transactionId
     * @param string $refundNumber
     * @param int $totalFee
     * @param int $refundFee
     * @param array $optional
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function byTransactionId(string $transactionId, string $refundNumber, int $totalFee, int $refundFee, array $optional = []): Response
    {
        return $this->refund($refundNumber, $totalFee, $refundFee, array_merge($optional, ['transaction_id' => $transactionId]));
    }

    /**
     * 退款
     * @param string $refundNumber
     * @param int $totalFee
     * @param int $refundFee
     * @param array $optional
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    protected function refund(string $refundNumber, int $totalFee, int $refundFee, array $optional = []): Response
    {
        $params = array_merge([
            'out_refund_no' => $refundNumber,
            'total_fee' => $totalFee,
            'refund_fee' => $refundFee,
            'appid' => $this->config->get('app_id'),
        ], $optional);

        return $this->safeRequest('secapi/pay/refund', $params);
    }

    /**
     * 撤销订单
     * @param string $outTradeNumber
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function reverseByOutTradeNumber(string $outTradeNumber): Response
    {
        return $this->reverse($outTradeNumber, 'out_trade_no');
    }

    /**
     * 撤销订单
     * @param string $number
     * @param string $type
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    protected function reverse(string $number, string $type): Response
    {
        $params = [
            'appid' => $this->config->get('app_id'),
            $type => $number,
        ];

        return $this->safeRequest('secapi/pay/reverse', $params);
    }

    /**
     * 查询退款订单
     * @param string $number
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function queryByOutTradeNumber(string $number): Response
    {
        return $this->query([
            'out_trade_no' => $number,
        ]);
    }

    /**
     * 查询订单
     * @param array $params
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    protected function query(array $params): Response
    {
        $params['appid'] = $this->config->get('app_id');

        return $this->request('pay/orderquery', $params);
    }

    /**
     * 企业付款到零钱
     * @param array $params
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function toBalance(array $params): Response
    {
        $base = [
            'mch_id' => null,
            'mchid' => $this->config->get('mch_id'),
            'mch_appid' => $this->config->get('app_id'),
        ];

        if (empty($params['spbill_create_ip'])) {
            $params['spbill_create_ip'] = request()->ip();
        }

        return $this->safeRequest('mmpaymkttransfers/promotion/transfers', array_merge($base, $params));
    }

    /**
     * 退款请求
     * @param string $endpoint
     * @param array $params
     * @param string $method
     * @param array $options
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    protected function safeRequest(string $endpoint, array $params, string $method = 'post', array $options = []): Response
    {
        $options = array_merge([
            'cert' => $this->config->get('certificate'),
            'ssl_key' => $this->config->get('private_key'),
        ], $options);

        return $this->request($endpoint, $params, $method, $options);
    }

	/**
	 * 根据微信付款码获取用户openid
	 * @param string $authCode
	 * @return Response|ResponseInterface
	 * @throws TransportExceptionInterface
	 */
	public function authCodeToOpenid(string $authCode)
	{
		return $this->request('tools/authcodetoopenid', [
			'appid' => $this->config->get('app_id'),
			'auth_code' => $authCode,
		]);
	}


}
