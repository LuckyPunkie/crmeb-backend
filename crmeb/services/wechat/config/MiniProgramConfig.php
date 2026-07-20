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
 * 小程序配置
 * Class MiniProgramConfig
 * @package crmeb\services\wechat\config
 */
class MiniProgramConfig implements ConfigHandlerInterface
{

    /**
     * APPid
     * @var string
     */
    public string $appId;

    /**
     * APPsecret
     * @var string
     */
    public string $secret;

    /**
     * Token
     * @var string
     */
    public string $token;

    /**
     * EncodingAESKey
     * @var string
     */
    public string $aesKey;

    /**
     * @var string
     */
    protected string $responseType = 'array';

    /**
     * http配置
     * @var HttpCommonConfig
     */
    protected HttpCommonConfig $httpConfig;

    /**
     * 是否初始化过
     * @var bool
     */
    protected bool $init = false;

    /**
     * MiniProgramConfig constructor.
     * @param HttpCommonConfig $commonConfig
     */
    public function __construct(HttpCommonConfig $commonConfig)
    {
        $this->httpConfig = $commonConfig;
        $this->init();
    }

    /**
     * 初始化
     */
    protected function init()
    {
        if ($this->init) {
            return;
        }
        $this->init = true;
        $this->appId = $this->httpConfig->getConfig(DefaultConfig::MINI_APPID, '');
        $this->secret = $this->httpConfig->getConfig('mini.secret', '');
        $this->token = $this->httpConfig->getConfig('mini.token', '');
        $this->aesKey = $this->httpConfig->getConfig('mini.key', '');
    }

    /**
     * 获取配置
     * @param string $key
     * @param null $default
     * @return mixed
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->httpConfig->getConfig($key, $default);
    }

    /**
     * 全部
     * @return array
     */
    #[ArrayShape([
        'app_id' => "string",
        'secret' => "string",
        'response_type' => "string",
        'log' => "array",
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
            'response_type' => $this->responseType,
            'use_stable_access_token' => true,
            'http' => $this->httpConfig->all()
        ];
    }
}
