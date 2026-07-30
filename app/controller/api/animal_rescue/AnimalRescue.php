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

namespace app\controller\api\animal_rescue;

use app\common\repositories\animal_rescue\AdoptionRepository;
use app\common\repositories\animal_rescue\AnimalRescueRepository;
use app\common\dao\animal_rescue\AdoptionDepositDao;
use app\common\dao\animal_rescue\AnimalRescueOrderDao;
use app\common\dao\animal_rescue\CloudAdoptionOrderDao;
use app\validate\api\AnimalRescueValidate;
use crmeb\basic\BaseController;
use crmeb\services\PayService;
use think\App;
use think\exception\ValidateException;
use think\facade\Log;

/**
 * 流浪动物救助 - 移动端API控制器
 * Class AnimalRescue
 * @package app\controller\api\animal_rescue
 */
class AnimalRescue extends BaseController
{
    /**
     * @var AnimalRescueRepository
     */
    protected $repository;
    protected $user;

    /**
     * @param App $app
     * @param AnimalRescueRepository $repository
     */
    public function __construct(App $app, AnimalRescueRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->user = $this->request->isLogin() ? $this->request->userInfo() : null;
        // 检查模块开关
        if (!systemConfig('animal_rescue_status')) {
            throw new ValidateException('未开启流浪动物救助功能');
        }
    }

    /**
     * 帖子列表（无需登录）
     * @return \think\response\Json
     */
    public function lst()
    {
        $where = $this->request->params(['keyword', 'type', 'city_id']);
        $where = array_merge($where, $this->repository::IS_SHOW_WHERE);
        [$page, $limit] = $this->getPage();
        $uid = $this->request->isLogin() ? (int)$this->request->uid() : 0;
        return app('json')->success($this->repository->getApiList($where, $page, $limit, $uid));
    }

    /**
     * 帖子详情（无需登录）
     * @param int $id
     * @return \think\response\Json
     */
    public function show($id)
    {
        $uid = $this->request->isLogin() ? (int)$this->request->uid() : 0;
        $info = $this->repository->getDetail((int)$id, $uid);
        if (!$info) {
            return app('json')->fail('帖子不存在');
        }
        return app('json')->success($info);
    }

    /**
     * 分类统计数（无需登录）
     * @return \think\response\Json
     */
    public function categoryCount()
    {
        return app('json')->success($this->repository->getCategoryCount());
    }

    /**
     * 发布帖子（需登录）
     * @param AnimalRescueValidate $validate
     * @return \think\response\Json
     */
    public function create(AnimalRescueValidate $validate)
    {
        $data = $this->request->params([
            'type', 'title', 'animal_name', 'animal_type', 'city_id',
            'phone', 'target_amount', 'deposit_amount', 'deposit_thaw_months',
            'content', 'images', 'animal_age', 'animal_health', 'end_time'
        ]);
        $validate->scene('create')->check($data);

        // 频率限制：发帖 3次/分钟
        if (!$this->throttle('createPost', $this->request->uid(), 3, 60)) {
            return app('json')->fail('操作过于频繁，请稍后重试');
        }

        try {
            $postId = $this->repository->createPost($data, $this->request->uid());
            return app('json')->success(['post_id' => $postId], '发布成功');
        } catch (\think\exception\ValidateException $e) {
            return app('json')->fail($e->getError() ?: $e->getMessage());
        } catch (\Exception $e) {
            Log::error('animal_rescue create error: ' . $e->getMessage() . ' uid=' . $this->request->uid());
            return app('json')->fail('操作失败，请稍后重试');
        }
    }

    /**
     * 编辑帖子（需登录）
     * @param int $id
     * @param AnimalRescueValidate $validate
     * @return \think\response\Json
     */
    public function update($id, AnimalRescueValidate $validate)
    {
        $data = $this->request->params([
            'title', 'animal_name', 'animal_type', 'city_id', 'phone',
            'target_amount', 'deposit_amount', 'deposit_thaw_months',
            'content', 'images', 'animal_age', 'animal_health', 'end_time'
        ]);
        if (empty($data)) {
            return app('json')->fail('请提供要修改的内容');
        }

        $result = $this->repository->updatePost((int)$id, $this->request->uid(), $data);
        if (!$result) {
            return app('json')->fail('编辑失败，帖子不存在或无权操作');
        }
        return app('json')->success('编辑成功');
    }

