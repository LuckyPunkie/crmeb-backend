<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2024 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace crmeb\basic;

use GuzzleHttp\Client;

/**
 * 服务基类
 * 为各种第三方服务提供统一的基础功能
 */
class BaseServices
{
    /**
     * HTTP客户端
     * @var Client
     */
    protected $httpClient;

    /**
     * 服务名称
     * @var string
     */
    protected $name;

    /**
     * 配置文件名
     * @var string
     */
    protected $configFile;

    /**
     * BaseServices constructor.
     * @param string $name 服务名称
     * @param array $config 配置参数
     * @param string $configFile 配置文件名
     */
    public function __construct(string $name = '', array $config = [], string $configFile = 'taoke')
    {
        $this->name = $name;
        $this->configFile = $configFile;
        $this->httpClient = new Client([
            'timeout' => 30,
            'verify' => false,
        ]);
        $this->initialize($config);
    }

    /**
     * 初始化方法
     * 子类可以重写此方法进行初始化操作
     * @param array $config 配置参数
     * @return void
     */
    protected function initialize(array $config = [])
    {
        // 子类可在此处进行初始化操作
    }

    /**
     * 获取HTTP客户端
     * @return Client
     */
    public function getHttpClient(): Client
    {
        return $this->httpClient;
    }

    /**
     * 设置HTTP客户端
     * @param Client $client
     * @return $this
     */
    public function setHttpClient(Client $client)
    {
        $this->httpClient = $client;
        return $this;
    }
}
