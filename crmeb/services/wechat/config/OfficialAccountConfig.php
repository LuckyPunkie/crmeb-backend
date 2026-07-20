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
 * 公众号配置
 * Class OfficialAccountConfig
 * @package crmeb\services\wechat\config
 */
class OfficialAccountConfig implements ConfigHandlerInterface
{

    /**
     * AppID
     * @var string
     */
    public string $appId;

    /**
     * AppSecret
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
     * 指定 API 调用返回结果的类型
     * @var string
     */
    protected string $responseType = 'array';

    /**
     * @var HttpCommonConfig
     */
    protected HttpCommonConfig $httpConfig;

    /**
     * @var bool
     */
    protected bool $init = false;

    /**
     * OfficialAccountConfig constructor.
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
        $this->appId = $this->httpConfig->getConfig('official.appid', '');
        $this->secret = $this->httpConfig->getConfig('official.secret', '');
        $this->token = $this->httpConfig->getConfig('official.token', '');
        $this->aesKey = ($this->httpConfig->getConfig('official.encode', -1) > 0 ? $this->httpConfig->getConfig('official.key', '') : '');
    }

    /**
     * 获取所有配置
     * @return array
     */
    #[ArrayShape([
        'app_id' => "string",
        'secret' => "string",
        'token' => "string",
        'aes_key' => "string",
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
