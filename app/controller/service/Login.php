<?php

namespace app\controller\service;

use app\common\repositories\store\service\StoreServiceRepository;
use crmeb\basic\BaseController;
use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use think\facade\Cache;

class Login extends BaseController
{
    /**
     * 扫码登录
     * @return \think\response\Json
     * @throws \Exception
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/8
     */
    public function scanLogin()
    {
        $uni = uniqid(true, false) . random_int(1, 100000000);
        $key = 'S' . md5(time() . $uni);
        $siteUrl = rtrim(systemConfig('site_url'), '/');
        $timeout = 600;
        Cache::set('_scan_ser_login' . $key, 0, $timeout);
        return app('json')->success(['timeout' => $timeout, 'key' => $key, 'qrcode' => $siteUrl . '/pages/chat/customer_login/index?key=' . $key]);
    }

    /**
     * 验证扫码
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/8
     */
    public function checkScanLogin()
    {
        $key = (string)$this->request->param('key');
        if ($key) {
            $uid = Cache::get('_scan_ser_login' . $key);
            if ($uid) {
                Cache::delete('_scan_ser_login' . $key);
                $repository = app()->make(StoreServiceRepository::class);
                $user = $repository->get($uid);
                if (!$user) {
                    return app('json')->status(400, '登录失败');
                }
                if (!$user['is_open'])
                    return app('json')->status(400, '登录失败');
                if (!$user['status'])
                    return app('json')->status(400, '登录失败');

                $tokenInfo = $repository->createToken($user);
                $user = $user->toArray();
                unset($user['pwd']);
                $data = [
                    'token' => $tokenInfo['token'],
                    'exp' => $tokenInfo['out'],
                    'admin' => $user
                ];
                return app('json')->status(200, $data);
            }
        }
        return app('json')->status(201, '未登录');
    }

    /**
     * 登录
     * @param StoreServiceRepository $repository
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/8
     */
    public function login(StoreServiceRepository $repository)
    {
        $data = $this->request->params(['account', 'password', 'key', 'code']);
        if (Cache::get('ser_login_freeze_' . $data['account']))
            return app('json')->fail('账号或密码错误次数太多，请稍后在尝试');
        $repository->checkCode($data['key'], $data['code']);

        $service = $repository->getWhere(['account' => $data['account'], 'is_del' => 0]);

        if (!$service) {
            return app('json')->fail('账号或者密码错误');
        }
        if (!$service['is_open'])
            return app('json')->fail('账号未开启');
        if (!$service['status'])
            return app('json')->fail('账号已被禁用');

        if (!password_verify($data['password'], $service['pwd'])) {
            return $this->loginFailure($data['account']);
        }
        $tokenInfo = $repository->createToken($service);
        $admin = $service->toArray();
        unset($admin['pwd']);
        $data = [
            'token' => $tokenInfo['token'],
            'exp' => $tokenInfo['out'],
            'admin' => $admin
        ];

        return app('json')->success($data);
    }

    /**
     * 退出登录
     * @param StoreServiceRepository $repository
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/8
     */
    public function logout(StoreServiceRepository $repository)
    {
        if ($this->request->isLogin())
            $repository->clearToken($this->request->token());
        return app('json')->success('退出登录');
    }

    /**
     * 获取验证码
     * @return mixed
     * @author xaboy
     * @day 2020-04-09
     */
    public function getCaptcha(StoreServiceRepository $repository)
    {
        $codeBuilder = new CaptchaBuilder(null, new PhraseBuilder(4));
        $key = $repository->createLoginKey($codeBuilder->getPhrase());
        $captcha = $codeBuilder->build()->inline();
        return app('json')->success(compact('key', 'captcha'));
    }

    /**
     * 登录尝试次数限制
     * @param $account
     * @param int $number
     * @param int $n
     * @author Qinii
     * @day 7/6/21
     */
    public function loginFailure($account, $number = 5, $n = 3)
    {
        $key = 'ser_login_failuree_' . $account;
        $numb = Cache::get($key) ?? 0;
        $numb++;
        if ($numb >= $number) {
            $fail_key = 'ser_login_freeze_' . $account;
            Cache::set($fail_key, 1, 15 * 60);
            return app('json')->fail('账号或密码错误次数太多，请稍后在尝试');
        }
        Cache::set($key, $numb, 5 * 60);
        $msg = '账号或密码错误';
        $_n = $number - $numb;
        if ($_n <= $n) {
            $msg .= ',还可尝试' . $_n . '次';
        }
        return app('json')->fail($msg);
    }
}
