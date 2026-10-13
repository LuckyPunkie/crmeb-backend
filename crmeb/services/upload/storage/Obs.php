<?php

namespace crmeb\services\upload\storage;

use crmeb\exceptions\AdminException;
use crmeb\exceptions\UploadException;
use crmeb\services\upload\extend\obs\Client as TyClient;
use crmeb\services\upload\BaseUpload;
use DateTimeInterface;
use GuzzleHttp\Psr7\Utils;
use think\exception\ValidateException;

class Obs extends BaseUpload
{
    /**
     * accessKey
     * @var mixed
     */
    protected $accessKey;

    /**
     * secretKey
     * @var mixed
     */
    protected $secretKey;

    /**
     * 句柄
     * @var TyClient
     */
    protected $handle;

    /**
     * 空间域名 Domain
     * @var mixed
     */
    protected $uploadUrl;

    /**
     * 存储空间名称  公开空间
     * @var mixed
     */
    protected $storageName;

    /**
     * COS使用  所属地域
     * @var mixed|null
     */
    protected $storageRegion;

    /**
     * @var string
     */
    protected $cdn;


    /**
     * 水印位置
     * @var string[]
     */
    protected $position = [
        '1' => 'tl',//：左上
        '2' => 'top',//：中上
        '3' => 'tr',//：右上
        '4' => 'left',//：左中
        '5' => 'center',//：中部
        '6' => 'right',//：右中
        '7' => 'bl',//：左下
        '8' => 'bottom',//：中下
        '9' => 'br',//：右下
    ];

