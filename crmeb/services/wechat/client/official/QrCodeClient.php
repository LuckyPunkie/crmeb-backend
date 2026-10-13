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
use EasyWeChat\Kernel\HttpClient\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * 二维码
 * Class QrCodeClient
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/18
 * @package crmeb\services\wechat\client\official
 */
class QrCodeClient extends BaseClient
{
    public const DAY = 86400;
    public const SCENE_MAX_VALUE = 100000;
    public const SCENE_QR_CARD = 'QR_CARD';
    public const SCENE_QR_TEMPORARY = 'QR_SCENE';
    public const SCENE_QR_TEMPORARY_STR = 'QR_STR_SCENE';
    public const SCENE_QR_FOREVER = 'QR_LIMIT_SCENE';
    public const SCENE_QR_FOREVER_STR = 'QR_LIMIT_STR_SCENE';

    /**
     * 获取url
     * @param string $ticket
     * @return string
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function url(string $ticket): string
    {
        return sprintf('https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=%s', urlencode($ticket));
    }

    /**
 	* createForeverQrcode
	* @param string|int $sceneValue
	* @return array|mixed[]
	* @throws TransportExceptionInterface
	* @throws \EasyWeChat\Kernel\Exceptions\BadResponseException
	* @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
	* @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
	* @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
	* @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
	*/
    public function forever(string|int $sceneValue): array
    {
        if (is_int($sceneValue) && $sceneValue > 0 && $sceneValue < self::SCENE_MAX_VALUE) {
            $type = self::SCENE_QR_FOREVER;
            $sceneKey = 'scene_id';
        } else {
            $type = self::SCENE_QR_FOREVER_STR;
            $sceneKey = 'scene_str';
        }
        $scene = [$sceneKey => $sceneValue];

        return $this->create($type, $scene, false)->toArray();
    }

    /**
 	* 临时二维码生成
	* @param int|string $sceneValue
	* @param int|null $expireSeconds
	* @return array|mixed[]
	* @throws TransportExceptionInterface
	* @throws \EasyWeChat\Kernel\Exceptions\BadResponseException
	* @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
	* @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
	* @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
	* @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
	*/
    public function temporary(int|string $sceneValue, ?int $expireSeconds = null): array
    {
        if (is_int($sceneValue) && $sceneValue > 0) {
            $type = self::SCENE_QR_TEMPORARY;
            $sceneKey = 'scene_id';
        } else {
            $type = self::SCENE_QR_TEMPORARY_STR;
            $sceneKey = 'scene_str';
        }
        $scene = [$sceneKey => $sceneValue];

        return $this->create($type, $scene, true, $expireSeconds)->toArray();
    }

    /**
     * 创建二维码
     * @param $actionName
     * @param $actionInfo
     * @param bool $temporary
     * @param null $expireSeconds
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    protected function create($actionName, $actionInfo, bool $temporary = true, $expireSeconds = null): ResponseInterface|Response
    {
        null !== $expireSeconds || $expireSeconds = 7 * self::DAY;

        $params = [
            'action_name' => $actionName,
            'action_info' => ['scene' => $actionInfo],
        ];

        if ($temporary) {
            $params['expire_seconds'] = min($expireSeconds, 30 * self::DAY);
        }

        return $this->api->postJson('cgi-bin/qrcode/create', $params);
    }
}