    /**
     * 删除帖子（需登录）
     * @param int $id
     * @return \think\response\Json
     */
    public function delete($id)
    {
        $result = $this->repository->deletePost((int)$id, $this->request->uid());
        if (!$result) {
            return app('json')->fail('删除失败，帖子不存在或无权操作');
        }
        return app('json')->success('删除成功');
    }

    /**
     * 捐款/云养下单（需登录）
     * @param AnimalRescueValidate $validate
     * @return \think\response\Json
     */
    public function donate(AnimalRescueValidate $validate)
    {
        $data = $this->request->params(['post_id', 'amount', 'pay_type', 'is_anonymous', 'message', 'is_subscribe']);
        $validate->scene('donate')->check($data);

        $post = $this->repository->getDetail((int)$data['post_id']);
        if (!$post) {
            return app('json')->fail('帖子不存在');
        }
        try {
            // 根据帖子类型走不同的下单逻辑
            if ($post->type == 3) {
                $result = $this->repository->cloudOrder($this->request->uid(), (int)$data['post_id'], $data);
                return app('json')->success($result);
            }
            $result = $this->repository->donateOrder($this->request->uid(), (int)$data['post_id'], $data);
            return app('json')->success($result);
        } catch (\think\exception\ValidateException $e) {
            return app('json')->fail($e->getError() ?: $e->getMessage());
        }
    }

    /**
     * 捐款/云养/保证金支付（需登录）
     * @param int $order_id
     * @return \think\response\Json
     */
    public function donatePay($order_id)
    {
        $payType = $this->request->param('pay_type', 'weixin');
        $uid = $this->request->uid();

        // 频率限制：支付操作 5次/分钟
        if (!$this->throttle('donatePay', $uid, 5, 60)) {
            return app('json')->fail('操作过于频繁，请稍后重试');
        }

        $rescueDao = app()->make(AnimalRescueOrderDao::class);
        $cloudDao = app()->make(CloudAdoptionOrderDao::class);
        $depositDao = app()->make(AdoptionDepositDao::class);

        // order_type 由前端明确传入，避免不同表 ID 相同导致查错
        $orderTypeHint = $this->request->param('order_type', '');
        if ($orderTypeHint === 'deposit') {
            $order = $depositDao->get((int)$order_id);
            $orderType = 'deposit';
        } elseif ($orderTypeHint === 'cloud') {
            $order = $cloudDao->get((int)$order_id);
            $orderType = 'cloud';
        } else {
            // 兼容旧逻辑：救助捐款
            $order = $rescueDao->get((int)$order_id);
            $orderType = 'rescue';
            if (!$order) {
                $order = $cloudDao->get((int)$order_id);
                $orderType = 'cloud';
            }
            if (!$order) {
                $order = $depositDao->get((int)$order_id);
                $orderType = 'deposit';
            }
        }

        if (!$order) {
            return app('json')->fail('订单不存在');
        }
        if ($orderType === 'deposit') {
            if ($order->status != 1 || !empty($order->pay_time)) {
                return app('json')->fail('保证金已缴纳，请勿重复支付');
            }
        } else {
            if ($order->paid == 1) {
                return app('json')->fail('订单不存在或已支付');
            }
        }
        if ((int)$order->uid !== (int)$uid) {
            return app('json')->fail('无权操作此订单');
        }

        if ($orderType === 'deposit') {
            $body = '领养保证金';
            $attach = 'adoption_deposit';
            $orderSn = $order->order_sn;
            $amount = $order->amount;
        } elseif ($orderType === 'cloud') {
            $body = '云养月捐';
            $attach = 'cloud_adoption';
            $orderSn = $order->order_sn;
            $amount = $order->amount;
        } else {
            $body = '流浪动物救助捐款';
            $attach = 'animal_rescue';
            $orderSn = $order->order_sn;
            $amount = $order->amount;
        }

        try {
            // 模拟支付：后台「支付设置-基础配置」开启 pay_mock_open，且前端选择 mock
            if ($payType === 'mock') {
                if (!systemConfig('pay_mock_open')) {
                    return app('json')->fail('未开启模拟支付');
                }
                if ($orderType === 'deposit') {
                    app()->make(AdoptionRepository::class)->depositPaySuccess($orderSn, $payType);
                } elseif ($orderType === 'cloud') {
                    $this->repository->cloudPaySuccess($orderSn, $payType);
                } else {
                    $this->repository->donatePaySuccess($orderSn, $payType);
                }
                Log::info('animal_rescue mock pay success: order_sn=' . $orderSn . ' type=' . $orderType . ' uid=' . $uid . ' amount=' . $amount);
                return app('json')->success([
                    'status' => 'success',
                    'mock' => true,
                    'order_sn' => $orderSn,
                    'pay_price' => $amount,
                    'entered' => true,
                ], '模拟支付成功，资金已入账');
            }

            $payService = new PayService($payType, [
                'order_sn' => $orderSn,
                'pay_price' => $amount,
                'body' => $body,
                'attach' => $attach,
            ], 'order');

            $config = $payService->pay($this->request->userInfo());
            return app('json')->success($config);
        } catch (\Exception $e) {
            Log::error('animal_rescue donatePay error: ' . $e->getMessage() . ' uid=' . $uid . ' order_id=' . $order_id);
            return app('json')->fail('支付调起失败，请稍后重试');
        }
    }