    /**
     * 上传图片
     * @param string $file
     * @param bool $isStream
     * @param string|null $fileContent
     * @return array|bool|mixed
     */
    public function move(string $file = 'file', bool $isStream = false, string $fileContent = null)
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
            $body = $fileContent;
        }
        $key = $this->getUploadPath($key);

        try {
            $uploadInfo = $this->app()->putObject($key, $body, 'application/octet-stream');
            $this->fileInfo->uploadInfo = $uploadInfo;
            $this->fileInfo->realName = isset($fileHandle) ? $fileHandle->getOriginalName() : $key;
            $this->fileInfo->filePath = ($this->cdn ?: $this->uploadUrl) . '/' . $key;
            $this->fileInfo->fileName = $key;
            $isImage = isset($fileHandle) ? $this->isImageFileHandle($fileHandle) : $this->isImageByPath($key);
            return $this->afterUpload($isImage, $this->fileInfo->filePath, $this->fileInfo->fileName);
        } catch (\Throwable $e) {
            return $this->setError($e->getMessage());
        }
    }

    public function stream($fileContent, string $key = null)
    {
        if (!$key) {
            $key = $this->saveFileName();
        }
        return $this->move($key, true, $fileContent);
    }

    public function delete(string $filePath)
    {
        try {
            return $this->app()->deleteObject($filePath);
        } catch (\Exception $e) {
            return $this->setError($e->getMessage());
        }
    }

    /**
     * 初始化
     * @param array $config
     * @return mixed|void
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
        $this->waterConfig['watermark_text_font'] = 'simfang仿宋.ttf';
    }

    /**
     * 实例化cos
     * @return TyClient
     */
    protected function app()
    {
        if (!$this->accessKey || !$this->secretKey) {
            throw new UploadException('请填写存储配置或者更换存储方式');
        }
        $this->handle = new TyClient([
            'accessKey' => $this->accessKey,
            'secretKey' => $this->secretKey,
            'region' => $this->storageRegion ?: 'cn-north-1',
            'bucket' => $this->storageName,
            'uploadUrl' => $this->uploadUrl
        ]);
        return $this->handle;
    }

    public function listbuckets(string $region = null, bool $line = false, bool $shared = false)
    {
        try {
            $res = $this->app()->listBuckets();
            return $res['Buckets']['Bucket'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function createBucket(string $name, string $region, string $acl = TyClient::DEFAULT_OBS_ACL)
    {
        $regionData = $this->getRegion();
        $regionData = array_column($regionData, 'value');
        if (!in_array($region, $regionData)) {
            return $this->setError('COS:无效的区域!');
        }
        $this->storageRegion = $region;
        $app = $this->app();
        //创建桶
        try {
            $app->createBucket($name, $region, $acl);
            $data = [
                'Statement' => [
                    'Sid' => '公共读' . $name,
                    'Effect' => 'Allow',
                    'Principal' => [
                        'ID' => ['*']
                    ],
                    'Action' => ['HeadBucket', 'GetBucketLocation', 'ListBucketVersions', 'GetObject', 'RestoreObject', 'GetObjectVersion'],
                    'Resource' => [$name, $name . '/*']
                ]
            ];

            $app->putPolicy($name, $region, $data);
        } catch (\Throwable $e) {
            return $this->setError('COS:' . $e->getMessage());
        }
        return true;
    }

    public function getRegion()
    {
        return $this->app()->getRegion();
    }

    public function deleteBucket(string $name, string $region = '')
    {
        try {
            $this->app()->deleteBucket($name, $region);
            return true;
        } catch (\Throwable $e) {
            return $this->setError($e->getMessage());
        }
    }

    public function getDomian($name, $region)
    {
        try {
            $res = $this->app()->getBucketDomain($name, $region);
            if ($res) {
                $domainRules = $res->toArray()['ListBucketCustomDomainsResult'];
                return array_column($domainRules, 'DomainName');
            } else {
                return [];
            }

        } catch (\Throwable $e) {
        }
        return [];
    }


    public function bindDomian(string $name, string $domain, string $region = null)
    {
        $parseDomin = parse_url($domain);
        try {
            $this->app()->putBucketDomain($name, $region, [
                'domainname' => $parseDomin['host'],
            ]);
            return true;
        } catch (\Throwable $e) {
            return $this->setError($e->getMessage());
        }
    }

    public function setBucketCors(string $name, string $region)
    {
        try {
            $this->app()->putBucketCors($name, $region, [
                'AllowedHeader' => ['*'],
                'AllowedMethod' => ['PUT', 'GET', 'POST', 'DELETE', 'HEAD'],
                'AllowedOrigin' => ['*'],
                'ExposeHeader' => ['ETag'],
                'MaxAgeSeconds' => 0
            ]);
            return true;
        } catch (\Throwable $e) {
            return $this->setError($e->getMessage());
        }
    }

    /**
     * @return array
     * @date 2023/6/13
     */
    public function getTempKeys($callbackUrl = '', $dir = '')
    {
//        return [
//            'access_key' => $this->accessKey,
//            'secret_key' => $this->secretKey,
//            'type' => 'OBS'
//        ];
        // TODO: Implement getTempKeys() method.
        $base64CallbackBody = base64_encode(json_encode([
            'callbackUrl' => $callbackUrl,
            'callbackBody' => 'filename=${object}&size=${size}&mimeType=${mimeType}&height=${imageInfo.height}&width=${imageInfo.width}',
            'callbackBodyType' => "application/x-www-form-urlencoded"
        ]));

        $policy = json_encode([
            'expiration' => $this->gmtIso8601(time() + 300),
            'conditions' =>
                [
                    [0 => 'content-length-range', 1 => 0, 2 => 1048576000],
                    ['bucket' => $this->storageName],
                    [0 => 'starts-with', 1 => '$key', 2 => $dir],
                ]
        ]);
        $base64Policy = base64_encode($policy);
        $signature = base64_encode(hash_hmac('sha1', $base64Policy, $this->secretKey, true));
        return [
            'accessid' => $this->accessKey,
            'host' => $this->uploadUrl,
            'policy' => $base64Policy,
            'signature' => $signature,
            'expire' => time() + 30,
            'callback' => $base64CallbackBody,
            'cdn' => $this->cdn,
            'type' => 'OBS'
        ];
    }

    /**
     * 获取ISO时间格式
     * @param $time
     * @return string
     * @throws \Exception
     */
    protected function gmtIso8601($time): string
    {
        $dtStr = date("c", $time);
        $myDateTime = new \DateTime($dtStr);
        $expiration = $myDateTime->format(DateTimeInterface::ISO8601);
        $pos = strpos($expiration, '+');
        $expiration = substr($expiration, 0, $pos);
        return $expiration . "Z";
    }

    /**
     * 缩略图
     * @param string $filePath
     * @param string $fileName
     * @param string $type
     * @return array|mixed
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
//                    if (systemConfig('image_thumbnail_status') && isset($config[$height]) && isset($config[$width])) {
                    if (isset($config[$height]) && isset($config[$width])) {
                        if (!strpos($filePath, '?x-oss-process')) {
                            $this->fileInfo->$key = $filePath . '?x-oss-process=image/resize,h_' . $config[$height] . ',w_' . $config[$width];
                        }
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
     * @return mixed|string
     */
    public function water(string $filePath = '')
    {
        $filePath = $this->getFilePath($filePath);
        $waterConfig = $this->waterConfig;
        $waterPath = $filePath;
        if ($waterConfig['image_watermark_status'] && $filePath) {
            if (strpos($filePath, '?x-oss-process') === false) {
                $filePath .= ',x-image-process=image';
            }
            switch ($waterConfig['watermark_type']) {
                case 1://图片
                    if (!$waterConfig['watermark_image']) {
                        throw new AdminException(400722);
                    }
                    // 华为入参格式：filename.jpg
                    $waterFileName = substr(parse_url($waterConfig['watermark_image'],PHP_URL_PATH), 1);
                    $waterPath = $filePath .= '/watermark,image_' . base64_encode($waterFileName) . ',t_' . $waterConfig['watermark_opacity'] . ',g_' . ($this->position[$waterConfig['watermark_position']] ?? 'nw') . ',x_' . $waterConfig['watermark_x'] . ',y_' . $waterConfig['watermark_y'];
                    break;
                case 2://文字
                    if (!$waterConfig['watermark_text']) {
                        throw new AdminException(400723);
                    }
                    $waterConfig['watermark_text_color'] = str_replace('#', '', $waterConfig['watermark_text_color']);
                    $waterPath = $filePath .= '/watermark,text_' . base64_encode($waterConfig['watermark_text']) . ',color_' . $waterConfig['watermark_text_color'] . ',size_' . $waterConfig['watermark_text_size'] . ',g_' . ($this->position[$waterConfig['watermark_position']] ?? 'nw') . ',x_' . $waterConfig['watermark_x'] . ',y_' . $waterConfig['watermark_y'];
                    break;
            }
        }
        return $waterPath;
    }
}
