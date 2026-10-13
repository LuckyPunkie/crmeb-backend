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
use EasyWeChat\Kernel\HttpClient\AccessTokenAwareClient;
use EasyWeChat\Kernel\HttpClient\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * 用户接口
 * Class UserClient
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/14
 * @package crmeb\services\wechat\offical\user
 */
class UserClient extends BaseClient
{
    /**
     * 获取单个用户
     * @param string $openid
     * @param string $lang
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function get(string $openid, string $lang = 'zh_CN'): ResponseInterface|Response
    {
        $params = [
            'openid' => $openid,
            'lang' => $lang,
        ];

        return $this->api->get('cgi-bin/user/info', $params);
    }

    /**
     * 创建菜单
     * @param array $buttons
     * @param array $matchRule
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function menuCreate(array $buttons, array $matchRule = []): ResponseInterface|Response
    {
        if (!empty($matchRule)) {
            return $this->api->postJson('cgi-bin/menu/addconditional', [
                'button' => $buttons,
                'matchrule' => $matchRule,
            ]);
        }

        return $this->api->postJson('cgi-bin/menu/create', ['button' => $buttons]);
    }

    /**
     * 批量获取用户
     * @param string|null $nextOpenId
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function list(string $nextOpenId = null): ResponseInterface|Response
    {
        $params = ['next_openid' => $nextOpenId];

        return $this->api->get('cgi-bin/user/get', $params);
    }

    /**
     * 搜索用户
     * @param array $openids
     * @param string $lang
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function select(array $openids, string $lang = 'zh_CN'): ResponseInterface|Response
    {
        return $this->api->postJson('cgi-bin/user/info/batchget', [
            'user_list' => array_map(function ($openid) use ($lang) {
                return [
                    'openid' => $openid,
                    'lang' => $lang,
                ];
            }, $openids),
        ]);
    }


}
