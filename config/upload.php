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

// +----------------------------------------------------------------------
// | 上传配置
// +----------------------------------------------------------------------

return [
    //默认上传模式
    'default' => 'local',
    //上传文件大小
    'filesize' => 52428800,
    //上传文件后缀类型
    'fileExt' => [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'pem',
        'mp3',
        'wma',
        'wav',
        'amr',
        'aac',
        'webm',
        'm4a',
        'mp4',
        'key',
        'xlsx',
        'xls',
        'ico',
        'avif',
        'txt',
        'pdf',
    ],
    //上传文件类型
    'fileMime' => [
        'image/jpg',
        'image/jpeg',
        'image/gif',
        'image/png',
        'image/avif',
        'text/plain',
        'audio/mpeg',
        'audio/mp3',
        'audio/wav',
        'audio/x-wav',
        'audio/amr',
        'audio/aac',
        'audio/mp4',
        'audio/webm',
        'audio/x-m4a',
        'video/webm',
        'video/mp4',
        'application/octet-stream',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-works',
        'application/vnd.ms-excel',
        'application/zip',
        'application/vnd.ms-excel',
        'application/vnd.ms-excel',
        'text/xml',
        'image/x-icon',
        'image/vnd.microsoft.icon',
        'application/x-x509-ca-cert',
        'application/pdf'
    ],
    //驱动模式
    'stores' => [
        //本地上传配置
        'local' => [],
        //七牛云上传配置
        'qiniu' => [],
        //oss上传配置
        'oss' => [],
        //cos上传配置
        'cos' => [],
        //obs华为储存
        'obs' => [],
        //ucloud存储
        'us3' => [],
        //jd
        'jdoss' => [],
        //天翼云
        'ctoss' => [],
    ],
    'iamge_fileExt' => ['jpg', 'jpeg', 'png', 'gif','webp','avif'],
    //上传文件类型
    'image_fileMime' => ['image/jpeg', 'image/gif', 'image/png','image/webp', 'image/avif'],
    //是否开启图片压缩
    'image_compress_status' => true,
    //图片压缩质量
    'image_compress_quality' => 80,
];
