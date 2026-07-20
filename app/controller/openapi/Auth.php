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


namespace app\controller\openapi;

use crmeb\basic\BaseController;
use crmeb\exceptions\AuthException;
use crmeb\services\JwtTokenService;
use Firebase\JWT\JWT;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Config;

/**
 * Class Auth
 * @package app\controller\api
 * @author xaboy
 * @day 2020-05-06
 */
class Auth extends BaseController
{

    /**
     * 对外接口获取token
     * @return array
     * @author Qinii
     * @day 2023/8/14
     */
    public function auth()
    {
        $auth = $this->request->openAuthInfo();
        $service = new JwtTokenService();
        $valid_exp = intval(Config::get('admin.openapi_token_valid_exp', 15));
        $exp = strtotime("+ {$valid_exp}hour");
        $token = $service->createToken($auth->id, 'openapi', $exp);

        Cache::set('openapi_' . $token['token'],  time() + $token['out'], $token['out']);
        return app('json')->success(['token' => $token['token'],'exp' => $token['out']]);
    }
}
