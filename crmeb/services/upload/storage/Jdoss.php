<?php

namespace crmeb\services\upload\storage;

use crmeb\exceptions\AdminException;
use crmeb\exceptions\UploadException;
use crmeb\services\upload\BaseUpload;
use GuzzleHttp\Psr7\Utils;

/**
 * 京东云 COS 文件上传
 * Class Jdoss
 * @package crmeb\services\upload\storage
 */
class Jdoss extends BaseUpload
{
    /**
     * accessKey
     * @var string|null
     */
    protected $accessKey;

    /**
     * secretKey
     * @var string|null
     */
    protected $secretKey;

    /**
     * 句柄
     * @var \crmeb\services\upload\extend\jdoss\Client
     */
    protected $handle;

    /**
     * 空间域名
     * @var string|null
     */
    protected $uploadUrl;

    /**
     * 存储空间名称
     * @var string|null
     */
    protected $storageName;

    /**
     * 所属地域
     * @var string|null
     */
    protected $storageRegion;

    /**
     * CDN 加速域名
     * @var string|null
     */
    protected $cdn;

    /**
     * 当桶不存在时是否自动创建
     * @var bool
     */
    protected $autoCreateBucket = false;

    /**
     * 水印位置映射
     * @var string[]
     */
    protected $position = [
        '1' => 'nw',    // 左上
        '2' => 'north', // 中上
        '3' => 'ne',    // 右上
        '4' => 'west',  // 左中
        '5' => 'center',// 中部
        '6' => 'east',  // 右中
        '7' => 'sw',    // 左下
        '8' => 'south', // 中下
        '9' => 'se',    // 右下
    ];

    /**
     * 初始化
     * @param array $config
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);
        $this->accessKey = $config['accessKey'] ?? null;
        $this->secretKey = $config['secretKey'] ?? null;
        $this->uploadUrl = $this->checkUploadUrl($config['uploadUrl'] ?? '');
        $this->storageName = $config['storageName'] ?? null;
        $this->storageRegion = $config['storageRegion'] ?? null;
        $this->cdn = $config['cdn'] ?? null;
        $this->autoCreateBucket = (bool)($config['autoCreateBucket'] ?? false);
        $this->waterConfig['watermark_text_font'] = 'simfang仿宋.ttf';
    }

    /**
     * 客户端实例
     * @return \crmeb\services\upload\extend\jdoss\Client
     */
    protected function app()
    {
        if (!$this->accessKey || !$this->secretKey) {
            throw new UploadException('请填写存储配置或者更换存储方式');
        }
        $this->handle = new \crmeb\services\upload\extend\jdoss\Client([
            'accessKey' => $this->accessKey,
            'secretKey' => $this->secretKey,
            'region' => $this->storageRegion ?: 'cn-north-1',
            'bucket' => $this->storageName,
            'uploadUrl' => $this->uploadUrl,
        ]);
        return $this->handle;
    }

    /**
     * 通用上传
     * @param string|null $file
     * @param bool $isStream
     * @param string|null $fileContent
     * @return array|bool|\StdClass
     */
    protected function upload(string $file = null, bool $isStream = false, string $fileContent = null)
    {
        if (!$isStream) {
            $fileHandle = app()->request->file($file);
            if (!$fileHandle) {
                return $this->setError('上传的文件不存在');
            }
            $valid = $this->validateFileHandle($fileHandle);
            if ($valid !== true) {
                return $this->setError($valid);
            }
            $key = $this->saveFileName($fileHandle->getRealPath(), $fileHandle->getOriginalExtension());
            $body = fopen($fileHandle->getRealPath(), 'rb');
            $body = (string)Utils::streamFor($body);
        } else {
            $key = $file;
            $body = $fileContent ?? '';
        }

        try {
            // 先校验桶是否存在，避免 NoSuchBucket
            if (!$this->storageName) {
                return $this->setError('JDOSS: 未配置存储桶名称（storageName），请先在后台创建并启用京东云空间，或传入 storageName/bucket/name 配置');
            }
            $region = $this->storageRegion ?: 'cn-north-1';
            try {
                $this->app()->headBucket($this->storageName, $region);
            } catch (\Throwable $e) {
                $msg = (string)$e->getMessage();
                if (stripos($msg, 'NoSuchBucket') !== false || stripos($msg, '404') !== false) {
                    if ($this->autoCreateBucket) {
                        try {
                            $this->app()->createBucket($this->storageName, $region, 'public-read');
                        } catch (\Throwable $ce) {
                            return $this->setError('JDOSS: 存储桶不存在且创建失败 - ' . $ce->getMessage());
                        }
                    } else {
                        return $this->setError('JDOSS: 存储桶不存在 - ' . $this->storageName . '（region: ' . $region . '）。可在配置开启 autoCreateBucket 自动创建，或先调用 createBucket。');
                    }
                }
            }

            $key = $this->getUploadPath($key);
            $uploadInfo = $this->app()->putObject($this->storageName, $region, $key, [
                'body' => $body,
                'timeout' => 30,
            ]);
            $this->fileInfo->uploadInfo = $uploadInfo;
            $this->fileInfo->filePath = ($this->cdn ?: $this->uploadUrl) . '/' . $key;
            $this->fileInfo->realName = isset($fileHandle) ? $fileHandle->getOriginalName() : $key;
            $this->fileInfo->fileName = $key;

            $isImage = isset($fileHandle) ? $this->isImageFileHandle($fileHandle) : $this->isImageByPath($key);
            return $this->afterUpload($isImage, $this->fileInfo->filePath, $this->fileInfo->fileName);
        } catch (UploadException $e) {
            return $this->setError($e->getMessage());
        } catch (\Throwable $e) {
            return $this->setError($e->getMessage());
        }
    }

