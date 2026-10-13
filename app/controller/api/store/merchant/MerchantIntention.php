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

namespace app\controller\api\store\merchant;

use app\common\repositories\system\merchant\MerchantAdminRepository;
use app\common\repositories\system\merchant\MerchantCategoryRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\system\merchant\MerchantTypeRepository;
use app\validate\api\MerchantIntentionValidate;
use crmeb\services\SmsService;
use crmeb\services\SwooleTaskService;
use crmeb\services\YunxinSmsService;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantIntentionRepository as repository;
use think\exception\ValidateException;
use app\common\repositories\circle\CircleRepository;
use app\common\repositories\circle\CircleAgentRepository;

class MerchantIntention extends BaseController
{
    protected $repository;
    protected $userInfo;

    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->userInfo = $this->request->isLogin() ? $this->request->userInfo() : null;
    }

    /**
     * 创建商户入驻意向
     * 该方法用于处理商户入驻的申请创建流程。它首先验证参数，然后检查商户名称和电话是否已存在，
     * 防止重复申请。如果一切检查通过，则创建入驻意向，并通过消息通知管理员有新的入驻申请。
     *
     * @return \think\response\Json
     * @throws ValidateException
     */
    public function create()
    {
        // 验证参数
        $data = $this->checkParams();

        // 检查商户入驻功能是否开启
        if (!systemConfig('mer_intention_open')) {
            return app('json')->fail('未开启商户入驻');
        }

        // 如果用户已登录，记录用户ID
        if ($this->userInfo) {
            $data['uid'] = $this->userInfo->uid;
        }

        // 实例化商户仓库，用于后续的商户名称和电话存在性检查
        $make = app()->make(MerchantRepository::class);

        // 检查商户名称是否已存在
        if ($make->fieldExists('mer_name', $data['mer_name'])) {
            throw new ValidateException('商户名称已存在，不可申请');
        }

        // 检查申请电话是否已存在
        if ($make->fieldExists('mer_phone', $data['phone'])) {
            throw new ValidateException('手机号已存在，不可申请');
        }

        // 实例化管理员仓库，用于检查手机号是否已是管理员
        $adminRepository = app()->make(MerchantAdminRepository::class);

        // 检查手机号是否已是管理员
        if ($adminRepository->fieldExists('account', $data['phone'])) {
            throw new ValidateException('手机号已是管理员，不可申请');
        }

        // 创建商户入驻意向
        $intention = $this->repository->create($data);

        // 发送管理员通知，提示有新的商户入驻申请
        SwooleTaskService::admin('notice', [
            'type' => 'new_intention',
            'data' => [
                'title' => '商户入驻申请',
                'message' => '您有一个新的商户入驻申请',
                'id' => $intention->mer_intention_id
            ]
        ]);

        // 返回成功响应
        return app('json')->success('提交成功');
    }

    /**
     * 更新商户入驻意向信息。
     *
     * 本函数用于处理商户入驻意向的更新操作。首先，它验证指定的意向记录是否存在，
     * 然后检查商户入驻功能是否开启。如果一切就绪，它将更新意向记录，并发送通知。
     *
     * @param int $id 商户入驻意向的ID。
     * @return json 返回操作的结果，成功或失败。
     */
    public function update($id)
    {
        // 检查指定的商户入驻意向是否存在且未被删除
        if (!$this->repository->getWhere(['mer_intention_id' => (int)$id, 'uid' => $this->userInfo->uid, 'is_del' => 0]))
            return app('json')->fail('数据不存在');

        // 验证并获取提交的参数
        $data = $this->checkParams();

        // 检查商户入驻功能是否开启
        if (!systemConfig('mer_intention_open')) {
            return app('json')->fail('未开启商户入驻');
        }

        // 设置更新时间
        $data['create_time'] = date('Y-m-d H:i:s', time());

        // 更新商户入驻意向信息
        $this->repository->updateIntention((int)$id, $data);

        // 发送通知，提示有新的商户入驻申请
        SwooleTaskService::admin('notice', [
            'type' => 'new_intention',
            'data' => [
                'title' => '商户入驻申请',
                'message' => '您有一个新的商户入驻申请',
                'id' => $id
            ]
        ]);

        return app('json')->success('修改成功');
    }


    /**
     * 获取用户列表
     *
     * 本方法用于获取当前登录用户的相关列表信息。通过调用repository层的getList方法，结合当前用户的uid，
     * 进行数据查询。支持分页查询，以提高数据检索的效率和灵活性。
     *
     * @return \Illuminate\Http\JsonResponse
     * 返回一个JSON响应，包含查询到的数据。如果查询成功，数据将被封装在success方法返回的对象中。
     */
    public function lst()
    {
        // 获取当前请求的分页信息
        [$page, $limit] = $this->getPage();

        // 根据当前用户的uid，调用repository获取列表数据
        $data = $this->repository->getList(['uid' => $this->userInfo->uid], $page, $limit);

        // 返回查询结果的JSON响应
        return app('json')->success($data);
    }

    /**
     * 根据ID获取详细信息。
     *
     * 本函数旨在通过提供的ID从仓库中检索特定资源的详细信息。如果资源不存在或被禁用，
     * 函数将返回一个错误消息。如果资源存在且启用，将返回资源的详细信息。
     *
     * @param int $id 要查询的资源ID。强制转换为整数以确保数据类型正确。
     * @return mixed 如果资源不存在或已被禁用，返回一个错误的JSON响应；
     *               如果资源存在且启用，返回包含资源详细信息的JSON响应。
     */
    function detail($id)
    {
        // 从仓库中根据ID和当前用户UID获取资源详细信息
        $data = $this->repository->detail((int)$id, $this->userInfo->uid);

        // 检查数据是否存在，如果不存在，返回一个失败的JSON响应
        if (!$data) {
            return app('json')->fail('数据不存在');
        }

        // 如果资源的状态为1（启用状态），构造登录URL
        if ($data->status == 1) {
            $data['login_url'] = rtrim(systemConfig('site_url'), '/') . '/' . config('admin.merchant_prefix');
        }

        // 返回成功的JSON响应，包含资源详细信息
        return app('json')->success($data);
    }

    /**
     * 检查参数有效性并处理商户注册请求。
     * 该方法从请求中提取参数，验证参数的正确性，包括手机号、商户名、验证码等，
     * 并对验证码和商户分类、店铺类型的存在性进行校验。
     *
     * @return array 提交的参数数据，经过验证和处理。
     * @throws ValidateException 如果验证码不正确、商户分类或店铺类型不存在，则抛出验证异常。
     */
    protected function checkParams()
    {
        // 从请求中提取相关参数
        $data = $this->request->params(['phone', 'mer_name', 'name', 'images', 'merchant_category_id', 'mer_type_id', 'circle_id', 'business_id']);
        // 执行参数验证
        app()->make(MerchantIntentionValidate::class)->check($data);
        // 验证短信验证码的正确性
        // $check = app()->make(SmsService::class)->checkSmsCode($data['phone'], $data['code'], 'intention');
        // 将商户类型ID转换为整数类型
        $data['mer_type_id'] = (int)$data['mer_type_id'];
        // 如果验证码验证失败，则抛出异常
        // if (!$check) throw new ValidateException('验证码不正确');
        // 校验商户分类是否存在
        if (!app()->make(MerchantCategoryRepository::class)->get($data['merchant_category_id'])) throw new ValidateException('商户分类不存在');
        // 如果指定的店铺类型不存在，则抛出异常
        if ($data['mer_type_id'] && !app()->make(MerchantTypeRepository::class)->exists($data['mer_type_id']))
            throw new ValidateException('店铺类型不存在');
        // 移除验证码参数
        // unset($data['code']);
        // 返回验证通过的参数数据
        return $data;
    }


    /**
     * 商户分类
     * @Author:Qinii
     * @Date: 2020/9/15
     * @return mixed
     */
    public function cateLst()
    {
        $lst = app()->make(MerchantCategoryRepository::class)->getSelect();
        return app('json')->success($lst);
    }

    /**
     * 获取商家类型列表
     *
     * 本函数用于查询并返回商家类型的列表数据。通过调用MerchantTypeRepository类中的getSelect方法，
     * 获取到商家类型的下拉选项列表，以便在前端展示或进行进一步的操作。
     *
     * @return \Illuminate\Http\JsonResponse 返回一个包含商家类型列表的JSON响应
     */
    public function typeLst()
    {
        // 通过依赖注入的方式获取MerchantTypeRepository实例，并调用其getSelect方法获取商家类型列表
        $lst = app()->make(MerchantTypeRepository::class)->getSelect();

        // 使用app容器中定义的json助手函数，将获取到的商家类型列表封装到一个成功响应中并返回
        return app('json')->success($lst);
    }

    public function circles()
    {
        $type = $this->request->param('type');

        $data = app()->make(CircleRepository::class)->getAllOptions(1, 3, $type);
        $data = formatCascaderData($data, 'name');

        return app('json')->success($data);
    }
    /**
     * 用户关联的组织商户列表
     */
    public function business()
    {
        $where = $this->request->params(['name']);
        $where['status'] = 1;
        $where['type'] = 1;

        $list = app()->make(CircleRepository::class)->search($where)->select()->toArray();
        foreach($list as &$val) {
            $val['value'] = $val['circle_id'];
            $val['label'] = $val['name'];
        }

        return app('json')->success($list);
    }
}

