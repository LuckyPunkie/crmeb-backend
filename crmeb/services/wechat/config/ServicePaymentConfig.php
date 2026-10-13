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

namespace crmeb\services\wechat\config;


use crmeb\services\wechat\contract\ConfigHandlerInterface;
use crmeb\services\wechat\DefaultConfig;

/**
 * Class V3PaymentConfig
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2022/9/30
 * @package crmeb\services\wechat\config
 */
class ServicePaymentConfig extends BaseConfig implements ConfigHandlerInterface
{

    /**
     * appid
     * @var string
     */
    public string $appId;

    /**
     * 服务商商户ID
     * @var string
     */
    public string $mchId;

	/**
	 * 特约子商户商户ID
	 * @var string
	 */
	public string $subMchid = '';

	/**
	 * 特约子商户appid（可选）
	 * @var string
	 */
	public string $subAppid = '';

    /**
     * API密钥
     * @var string
     */
    public string $key;

    /**
     * 证书序列号
     * @var string
     */
    public string $serialNo;

    /**
     * 证书cert
     * @var string
     */
    public string $certPath;

    /**
     * 证书key
     * @var string
     */
    public string $keyPath;

    /**
     * 支付异步回调地址
     * @var string
     */
    public string $notifyUrl;

    /**
     * 退款异步通知
     * @var string
     */
    public string $refundUrl;

    /**
     * 小程序appid
     * @var string
     */
    public string $routineAppid;

    /**
     * 应用appid
     * @var string
     */
    public string $appAppid;

    /**
     * 微信公众号appid
     * @var string
     */
    public string $wechatAppid;

    /**
     * v3支付公钥
     * @var string
     */
    public string $publicKey;

    /**
     * v3支付公钥证书
     * @var string
     */
    public string $publicPem;

    /**
     * web appid
     * @var string
     */
    public string $webAppid;

    /**
     * 是否服务商支付
     * @var bool
     */
    public bool $isServicePay = false;

    /**
     * 服务商全称
     */
    public string $serviceName = '';

    public string $v3_key = '';


    /**
     * 初始化
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    protected function getWechat()
    {
        $this->init = true;
        $this->isServicePay = false;
        $this->mchId = $this->httpConfig->getConfig('service_pay.mchid', '');
        $this->serviceName = $this->httpConfig->getConfig('service_pay.service_name', '');
        $this->serialNo = $this->httpConfig->getConfig('service_pay.serial_no', '');
        $this->key = $this->httpConfig->getConfig('service_pay.v3_key', '');
        $this->v3_key = $this->httpConfig->getConfig('service_pay.v3_key', '');
		$this->publicKey = $this->httpConfig->getConfig('service_pay.v3_public_id', '');
		$this->publicPem = $this->httpConfig->getConfig('service_pay.v3_public_pem', '');
        $this->certPath = app()->getRootPath() . 'public' .$this->httpConfig->getConfig('service_pay.client_cert', '');
        $this->keyPath  = app()->getRootPath() . 'public' .$this->httpConfig->getConfig('service_pay.client_key', '');
        $this->notifyUrl = trim($this->httpConfig->getConfig(DefaultConfig::COMMENT_URL)) . DefaultConfig::value('service_pay.notifyUrl');
        $this->refundUrl = trim($this->httpConfig->getConfig(DefaultConfig::COMMENT_URL)) . DefaultConfig::value('service_pay.refundUrl');
        $this->wechatAppid = $this->httpConfig->getConfig(DefaultConfig::OFFICIAL_APPID, '');
        $this->routineAppid = $this->httpConfig->getConfig(DefaultConfig::MINI_APPID, '');
        // 注意：subMchid 和 subAppid 需要在运行时动态设置，不在这里初始化
    }

    /**
     * @return array
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2022/9/30
     */
    public function all(): array
    {
        return [
			'mch_id' => $this->mchId,
            'serial_no' => $this->serialNo,
            'key' => $this->key,
            'cert_path' => $this->certPath,
            'key_path' => $this->keyPath,
            'notify_url' => $this->notifyUrl,
            'http' => $this->httpConfig->all(''),
            'other' => [
                'wechat' => [
                    'appid' => $this->wechatAppid,
                ],
                'miniprog' => [
                    'appid' => $this->routineAppid,
                ]
            ],
        ];
    }
}