    /**
     * 文件流上传
     * @param mixed $fileContent
     * @param string|null $key
     * @return array|bool|mixed|\StdClass
     */
    public function stream($fileContent, string $key = null)
    {
        if (!$key) {
            $key = $this->saveFileName();
        }
        return $this->upload($key, true, (string)Utils::streamFor($fileContent));
    }

    /**
     * 文件上传
     * @param string $file
     * @param bool $realName
     * @return array|bool|mixed|\StdClass
     */
    public function move(string $file = 'file', $realName = false)
    {
        return $this->upload($file);
    }

    /**
     * 删除文件
     * @param string $filePath
     * @return mixed
     */
    public function delete(string $filePath)
    {
        try {
            return $this->app()->deleteObject($this->storageName, $this->storageRegion ?: 'cn-north-1', $filePath);
        } catch (\Throwable $e) {
            return $this->setError($e->getMessage());
        }
    }

    /**
     * 桶列表
     * @param string $region
     * @param bool $line
     * @param bool $shared
     * @return array|mixed
     */
    public function listbuckets(string $region = 'cn-north-1', bool $line = false, bool $shared = false)
    {
        try {
            $res = $this->app()->listBuckets();
            return $res['Buckets']['Bucket'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 创建桶
     * @param string $name
     * @param string $region
     * @param string $acl
     * @return array|bool|mixed
     */
    public function createBucket(string $name, string $region = 'cn-north-1', string $acl = 'public-read')
    {
        $regionData = array_column($this->getRegion(), 'value');
        if (!in_array($region, $regionData, true)) {
            return $this->setError('JDOSS:无效的区域!');
        }
        $this->storageRegion = $region;
        $app = $this->app();
        try {
            $app->headBucket($name, $region);
        } catch (\Throwable $e) {
            if (strstr($e->getMessage(), '404') !== false) {
                return $this->setError('JDOSS:' . $e->getMessage());
            }
        }
        try {
            return $app->createBucket($name, $region, $acl);
        } catch (\Throwable $e) {
            return $this->setError('JDOSS:' . $e->getMessage());
        }
    }

    /**
     * 删除桶
     * @param string $name
     * @param string $region
     * @return bool
     */
    public function deleteBucket(string $name, string $region = '')
    {
        try {
            $this->storageRegion = $region ?: ($this->storageRegion ?: 'cn-north-1');
            $this->app()->deleteBucket($name, $this->storageRegion);
            return true;
        } catch (\Throwable $e) {
            return $this->setError($e->getMessage());
        }
    }

    /**
     * 区域
     * @return array
     */
    public function getRegion()
    {
        return [
            ['value' => 'cn-north-1', 'label' => '华北-北京'],
            ['value' => 'cn-east-1',  'label' => '华东-宿迁'],
            ['value' => 'cn-east-2',  'label' => '华东-上海'],
            ['value' => 'cn-south-1', 'label' => '华南-广州'],
        ];
    }

    /**
     * 获取自定义域名
     * @param string $name
     * @param string|null $region
     * @return array|mixed
     */
    public function getDomian(string $name, string $region = null)
    {
        // 兼容实现：返回当前可用域名来源（优先 CDN，其次上传域名）
        $domains = [];
        if ($this->cdn) {
            $parse = parse_url($this->cdn);
            if (!empty($parse['host'])) {
                $domains[] = $parse['host'];
            } elseif (!empty($parse['path'])) {
                $domains[] = ltrim($parse['path'], '/');
            } else {
                $domains[] = $this->cdn;
            }
        }
        if ($this->uploadUrl) {
            $parse = parse_url($this->uploadUrl);
            if (!empty($parse['host'])) {
                $domains[] = $parse['host'];
            } elseif (!empty($parse['path'])) {
                $domains[] = ltrim($parse['path'], '/');
            } else {
                $domains[] = $this->uploadUrl;
            }
        }
        // 去重
        $domains = array_values(array_unique(array_filter($domains)));
        return $domains;
    }

    /**
     * 绑定自定义域名（兼容：仅在运行期设置为 CDN，不调用远端 API）
     * @param string $name
     * @param string $domain
     * @param string|null $region
     * @return bool|mixed
     */
    public function bindDomian(string $name, string $domain, string $region = null)
    {
        $parsed = parse_url($domain);
        // 校验域名
        $host = $parsed['host'] ?? null;
        if (!$host) {
            // 可能传入的是纯主机名，直接使用
            if (preg_match('/^([a-z0-9-]+\.)+[a-z]{2,}$/i', $domain)) {
                $host = $domain;
            }
        }
        if (!$host) {
            return $this->setError('JDOSS: 无效的域名');
        }
        // 设置为 CDN 域名（下次返回 URL 时优先使用）
        $this->cdn = isset($parsed['scheme']) ? ($parsed['scheme'] . '://' . $host) : ('http://' . $host);
        return true;
    }

    /**
     * 设置跨域
     * @param string $name
     * @param string $region
     * @return bool
     */
    public function setBucketCors(string $name, string $region)
    {
        $this->storageRegion = $region ?: ($this->storageRegion ?: 'cn-north-1');
        try {
            $this->app()->putBucketCors($name, $this->storageRegion, [
                'CORSConfiguration' => [
                    'CORSRules' => [[
                        'AllowedHeaders' => ['*'],
                        'AllowedMethods' => ['POST', 'GET', 'PUT', 'DELETE', 'HEAD'],
                        'AllowedOrigins' => ['*'],
                        'ExposeHeaders' => ['Etag'],
                        'MaxAgeSeconds' => 0,
                    ]],
                ],
            ]);
            return true;
        } catch (\Throwable $e) {
            return $this->setError($e->getMessage());
        }
    }

    /**
     * 生成直传参数（简化版）
     * @param string $key
     * @param string $path
     * @param string $contentType
     * @param string $expires
     * @return array|mixed
     */
    public function getTempKeys($key = '', $path = '', $contentType = '', $expires = '+10 minutes')
    {
        try {
            $app = $this->app();
            $host = $app->getRequestUrl($this->storageName, $this->storageRegion ?: 'cn-north-1');
            $url = 'https://' . $host . '/' . $key;
            $params = $this->getTempKeysParam($host, $url);
            $query = [];
            foreach ($params as $k => $v) {
                $query[] = $k . '=' . $v;
            }
            return [
                'upload_url' => $url . '?' . implode('&', $query),
                'type' => 'JDOSS',
                'url' => ($this->cdn ?: $this->uploadUrl) . '/' . $key,
                'cdn' => $this->cdn,
                'bucket' => $this->storageName,
                'region' => $this->storageRegion ?: 'cn-north-1',
            ];
        } catch (\Throwable $e) {
            return $this->setError($e->getMessage());
        }
    }

    /**
     * 获取直传签名参数
     * @param string $host
     * @param string $url
     * @return array
     */
    public function getTempKeysParam(string $host, string $url)
    {
        $amzDate = gmdate('Ymd\THis\Z');
        $sdt = substr($amzDate, 0, 8);
        $credentialScope = $sdt . '/' . ($this->storageRegion ?: 'cn-north-1') . '/' . 's3' . '/aws4_request';
        $clientHeader = [
            'Host' => $host,
            'X-Amz-Content-Sha256' => 'UNSIGNED-PAYLOAD',
            'X-Amz-Date' => $amzDate,
        ];
        $param = [
            'X-Amz-Content-Sha256' => 'UNSIGNED-PAYLOAD',
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->accessKey . '/' . $credentialScope,
            'X-Amz-Date' => $amzDate,
            'X-Amz-SignedHeaders' => 'host;X-Amz-Content-Sha25;X-Amz-Date',
            'X-Amz-Expires' => 600,
        ];
        [$canonicalRequest, $signedHeaders] = $this->app()->createCanonicalRequest($url, 'GET', $clientHeader, ['query' => $param]);
        $stringToSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $credentialScope . "\n" . hash('sha256', $canonicalRequest);
        $key = $this->getSigningKey($sdt, ($this->storageRegion ?: 'cn-north-1'), 's3', $this->secretKey);
        $signature = hash_hmac('sha256', $stringToSign, $key);
        $param['X-Amz-Signature'] = $signature;
        return $param;
    }

    /**
     * 生成签名 key
     * @param string $shortDate
     * @param string $region
     * @param string $service
     * @param string $secretKey
     * @return string
     */
    public function getSigningKey($shortDate, $region, $service, $secretKey)
    {
        $kSecret = 'AWS4' . $secretKey;
        $kDate = hash_hmac('sha256', $shortDate, $kSecret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    /**
     * 缩略图
     * @param string $filePath
     * @param string $fileName
     * @param string $type
     * @return array
     */
    public function thumb(string $filePath = '', string $fileName = '', string $type = 'all')
    {
        $filePath = $this->getFilePath($filePath);
        $data = ['big' => $filePath, 'mid' => $filePath, 'small' => $filePath];
        $this->fileInfo->filePathBig = $this->fileInfo->filePathMid = $this->fileInfo->filePathSmall = $this->fileInfo->filePathWater = $filePath;
        if ($filePath) {
            $config = $this->thumbConfig;
            foreach ($this->thumb as $v) {
                if ($type == 'all' || $type == $v) {
                    $height = 'thumb_' . $v . '_height';
                    $width = 'thumb_' . $v . '_width';
                    $key = 'filePath' . ucfirst($v);
                    if (isset($config[$height]) && isset($config[$width]) && $config[$height] && $config[$width]) {
                        $this->fileInfo->$key = $filePath . '?x-oss-process=img/s' . $config[$width] . '/' . $config[$height];
                        $this->fileInfo->$key = $this->water($this->fileInfo->$key);
                        $data[$v] = $this->fileInfo->$key;
                    } else {
                        $this->fileInfo->$key = $this->water($this->fileInfo->$key);
                        $data[$v] = $this->fileInfo->$key;
                    }
                }
            }
        }
        return $data;
    }

    /**
     * 水印
     * @param string $filePath
     * @return string
     */
    public function water(string $filePath = '')
    {
        $filePath = $this->getFilePath($filePath);
        $waterConfig = $this->waterConfig;
        $waterPath = $filePath;
        if ($waterConfig['image_watermark_status'] && $filePath) {
            if (strpos($filePath, '?x-oss-process') === false) {
                $filePath .= '?x-oss-process=img';
            }
            switch ($waterConfig['watermark_type']) {
                case 1: // 图片
                    if (!$waterConfig['watermark_image']) {
                        throw new AdminException('请先配置水印图片');
                    }
                    $waterPath = $filePath .= '/wmi/wk/' . base64_encode($waterConfig['watermark_image']) . '/wd/' . $waterConfig['watermark_opacity'] . '/wp/' . ($this->position[$waterConfig['watermark_position']] ?? 'nw') . '/wdx/' . $waterConfig['watermark_x'] . '/wdy/' . $waterConfig['watermark_y'];
                    break;
                case 2: // 文字
                    if (!$waterConfig['watermark_text']) {
                        throw new AdminException('请先配置水印文字');
                    }
                    $waterConfig['watermark_text_color'] = str_replace('#', '', $waterConfig['watermark_text_color']);
                    $waterPath = $filePath .= '/wmt/wt/' . base64_encode($waterConfig['watermark_text']) . '/wc/' . $waterConfig['watermark_text_color'] . '/ws/' . $waterConfig['watermark_text_size'] . '/wp/' . ($this->position[$waterConfig['watermark_position']] ?? 'nw') . '/wdx/' . $waterConfig['watermark_x'] . '/wdy/' . $waterConfig['watermark_y'] . '/wr/' . $waterConfig['watermark_text_angle'];
                    break;
            }
        }
        return $waterPath;
    }

    /**
     * 获取视频封面图
     * @param string $filePath
     * @param string $type
     * @param int $time
     * @return array
     */
    public function videoCoverImage(string $filePath = '', string $type = 'all', int $time = 1)
    {
        $data = ['big' => $filePath, 'mid' => $filePath, 'small' => $filePath];
        $this->fileInfo->filePathBig = $this->fileInfo->filePathMid = $this->fileInfo->filePathSmall = $this->fileInfo->filePathWater = $filePath;
        if ($filePath) {
            foreach ($this->thumb as $v) {
                if ($type == 'all' || $type == $v) {
                    $height = 600;
                    $width = 400;
                    $key = 'filePath' . ucfirst($v);
                    $this->fileInfo->$key = $filePath . '?x-oss-process=video/snapshot,t_' . ($time * 1000) . ',f_jpg,w_' . $width . ',h_' . $height . ',m_fast';
                    $data[$v] = $this->fileInfo->$key;
                }
            }
        }
        return $data;
    }
}
