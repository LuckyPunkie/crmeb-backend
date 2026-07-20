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


namespace app\controller\merchant\system\admin;


use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantAdminRepository;
use app\validate\admin\LoginValidate;
use crmeb\services\SwooleTaskService;
use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Cache;

class Login extends BaseController
{
    protected $repository;

    public function __construct(App $app, MerchantAdminRepository $repository)
    {

        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 判断是否需要滑块验证码
     * @return \think\response\Json
     * @author Qinii
     * @day 2022/10/11
     */
    public function ajCaptchaStatus()
    {
        $data = $this->request->params(['account']);
        $key = 'mer_login_failuree_'.$data['account'];
        $numb = (Cache::get($key) ?? 0);
        return app('json')->success(['status' => $numb > 2 ]);
    }

    /**
     * 登录方法
     *
     * @param LoginValidate $validate 登录验证器
     * @return \think\response\Json
     */
    public function login(LoginValidate $validate)
    {
        $data = $this->request->params(['account', 'password', 'code', 'key', ['captchaType', ''], ['captchaVerification', ''], 'token']);
        // 验证请求参数
        $validate->check($data);

        //图形验证码废弃
//        if(Cache::get('mer_login_freeze_'.$data['account']))
//            return app('json')->fail('账号或密码错误次数太多，请稍后在尝试');
//        $this->repository->checkCode($data['key'], $data['code']);

        // 判断登录失败次数
        $key = 'mer_login_failuree_' . $data['account'];
        $numb = (Cache::get($key) ?? 0);
        if ($numb > 2) {
            // 如果登录失败次数超过2次，则需要进行滑块验证
            if (!$data['captchaType'] || !$data['captchaVerification'])
                return app('json')->fail('请滑动滑块验证');
            try {
                aj_captcha_check_two($data['captchaType'], $data['captchaVerification']);
            } catch (\Throwable $e) {
                return app('json')->fail($e->getMessage());
            }
        }
        // 调用登录方法
        $adminInfo = $this->repository->login($data['account'], $data['password']);
        // 创建 token
        $tokenInfo = $this->repository->createToken($adminInfo);
        // 构造返回数据
        $admin = $adminInfo->toArray();
        unset($admin['pwd']);
        $data = [
            'token' => $tokenInfo['token'],
            'exp' => $tokenInfo['out'],
            'admin' => $admin
        ];
        // 删除登录失败次数缓存
        Cache::delete($key);
        // 返回成功结果
        return app('json')->success($data);
    }


    /**
     * 退出登录方法
     *
     * @return \Illuminate\Http\JsonResponse 返回 JSON 格式的退出登录结果
     */
    public function logout()
    {
        // 判断当前请求是否已经登录
        if ($this->request->isLogin())
            // 清除当前请求的 token
            $this->repository->clearToken($this->request->token());
        // 返回 JSON 格式的退出登录结果
        return app('json')->success('退出登录');
    }


    /**
     * 获取验证码
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCaptcha()
    {
        // 创建一个验证码构建器实例
        $codeBuilder = new CaptchaBuilder(null, new PhraseBuilder(4));
        // 生成登录密钥
        $key = $this->repository->createLoginKey($codeBuilder->getPhrase());
        // 生成验证码图片
        $captcha = $codeBuilder->build()->inline();
        // 返回 JSON 格式的成功响应，包含登录密钥和验证码图片
        return app('json')->success(compact('key', 'captcha'));
    }

}
