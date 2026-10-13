<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace crmeb\services\upload\extend\jdoss;

use crmeb\exceptions\UploadException;
use crmeb\services\upload\BaseClient;
use crmeb\services\upload\XML;
use GuzzleHttp\Psr7\Utils;

/**
 * 京东云上传客户端
 * Class Client
 * @package crmeb\services\upload\extend\jdoss
 */
class Client extends BaseClient
{
    const ALGORITHM_REQUEST = 'AWS4-HMAC-SHA256';

    const BLACKLIST_HEADERS = [
        'cache-control' => true,
        'content-type' => true,
        'content-length' => true,
        'expect' => true,
        'max-forwards' => true,
        'pragma' => true,
        'range' => true,
        'te' => true,
        'if-match' => true,
        'if-none-match' => true,
        'if-modified-since' => true,
        'if-unmodified-since' => true,
        'if-range' => true,
        'accept' => true,
        'authorization' => true,
        'proxy-authorization' => true,
        'from' => true,
        'referer' => true,
        'user-agent' => true,
        'x-amzn-trace-id' => true,
        'aws-sdk-invocation-id' => true,
        'aws-sdk-retry' => true,
    ];

    /**
     * AK
     * @var string
     */
    protected $accessKeyId;

    /**
     * SK
     * @var string
     */
    protected $secretKey;

    /**
     * 桶名
     * @var string
     */
    protected $bucketName;

    /**
     * 地区
     * @var string
     */
    protected $region;

    /**
     * @var mixed|string
     */
    protected $uploadUrl;

    /**
     * @var string
     */
    protected $baseUrl = 's3.<REGION>.jdcloud-oss.com';

    /**
     * 默认地域
     */
    const DEFAULT_REGION = 'cn-north-1';

    /**
     * Client constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->accessKeyId = $config['accessKey'] ?? '';
        $this->secretKey = $config['secretKey'] ?? '';
        $this->bucketName = $config['bucket'] ?? '';
        $this->region = $config['region'] ?? self::DEFAULT_REGION;
        $this->uploadUrl = $config['uploadUrl'] ?? '';
    }

    /**
     * 上传对象
     * @param string $bucket
     * @param string $region
     * @param string $key
     * @param array $data
     * @return array|mixed
     */
    public function putObject(string $bucket, string $region, string $key, array $data)
    {
        $url = $this->getRequestUrl($bucket, $region);
        $header = [
            'Host' => $url,
        ];
        if (isset($data['body'])) {
            $header['Content-Length'] = strlen($data['body']);
        }
        return $this->request('https://' . $url . '/' . ltrim($key, '/'), 'PUT', $data, $header);
    }

    /**
     * 删除对象
     * @param string $bucket
     * @param string $region
     * @param string $key
     * @return array|mixed
     */
    public function deleteObject(string $bucket, string $region, string $key)
    {
        $url = $this->getRequestUrl($bucket, $region);
        $header = [
            'Host' => $url,
        ];
        return $this->request('https://' . $url . '/' . ltrim($key, '/'), 'DELETE', [], $header);
    }

    /**
     * 获取桶列表
     * @return array|mixed
     */
    public function listBuckets()
    {
        $url = $this->getRequestUrl();
        $header = [
            'Host' => $url,
        ];
        return $this->request('https://' . $url . '/', 'GET', [], $header);
    }

    /**
     * 检测桶
     * @param string $bucket
     * @param string $region
     * @return array|mixed
     */
    public function headBucket(string $bucket, string $region = '')
    {
        $url = $this->getRequestUrl($bucket, $region);
        $header = [
            'Host' => $url,
        ];
        return $this->request('https://' . $url, 'HEAD', [], $header);
    }

    /**
     * 创建桶
     * @param string $name
     * @param string $region
     * @param string $acl
     * @return array|mixed
     */
    public function createBucket(string $name, string $region, string $acl)
    {
        $url = $this->getRequestUrl($name, $region);
        $header = [
            'Host' => $url,
            'x-amz-acl' => $acl,
        ];
        return $this->request('https://' . $url . '/', 'PUT', [], $header);
    }

    /**
     * 删除桶
     * @param string $bucket
     * @param string $region
     * @return array|mixed
     */
    public function deleteBucket(string $bucket, string $region = '')
    {
        $url = $this->getRequestUrl($bucket, $region);
        $header = [
            'Host' => $url,
        ];
        return $this->request('https://' . $url . '/', 'DELETE', [], $header);
    }

    /**
     * 设置桶跨域规则
     * @param string $bucket
     * @param string $region
     * @param array $data
     * @return array|mixed
     */
    public function putBucketCors(string $bucket, string $region = '', array $data = [])
    {
        $url = $this->getRequestUrl($bucket, $region);
        $header = [
            'Host' => $url,
            'content-type' => 'application/xml',
        ];
        return $this->request('https://' . $url . '/?cors', 'PUT', $data, $header);
    }

