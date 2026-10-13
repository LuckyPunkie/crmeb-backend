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
 * 用户
 * Class UserClient
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/18
 * @package crmeb\services\wechat\client\work
 */
class UserClient extends BaseClient
{

    /**
     * 获取部门成员详细信息
     * @param int $departmentId
     * @param bool $fetchChild
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getDetailedDepartmentUsers(int $departmentId, bool $fetchChild = false): ResponseInterface|Response
    {
        $params = [
            'department_id' => $departmentId,
            'fetch_child' => (int)$fetchChild,
        ];

        return $this->api->get('cgi-bin/user/list', $params);
    }

    /**
     * 获取通讯录成员详情
     * @param string $userId
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function get(string $userId): ResponseInterface|Response
    {
        return $this->api->get('cgi-bin/user/get', ['userid' => $userId]);
    }

    /**
     * userid转openid
     * @param string $userId
     * @param int|null $agentId
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function userIdToOpenid(string $userId, int $agentId = null): ResponseInterface|Response
    {
        $params = [
            'userid' => $userId,
            'agentid' => $agentId,
        ];

        return $this->api->postJson('cgi-bin/user/convert_to_openid', $params);
    }

	/**
	 * 根据code获取成员详情
	 * @param string $code
	 * @return ResponseInterface|Response
	 * @throws TransportExceptionInterface
	 */
	public function userFromCode(string $code): ResponseInterface|Response
	{
		$params = [
			'code' => $code,
		];

		return $this->api->get('cgi-bin/user/getuserinfo', $params);
	}
}
