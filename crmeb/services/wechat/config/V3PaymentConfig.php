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
class V3PaymentConfig extends BaseConfig implements ConfigHandlerInterface
{
    public string $channel = 'wechat';
    public string $appId = '';
    public string $mchId = '';
    public string $key = '';
    public string $certPath = '';
    public string $keyPath = '';
    public string $notifyUrl = '';
    public string $refundUrl = '';
    public string $serialNo = '';
    public string $publicKey = '';
    public string $publicPem = '';

    /**
     * 是否v3支付
     * @var bool
     */
    public bool $isV3PAy = true;

    /**
     * 是否为平台证书模式
     * @var bool
     */
    public bool $isSerialOn = false;

    // /**
    //  * @var HttpCommonConfig
    //  */
    // protected HttpCommonConfig $httpConfig;

    // /**
    //  * @var bool
    //  */
    // protected bool $init = false;

    // /**
    //  * V3PaymentConfig constructor.
    //  */
    // public function __construct()
    // {
    //     $this->httpConfig = app()->make(HttpCommonConfig::class);
    //     $this->init();
    // }

    // public function init()
    // {
    //     if ($this->init) { return; }
    //     $this->channel === 'wechat' ? $this->getWechat() : $this->getMini();
    // }

    // public function setChannel(string $channel): self
    // {
    //     $this->channel = $channel;
    //     $this->init();
    //     return $this;
    // }

    /**
     * 初始化
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    protected function getWechat()
    {

        //$this->init = true;
        $this->appId = $this->httpConfig->getConfig(DefaultConfig::OFFICIAL_APPID);
        $this->mchId = $this->httpConfig->getConfig(DefaultConfig::WEIXIN_PAY_MCHID);
        $this->key = $this->httpConfig->getConfig('pay.weixin.v3_key');
        $this->certPath = app()->getRootPath() . 'public' .$this->httpConfig->getConfig('pay.weixin.client_cert');
        $this->keyPath = app()->getRootPath() . 'public' .$this->httpConfig->getConfig('pay.weixin.client_key');
        $this->notifyUrl =  trim($this->httpConfig->getConfig(DefaultConfig::COMMENT_URL))
            .$this->httpConfig->getConfig('pay.weixin.notifyUrl');
        $this->refundUrl =  trim($this->httpConfig->getConfig(DefaultConfig::COMMENT_URL))
            .$this->httpConfig->getConfig('pay.weixin.refundUrl');
        $this->serialNo = $this->httpConfig->getConfig('pay.weixin.v3_serial_no');
        $this->publicKey = $this->httpConfig->getConfig('pay.weixin.v3_public_id');
        $this->publicPem = $this->httpConfig->getConfig('pay.weixin.v3_public_pem');
    }

    public function getMini()
    {
        $this->appId = $this->httpConfig->getConfig(DefaultConfig::OFFICIAL_APPID);
        $this->mchId = $this->httpConfig->getConfig(DefaultConfig::MINI_PAY_MCHID);
        $this->key = $this->httpConfig->getConfig('pay.mini.v3_key');
        $this->certPath = app()->getRootPath() . 'public' .$this->httpConfig->getConfig('pay.mini.client_cert');
        $this->keyPath = app()->getRootPath() . 'public' .$this->httpConfig->getConfig('pay.mini.client_key');
        $this->notifyUrl =  trim($this->httpConfig->getConfig(DefaultConfig::COMMENT_URL))
            .$this->httpConfig->getConfig('pay.mini.notifyUrl');
        $this->refundUrl =  trim($this->httpConfig->getConfig(DefaultConfig::COMMENT_URL))
            .$this->httpConfig->getConfig('pay.mini.refundUrl');
        $this->serialNo = $this->httpConfig->getConfig('pay.mini.v3_serial_no');
        $this->publicKey = $this->httpConfig->getConfig('pay.mini.v3_public_id');
        $this->publicPem = $this->httpConfig->getConfig('pay.mini.v3_public_pem');
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
            'app_id' => $this->appId,
            'serial_no' => $this->serialNo,
            'mch_id' => $this->mchId,
            'key' => $this->key,
            'cert_path' => $this->certPath,
            'key_path' => $this->keyPath,
            'notify_url' => $this->notifyUrl,
            'http' => $this->httpConfig->all(''),
            'other' => [
                'wechat' => [
                    'appid' => $this->httpConfig->getConfig(DefaultConfig::OFFICIAL_APPID),
                ],
                'miniprog' => [
                    'appid' =>  $this->httpConfig->getConfig(DefaultConfig::OFFICIAL_APPID),
                ]
            ],
        ];
    }
}
