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


use app\common\repositories\system\merchant\MerchantAdminRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use crmeb\services\security\CoreAuthTokenService;
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
class MerchantHandler
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
     * 商户 WebSocket 登录校验。
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
         * @var MerchantAdminRepository $repository
         */
        $repository = app()->make(MerchantAdminRepository::class);
        try {
            $payload = app()->make(CoreAuthTokenService::class)->verifyMerchantToken($token);
        } catch (Throwable $e) {//Token 过期
            return app('json')->fail('token 已过期，请重新登录');
        }

        $admin = $repository->get($payload->jti[0]);
        if (!$admin)
            return app('json')->fail('账号或者密码错误');
//        if (!$admin['status'])
//            return app('json')->fail('账号已被禁用');

        /**
         * @var MerchantRepository $merchantRepository
         */
        $merchantRepository = app()->make(MerchantRepository::class);

        $merchant = $merchantRepository->get($admin['mer_id']);

        if (!$merchant || !$merchant['status'])
            return app('json')->fail('商户已被锁定');

        return app('json')->success(['uid' => $admin->merchant_admin_id, 'mer_id' => $admin['mer_id'], 'data' => $admin->toArray()]);
    }

}
