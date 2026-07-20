<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------


namespace app\webscoket\handler;


use app\common\repositories\system\admin\AdminRepository;
use crmeb\services\security\CoreAuthTokenService;
use Swoole\Server;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use Throwable;

/**
 * Class Handler
 * @package app\webscoket
 * @author xaboy
 * @day 2020-04-29
 */
class AdminHandler
{
    /**
     * @param array $data
     * @return mixed
     * @author xaboy
     * @day 2020-05-06
     */
    public function test(array $data)
    {
        return app('json')->success($data['data'] ?? []);
    }

    /**
     * 平台/代理商 WebSocket 登录校验。
     *
     * @param array $data 登录数据，必须包含 v2 Token。
     * @return mixed 返回 JSON 成功或失败响应。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-06
     */
    public function login(array $data)
    {
        $token = $data['token'] ?? '';
        if (!$token) return app('json')->fail('token 无效');

        /**
         * @var AdminRepository $repository
         */
        $repository = app()->make(AdminRepository::class);
        try {
            $payload = app()->make(CoreAuthTokenService::class)->verifyAdminToken($token);
        } catch (Throwable $e) {//Token 过期
            return app('json')->fail('token 已过期，请重新登录');
        }

        $admin = $repository->get($payload->jti[0]);
        if (!$admin)
            return app('json')->fail('账号或者密码错误');
//        if (!$admin['status'])
//            return app('json')->fail('账号已被禁用');
        return app('json')->success(['uid' => $admin->admin_id, 'data' => $admin->toArray()]);
    }

}
