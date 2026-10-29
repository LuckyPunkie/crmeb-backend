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


namespace crmeb\services;

use app\common\repositories\system\StorageRepository;
use crmeb\services\upload\Upload;
use think\facade\Log;

/**
 * Class UploadService
 * @package crmeb\services
 */
class UploadService
{

    const STORAGE_LOCAL = 1;
    const STORAGE_QINIU = 2;
    const STORAGE_ALIYUN = 3;
    const STORAGE_TENGXUN = 4;
    const STORAGE_HUAWEI = 5;
    const STORAGE_UCLOUD = 6;
    const STORAGE_JINGDONG = 7;
    const STORAGE_TIANYI = 8;


    public function getType()
    {
        return [
            ['value' => self::STORAGE_QINIU, 'label' => '七牛云存储',],
            ['value' => self::STORAGE_ALIYUN, 'label' => '阿里云存储',],
            ['value' => self::STORAGE_TENGXUN, 'label' => '腾讯云存储',],
            ['value' => self::STORAGE_HUAWEI, 'label' => '华为云存储',],
            ['value' => self::STORAGE_UCLOUD, 'label' => 'UC云存储',],
            ['value' => self::STORAGE_JINGDONG, 'label' => '京东云存储',],
            ['value' => self::STORAGE_TIANYI, 'label' => '天翼云存储',],
        ];
    }

    public static function getPrefix($type = 1)
    {
        $prefix = [
            self::STORAGE_QINIU => 'qiniu_',
            self::STORAGE_ALIYUN => '',
            self::STORAGE_TENGXUN => 'tengxun_',
            self::STORAGE_HUAWEI => 'obs_',
            self::STORAGE_UCLOUD => 'uc_',
            self::STORAGE_JINGDONG => 'jdoss_',
            self::STORAGE_TIANYI => 'ctoss_',
        ];
        if ($type != 1) {
            $prefix =  $prefix[$type] ?? $prefix;
        }
        return $prefix;
    }

    /**
     * @param $type
     * @return Upload
     */
    public static function create($type = null)
    {
        $type = $type ? : (systemConfig('upload_type') ?: 1);
        $type = (int)$type;
        $thumb = systemConfig(['thumb_big_height', 'thumb_big_width', 'thumb_mid_height', 'thumb_mid_width', 'thumb_small_height', 'thumb_small_width','image_thumb_status']);
        $water = systemConfig(['image_watermark_status', 'watermark_type', 'watermark_image', 'watermark_opacity', 'watermark_position', 'watermark_rotate', 'watermark_text', 'watermark_text_angle', 'watermark_text_color', 'watermark_text_size', 'watermark_x', 'watermark_y']);
        $water = array_filter($water);
        $config = [
            'image_thumb_status' => systemConfig('image_thumb_status'),
        ];
        //除了本地存储其他都去获取配置信息
        if ($type != self::STORAGE_LOCAL) {
            $prefix = self::getPrefix();
            $config['accessKey'] = systemConfig($prefix[$type].'accessKey');
            $config['secretKey'] = systemConfig($prefix[$type].'secretKey');
            $make = app()->make(StorageRepository::class);
            $res = $make->getConfig($type,$config['accessKey']);
            $config['uploadUrl'] = self::normalizeDomain($res['domain']);
            $config['storageName'] = $res['name'];
            //京东云特殊处理
            if (self::STORAGE_JINGDONG !== $type){
                $config['storageRegion'] = $res['region'];
            }else{
                $config['storageRegion'] = systemConfig('jd_storageRegion') ?: $res['region'];
            }
            if(isset($res['cdn']) && $res['cdn']){
                $config['cdn'] = self::normalizeDomain($res['cdn']);
            }

        }

        if ($type == self::STORAGE_TENGXUN) {
            $config['appid'] = systemConfig('tengxun_appid');
        }

        $config = array_merge($config, ['thumb' => $thumb,'water' => $water,]);
        return new Upload($type, $config);
    }

    /**
     * 服务器本地生成 / 处理过的文件（证件加水印、带台号的桌码等）按当前存储方式转存
     * 本地存储时原样返回；转存失败也返回本地地址，不影响业务
     * @param string $localFile 本地绝对路径
     * @param string $dir 云存储目录
     * @param string $localUrl 本地文件的访问地址
     * @return string
     */
    public static function storeLocalFile(string $localFile, string $dir, string $localUrl): string
    {
        $type = (int)systemConfig('upload_type') ?: self::STORAGE_LOCAL;
        if ($type === self::STORAGE_LOCAL || !is_file($localFile)) {
            return $localUrl;
        }
        try {
            $upload = self::create($type);
            if ($upload->to($dir)->stream(file_get_contents($localFile), basename($localFile)) === false) {
                Log::warning('本地文件转存云存储失败: ' . $localFile . ' ' . $upload->getError());
                return $localUrl;
            }
        } catch (\Throwable $e) {
            Log::warning('本地文件转存云存储异常: ' . $localFile . ' ' . $e->getMessage());
            return $localUrl;
        }
        @unlink($localFile);
        return $upload->getFileInfo()->filePath;
    }

    /**
     * 补全 URL 协议
     * @param string $url
     * @return string
     */
    protected static function formatUrlScheme(string $url): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/^https?:\/\//i', $url)) return $url;
        if (strpos($url, '//') === 0) return 'https:' . $url;
        if (preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $url)) return $url;
        return 'https://' . $url;
    }

    /**
     * 统一存储域名格式，域名末尾不保留斜杠。
     */
    public static function normalizeDomain(string $url): string
    {
        return rtrim(self::formatUrlScheme($url), '/');
    }

    /**
     * 判断存储域名是否为有效的 HTTP(S) URL。
     */
    public static function isValidDomain(string $url): bool
    {
        $rawUrl = trim($url);
        if (preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $rawUrl)
            && !preg_match('/^https?:\/\//i', $rawUrl)) {
            return false;
        }
        $url = self::normalizeDomain($url);
        if ($url === '' || preg_match('/\s/', $url)) return false;

        $parts = parse_url($url);
        return is_array($parts)
            && isset($parts['scheme'], $parts['host'])
            && in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            && !isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment']);
    }

    /**
     * 获取 URL 的源（协议、主机和端口），用于匹配空间域名与 CDN 域名。
     */
    public static function getUrlOrigin(string $url): string
    {
        $url = self::normalizeDomain($url);
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) return '';

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) return '';

        $origin = $scheme . '://' . strtolower($parts['host']);
        if (isset($parts['port'])) $origin .= ':' . $parts['port'];
        return $origin;
    }




}