    /**
     * 获取请求 host
     * @param string $bucket
     * @param string $region
     * @return string
     */
    public function getRequestUrl(string $bucket = '', string $region = self::DEFAULT_REGION)
    {
        $region = $region ?: $this->region ?: self::DEFAULT_REGION;
        if (!$this->accessKeyId) {
            throw new UploadException('请传入SecretId');
        }
        if (!$this->secretKey) {
            throw new UploadException('请传入SecretKey');
        }
        return ($bucket ? $bucket . '.' : '') . 's3.' . $region . '.jdcloud-oss.com';
    }

    /**
     * 发起请求
     * @param string $url
     * @param string $method
     * @param array $data
     * @param array $clientHeader
     * @param int $timeout
     * @return array|mixed
     */
    protected function request(string $url, string $method, array $data = [], array $clientHeader = [], int $timeout = 10)
    {
        if (!isset($clientHeader['Content-Length'])) {
            $clientHeader['Content-Length'] = isset($data['body']) ? strlen((string)$data['body']) : 0;
        }
        $clientHeader['x-amz-date'] = gmdate('Ymd\THis\Z');
        $clientHeader['x-amz-content-sha256'] = hash('sha256', (string)($data['body'] ?? ''), false);
        $clientHeader['Authorization'] = $this->generateAwsSignatureV4($data['region'] ?? $this->region ?: self::DEFAULT_REGION, $url, strtoupper($method), $clientHeader, $data);
        // 允许通过 data['timeout'] 动态设置超时时间
        if (isset($data['timeout']) && is_numeric($data['timeout'])) {
            $timeout = max(1, (int)$data['timeout']);
        }
        return $this->requestClient($url, strtoupper($method), $data, $clientHeader, $timeout);
    }

