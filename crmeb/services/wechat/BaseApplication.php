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

namespace crmeb\services\wechat;

use think\helper\Str;
use EasyWeChat\Work\Application;
use Symfony\Component\HttpFoundation\HeaderBag;
use EasyWeChat\Pay\Application as PayApplication;
use EasyWeChat\MiniApp\Application as MiniAppApplication;
use crmeb\services\wechat\contract\BaseApplicationInterface;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use EasyWeChat\OfficialAccount\Application as OfficialAccountApplication;
use think\Request;

/**
 * Class BaseApplication
 * @package crmeb\services\wechat
 */
abstract class BaseApplication implements BaseApplicationInterface
{

    //app端
    const APP = 'app';
    //h5端、公众端
    const WEB = 'web';
    //小程序端
    const MINI = 'mini';
    //开发平台
    const OPEN = 'open';
    //pc端
    const PC = 'pc';

    const BASE_URL = 'https://api.weixin.qq.com';

    /**
     * 访问端
     * @var string
     */
    protected string $accessEnd = '';

    /**
     *
     * @var string
     */
    protected string $name = '';

    /**
     * @var array
     */
    protected static array $property = [];

    /**
     * @var string
     */
    protected string $pushMessageHandler;

    /**
     * Debug
     * @var bool
     */
    protected bool $debug = true;

    /**
     * 设置消息处理类
     * @param string $handler
     * @return $this
     */
    public function setPushMessageHandler(string $handler): static
    {
        $this->pushMessageHandler = $handler;
        return $this;
    }

    /**
     * 设置访问端
     * @param string $accessEnd
     * @return $this
     */
    public function setAccessEnd(string $accessEnd)
    {
        if (in_array($accessEnd, [self::APP, self::WEB, self::MINI])) {
            $this->accessEnd = $accessEnd;
        }
        $this->config->setAccessEnd($this->accessEnd);
        return $this;
    }

    /**
     * 自动获取访问端
     * @param Request $request
     * @return string
     */
    public function getAuthAccessEnd(Request $request): string
    {
        if (!$this->accessEnd) {
            try {
                if ($request->isApp()) {
                    $this->accessEnd = self::APP;
                } else if ($request->isPc()) {
                    $this->accessEnd = self::PC;
                } else if ($request->isWechat() || $request->isH5()) {
                    $this->accessEnd = self::WEB;
                } else if ($request->isRoutine()) {
                    $this->accessEnd = self::MINI;
                } else {
                    $this->accessEnd = self::WEB;
                }
            } catch (\Throwable $e) {
                $this->accessEnd = self::WEB;
            }
        }
        return $this->accessEnd;
    }

    /**
     * 记录错误日志
     * @param \Throwable $e
     */
    protected static function error(\Throwable $e)
    {
        static::instance()->debug && response_log_write([
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => $e->getTrace()
        ]);
    }

    /**
     * 请求日志
     * @param string $message
     * @param $request
     * @param $response
     */
    protected static function logger(string $message, $request, $response)
    {
        $debug = static::instance()->debug;

        if ($debug) {
            if (is_object($response) && method_exists($response, 'toArray')) {
                try {
                    $response = $response->toArray(false);
                } catch (\Throwable $e) {
                    $response = method_exists($response, 'getContent') ? $response->getContent(false) : $e->getMessage();
                }
            }

            response_log_write([
                'message' => $message,
                'request' => $request,
                'response' => $response
            ], 'info');

        }
    }

    /**
     * 设置request
     * @param PayApplication|OfficialAccountApplication|MiniAppApplication|Application $application
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    protected function setRequest(PayApplication|OfficialAccountApplication|MiniAppApplication|Application $application)
    {
        $request = request();
        $symfonyRequest = new SymfonyRequest($request->get(), $request->post(), [], $request->cookie(), [], $request->server(), $request->getContent());
        $symfonyRequest->headers = new HeaderBag($request->header());
        $application->setRequestFromSymfonyRequest($symfonyRequest);
    }

    /**
     * 设置日志
     * @param MiniAppApplication|OfficialAccountApplication $loggerAwareTrait
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    protected function setLogger(MiniAppApplication|OfficialAccountApplication $loggerAwareTrait)
    {
        $loggerAwareTrait->setLogger(app()->log);
    }

    /**
     * 设置缓存
     * @param $withCache Application|OfficialAccountApplication|MiniAppApplication|
     * @return void
     */
    protected function setCache($withCache)
    {
        $withCache->setCache(app()->cache)->setCacheNamespace('merchant-');
    }

    /**
     * 设置http请求
     * @param $application Application|OfficialAccountApplication|MiniAppApplication|PayApplication
     * @param string $baseUrl
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2024/1/25
     */
    protected function setHttpClient($application, string $baseUrl = self::BASE_URL)
    {
        $application->setHttpClient(new CustomHttpClient($baseUrl));
    }

    /**
     * 获取请求驱动
     * @param string $name
     * @return string
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getClientClassName(string $name)
    {
        $class = Str::studly($name) . 'Client';
        $className = '\\crmeb\\services\\wechat\\client\\' . $this->name . '\\' . $class;

        return $className;
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed|object
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function __call($name, $arguments)
    {
        $className = $this->getClientClassName($name);
        if (class_exists($className)) {
            $client = $this->application()->getClient();
            return new $className($client);
        } else {
            throw new WechatException('请求类不存在');
        }
    }
}