    /**
     * 提交领养申请（需登录）
     * @param AnimalRescueValidate $validate
     * @param AdoptionRepository $adoptionRepo
     * @return \think\response\Json
     */
    public function applyAdoption(AnimalRescueValidate $validate, AdoptionRepository $adoptionRepo)
    {
        $data = $this->request->params([
            'post_id', 'real_name', 'phone', 'id_card', 'address', 'income_info', 'housing_type', 'agreed'
        ]);
        $data['phone'] = $data['phone'] ?? '';
        $validate->scene('applyAdoption')->check($data);

        try {
            $applicationId = $adoptionRepo->apply($this->request->uid(), (int)$data['post_id'], $data);
            return app('json')->success(['application_id' => $applicationId], '申请提交成功，请等待审核');
        } catch (\Exception $e) {
            Log::error('animal_rescue applyAdoption error: ' . $e->getMessage() . ' uid=' . $this->request->uid());
            return app('json')->fail('操作失败，请稍后重试');
        }
    }

    /**
     * 缴纳保证金（需登录）
     * @param AnimalRescueValidate $validate
     * @param AdoptionRepository $adoptionRepo
     * @return \think\response\Json
     */
    public function payDeposit(AnimalRescueValidate $validate, AdoptionRepository $adoptionRepo)
    {
        $data = $this->request->params(['application_id', 'pay_type']);
        $data['pay_type'] = $data['pay_type'] ?? 'weixin';
        $validate->scene('payDeposit')->check($data);

        try {
            $result = $adoptionRepo->payDeposit($this->request->uid(), (int)$data['application_id'], $data);
            return app('json')->success($result, '保证金下单成功');
        } catch (\Exception $e) {
            Log::error('animal_rescue payDeposit error: ' . $e->getMessage() . ' uid=' . $this->request->uid());
            return app('json')->fail('操作失败，请稍后重试');
        }
    }

    /**
     * 我的参与记录（需登录）
     * @return \think\response\Json
     */
    public function myRecords()
    {
        $where = $this->request->params(['type']);
        [$page, $limit] = $this->getPage();
        return app('json')->success(
            $this->repository->getMyRecords($this->request->uid(), $where, $page, $limit)
        );
    }

    /**
     * 我的发布列表（需登录）
     * @return \think\response\Json
     */
    public function myPosts()
    {
        $where = $this->request->params(['type']);
        [$page, $limit] = $this->getPage();
        return app('json')->success(
            $this->repository->getMyPosts($this->request->uid(), $where, $page, $limit)
        );
    }

    /**
     * 发布者查看自己帖子下的领养申请列表（需登录）
     * @param int $post_id
     * @param AdoptionRepository $adoptionRepo
     * @return \think\response\Json
     */
    public function postApplications($post_id, AdoptionRepository $adoptionRepo)
    {
        $where = $this->request->params(['status']);
        [$page, $limit] = $this->getPage();
        return app('json')->success(
            $adoptionRepo->getPostApplications((int)$post_id, $this->request->uid(), $where, $page, $limit)
        );
    }

    /**
     * 查看申请详情（发布者或申请人）（需登录）
     * @param int $id
     * @param AdoptionRepository $adoptionRepo
     * @return \think\response\Json
     */
    public function applicationDetail($id, AdoptionRepository $adoptionRepo)
    {
        return app('json')->success(
            $adoptionRepo->getApplicationDetail((int)$id, $this->request->uid())
        );
    }

