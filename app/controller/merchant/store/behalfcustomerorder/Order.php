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
namespace app\controller\merchant\store\behalfcustomerorder;

use think\App;
use think\facade\Cache;
use crmeb\services\SmsService;
use crmeb\basic\BaseController;
use app\validate\api\UserAuthValidate;
use app\validate\merchant\OrderValidate;
use app\common\repositories\user\UserRepository;
use app\common\repositories\store\order\StoreCartRepository;
use app\common\repositories\store\order\StoreGroupOrderRepository;
use app\common\repositories\store\order\MerchantOrderCreateRepository;

class Order extends BaseController
{
    protected $validate;
    protected $repository;
    protected $cartRepository;
    protected $userRepository;

    public function __construct(App $app, OrderValidate $validate, MerchantOrderCreateRepository $repository, StoreCartRepository $cartRepository, UserRepository $userRepository)
    {
        parent::__construct($app);
        $this->validate = $validate;
        $this->repository = $repository;
        $this->cartRepository = $cartRepository;
        $this->userRepository = $userRepository;
    }

    public function __destruct()
    {
        unset($this->validate);
        unset($this->repository);
        unset($this->cartRepository);
        unset($this->userRepository);
    }

    public function getValidate()
    {
        return $this->validate;
    }

    public function getRepository()
    {
        return $this->repository;
    }
    public function getCartRepository()
    {
        return $this->cartRepository;
    }

