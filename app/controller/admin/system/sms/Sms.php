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


namespace app\controller\admin\system\sms;


use crmeb\basic\BaseController;
use app\common\repositories\system\config\ConfigValueRepository;
use app\common\repositories\system\sms\SmsRecordRepository;
use app\validate\admin\SmsRegisterValidate;
use crmeb\services\YunxinSmsService;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Cache;

/**
 *  短信 及 一号通  新版本弃用
 * Class Sms
 * @package app\controller\admin\system\sms
 * @author xaboy
 * @day 2020-05-18
 */
class Sms extends BaseController
{
    /**
     * @var YunxinSmsService
     */
    protected $service;

    /**
     * Sms constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = YunxinSmsService::create();
    }

    /**
     * 发送验证码
     *
     * 本函数用于处理用户请求发送验证码的逻辑。它首先验证用户提供的手机号码是否有效，
     * 然后调用服务层方法尝试发送验证码。如果手机号码无效或发送失败，函数将返回相应的错误信息；
     * 如果发送成功，函数将返回成功的消息。
     *
     * @return json 返回包含发送结果的JSON对象
     */
    public function captcha()
    {
        // 获取请求中的手机号码
        $phone = request()->param('phone');

        // 检查手机号码是否存在
        if (!$phone)
            return app('json')->fail('请输入手机号');

        // 验证手机号码格式是否正确
        if (!preg_match('/^1[3456789]{1}\d{9}$/', $phone))
            return app('json')->fail('请输入正确的手机号');

        // 调用服务层方法发送验证码
        $res = $this->service->captcha($phone);

        // 检查发送结果，如果发送失败则返回错误信息
        if (!isset($res['status']) && $res['status'] !== 200)
            return app('json')->fail($res['data']['message'] ?? $res['msg'] ?? '发送失败');

        // 如果发送成功，则返回成功的消息
        return app('json')->success($res['data']['message'] ?? $res['msg'] ?? '发送成功');
    }

    /**
     * 保存注册信息并进行验证
     *
     * 本函数负责接收注册请求，对请求数据进行验证，加密密码，然后将数据发送给服务层进行注册操作。
     * 如果注册失败，会根据错误码返回相应的错误信息；如果注册成功，则会更新配置信息。
     *
     * @param SmsRegisterValidate $validate 验证数据的验证器对象
     * @return json 返回注册结果的JSON对象，包含成功或失败的信息
     */
    public function save(SmsRegisterValidate $validate)
    {
        // 从请求中获取注册信息
        $data = $this->request->params(['account', 'password', 'phone', 'code', 'url', 'sign']);

        // 使用验证器对获取的注册信息进行验证
        $validate->check($data);

        // 对密码进行MD5加密
        $data['password'] = md5($data['password']);

        // 调用服务层方法进行注册操作
        $res = $this->service->registerData($data);

        // 如果注册失败，返回相应的错误信息
        if ($res['status'] == 400) return app('json')->fail('短信平台：' . $res['msg']);

        // 如果注册成功，更新配置信息
        $this->service->setConfig($data['account'], $data['password']);

        // 返回注册成功的消息
        return app('json')->success('短信平台：' . $res['msg']);
    }

    /**
     * 保存基本配置信息，用于短信平台的账号密码存储。
     *
     * 本函数主要用于处理用户提交的短信平台账号密码，并进行验证和保存。
     * 它首先从请求中提取账号和密码，然后使用验证器对输入进行检查。
     * 如果验证成功，它将账号和加密的密码保存到服务中，并尝试获取公共短信模板。
     * 如果获取模板成功，说明账号密码正确，保存配置信息并返回登录成功的响应。
     * 如果获取模板失败，说明账号或密码错误，返回相应的错误响应。
     *
     * @param SmsRegisterValidate $validate 验证器对象，用于校验输入数据的合法性。
     * @param ConfigValueRepository $repository 配置信息存储对象，用于保存账号密码信息。
     * @return json 返回登录成功或失败的JSON响应。
     */
    public function save_basics(SmsRegisterValidate $validate, ConfigValueRepository $repository)
    {
        // 从请求中提取账号和密码参数。
        $data = $this->request->params([
            'account', 'password'
        ]);

        // 验证用户是否已登录，并检查输入数据的合法性。
        $validate->isLogin()->check($data);

        // 设置短信服务的账号和加密后的密码。
        $this->service->setConfig($data['account'], md5($data['password']));

        // 尝试获取公共短信模板，用于验证账号密码是否有效。
        // 添加公共短信模板
        $templateList = $this->service->publictemp([]);

        // 如果获取模板成功，说明账号密码正确，保存配置并返回成功响应。
        if ($templateList['status'] != 400) {
            $repository->setFormData(['sms_account' => $data['account'], 'sms_token' => md5($data['password'])], 0);
            return app('json')->success('登录成功');
        } else {
            // 如果获取模板失败，说明账号或密码错误，返回失败响应。
            return app('json')->fail('账号或密码错误');
        }
    }

