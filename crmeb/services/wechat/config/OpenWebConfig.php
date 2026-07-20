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
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;

/**
 * 开放平台网页端配置
 * Class OpenWebConfig
 * @package crmeb\services\wechat\config
 */
class OpenWebConfig implements ConfigHandlerInterface
{

    /**
     * Appid
     * @var string
     */
    public string $appId;

    /**
     * Appsecret
     * @var string
     */
    public string $secret;

    /**
     * @var string
     */
    public string $token;

    /**
     * @var string
     */
    public string $aesKey;

    /**
     * @var bool
     */
    protected bool $init = false;

    /**
     * @var HttpCommonConfig
     */
    protected HttpCommonConfig $config;

    /**
     * OpenWebConfig constructor.
     * @param HttpCommonConfig $config
     */
    public function __construct(HttpCommonConfig $config)
    {
        $this->config = $config;
        $this->init();
    }

    /**
     * OpenWebConfig constructor.
     */
    public function init()
    {
        if ($this->init) {
            return;
        }
        $this->init = true;
        $this->appId = $this->config->getConfig('official.appid', '');
        $this->secret = $this->config->getConfig('official.secret', '');
        $this->token = $this->config->getConfig('official.token', '');
        $this->aesKey = $this->config->getConfig('official.key', '');
    }

    /**
     * 获取配置
     * @param string $key
     * @param null $default
     * @return mixed
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->config->getConfig($key, $default);
    }

    /**
     * @return array
     */
    #[ArrayShape([
        'app_id' => "string",
        'secret' => "string",
        'token' => "string",
        'aes_key' => "string",
        'http' => "bool[]"
    ])]
    #[Pure]
    public function all(): array
    {
        return [
            'app_id' => $this->appId,
            'secret' => $this->secret,
            'token' => $this->token,
            'aes_key' => $this->aesKey,
            'http' => $this->config->all()
        ];
    }
}
