<?php

namespace app\common\model\system;

use app\common\model\BaseModel;

/**
 * 直传 OSS 的图片/视频登记（OSS 回调验签后写入）
 */
class UploadMedia extends BaseModel
{
    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'upload_media';
    }
}
