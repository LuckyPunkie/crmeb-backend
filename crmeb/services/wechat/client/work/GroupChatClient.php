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
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * 客户群聊配置
 * Class GroupChatClient
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/15
 * @package crmeb\services\wechat\client\work
 */
class GroupChatClient extends BaseClient
{
    /**
     * 更新客户群进群方式配置
     * @param string $configId
     * @param string $roomBaseName
     * @param array $chatIdList
     * @param string $state
     * @param int $autoCreateRoom
     * @param int $roomBaseId
     * @param string|null $remark
     * @param int $scene
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function updateJoinWay(string $configId, string $roomBaseName, array $chatIdList, string $state, int $autoCreateRoom = 1, int $roomBaseId = 1, string $remark = null, int $scene = 2): ResponseInterface|Response
    {
        $data = [
            'config_id' => $configId,
            'scene' => $scene,
            'remark' => $remark,
            'auto_create_room' => $autoCreateRoom,
            'room_base_name' => $roomBaseName,
            'room_base_id' => $roomBaseId,
            'chat_id_list' => $chatIdList,
            'state' => $state,
        ];
        return $this->api->postJson('cgi-bin/externalcontact/groupchat/update_join_way', $data);
    }

    /**
     * 配置客户群进群方式
     * @param string $roomName
     * @param array $chatIdList
     * @param string $state
     * @param int $autoCreateRoom
     * @param int $roomBaseId
     * @param string|null $remark
     * @param int $scene
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function addJoinWay(string $roomName, array $chatIdList, string $state, int $autoCreateRoom = 1, int $roomBaseId = 1, string $remark = null, int $scene = 2): ResponseInterface|Response
    {
        $data = [
            'scene' => $scene,
            'remark' => $remark,
            'chat_id_list' => $chatIdList,
            'auto_create_room' => $autoCreateRoom,
            'room_base_name' => $roomName,
            'room_base_id' => $roomBaseId,
            'state' => $state
        ];

        return $this->api->postJson('cgi-bin/externalcontact/groupchat/add_join_way', $data);
    }

    /**
     * 获取客户群进群方式配置
     * @param string $configId
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getJoinWay(string $configId): ResponseInterface|Response
    {
        return $this->api->postJson('cgi-bin/externalcontact/groupchat/get_join_way', ['config_id' => $configId]);
    }

    /**
     * 删除客户群进群方式配置
     * @param string $configId
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function deleteJoinWay(string $configId): ResponseInterface|Response
    {
        return $this->api->postJson('cgi-bin/externalcontact/groupchat/del_join_way', ['config_id' => $configId]);
    }

}