    /**
     * 发起请求并返回真实结果
     * @param string $url
     * @param string $method
     * @param array $data
     * @param array $clientHeader
     * @param int $timeout
     * @return array|mixed
     */
    protected function requestClient(string $url, string $method, array $data = [], array $clientHeader = [], int $timeout = 10)
    {
        $headers = [];
        foreach ($clientHeader as $key => $item) {
            $headers[] = $key . ':' . $item;
        }

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($data['body'])) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data['body']);
        } elseif (!empty($data['json'])) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data['json']));
        }
        // 连接和整体超时设置
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, min(10, $timeout));
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        if (strpos($url, 'https://') === 0) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }

        $response = curl_exec($curl);
        $status = curl_getinfo($curl);
        $curlError = curl_error($curl);
        $curlErrNo = curl_errno($curl);
        curl_close($curl);

        if ($response === false || $curlErrNo) {
            throw new UploadException($curlError ?: 'JDOSS请求失败');
        }

        $headerSize = (int)($status['header_size'] ?? 0);
        $rawHeader = substr($response, 0, $headerSize);
        $body = trim(substr($response, $headerSize));
        $httpCode = (int)($status['http_code'] ?? 0);
        $responseHeaders = $this->parseResponseHeaders($rawHeader);

        if ($httpCode >= 200 && $httpCode < 300) {
            if ($body === '') {
                return [
                    'http_code' => $httpCode,
                    'headers' => $responseHeaders,
                ];
            }

            if ($this->isXml) {
                $result = XML::parse($body);
                return $result ?: [
                    'http_code' => $httpCode,
                    'headers' => $responseHeaders,
                    'body' => $body,
                ];
            }

            $result = json_decode($body, true);
            return $result ?: [
                'http_code' => $httpCode,
                'headers' => $responseHeaders,
                'body' => $body,
            ];
        }

        throw new UploadException($this->parseErrorMessage($body, $httpCode));
    }

    /**
     * 解析响应头
     * @param string $rawHeader
     * @return array
     */
    protected function parseResponseHeaders(string $rawHeader): array
    {
        $headers = [];
        $lines = preg_split('/\r\n|\n|\r/', trim($rawHeader));
        foreach ($lines as $line) {
            if (!$line || strpos($line, ':') === false) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $headers[trim($key)] = trim($value);
        }
        return $headers;
    }

    /**
     * 解析错误消息
     * @param string $body
     * @param int $httpCode
     * @return string
     */
    protected function parseErrorMessage(string $body, int $httpCode): string
    {
        if ($body !== '') {
            if ($this->isXml && strpos(ltrim($body), '<') === 0) {
                $result = XML::parse($body);
                if (is_array($result)) {
                    if (!empty($result['Code']) && !empty($result['Message'])) {
                        return $result['Code'] . ': ' . $result['Message'];
                    }
                    if (!empty($result['Message'])) {
                        return (string)$result['Message'];
                    }
                }
            } elseif (!$this->isXml) {
                $result = json_decode($body, true);
                if (is_array($result)) {
                    if (!empty($result['message'])) {
                        return (string)$result['message'];
                    }
                    if (!empty($result['Message'])) {
                        return (string)$result['Message'];
                    }
                }
            }

            $body = strip_tags($body);
            if ($body !== '') {
                return $body;
            }
        }

        return 'JDOSS请求失败，HTTP状态码：' . $httpCode;
    }

    /**
     * 生成签名
     * @param string $region
     * @param string $url
     * @param string $httpMethod
     * @param array $header
     * @param array $data
     * @param string $service
     * @return string
     */
    protected function generateAwsSignatureV4(string $region, string $url, string $httpMethod, array $header, array $data = [], string $service = 's3')
    {
        $algorithm = self::ALGORITHM_REQUEST;
        $t = new \DateTime('UTC');
        $amzDate = $t->format('Ymd\THis\Z');
        $dateStamp = $t->format('Ymd');
        [$canonicalRequest, $signedHeaders] = $this->createCanonicalRequest($url, $httpMethod, $header, $data);
        $credentialScope = $dateStamp . '/' . $region . '/' . $service . '/aws4_request';
        $stringToSign = $algorithm . "\n" . $amzDate . "\n" . $credentialScope . "\n" . hash('sha256', $canonicalRequest);
        $signingKey = hash_hmac('sha256', 'aws4_request',
            hash_hmac('sha256', $service,
                hash_hmac('sha256', $region,
                    hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true),
                    true),
                true),
            true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        return $algorithm . ' Credential=' . $this->accessKeyId . '/' . $credentialScope . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;
    }

    /**
     * 生成规范请求
     * @param string $url
     * @param string $httpMethod
     * @param array $header
     * @param array $data
     * @return array
     */
    public function createCanonicalRequest(string $url, string $httpMethod, array $header, array $data = [])
    {
        $canonicalQueryString = '';
        $payload = '';
        if (!empty($data['query'])) {
            $query = $data['query'];
            ksort($query);
            $queryAttr = [];
            foreach ($query as $key => $item) {
                $queryAttr[rawurlencode((string)$key)] = rawurlencode((string)$item);
            }
            if ($queryAttr) {
                $canonicalQueryString = implode('&', array_map(function ($key, $value) {
                    return $key . '=' . $value;
                }, array_keys($queryAttr), $queryAttr));
            }
        } elseif (!empty($data['body'])) {
            $payload = (string)$data['body'];
        } elseif (!empty($data['json'])) {
            $payload = json_encode($data['json']);
        }

        $normalizedHeaders = [];
        foreach ($header as $key => $item) {
            $key = strtolower($key);
            if (isset(self::BLACKLIST_HEADERS[$key])) {
                continue;
            }
            $normalizedHeaders[$key] = preg_replace('/\s+/', ' ', trim((string)$item));
        }
        ksort($normalizedHeaders);

        $canonicalHeadersAttr = [];
        $signedHeadersAttr = [];
        foreach ($normalizedHeaders as $key => $item) {
            $canonicalHeadersAttr[] = $key . ':' . $item;
            $signedHeadersAttr[] = $key;
        }
        $canonicalHeaders = implode("\n", $canonicalHeadersAttr);
        $signedHeaders = implode(';', $signedHeadersAttr);
        $canonicalUri = $this->createCanonicalizedPath($url);
        $bodyDigest = $this->buildBodyDigest($header, (string)Utils::streamFor($payload));
        $canonicalRequest = $httpMethod . "\n" . $canonicalUri . "\n" . $canonicalQueryString . "\n" . $canonicalHeaders . "\n\n" . $signedHeaders . "\n" . $bodyDigest;
        return [$canonicalRequest, $signedHeaders];
    }

    /**
     * 生成规范路径
     * @param string $url
     * @return string
     */
    public function createCanonicalizedPath(string $url)
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        if ($path === '') {
            return '/';
        }
        $segments = explode('/', $path);
        $segments = array_map(function ($segment) {
            return rawurlencode($segment);
        }, $segments);
        $canonicalUri = implode('/', $segments);
        return $canonicalUri ?: '/';
    }

    /**
     * 处理 body hash
     * @param array $header
     * @param string $body
     * @return string
     */
    protected function buildBodyDigest(array $header, string $body = ''): string
    {
        if (isset($header['x-amz-content-sha256'])) {
            return $header['x-amz-content-sha256'];
        }
        if (isset($header['X-Amz-Content-Sha256'])) {
            return $header['X-Amz-Content-Sha256'];
        }
        return hash('sha256', $body);
    }

    /**
     * 处理 chunk body
     * @param string $body
     * @return string
     */
    public function dechunk(string $body): string
    {
        $h = fopen('php://temp', 'w+');
        stream_filter_append($h, 'dechunk', \STREAM_FILTER_WRITE);
        fwrite($h, $body);
        $body = stream_get_contents($h, -1, 0);
        rewind($h);
        ftruncate($h, 0);
        return $body;
    }

    /**
     * 获取区域
     * @return array
     */
    public function getRegion()
    {
        return [
            [
                'value' => 'cn-north-1',
                'label' => '华北-北京',
            ],
            [
                'value' => 'cn-south-1',
                'label' => '华南-广州',
            ],
            [
                'value' => 'cn-east-2',
                'label' => '华东-上海',
            ],
            [
                'value' => 'cn-east-1',
                'label' => '华东-宿迁',
            ]
        ];
    }
}
