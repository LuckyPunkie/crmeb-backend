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

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\HttpClient\Response\AsyncContext;
use Swoole\Coroutine\Http\Client;

/**
 * 携程curl请求
 * Class CustomHttpClient
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2024/1/24
 * @package crmeb\services\wechat
 */
class CustomHttpClient implements HttpClientInterface
{

    /**
     * @var string
     */
    protected string $baseUrl = '';

    /**
     * @var int
     */
    protected int $timeout = 3;

    /**
     * @var array
     */
    protected array $httpConfig = [];

    /**
     * CustomHttpClient constructor.
     * @param string $baseUrl
     * @param array $httpConfig
     */
    public function __construct(string $baseUrl, array $httpConfig = [])
    {
        $this->baseUrl = $baseUrl;
        $this->httpConfig = $httpConfig;
    }

    /**
     * 发起请求
     * @param string $method
     * @param string $url
     * @param array $options
     * @return ResponseInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2024/1/24
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        // 合并传入的选项和默认选项
        $options = array_merge(self::OPTIONS_DEFAULTS, $this->httpConfig, $options);

        if (!empty($options['query'])) {
            $url .= (strstr($url, '?') !== false ? '&' : '?') . http_build_query($options['query']);
        }

        $isAbsoluteUrl = (bool)preg_match('/^https?:\/\//i', $url);
        $urlInfo = parse_url($isAbsoluteUrl ? $url : $this->baseUrl);

        $ssl = ($urlInfo['scheme'] ?? 'http') === 'https';
        $host = $urlInfo['host'] ?? str_replace(['https://', 'http://', '/'], '', $this->baseUrl);
        $port = $urlInfo['port'] ?? ($ssl ? 443 : 80);

        if ($isAbsoluteUrl) {
            $path = $urlInfo['path'] ?? '/';
            if (!empty($urlInfo['query'])) {
                $path .= '?' . $urlInfo['query'];
            }
        } else {
            $basePath = $urlInfo['path'] ?? '';
            $basePath = $basePath && $basePath !== '/' ? '/' . trim($basePath, '/') : '';
            $path = $basePath . '/' . ltrim($url, '/');
        }
        $effectiveUrl = ($ssl ? 'https://' : 'http://') . $host . (($ssl && $port === 443) || (!$ssl && $port === 80) ? '' : ':' . $port) . ($path ?: '/');

        // 使用 Swoole Client 完成请求
        $client = new Client($host, $port, $ssl);

        $client->set([
            'timeout' => !empty($options['timeout']) ? $options['timeout'] : $this->timeout,
            'ssl_cert_file' => $options['cert'] ?? null,
            'ssl_key_file' => $options['ssl_key'] ?? null
        ]);

        $client->setMethod($method);
        $headers = [];
        // 设置请求头
        foreach ($options['headers'] as $key => $value) {
            if (is_string($key)) {
                $values = [];
                if (is_array($value)) {
                    foreach ($value as $item) {
                        [$type, $val] = strstr($item, ':') !== false ? explode(':', $item) : [null, null];
                        if ($type && $val) {
                            $values[] = $val;
                        }
                    }
                    $headers[$key] = implode(',', $values);
                } else {
                    $values[] = $value;
                    $headers[$key] = implode(',', $values);
                }
            } else {
                $values = [];
                [$type, $val] = strstr($value, ':') !== false ? explode(':', $value) : [null, null];
                $values[] = $val;
                $headers[$type] = implode(',', $values);
            }
        }

        if (!empty($options['json'])) {
            if (!isset($headers['Content-Type']) && !isset($headers['content-type'])) {
                $headers['Content-Type'] = 'application/json';
            }
            $client->setHeaders($headers);
            $client->setData(json_encode($options['json'], JSON_UNESCAPED_UNICODE));
        } else {
            $client->setHeaders($headers);
            if (!empty($options['body'])) {
                $client->setData($options['body']);
            }
        }

        try {
            // 发起请求
            $client->execute($path ?: '/');

            // 创建响应对象
            return new SwooleResponse($client, $effectiveUrl);
        } finally {
            // 关闭客户端连接
            $client->close();
        }
    }

    /**
     * @param iterable|ResponseInterface $responses
     * @param float|null $timeout
     * @return ResponseStreamInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2024/1/25
     */
    public function stream(iterable|ResponseInterface $responses, float $timeout = null): ResponseStreamInterface
    {
        // 创建 ResponseStreamInterface 对象并进行流式处理
        $stream = new AsyncContext($responses, $this->httpClient, $timeout);

        // 返回流对象
        return $stream;
    }

    /**
     * @param array $options
     * @return $this
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2024/1/25
     */
    public function withOptions(array $options): static
    {
        $this->httpConfig = array_merge(self::OPTIONS_DEFAULTS, $options);
        if (!empty($this->httpConfig['base_uri']) && $this->httpConfig['base_uri'] !== $this->baseUrl) {
            $this->baseUrl = $this->httpConfig['base_uri'];
        }
        return $this;
    }
}
