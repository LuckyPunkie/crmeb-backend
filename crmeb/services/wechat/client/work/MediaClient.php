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

namespace crmeb\services\wechat\client\work;


use crmeb\services\wechat\client\BaseClient;
use EasyWeChat\Kernel\HttpClient\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * 附件管理
 * Class MediaClient
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/15
 * @package crmeb\services\wechat\client\work
 */
class MediaClient extends BaseClient
{

    /**
     * @param string $type
     * @param string $path
     * @param array $form
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function upload(string $type, string $path, array $form = []): Response
    {
        $files = [
            'media' => $path,
        ];

        return $this->httpUpload('cgi-bin/media/upload', $files, $form, compact('type'));
    }

    /**
     * 上传附件资源
     * @param string $path
     * @param string $mediaType
     * @param string $attachmentType
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function uploadAttachment(string $path, string $mediaType, string $attachmentType): Response
    {
        $query = [
            'media_type' => $mediaType,
            'attachment_type' => $attachmentType,
        ];

        return $this->httpUpload('cgi-bin/media/upload_attachment', ['media' => $path], $query);
    }

    /**
     * 获取临时素材
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     * @param string $mediaId
     * @return Response
     * @throws TransportExceptionInterface
     */
    public function get(string $mediaId): Response
    {
        return $this->getResources($mediaId, 'cgi-bin/media/get');
    }
}
