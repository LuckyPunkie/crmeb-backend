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
use crmeb\services\wechat\message\MessageInterface;
use crmeb\services\wechat\message\Raw;
use EasyWeChat\Kernel\HttpClient\AccessTokenAwareClient;
use EasyWeChat\Kernel\HttpClient\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * 消息
 * Class StaffClient
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/14
 * @package crmeb\services\wechat\client\offical
 */
class StaffClient extends BaseClient
{

    /**
     * 发送消息
     * @param MessageInterface|string $message
     * @param string $to
     * @param string $account
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function send(MessageInterface|string $message, string $to, string $account = ''): ResponseInterface|Response
    {
        if (empty($message)) {
            throw new \RuntimeException('No message to send.');
        }

        if ($message instanceof Raw) {
            $message = json_decode($message->get('content'), true);
        } else {
            $prepends = [
                'touser' => $to,
            ];
            if ($account) {
                $prepends['customservice'] = ['kf_account' => $account];
            }
            $message = $message->transformForJsonRequest($prepends);
        }

        return $this->api->postJson('cgi-bin/message/custom/send', $message);
    }
}
