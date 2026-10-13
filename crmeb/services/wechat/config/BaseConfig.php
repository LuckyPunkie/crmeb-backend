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
class BaseConfig implements ConfigHandlerInterface
{
    /**
     * @var string
     */
    protected string $channel = 'wechat';
    /**
     * @var HttpCommonConfig
     */
    protected HttpCommonConfig $httpConfig;

    /**
     * @var bool
     */
    protected bool $init = false;

    /**
     * PaymentConfig constructor.
     * @param HttpCommonConfig $commonConfig
     */
    public function __construct(HttpCommonConfig $commonConfig)
    {
        $this->httpConfig = $commonConfig;
        $this->init();
    }

    public function setAccessEnd($channel): self
    {
        $this->channel = $channel;
        $this->init();
        return $this;
    }

    /**
     * 初始化
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/18
     */
    protected function init()
    {
        if ($this->init) {
            return;
        }
        $this->channel === 'wechat' ? $this->getWechat() : $this->getMini();
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

    public function getMini()
    {
        $this->init = true;
    }

    public function all(): array
    {
        return $this->toArray();
    }
}
