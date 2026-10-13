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
            $config['uploadUrl'] = self::formatUrlScheme($res['domain']);
            $config['storageName'] = $res['name'];
            //京东云特殊处理
            if (self::STORAGE_JINGDONG !== $type){
                $config['storageRegion'] = $res['region'];
            }else{
                $config['storageRegion'] = systemConfig('jd_storageRegion') ?: $res['region'];
            }
            if(isset($res['cdn']) && $res['cdn']){
                $config['cdn'] = trim(self::formatUrlScheme($res['cdn']),'/').'/';
            }

        }

        if ($type == self::STORAGE_TENGXUN) {
            $config['appid'] = systemConfig('tengxun_appid');
        }

        $config = array_merge($config, ['thumb' => $thumb,'water' => $water,]);
        return new Upload($type, $config);
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
        return 'https://' . $url;
    }




}
