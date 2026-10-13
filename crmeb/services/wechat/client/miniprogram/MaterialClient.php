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

namespace crmeb\services\wechat\client\miniprogram;


use crmeb\services\wechat\client\BaseClient;
use crmeb\services\wechat\WechatException;
use EasyWeChat\Kernel\HttpClient\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * 附件
 * Class MaterialClient
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/26
 * @package crmeb\services\wechat\client\miniprogram
 */
class MaterialClient extends BaseClient
{

    /**
     * 附件类型
     * @var array|string[]
     */
    protected array $allowTypes = ['image', 'voice', 'video', 'thumb'];

    /**
     * 上传
     * @param string $type
     * @param string $path
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function upload(string $type, string $path): Response
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