    public function getUserRepository()
    {
        return $this->userRepository;
    }
    /**
     * 检查订单
     *
     * @return void
     */
    public function check()
    {
        $params = $this->request->params(['uid', 'cart_ids', 'address_id', 'delivery_way', ['use_coupon', []], 'is_free_shipping', 'use_integral', 'tourist_unique_key']);

        $validate = $this->getValidate();
        if (!$validate->sceneCheck($params)) {
            return app('json')->fail($validate->getError());
        }
        $params['merId'] = $this->request->merId();

        if (!$this->isValidIntersection($params['cart_ids'], $params['uid'], $params['merId'], $params['tourist_unique_key'])) {
            return app('json')->fail('数据无效');
        }

        return app('json')->success('ok', $this->getRepository()->checkOrder($params));
    }
    /**
     * 创建订单
     *
     * @return void
     */
    public function create()
    {
        $params = $this->request->params(
            [
                'uid',                  // 用户ID
                'cart_ids',             // 购物车ID集合,数组类型
                'address_id',           // 地址ID
                'delivery_way',         // 配送方式
                ['use_coupon', []],     // 使用的优惠券ID集合,数组类型
                'is_free_shipping',     // 是否免运费
                'use_integral',         // 是否使用积分
                'tourist_unique_key',   // 游客唯一标识
                'pay_type',             // 支付方式
                'key',                  // 订单key
                'mark',                 // 备注
                'old_pay_price'        // 原支付价格
            ]
        );

        $params['merId'] = $this->request->merId();
        $merchant = $this->getRepository()->merchantInfo($params['merId']);

        // 验证参数
        $validate = $this->getValidate();
        if (!$validate->sceneCreate($params, $merchant)) {
            return app('json')->fail($validate->getError());
        }
        $params['pay_type'] = ($params['pay_type'] === 'pc') ? 'balance' : $params['pay_type'];
        if (!in_array($params['pay_type'], MerchantOrderCreateRepository::PAY_TYPE, true)) {
            return app('json')->fail('请选择正确的支付方式');
        }
        // 幂等验证，防止重复提交订单
        if (!$this->isValidIntersection($params['cart_ids'], $params['uid'], $params['merId'], $params['tourist_unique_key'])) {
            return app('json')->fail('已生成订单，请勿重复提交～');
        }
        // 创建订单
        $repository = $this->getRepository();
        $groupOrder = $repository->createOrder($params);
        if (!$groupOrder) {
            return app('json')->fail('创建订单失败');
        }
        $data = ['order_id' => $groupOrder->group_order_id, 'pay_type' => $params['pay_type'], 'pay_price' => $groupOrder['pay_price']];
        // 金额为0，直接设置为已付款,并返回支付成功信息
        if ($groupOrder['pay_price'] == 0) {
            $repository->paySuccess($groupOrder);
            return app('json')->success('支付成功', $data);
        }
        // 线下支付，直接返回成功
        if ($params['pay_type'] == 'offline') {
            return app('json')->success('线下支付，请确认', $data);
        }

        return app('json')->success('创建订单成功', $data);
    }
    /**
     * 获取支付方式列表，支付方式配置信息等
     *
     * @return void
     */
    public function payConfig()
    {
        $params = $this->request->params(['uid']);
        $validate = $this->getValidate();
        if (!$validate->scenePayConfig($params)) {
            return app('json')->fail($validate->getError());
        }
        $uid = $params['uid'];

        $config = $this->repository->merchantPayConfig($uid, $this->request->merId());
        if (!$config) {
            return app('json')->fail('获取支付配置失败');
        }

        $config['yue_pay_status'] = $uid ? $config['yue_pay_status'] : 0;
        return app('json')->success($config);
    }
    /**
     * 订单付款操作
     *
     * @param [type] $id
     * @return json
     */
    public function pay($id, StoreGroupOrderRepository $groupOrderRepository)
    {
        $params = $this->request->params(['uid', 'pay_type', 'phone', 'sms_code', 'auth_code']);
        $params['id'] = $id;
        $validate = $this->getValidate();
        $groupOrder = $groupOrderRepository->detail($params['uid'], $id, false);
        if (!$validate->scenePay($params, $groupOrder)) {
            return app('json')->fail($validate->getError());
        }

        $repository = $this->getRepository();
        $payType = $params['pay_type'];
        if ($payType == 'balance') {
            $smsCode = app()->make(SmsService::class)->checkSmsCode($params['phone'], $params['sms_code'], 'balance');
            if (!$smsCode) {
                return app('json')->fail('验证码不正确');
            }
        }
        $repository->changePayType($groupOrder, array_search($payType, MerchantOrderCreateRepository::PAY_TYPE));
        // 金额为0，直接设置为已付款
        if ($groupOrder['pay_price'] == 0) {
            $repository->paySuccess($groupOrder);
            return app('json')->success('支付成功', ['order_id' => $groupOrder['group_order_id']]);
        }
        // 线下支付，直接返回成功
        if ($payType == 'offline') {
            return app('json')->success('线下支付，请确认', ['order_id' => $groupOrder['group_order_id']]);
        }
        $user = $this->getUserRepository()->userInfo($params['uid']);
        // 支付操作
        try {
            return $repository->merchantPay($params, $user, $groupOrder);
        } catch (\Exception $e) {
            return app('json')->fail('支付失败', $e->getMessage(), ['order_id' => $groupOrder->group_order_id, 'pay_price' => $groupOrder->pay_price]);
        }
    }
    /**
     * 获取支付状态
     *
     * @param $id
     * @return void
     */
    public function payStatus($id)
    {
        $params = $this->request->params(['uid', 'pay_type']);
        $params['id'] = $id;

        $validate = $this->getValidate();
        if (!$validate->sceneStatus($params)) {
            return app('json')->fail($validate->getError());
        }
        $orderRepository = $this->getRepository();
        $status = $orderRepository->payStatus($params);

        return app('json')->success($status);
    }
    /**
     * 余额支付获取验证码
     *
     * @param UserAuthValidate $validate
     * @return void
     */
    public function verify(UserAuthValidate $validate)
    {
        $data = $this->request->params(['phone', ['type', 'balance']]);
        $validate->sceneVerify()->check($data);
        $smsLimitKey = 'sms_limit_' . $data['phone'];
        $limit = Cache::get($smsLimitKey) ?? 0;
        $smsLimit = systemConfig('smsLimit');
        if ($smsLimit && $limit > $smsLimit) {
            return app('json')->fail('请求太频繁请稍后再试');
        }
        try {
            $smsCode = str_pad(random_int(1, 9999), 4, 0, STR_PAD_LEFT);
            $smsTime = systemConfig('sms_time') ?? 30;
            SmsService::create()->send($data['phone'], 'VERIFICATION_CODE', ['code' => $smsCode, 'time' => $smsTime]);
        } catch (\Exception $e) {
            return app('json')->fail($e->getMessage());
        }

        $smsKey = app()->make(SmsService::class)->sendSmsKey($data['phone'], $data['type']);
        Cache::set($smsKey, $smsCode, $smsTime * 60);
        Cache::set($smsLimitKey, $limit + 1, 60);
        // 短信发送成功
        return app('json')->success('短信发送成功');
    }

    private function isValidIntersection(array $cartIds, int $uid, int $merId, string $touristUniqueKey)
    {
        return (count($cartIds) == count($this->getCartRepository()->validIntersection($cartIds, $uid, $merId, $touristUniqueKey)));
    }
}
