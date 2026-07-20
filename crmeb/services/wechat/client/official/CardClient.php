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
 * 卡片
 * Class CardClient
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/18
 * @package crmeb\services\wechat\client\official
 */
class CardClient extends BaseClient
{
    const STATUS_CONTENT = 'CARD_STATUS_VERIFY_OK';

    /**
     * 获取会员卡列表
     * @param int $offset
     * @param int $count
     * @param string $statusList
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function list(int $offset = 0, int $count = 10, string $statusList = self::STATUS_CONTENT): ResponseInterface|Response
    {
        $params = [
            'offset' => $offset,
            'count' => $count,
            'status_list' => $statusList,
        ];

        return $this->api->postJson('card/batchget', $params);
    }

    /**
     * 获取颜色
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function colors(): ResponseInterface|Response
    {
        return $this->api->get('card/getcolors');
    }

    /**
     * 创建卡卷
     * @param string $cardType
     * @param array $attributes
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function create(string $cardType = 'member_card', array $attributes = []): ResponseInterface|Response
    {
        $params = [
            'card' => [
                'card_type' => strtoupper($cardType),
                strtolower($cardType) => $attributes,
            ],
        ];

        return $this->api->postJson('card/create', $params);
    }

    /**
     * 获取卡卷信息
     * @param string $cardId
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function get(string $cardId): ResponseInterface|Response
    {
        $params = [
            'card_id' => $cardId,
        ];

        return $this->api->postJson('card/get', $params);
    }

    /**
     * 修改卡卷
     * @param string $cardId
     * @param string $type
     * @param array $attributes
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function update(string $cardId, string $type, array $attributes = []): ResponseInterface|Response
    {
        $card = [];
        $card['card_id'] = $cardId;
        $card[strtolower($type)] = $attributes;

        return $this->api->postJson('card/update', $card);
    }

    /**
     * 删除卡卷
     * @param string $cardId
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function delete(string $cardId): ResponseInterface|Response
    {
        $params = [
            'card_id' => $cardId,
        ];

        return $this->api->postJson('card/delete', $params);
    }

    /**
     * 创建二维码.
     * @param array $cards
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function createQrCode(array $cards): ResponseInterface|Response
    {
        return $this->api->postJson('card/qrcode/create', $cards);
    }

    /**
     * 设置开卡字段接口.
     * @param string $cardId
     * @param array $settings
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function setActivationForm(string $cardId, array $settings): ResponseInterface|Response
    {
        $params = array_merge(['card_id' => $cardId], $settings);

        return $this->api->postJson('card/membercard/activateuserform/set', $params);
    }

    /**
     * 会员卡接口激活.
     * @param array $info
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function activate(array $info = []): ResponseInterface|Response
    {
        return $this->api->postJson('card/membercard/activate', $info);
    }

    /**
     * 拉取会员信息接口.
     * @param string $cardId
     * @param string $code
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function getUser(string $cardId, string $code): ResponseInterface|Response
    {
        $params = [
            'card_id' => $cardId,
            'code' => $code,
        ];

        return $this->api->postJson('card/membercard/userinfo/get', $params);
    }

    /**
     * 更新会员信息.
     * @param array $params
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function updateUser(array $params = []): ResponseInterface|Response
    {
        return $this->api->postJson('card/membercard/updateuser', $params);
    }
}
