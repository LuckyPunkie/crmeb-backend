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
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * 朋友圈
 * Class MomentClient
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/15
 * @package crmeb\services\wechat\client\work
 */
class MomentClient extends BaseClient
{

    /**
     * 创建发表任务
     * @param array $param
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function createTask(array $param): ResponseInterface|Response
    {
        return $this->api->postJson('cgi-bin/externalcontact/add_moment_task', $param);
    }

    /**
     * 获取任务创建结果
     * @param string $jobId
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getTask(string $jobId): ResponseInterface|Response
    {
        return $this->api->get('cgi-bin/externalcontact/get_moment_task_result', ['jobid' => $jobId]);
    }

    /**
     * 获取企业全部的发表列表
     * @param array $params
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function list(array $params): ResponseInterface|Response
    {
        return $this->api->postJson('cgi-bin/externalcontact/get_moment_list', $params);
    }

    /**
     * 获取客户朋友圈企业发表的列表
     * @param string $momentId
     * @param string $cursor
     * @param int $limit
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getTasks(string $momentId, string $cursor = '', int $limit = 500): ResponseInterface|Response
    {
        $params = [
            'moment_id' => $momentId,
            'cursor' => $cursor,
            'limit' => $limit
        ];

        return $this->api->postJson('cgi-bin/externalcontact/get_moment_task', $params);
    }

    /**
     * 获取客户朋友圈发表时选择的可见范围
     * @param string $momentId
     * @param string $userId
     * @param string $cursor
     * @param int $limit
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getCustomers(string $momentId, string $userId, string $cursor, int $limit): ResponseInterface|Response
    {
        $params = [
            'moment_id' => $momentId,
            'userid' => $userId,
            'cursor' => $cursor,
            'limit' => $limit
        ];

        return $this->api->postJson('cgi-bin/externalcontact/get_moment_customer_list', $params);
    }

    /**
     * 获取客户朋友圈发表后的可见客户列表
     * @param string $momentId
     * @param string $userId
     * @param string $cursor
     * @param int $limit
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getSendResult(string $momentId, string $userId, string $cursor, int $limit): ResponseInterface|Response
    {
        $params = [
            'moment_id' => $momentId,
            'userid' => $userId,
            'cursor' => $cursor,
            'limit' => $limit
        ];

        return $this->api->postJson('cgi-bin/externalcontact/get_moment_send_result', $params);
    }

    /**
     * 获取客户朋友圈的互动数据
     * @param string $momentId
     * @param string $userId
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getComments(string $momentId, string $userId): ResponseInterface|Response
    {
        $params = [
            'moment_id' => $momentId,
            'userid' => $userId
        ];

        return $this->api->postJson('cgi-bin/externalcontact/get_moment_comments', $params);
    }
}
