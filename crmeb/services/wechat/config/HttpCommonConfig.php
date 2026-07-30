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
use crmeb\services\wechat\contract\ServeConfigInterface;
use crmeb\services\wechat\DefaultConfig;

/**
 * Http请求配置
 * Class HttpCommonConfig
 * @package crmeb\services\wechat\config
 */
class HttpCommonConfig implements ConfigHandlerInterface
{
    /**
     * @var bool[]
     */
    protected array $config = [
        'verify' => false,
        'timeout' => 5,
        // EasyWeChat AccessToken 依赖 http.base_uri；缺失会拼出 http://:/ 导致小程序码生成失败
        'base_uri' => 'https://api.weixin.qq.com/',
    ];

    /**
     * @var string
     */
    protected string $serve;

    /**
     * @param string $serve
     * @return $this
     */
    public function setServe(string $serve): static
    {
        $this->serve = $serve;
        return $this;
    }

    /**
     * 获取服务端实例
     * @return ServeConfigInterface
     */
    public function getServe()
    {
        return app()->make($this->serve);
    }

    /**
     * 直接获取配置
     * @param string $key
     * @param null $default
     * @return mixed
     */
    public function getConfig(string $key, $default = null)
    {
        if ($value = DefaultConfig::value($key)) {
            return $value;
        }
        return $this->getServe()->getConfig(DefaultConfig::key($key), $default);
    }

    /**
     * @return array
     */
    public function all(): array
    {
        return $this->config;
    }
}
