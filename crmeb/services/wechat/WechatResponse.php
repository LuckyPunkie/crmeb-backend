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

use think\Collection;
use Throwable;
use EasyWeChat\Kernel\HttpClient\Response;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

/**
 * 微信错误
 * Class WechatResponse
 * @package crmeb\services\wechat
 */
class WechatResponse extends Collection
{

    /**
     * @var Throwable
     */
    protected ?Throwable $e = null;

    /**
     * 是否抛出默认错误
     * @var bool
     */
    protected bool $error = true;

    /**
     * 数据转换
     * @var array
     */
    protected $items = [];

    /**
     * then() 处理后的返回值
     * @var mixed
     */
    protected $response;

    /**
     * WechatResponse constructor.
     * @param $items
     */
    public function __construct($items = [])
    {
        $this->items = is_object($items) && method_exists($items, 'toArray') ? $items->toArray() : $items;
        $this->wechatError();
    }

    /**
     * 错误统一处理
     */
    public function wechatError()
    {
        if (!$this->error) {
            return;
        }
        if (isset($this->items['errcode']) && 0 !== $this->items['errcode']) {
            throw new WechatException(
                ErrorMessage::getWorkMessage(
                    $this->items['errcode'] ?? 0,
                    $this->items['errmsg'] ?? null
                )
            );
        }
    }

    /**
     * @param bool $boole
     * @return $this
     */
    public function serError(bool $boole)
    {
        $this->error = $boole;
        return $this;
    }

    /**
     * 兼容历史代码中的对象属性读取方式，例如 $response->media_id。
     *
     * @param string $name
     * @return mixed|null
     */
    public function __get(string $name)
    {
        return $this->items[$name] ?? null;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->items[$name]);
    }

    /**
     * 正确处理
     * @param callable $then
     * @param bool $error
     * @return $this
     */
    public function then(callable $then, bool $error = null)
    {
        $error = $error || $this->error;
        if (0 !== $this->items['errcode'] && $error) {
            throw new WechatException($this->items['errmsg']);
        }
        try {
            $this->response = $then($this->items);
        } catch (Throwable $e) {
            $this->e = $e;
        }
        return $this;
    }

    /**
     * 异常处理
     * @param callable $catch
     * @return $this
     */
    public function catch(callable $catch)
    {

        if (!$this->e) {
            $this->e = new WechatException('success');
        }

        $catch($this->e, $this->items);

        return $this;
    }

    /**
     * 获取返回值
     * @return string
     */
    public function getResponse()
    {
        return $this->response;
    }
}
