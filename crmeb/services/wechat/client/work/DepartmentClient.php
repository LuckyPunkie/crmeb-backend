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
 * 部门
 * Class DepartmentClient
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/15
 * @package crmeb\services\wechat\client\work
 */
class DepartmentClient extends BaseClient
{

    /**
     * 获取部门信息
     * @param int $id
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function get(int $id): ResponseInterface|Response
    {
        return $this->api->get('cgi-bin/department/get', compact('id'));
    }

    /**
     * 获取部门列表
     * @param int|null $id
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function list(?int $id = null): ResponseInterface|Response
    {
        return $this->api->get('cgi-bin/department/list', compact('id'));
    }

    /**
     * 获取子部门ID列表
     * @param int|null $id
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function simpleList(?int $id = null): ResponseInterface|Response
    {
        return $this->api->get('cgi-bin/department/simplelist', compact('id'));
    }

    /**
     * 获取成员ID列表
     * @param int $limit
     * @param string $cursor
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getUserListIds(int $limit = 0, string $cursor = ''): ResponseInterface|Response
    {
        $data = [];

        if ($limit) {
            $data['limit'] = $limit;
        }

        if ($cursor) {
            $data['cursor'] = $cursor;
        }

        return $this->api->postJson('cgi-bin/user/list_id', $data);
    }


}
