<?php
/**
 *  +----------------------------------------------------------------------
 *  | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
 *  +----------------------------------------------------------------------
 *  | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
 *  +----------------------------------------------------------------------
 *  | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
 *  +----------------------------------------------------------------------
 *  | Author: CRMEB Team <admin@crmeb.com>
 *  +----------------------------------------------------------------------
 */

namespace crmeb\services\wechat;

use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\RedirectionException;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Swoole\Coroutine\Http\Client;

/**
 * 携程请求返回资源
 * Class SwooleResponse
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2024/1/25
 * @package crmeb\services\wechat
 */
class SwooleResponse implements ResponseInterface
{
    /**
     * @var Client
     */
    private $swooleResponse;

    /**
     * @var string
     */
    private string $url = '';

    /**
     * SwooleResponse constructor.
     * @param Client $swooleResponse
     * @param string $url
     */
    public function __construct(Client $swooleResponse, string $url = '')
    {
        $this->swooleResponse = $swooleResponse;
        $this->url = $url;
    }

    /**
     * @param bool $throw
     * @return string
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2024/1/25
     */
    public function getContent(bool $throw = true): string
    {
        if ($throw) {
            $this->checkStatusCode();
        }

        return $this->swooleResponse->getBody();
    }

    /**
     * @return int
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2024/1/25
     */
    public function getStatusCode(): int
    {
        return $this->swooleResponse->getStatusCode();
    }

    /**
     * @param bool $throw
     * @return array
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2024/1/25
     */
    public function getHeaders(bool $throw = true): array
    {
        if ($throw) {
            $this->checkStatusCode();
        }

        $headers = [];
        foreach (($this->swooleResponse->getHeaders() ?: []) as $name => $value) {
            $headers[strtolower($name)] = array_values(array_map('strval', (array)$value));
        }
        return $headers;
    }

    /**
     * @param bool $throw
     * @return array
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2024/1/25
     */
    public function toArray(bool $throw = true): array
    {
        $content = $this->getContent($throw);
        if ($content === '' && $throw) {
            throw new \InvalidArgumentException('The content is not valid JSON.');
        }

        return json_decode($content, true);
    }

    /**
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2024/1/25
     */
    public function cancel(): void
    {
        $this->swooleResponse->close();
    }

    /**
     * @param string|null $type
     * @return mixed
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2024/1/24
     */
    public function getInfo(string $type = null): mixed
    {
        if ($type === 'http_code') {
            return $this->getStatusCode();
        }

        $httpResponse = [
            'http_code' => $this->getStatusCode(),
            'response_headers' => $this->getHeaderLines(),
            'url' => $this->url,
        ];

        return $type ? $httpResponse[$type] ?? null : $httpResponse;
    }

    /**
     * @return array
     */
    private function getHeaderLines(): array
    {
        $headers = [];
        foreach (($this->swooleResponse->getHeaders() ?: []) as $name => $value) {
            foreach ((array)$value as $item) {
                $headers[] = $name . ': ' . $item;
            }
        }
        return $headers;
    }

    /**
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2024/1/25
     */
    private function checkStatusCode()
    {
        $code = $this->getInfo('http_code');

        if (500 <= $code) {
            throw new ServerException($this);
        }

        if (400 <= $code) {
            throw new ClientException($this);
        }

        if (300 <= $code) {
            throw new RedirectionException($this);
        }
    }
}
