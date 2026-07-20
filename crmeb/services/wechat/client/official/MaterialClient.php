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
use EasyWeChat\Kernel\HttpClient\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * 永久素材上传-附件
 * Class MaterialClient
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/18
 * @package crmeb\services\wechat\client\official
 */
class MaterialClient extends BaseClient
{

    /**
     * 上传图片
     * @param string $path
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function uploadImage(string $path): Response
    {
        return $this->upload('image', $path);
    }

    /**
     * 上传音频
     * @param string $path
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function uploadVoice(string $path): Response
    {
        return $this->upload('voice', $path);
    }

    /**
     * 上传文件
     * @param string $type
     * @param string $path
     * @param array $form
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function upload(string $type, string $path, array $form = []): Response
    {
        if (!file_exists($path) || !is_readable($path)) {
            throw new WechatException(sprintf('File does not exist, or the file is unreadable: "%s"', $path));
        }

        $form['type'] = $type;

        return $this->httpUpload($this->getApiByType($type), ['media' => $path], $form);
    }

    /**
     * 获取接口
     * @param string $type
     * @return string
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function getApiByType(string $type): string
    {
        return match ($type) {
            'news_image' => 'cgi-bin/media/uploadimg',
            default => 'cgi-bin/material/add_material'
        };
    }
}