    /**
     * 发布者审核领养申请（需登录）
     * @param int $id
     * @param AnimalRescueValidate $validate
     * @param AdoptionRepository $adoptionRepo
     * @return \think\response\Json
     */
    public function auditAdoption($id, AnimalRescueValidate $validate, AdoptionRepository $adoptionRepo)
    {
        $data = $this->request->params(['status', 'remark']);
        $data['status'] = (int)($data['status'] ?? 0);
        $validate->scene('audit')->check($data);

        try {
            $adoptionRepo->auditByPublisher((int)$id, $this->request->uid(), $data['status'], $data['remark'] ?? '');
            $msg = $data['status'] == 2 ? '审核通过' : '已拒绝';
            return app('json')->success($msg);
        } catch (\Exception $e) {
            Log::error('animal_rescue auditAdoption error: ' . $e->getMessage() . ' uid=' . $this->request->uid());
            return app('json')->fail('操作失败，请稍后重试');
        }
    }

    /**
     * 申请人查看自己的领养申请列表（需登录）
     * @param AdoptionRepository $adoptionRepo
     * @return \think\response\Json
     */
    public function myApplications(AdoptionRepository $adoptionRepo)
    {
        $where = $this->request->params(['status']);
        [$page, $limit] = $this->getPage();
        return app('json')->success(
            $adoptionRepo->getMyApplications($this->request->uid(), $where, $page, $limit)
        );
    }

    /**
     * 当前用户是否可发月捐（认证救助站）
     */
    public function shelterCheck(\app\common\repositories\animal_rescue\FundAuditRepository $fundRepo)
    {
        $merId = $fundRepo->resolveShelterMerIdByUid($this->request->uid());
        $shelter = $merId ? $fundRepo->buildShelterInfo($merId) : null;
        return app('json')->success([
            'can_publish_monthly' => (bool)$shelter,
            'mer_id' => $merId,
            'shelter' => $shelter,
        ]);
    }

    /**
     * 提交拨款凭证
     */
    public function submitFundVoucher($id, \app\common\repositories\animal_rescue\FundAuditRepository $fundRepo)
    {
        $data = $this->request->params(['cost_list', 'invoice_images', 'other_files', 'remark', 'submit']);
        try {
            // submit=0 仅保存草稿；默认提交进入审核
            if (isset($data['submit']) && (int)$data['submit'] === 0) {
                $auditId = $fundRepo->saveVoucherDraft((int)$id, $this->request->uid(), $data);
                return app('json')->success(['audit_id' => $auditId], '凭证已保存');
            }
            $auditId = $fundRepo->submitVoucher((int)$id, $this->request->uid(), $data);
            return app('json')->success(['audit_id' => $auditId], '凭证已提交，等待平台审核');
        } catch (\think\exception\ValidateException $e) {
            return app('json')->fail($e->getError() ?: $e->getMessage());
        } catch (\Exception $e) {
            Log::error('animal_rescue submitFundVoucher: ' . $e->getMessage());
            return app('json')->fail('提交失败，请稍后重试');
        }
    }

    /**
     * 查看帖子拨款凭证
     */
    public function fundVoucherDetail($id, \app\common\repositories\animal_rescue\FundAuditRepository $fundRepo)
    {
        $post = $this->repository->getDetail((int)$id);
        if (!$post) {
            return app('json')->fail('帖子不存在');
        }
        if ((int)$post['uid'] !== (int)$this->request->uid()) {
            return app('json')->fail('无权查看');
        }
        $auditId = (int)($post['audit_id'] ?? 0);
        $audit = $auditId ? $fundRepo->getAuditDetail($auditId) : null;
        return app('json')->success([
            'post' => $post,
            'audit' => $audit,
        ]);
    }

    /**
     * 简单频率限制（基于缓存）
     * @param string $action 操作标识
     * @param int $uid 用户ID
     * @param int $maxAttempts 最大尝试次数
     * @param int $decaySeconds 衰减时间（秒）
     * @return bool true=通过, false=频率超限
     */
    private function throttle(string $action, int $uid, int $maxAttempts = 5, int $decaySeconds = 60): bool
    {
        $key = 'throttle:animal_rescue:' . $action . ':' . $uid;
        try {
            $cache = app()->cache;
            $attempts = (int)$cache->get($key, 0);
            if ($attempts >= $maxAttempts) {
                return false;
            }
            $cache->set($key, $attempts + 1, $decaySeconds);
            return true;
        } catch (\Exception $e) {
            return true;
        }
    }
}
