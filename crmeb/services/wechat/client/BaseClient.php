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

namespace crmeb\services\wechat\client;

use EasyWeChat\Kernel\Form\File;
use EasyWeChat\Kernel\Form\Form;
use EasyWeChat\Kernel\HttpClient\Response;
use EasyWeChat\Kernel\HttpClient\AccessTokenAwareClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * 基础接口请求
 * Class BaseClient
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/18
 * @package crmeb\services\wechat\client
 */
abstract class BaseClient
{
    /**
     * UserClient constructor.
     * @param AccessTokenAwareClient $api
     */
    public function __construct(protected AccessTokenAwareClient $api)
    {
    }

    /**
     * 上传文件
     * @param string $url
     * @param array $files
     * @param array $form
     * @param array $query
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function httpUpload(string $url, array $files = [], array $form = [], array $query = [])
    {
		$form['media'] = File::fromPath($files['media']);
		$options = Form::create($form)->toArray();
        return $this->api->request(
			'POST',
            $url.'?'.http_build_query($query),
            $options
        );
    }

    /**
     * @param string $mediaId
     * @param string $uri
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    protected function getResources(string $mediaId, string $uri)
    {
        return $this->api->request('GET', $uri, [
            'query' => [
                'media_id' => $mediaId,
            ],
        ]);
    }
}
