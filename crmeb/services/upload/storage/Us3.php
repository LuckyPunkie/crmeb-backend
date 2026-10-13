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


namespace crmeb\services\upload\storage;

use crmeb\exceptions\AdminException;
use crmeb\services\upload\BaseUpload;
use crmeb\exceptions\UploadException;
use think\exception\ValidateException;
use UCloud\Storage\UploadManager as UcloudUploadManager;
use UCloud\Auth;
use UCloud\UFile\Apis\DeleteBucketRequest;
use UCloud\UFile\Apis\DescribeBucketRequest;
use UCloud\UFile\UFileClient;
use UCloud\UFile\Apis\CreateBucketRequest;
use UCloud\Core\Logger\DisabledLogger;

/**
 * Ucloud us3
 * Class Us3
 */
class Us3 extends BaseUpload
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
     * @var object
     */
    public $handle;

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
     * cdn 域名
     * @var
     */
    protected $cdn;

    /**
     *  缩略图开关
     * @var mixed|null
     */
    protected $thumb_status;

    /**
     * 缩略图比例
     * @var mixed|null
     */
    protected $thumb_rate;


    /**
     * 水印位置
     * @var string[]
     */
    protected $position = [
        '1' => 'NorthWest',//：左上
        '2' => 'North',//：中上
        '3' => 'NorthEast',//：右上
        '4' => 'West',//：左中
        '5' => 'Center',//：中部
        '6' => 'East',//：右中
        '7' => 'SouthWest',//：左下
        '8' => 'South',//：中下
        '9' => 'SouthEast',//：右下
    ];
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
        $this->storageName = $config['storageName'] ?? null;
        $this->storageRegion = $config['storageRegion'] ?? null;
        $this->cdn = $config['cdn'] ?? null;
        if(isset($config['uploadUrl']) && $config['uploadUrl']){
            $host = parse_url($config['uploadUrl']);
            $this->uploadUrl = substr($host['host'], strlen($config['storageName']));
        }

    }

    /**
     * 实例化
     * @return object|Auth
     */
    public function app()
    {
        if (!$this->accessKey || !$this->secretKey) {
            throw new UploadException('请填写存储配置或者更换存储方式');
        }
        if (!$this->handle) {
            $this->handle = new Auth($this->accessKey, $this->secretKey, $this->uploadUrl);
        }

        return $this->handle;
    }


    /**
     * @param string $name 创建Bucket的名称
     * @param string $region 地域
     * @param string $type Bucket访问类型，public或private; 默认为private
     * @return array
     */
    public function createBucket(string $name, string $region, string $type = 'public')
    {
        try {
            $uFileClient = new UFileClient([
                'publicKey' => $this->accessKey,
                'privateKey' => $this->secretKey,
                'logger' => new DisabledLogger()
            ]);
            $type = $type == 'public-read' || $type == 'public-read-write' ? 'public' : 'private';//适配US3入参
            $createBucketRequest = new CreateBucketRequest();
            $createBucketRequest->setBucketName($name);
            $createBucketRequest->setRegion($region);
            $createBucketRequest->setType($type);
            return $uFileClient->createBucket($createBucketRequest)->toArray();
        } catch (\Exception $e) {
            throw new UploadException($e->getMessage());
        }
    }

    /**
     * 获取区域列表
     * @return array
     */
    public function getRegion()
    {
        try {
            return [
                [
                    'value' => 'cn-bj2',
                    'label' => '华北一',
                ],
                [
                    'value' => 'cn-gd',
                    'label' => '广州'
                ],
                [
                    'value' => 'cn-qz',
                    'label' => '福建'
                ],
                [
                    'value' => 'cn-sh2',
                    'label' => '上海二'
                ],
                [
                    'value' => 'cn-wlcb',
                    'label' => '华北二'
                ],
                [
                    'value' => 'afr-nigeria',
                    'label' => '拉各斯'
                ],
                [
                    'value' => 'bra-saopaulo',
                    'label' => '圣保罗'
                ],
                [
                    'value' => 'ge-fra',
                    'label' => '法兰克福'
                ],
                [
                    'value' => 'hk',
                    'label' => '香港'
                ],
                [
                    'value' => 'idn-jakarta',
                    'label' => '雅加达'
                ],
                [
                    'value' => 'ind-mumbai',
                    'label' => '孟买'
                ],
                [
                    'value' => 'jpn-tky',
                    'label' => '东京'
                ],
                [
                    'value' => 'kr-seoul',
                    'label' => '首尔'
                ],
                [
                    'value' => 'ph-mnl',
                    'label' => '马尼拉'
                ],
                [
                    'value' => 'sg',
                    'label' => '新加坡'
                ],
                [
                    'value' => 'th-bkk',
                    'label' => '曼谷'
                ],
                [
                    'value' => 'tw-tp',
                    'label' => '台北'
                ],
                [
                    'value' => 'uae-dubai',
                    'label' => '迪拜'
                ],
                [
                    'value' => 'uk-london',
                    'label' => '伦敦'
                ],
                [
                    'value' => 'us-ca',
                    'label' => '洛杉矶'
                ],
                [
                    'value' => 'us-ws',
                    'label' => '华盛顿'
                ],
                [
                    'value' => 'vn-sng',
                    'label' => '胡志明市'
                ]
            ];
//            接口调用获取region 暂未返回中文
//            $accountClient = new UAccountClient([
//                'publicKey' => $this->accessKey,
//                'privateKey' => $this->secretKey,
//                'logger' => new DisabledLogger()
//            ]);
//            $result = $accountClient->getRegion(new GetRegionRequest())->toArray();
//            $list = [];
//            if(isset($result['Regions']) && !empty($result['Regions'])){
//                foreach ($result['Regions'] as $item){
//                    $list[] = ['lable'=>$item['RegionName'],'value'=>$item['Region']];
//                }
//            }

        } catch (\Exception $e) {
            throw new UploadException($e->getMessage());
        }
    }

    /**
     * 获取空间列表
     * @return array
     */
    public function listbuckets(string $region = null, bool $line = false, bool $shared = false): array
    {
        $uFileClient = new UFileClient([
            'publicKey' => $this->accessKey,
            'privateKey' => $this->secretKey,
            'logger' => new DisabledLogger()
        ]);
        $result = $uFileClient->describeBucket(new DescribeBucketRequest())->toArray();
        if (!isset($result['RetCode']) || $result['RetCode'] !== 0) {
            throw new UploadException('获取Bucket列表失败');
        }
        return $result['DataSet'];
    }

    /**
     * 删除存储空间
     * @param string $name
     * @param string $region
     * @return bool
     */
    public function deleteBucket(string $name, string $region = '')
    {
        try {
            $uFileClient = new UFileClient([
                'publicKey' => $this->accessKey,
                'privateKey' => $this->secretKey,
                'logger' => new DisabledLogger()
            ]);
            $deleteBucketRequest = new DeleteBucketRequest();
            $deleteBucketRequest->setBucketName($name);
            $result = $uFileClient->deleteBucket($deleteBucketRequest)->toArray();
            if (!isset($result['RetCode']) || $result['RetCode'] !== 0) {
                throw new UploadException('删除Bucket失败');
            }
            return true;
        } catch (\Throwable $e) {
            throw new UploadException($e->getMessage() ?? '删除失败');
        }
    }

    /**
     * 修改域名 暂无
     * @return void
     */
    public function updateDomain()
    {

    }

    /**
     * 绑定自定义域名
     * @param string $name
     * @param string $domain
     * @param string|null $region
     * @return mixed
     */
    public function bindDomian(string $name, string $domain, string $region = null)
    {

    }

    /**
     * 上传文件
     * @param string $file
     * @return array|bool|mixed|\StdClass|string
     */
    public function move(string $file = 'file')
    {
        $fileHandle = app()->request->file($file);

        if (!$fileHandle) {
            return $this->setError('上传的文件不存在');
        }
        $valid = $this->validateFileHandle($fileHandle);
        if ($valid !== true) {
            return $this->setError($valid);
        }
        $key = $this->saveFileName($fileHandle->getRealPath(), $fileHandle->getOriginalExtension());
        $path = ($this->path ? trim($this->path, '/') . '/' : '');
        try {
            $uploadMgr = new UcloudUploadManager($this->app());
            [$result, $error] = $uploadMgr->PutFile($this->storageName, $path . $key, $fileHandle->getRealPath());
            if ($error !== null) {
                return $this->setError($error->ErrMsg);
            }

            if ($this->cdn) {
                $src = $this->cdn . $path . $key;
            } else {
                $src = 'https://' . $uploadMgr->MakePublicUrl($this->storageName, $path . $key);
            }

            $this->fileInfo->uploadInfo = $result;
            $this->fileInfo->realName = $fileHandle->getOriginalName();
            $this->fileInfo->filePath = $src;
            $this->fileInfo->fileName = $key;
            return $this->afterUpload($this->isImageFileHandle($fileHandle), $this->fileInfo->filePath, $this->fileInfo->fileName);
        } catch (UploadException $e) {
            return $this->setError($e->getMessage());
        }
    }

    /**
     * 文件流上传
     * @param string $fileContent
     * @param string|null $key
     * @return array|bool|mixed|\StdClass
     */
    public function stream($fileContent, string $key = null)
    {
        $file = sys_get_temp_dir() . $key;
        file_put_contents($file, $fileContent, true);
        if (!$key) {
            $key = $this->saveFileName();
        }
        $path = ($this->path ? trim($this->path, '/') . '/' : '');


        try {
            $uploadMgr = new UcloudUploadManager($this->app());
            list($result, $err) = $uploadMgr->MultipartForm($this->storageName, $path . $key, $file);
            unlink($file);
            if ($err !== null) {
                // return array(null, $err);
                return $this->setError($err->ErrMsg);
            }
            if ($this->cdn) {
                $src = $this->cdn . '/' . $path . $key;
            } else {
                $src = 'https://' . $uploadMgr->MakePublicUrl($this->storageName, $path . $key);
            }

            $this->fileInfo->uploadInfo = $result;
            $this->fileInfo->realName = $key;
            $this->fileInfo->filePath = $src;
            $this->fileInfo->fileName = $key;
            return $this->afterUpload($this->isImageByPath($key), $this->fileInfo->filePath, $this->fileInfo->fileName);
        } catch (UploadException $e) {
            return $this->setError($e->getMessage());
        }
    }

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
                        if (!strpos($filePath, '?iopcmd=thumbnail')) {
                            $this->fileInfo->$key = $filePath . '?iopcmd=thumbnail&type=5&width=' . $config[$width] . '&height=' . $config[$height];
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
     * 添加水印
     * @return mixed
     */
    public function water(string $filePath = '')
    {
        $waterPath = $filePath;
        if ($this->waterConfig['image_watermark_status'] && $filePath) {
            if (strpos($filePath, '?') === false) {
                $filePath .= '?iopcmd=watermark';
            } else {
                $filePath .= '|iopcmd=watermark';
            }
            switch ($this->waterConfig['watermark_type']) {
                case 1://图片
                    if (!$this->waterConfig['watermark_image']) {
                        throw new AdminException('请上传水印图');
                    }
                    $waterPath = $filePath .= '&type=2&imageurl=' . base64_encode($this->waterConfig['watermark_image']) . '&gravity=' . ($this->position[$this->waterConfig['watermark_position']] ?? 'SouthEest') . '&opacity=' . $this->waterConfig['watermark_opacity'] . '&ax=' . $this->waterConfig['watermark_x'] . '&ay=' . $this->waterConfig['watermark_y'];
                    break;
                case 2://文字
                    if (!$this->waterConfig['watermark_text']) {
                        throw new AdminException('请填写水印文字');
                    }
                    $waterPath = $filePath .= '&type=1&text=' . base64_encode($this->waterConfig['watermark_text']) . '&fill=' . base64_encode($this->waterConfig['watermark_text_color']) . '&fontsize=' . $this->waterConfig['watermark_text_size'] . '&gravity=' . ($this->position[$this->waterConfig['watermark_position']] ?? 'SouthWest') . '&ax=' . $this->waterConfig['watermark_x'] . '&ay=' . $this->waterConfig['watermark_y'];
                    break;
            }
            return $waterPath;
        }
    }


    /**
     * 获取上传配置信息
     * @return array
     */
    public function getSystem()
    {
        $token = $this->app()->uploadToken($this->storageName);
        $domain = $this->uploadUrl;
        $key = $this->saveFileName();
        return compact('token', 'domain', 'key');
    }

    /**
     * 删除资源
     * @param $key
     * @param $bucket
     * @return mixed
     */
    public function delete(string $key)
    {
        $bucketManager = new UcloudUploadManager($this->app());
        return $bucketManager->delete($this->storageName, $key);
    }

    /**
     *
     * @return mixed|string
     */
    public function getTempKeys()
    {
        return [
            'accessid' => $this->accessKey,
            'secretKey' => $this->secretKey,
            'host' => $this->uploadUrl,
            'storageName' => $this->storageName,
            'cdn' => $this->cdn,
            'type' => 'US3'
        ];
    }

    /**
     * 设置跨域
     * @param string $name
     * @param string $region
     * @return mixed
     */
    public function setBucketCors(string $name, string $region)
    {
        return true;
    }

}
