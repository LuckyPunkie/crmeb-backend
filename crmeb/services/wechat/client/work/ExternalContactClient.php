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
 * 外部联系
 * Class ExternalContactClient
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/15
 * @package crmeb\services\wechat\client\work
 */
class ExternalContactClient extends BaseClient
{

    /**
     * 获取外部联系人详情
     * @param string $externalUserId
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function get(string $externalUserId): ResponseInterface|Response
    {
        return $this->api->get('cgi-bin/externalcontact/get', [
            'external_userid' => $externalUserId,
        ]);
    }

    /**
     * 批量获取客户详情
     * @param array $userIdList
     * @param string $cursor
     * @param int $limit
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function batchGet(array $userIdList, string $cursor = '', int $limit = 100): ResponseInterface|Response
    {
        return $this->api->postJson('cgi-bin/externalcontact/batch/get_by_user', [
            'userid_list' => $userIdList,
            'cursor' => $cursor,
            'limit' => $limit,
        ]);
    }

    /**
     * 获取企业标签库
     * @param array $tagIds
     * @param array $groupIds
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getCorpTags(array $tagIds = [], array $groupIds = []): ResponseInterface|Response
    {
        $params = [
            'tag_id' => $tagIds,
            'group_id' => $groupIds
        ];

        return $this->api->postJson('cgi-bin/externalcontact/get_corp_tag_list', $params);
    }

    /**
     * 添加企业客户标签
     * @param array $params
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function addCorpTag(array $params): ResponseInterface|Response
    {
        return $this->api->postJson('cgi-bin/externalcontact/add_corp_tag', $params);
    }

    /**
     * 编辑企业客户标签
     * @param string $id
     * @param string|null $name
     * @param int|null $order
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function updateCorpTag(string $id, ?string $name = null, ?int $order = null): ResponseInterface|Response
    {
        $params = [
            "id" => $id
        ];

        if (!\is_null($name)) {
            $params['name'] = $name;
        }

        if (!\is_null($order)) {
            $params['order'] = $order;
        }

        return $this->api->postJson('cgi-bin/externalcontact/edit_corp_tag', $params);
    }

    /**
     * 删除企业客户标签
     * @param array $tagId
     * @param array $groupId
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function deleteCorpTag(array $tagId, array $groupId): ResponseInterface|Response
    {
        $params = [
            "tag_id" => $tagId,
            "group_id" => $groupId,
        ];

        return $this->api->postJson('cgi-bin/externalcontact/del_corp_tag', $params);
    }

    /**
     * 编辑客户企业标签
     * @param array $params
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function markTags(array $params): ResponseInterface|Response
    {
        return $this->api->postJson('cgi-bin/externalcontact/mark_tag', $params);
    }

    /**
     * 获取客户群列表
     * @param array $params
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getGroupChats(array $params): ResponseInterface|Response
    {
        return $this->api->postJson('cgi-bin/externalcontact/groupchat/list', $params);
    }

    /**
     * 获取客户群详情
     * @param string $chatId
     * @param int $needName
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getGroupChat(string $chatId, int $needName = 0): ResponseInterface|Response
    {
        $params = [
            'chat_id' => $chatId,
            'need_name' => $needName,
        ];

        return $this->api->postJson('cgi-bin/externalcontact/groupchat/get', $params);
    }

    /**
     * 获取「群聊数据统计」数据. (按自然日聚合的方式)
     * @param int $dayBeginTime
     * @param int $dayEndTime
     * @param array $userIds
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function groupChatStatisticGroupByDay(int $dayBeginTime, int $dayEndTime, array $userIds = []): ResponseInterface|Response
    {
        $params = [
            'day_begin_time' => $dayBeginTime,
            'day_end_time' => $dayEndTime,
            'owner_filter' => [
                'userid_list' => $userIds
            ]
        ];
        return $this->api->postJson('cgi-bin/externalcontact/groupchat/statistic_group_by_day', $params);
    }

}
