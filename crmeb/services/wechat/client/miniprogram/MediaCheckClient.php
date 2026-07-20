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
use EasyWeChat\Kernel\HttpClient\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MediaCheckClient extends BaseClient
{
    const MSG_API = 'wxa/msg_sec_check';
    const MEDIA_API = 'wxa/media_check_async';
    const LABEL = [
        100   => '正常',
        10001 => '广告',
        20001 => '时政',
        20002 => '色情',
        20003 => '辱骂',
        20006 => '违法犯罪',
        20008 => '欺诈',
        20012 => '低俗',
        20013 => '版权',
        21000 => '其他'
    ];

    /**
     * 文本敏感词检测
     *
     * @throws TransportExceptionInterface
     */
    public function msgSecCheck(string $content, int $scene, string $openId)
    {
        return $this->api->postJson(self::MSG_API, [
            'content' => $content,
            'version' => 2,
            'scene' => $scene,
            'openid' => $openId,
        ]);
    }

    /**
     * 图片或音频异步检测
     *
     * @throws TransportExceptionInterface
     */
    public function mediaSecCheck(string $mediaUrl, int $scene, string $openId, int $mediaType)
    {
        return $this->api->postJson(self::MEDIA_API, [
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'version' => 2,
            'scene' => $scene,
            'openid' => $openId,
        ]);
    }
}