    /**
     * 检查用户是否已登录。
     *
     * 本函数通过调用服务层的方法来获取用户的账号信息，以此来判断用户是否已登录。
     * 如果用户已登录，即账号信息存在，将返回一个包含登录状态和账号信息的JSON对象。
     * 如果用户未登录，或者账号信息获取失败，将返回一个包含登录状态为false的JSON对象。
     *
     * @return \Illuminate\Http\JsonResponse 返回一个JSON响应，其中包含登录状态和/或账号信息。
     */
    public function is_login()
    {
        // 尝试获取用户的账号信息
        if ($sms_info = $this->service->account()) {
            // 如果账号信息获取成功，返回一个包含登录状态为true和账号信息的JSON响应
            return app('json')->success(['status' => true, 'info' => $sms_info]);
        } else {
            // 如果账号信息获取失败，返回一个包含登录状态为false的JSON响应
            return app('json')->success(['status' => false]);
        }
    }

    /**
     * 获取短信记录
     *
     * 本方法用于根据请求参数获取指定条件下的短信记录列表。它支持分页查询，通过调用SmsRecordRepository中的getList方法来实现。
     * 参数包括页码和每页记录数，以及请求中可能携带的短信类型参数。
     *
     * @param SmsRecordRepository $repository 短信记录仓库对象，用于执行查询操作。
     * @return json 返回包含查询结果的JSON对象，成功时包含数据列表，错误时包含错误信息。
     */
    public function record(SmsRecordRepository $repository)
    {
        // 获取请求中的页码和每页记录数
        [$page, $limit] = $this->getPage();

        // 从请求中获取类型参数，默认为0
        $where = $this->request->params(['type', 0]);

        // 调用getList方法获取短信记录列表，并使用json助手函数返回成功响应
        return app('json')->success($repository->getList($where, $page, $limit));
    }

    /**
     * 获取短信记录相关数据
     *
     * 本函数通过调用服务层获取短信发送数量及总数，并结合短信记录仓库获取已记录的短信数量，
     * 以及获取当前的短信账户信息，最终组装成数据集返回。
     *
     * @param SmsRecordRepository $repository 短信记录仓库，用于获取短信记录的数量。
     * @return json 返回包含短信数量、总数、记录数量及账户信息的数据集。
     */
    public function data(SmsRecordRepository $repository)
    {
        // 调用服务层方法获取短信发送统计信息
        $countInfo = $this->service->count();

        // 根据获取的统计信息状态判断是否成功获取数据
        if ($countInfo['status'] == 400) {
            // 如果获取数据失败，初始化短信数量和总数为0
            $info['number'] = 0;
            $info['total_number'] = 0;
        } else {
            // 如果获取数据成功，填充短信数量和总数
            $info['number'] = $countInfo['data']['number'];
            $info['total_number'] = $countInfo['data']['send_total'];
        }

        // 获取短信记录的数量
        $info['record_number'] = $repository->count();

        // 获取当前短信账户信息
        $info['sms_account'] = $this->service->account();

        // 返回组装后的数据集
        return app('json')->success($info);
    }

    /**
     * 用户退出登录操作
     *
     * 本函数负责清除与用户登录状态相关的缓存数据，确保用户安全退出。
     * 它通过删除特定的缓存键来实现，这些键包括用户的短信账户信息、服务账户信息以及相关的令牌。
     * 使用缓存系统和配置值仓库的结合，本函数能够高效且彻底地清理用户的登录状态。
     *
     * @param ConfigValueRepository $repository 配置值仓库实例，用于清除特定配置项的值。
     * @return \Illuminate\Http\JsonResponse 返回一个表示成功退出的JSON响应。
     */
    public function logout(ConfigValueRepository $repository)
    {
        // 从缓存中删除短信账户信息
        Cache::delete('sms_account');
        // 从缓存中删除服务账户信息
        Cache::delete('serve_account');

        // 使用配置值仓库清除特定键值相关的缓存，包括短信令牌和服务器令牌
        $repository->clearBykey(['sms_account', 'sms_token', 'serve_account', 'serve_token'], 0);

        // 返回一个表示成功退出的JSON响应
        return app('json')->success('退出成功');
    }


    /**
     *  修改密码
     * @Author:Qinii
     * @Date: 2020/9/2
     * @return mixed
     */
    public function changePassword()
    {
        $data = $this->request->params(['password', 'phone', 'code']);
        if (empty($data['password']))
            return app('json')->fail('密码不能为空');
        $data['password'] = md5($data['password']);
        $res = $this->service->smsChange($data);
        if ($res['status'] == 400) return app('json')->fail('短信平台：' . $res['msg']);
        $this->service->setConfig($this->service->account(), $data['password']);
        return app('json')->success('修改成功');
    }

    /**
     *  修改签名
     * @Author:Qinii
     * @Date: 2020/9/2
     * @return mixed
     */
    public function changeSign()
    {
        $data = $this->request->params(['sign', 'phone', 'code']);
        if (empty($data['sign'])) return app('json')->fail('签名不能为空');
        $res = $this->service->smsChange($data);
        if ($res['status'] == 400) return app('json')->fail('短信平台：' . $res['msg']);
        return app('json')->success('修改已提交,审核通过后自动更改');
    }
}
