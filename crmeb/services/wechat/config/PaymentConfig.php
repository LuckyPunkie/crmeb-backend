<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace crmeb\services\wechat\config;

use crmeb\services\wechat\contract\ConfigHandlerInterface;
use crmeb\services\wechat\DefaultConfig;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;

/**
 * 支付配置
 * Class PaymentConfig
 * @package crmeb\services\wechat\config
 */
class PaymentConfig extends BaseConfig implements ConfigHandlerInterface
{
    /**
     * @var string
     */
    protected string $channel = 'wechat';
    public $appId;
    public $mchId;
    public $key;
    public $certPath;
    public $keyPath;
    public $notifyUrl;
    public $refundUrl;
    public $isV3PAy;
    public $routineMchId;
    // /**
    //  * @var HttpCommonConfig
    //  */
    // protected HttpCommonConfig $httpConfig;

    // /**
    //  * @var bool
    //  */
    // protected bool $init = false;

    // /**
    //  * PaymentConfig constructor.
    //  * @param HttpCommonConfig $commonConfig
    //  */
    // public function __construct(HttpCommonConfig $commonConfig)
    // {
    //     $this->httpConfig = $commonConfig;
    //     $this->init();
    // }

    // public function setChannel($channel): self
    // {
    //     $this->channel = $channel;
    //     $this->init();
    //     return $this;
    // }

    // /**
    //  * 初始化
    //  * @author 等风来
    //  * @email 136327134@qq.com
    //  * @date 2023/9/18
    //  */
    // protected function init()
    // {
    //     if ($this->init) {
    //         return;
    //     }
    //     $this->channel === 'wechat' ? $this->getWechat() : $this->getMini();
    // }


    public function getWechat()
    {
        //$this->init = true;
        $this->appId = $this->httpConfig->getConfig(DefaultConfig::OFFICIAL_APPID);
        $this->mchId = $this->httpConfig->getConfig(DefaultConfig::WEIXIN_PAY_MCHID);
        $this->key = $this->httpConfig->getConfig('pay.weixin.key');
        $this->certPath = app()->getRootPath() . 'public' .$this->httpConfig->getConfig('pay.weixin.client_cert');
        $this->keyPath = app()->getRootPath() . 'public' .$this->httpConfig->getConfig('pay.weixin.client_key');
        $this->notifyUrl =  trim($this->httpConfig->getConfig(DefaultConfig::COMMENT_URL))
            .$this->httpConfig->getConfig('pay.weixin.notifyUrl');
        $this->refundUrl =  trim($this->httpConfig->getConfig(DefaultConfig::COMMENT_URL))
            .$this->httpConfig->getConfig('pay.weixin.refundUrl');
        $this->isV3PAy = $this->httpConfig->getConfig('pay.weixin.isV3PAy');
    }

    public function getMini()
    {
        //$this->init = true;
        $this->appId = $this->httpConfig->getConfig(DefaultConfig::MINI_APPID);
        $this->mchId = $this->httpConfig->getConfig(DefaultConfig::MINI_PAY_MCHID);
        $this->key = $this->httpConfig->getConfig('pay.mini.key');
        $this->certPath = app()->getRootPath() . 'public' .$this->httpConfig->getConfig('pay.mini.client_cert');
        $this->keyPath = app()->getRootPath() . 'public' .$this->httpConfig->getConfig('pay.mini.client_key');
        $this->notifyUrl =  trim($this->httpConfig->getConfig(DefaultConfig::COMMENT_URL))
            .$this->httpConfig->getConfig('pay.mini.notifyUrl');
        $this->refundUrl =  trim($this->httpConfig->getConfig(DefaultConfig::COMMENT_URL))
            .$this->httpConfig->getConfig('pay.mini.refundUrl');
        $this->routineMchId = $this->httpConfig->getConfig('pay.mini.routine_mchid','');
        $this->isV3PAy = $this->httpConfig->getConfig('pay.mini.isV3PAy');
    }

    // /**
    //  * 获取配置
    //  * @param string $key
    //  * @param null $default
    //  * @return mixed
    //  */
    // public function getConfig(string $key, $default = null)
    // {
    //     return $this->httpConfig->getConfig($key, $default);
    // }

    /**
     * 全部配置
     * @return array
     */
    public function all(): array
    {
        return [
            'app_id' => $this->appId,
            'mch_id' => $this->mchId,
            'v2_secret_key' =>  $this->key,
            'private_key' => $this->keyPath,
            'certificate' => $this->certPath,
            'notify_url' => $this->notifyUrl,
            'isV3PAy' => $this->isV3PAy,
            'http' => $this->httpConfig->all()
        ];
    }
}
