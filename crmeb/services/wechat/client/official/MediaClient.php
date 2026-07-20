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

namespace crmeb\services\wechat\client\official;


use crmeb\services\wechat\client\BaseClient;
use crmeb\services\wechat\WechatException;

class MediaClient extends BaseClient
{
    /**
     * Allow media type.
     *
     * @var array
     */
    protected $allowTypes = ['image', 'voice', 'video', 'thumb'];

    public function uploadImage($path)
    {
        return $this->upload('image', $path);
    }

    public function uploadVideo($path)
    {
        return $this->upload('video', $path);
    }

    public function uploadVoice($path)
    {
        return $this->upload('voice', $path);
    }

    public function uploadThumb($path)
    {
        return $this->upload('thumb', $path);
    }

    public function upload(string $type, string $path)
    {
        if (!file_exists($path) || !is_readable($path)) {
            throw new WechatException(sprintf("File does not exist, or the file is unreadable: '%s'", $path));
        }

        if (!in_array($type, $this->allowTypes, true)) {
            throw new WechatException(sprintf("Unsupported media type: '%s'", $type));
        }

        return $this->httpUpload('cgi-bin/media/upload', ['media' => $path], ['type' => $type]);
    }
}
