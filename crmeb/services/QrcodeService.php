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


use crmeb\services\wechat\OfficialAccount;
use app\common\repositories\system\attachment\AttachmentRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\wechat\RoutineQrcodeRepository;
use Endroid\QrCode\QrCode;
use think\exception\ValidateException;
use think\facade\Config;

class QrcodeService
{

    /**
     * 获取二维码
     * @param $url
     * @param $name
     * @return array|bool|string
     */
    public function getQRCodePath($url, $name)
    {
        if (!strlen(trim($url)) || !strlen(trim($name))) return false;
        try {
            $uploadType = systemConfig('upload_type');
            //TODO 没有选择默认使用本地上传
            if (!$uploadType) $uploadType = 1;
            $uploadType = (int)$uploadType;
            $siteUrl = systemConfig('site_url');
            if (!$siteUrl) return '请前往后台设置->系统设置->网站域名 填写您的域名格式为：http://域名';
            $info = [];
            $outfile = Config::get('qrcode.cache_dir');
            $code = new QrCode($url);
            $result = (new \Endroid\QrCode\Writer\PngWriter())->write($code);
            if ($uploadType === 1) {
                if (!is_dir('./public/' . $outfile))
                    mkdir('./public/' . $outfile, 0777, true);
                $result->saveToFile('./public/' . $outfile . '/' . $name);
                $info["code"] = 200;
                $info["name"] = $name;
                $info["dir"] = rtrim($siteUrl, '/') . '/' . $outfile . '/' . $name;
                $info["time"] = time();
                $info['size'] = 0;
                $info['type'] = $result->getMimeType();
                $info["image_type"] = 1;
                $info['thumb_path'] = $info["dir"];
                return $info;
            } else {
                $upload = UploadService::create($uploadType);
                $res = $upload->to($outfile)->validate()->stream($result->getString(), $name);
                if ($res === false) {
                    return $upload->getError();
                }
                $info = $upload->getUploadInfo();
                $info['image_type'] = $uploadType;
                return $info;
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }


    /**
     * 获取二维码完整路径，不存在则自动生成
     * @param string $name 路径名
     * @param string $link 需要生成二维码的跳转路径
     * @param bool $force 是否返回false
     * @return bool|mixed|string
     */
    public function getWechatQrcodePath(string $name, string $link, bool $force = false, $key = '')
    {
        $imageInfo = app()->make(AttachmentRepository::class)->getWhere(['attachment_name' => $name]);
        if($imageInfo) {
            return $imageInfo['attachment_src'];
        }

        $siteUrl = rtrim(systemConfig('site_url'), '/');
        $codeUrl = tidy_url($link, null, $siteUrl);
        if ($key && systemConfig('open_wechat_share')) {
            $qrcode = OfficialAccount::foreverQrCode('_scan_url_' . $key);
            $codeUrl = OfficialAccount::qrCodeUrl($qrcode['ticket']);
        }

        $imageInfo = $this->getQRCodePath($codeUrl, $name);
        if (is_string($imageInfo) && $force)  {
            return false;
        }
        if (is_string($imageInfo)) {
            throw new ValidateException('请检查存储配置，错误提示：'.$imageInfo);
        }

        $imageInfo['dir'] = tidy_url($imageInfo['dir'], null, $siteUrl);
        app()->make(AttachmentRepository::class)->create($imageInfo['image_type'], -1, 0, [
            'attachment_category_id' => 0,
            'attachment_name' => $imageInfo['name'],
            'attachment_src' => $imageInfo['dir']
        ]);
        return $imageInfo['dir'];
    }

    /**
     * 获取小程序分享二维码
     * @param int $id
     * @param int $uid
     * @param int $type 1 = 拼团,2 = 秒杀
     * @param array $parame
     * @return bool|string
     */
    public function hotQrcodePath(int $id, int $uid, int $type, array $parame = [])
    {
        $page = '';
        $namePath = '';
        $data = 'id=' . $id . '&spid=' . $uid;
        switch ($type) {
            case 1:
                $page = 'pages/activity/goods_combination_details/index';
                $namePath = 'combination_' . $id . '_' . $uid . '.jpg';
                break;
            case 2:
                $page = 'pages/activity/goods_seckill_details/index';
                $namePath = 'seckill_' . $id . '_' . $uid . '.jpg';
                if (isset($parame['stop_time']) && $parame['stop_time']) {
                    $data .= '&time=' . $parame['stop_time'];
                    $namePath = $parame['stop_time'] . $namePath;
                }
                break;
        }
        if (!$page || !$namePath) {
            return false;
        }

        return $this->getRoutineQrcodePath($namePath, $page, $data);
    }

    /**
     * @param $namePath
     * @param $page
     * @param $data
     * @return bool|int|mixed|string
     * @author xaboy
     * @day 2020/6/18
     */
    public function getRoutineQrcodePath($namePath, $page, $data, $to = 'routine/product')
    {
        //try {
            $attachmentRepository = app()->make(AttachmentRepository::class);
            $imageInfo =   $attachmentRepository->getWhere(['attachment_name' => $namePath]);
            if ($imageInfo) {
                $url = $imageInfo['attachment_src'];
                $urlArr = parse_url($url);
                if (!$urlArr['path'] || !is_file(public_path().$urlArr['path']))
                    $imageInfo = null;
            }

            if (!$imageInfo) {
                $res = app()->make(RoutineQrcodeRepository::class)->getPageCode($page, $data, 400);
                if (!$res)  throw new ValidateException('二维码生成失败，请检查域名白名单等');
                $uploadType = (int)systemConfig('upload_type') ?: 1;
                $upload = UploadService::create($uploadType);
                $res = $upload->to($to)->validate()->stream((string)$res, $namePath);
                if ($res === false)
                    throw new ValidateException('文件流上传失败，存储类型：'.$uploadType);;
                $imageInfo = $upload->getUploadInfo();
                $imageInfo['image_type'] = $uploadType;
                $imageInfo['dir'] = tidy_url($imageInfo['dir'], 0);
                $remoteImage = remoteImage($imageInfo['dir']);
                if (!$remoteImage['status'])  throw new ValidateException('小程序二维码生成失败');
                $attachmentRepository->create($uploadType, -1, 0, [
                    'attachment_category_id' => 0,
                    'attachment_name' => $imageInfo['name'],
                    'attachment_src' => $imageInfo['dir']
                ]);
                $url = $imageInfo['dir'];
            }
            if (substr($url,0,5) != 'https')
                throw new ValidateException('图片地址必须为hppts');
            return $url;
        //} catch (\Throwable $e) {
        //    throw new ValidateException($e->getMessage());
        //}
    }

}
