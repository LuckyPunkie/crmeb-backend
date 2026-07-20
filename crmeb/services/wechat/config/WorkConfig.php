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
use crmeb\services\wechat\contract\WorkAppConfigHandlerInterface;
use crmeb\services\wechat\DefaultConfig;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;

/**
 * 企业微信配置
 * Class WorkConfig
 * @package crmeb\services\wechat\config
 */
class WorkConfig implements ConfigHandlerInterface
{

    //应用
    const TYPE_APP = 'app';
    //客户联系
    const TYPE_USER = 'user';
    //通讯录同步
    const TYPE_ADDRESS = 'address';
    //客服
    const TYPE_KEFU = 'kefu';
    //审批
    const TYPE_APPROVE = 'approve';
    //会议室
    const TYPE_MEETING = 'meeting';
    //自建应用
    const TYPE_USER_APP = 'build';

    /**
     * @var string
     */
    public string $corpId;

    /**
     * @var string
     */
    public string $token;

    /**
     * @var string
     */
    public string $aesKey;

    /**
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
     * @var string
     */
    protected string $handler;

    /**
     * @var array
     */
    protected array $appConfig;

    /**
     * WorkConfig constructor.
     * @param HttpCommonConfig $commonConfig
     */
    public function __construct(HttpCommonConfig $commonConfig)
    {
        $this->httpConfig = $commonConfig;
        $this->init();
    }

    /**
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/18
     */
    protected function init()
    {
        if ($this->init) {
            return;
        }
        $this->init = true;
        $this->corpId = $this->httpConfig->getConfig(DefaultConfig::WORK_CORP_ID, '');
        $this->token = $this->httpConfig->getConfig('work.token', '');
        $this->aesKey = $this->httpConfig->getConfig('work.key', '');
    }

	/**
	 * 设置
	 * @param string $key
	 * @param $value
	 * @return $this
	 */
	public function set(string $key, $value): self
	{
		$this->{$key} = $value;
		return $this;
	}

	/**
	 * 获取配置
	 * @param string|null $key
	 * @return null
	 */
	public function get(string $key = null)
	{
		$this->init();
		if ($key) {
			if (isset($this->{$key})) {
				return $this->{$key};
			}
		}
		return null;
	}

    /**
     * 获取全部值
     * @return array
     */
    #[ArrayShape([
        'corp_id' => "string",
        'token' => "string",
        'aes_key' => "string",
        'response_type' => "string",
        'log' => "array",
        'http' => "bool[]"
    ])]
    #[Pure]
    public function all($channel): array
    {
        return [
            'corp_id' => $this->corpId,
            'token' => $this->token,
            'aes_key' => $this->aesKey,
            'response_type' => $this->responseType,
            'http' => $this->httpConfig->all()
        ];
    }

    /**
     * 获取应用配置
     * @param string $type
     * @return array
     */
    public function getAppConfig(string $type): array
    {
        if (!isset($this->appConfig[$type])) {
            /** @var WorkAppConfigHandlerInterface $make */
            $make = app()->make($this->handler);
            if (!$this->corpId) {
                $this->init();
            }
            $this->appConfig[$type] = $make->getAppConfig($this->corpId, $type);
        }
        return $this->appConfig[$type];
    }

    /**
     * 设置
     * @param string $handler
     * @return $this
     */
    public function setHandler(string $handler): self
    {
        $this->handler = $handler;
        return $this;
    }
}
