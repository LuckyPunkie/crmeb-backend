<?php

namespace app\controller\api\store\nearby;

use think\App;
use crmeb\basic\BaseController;
use crmeb\services\PayService;
use app\common\model\user\User;
use app\common\model\store\coupon\StoreCoupon;
use app\common\model\store\nearby\NearbyCouponOrder;
use think\facade\Db;
use think\facade\Log;

/**
 * 附近好店 代金券购买 C端API
 * POST /api/nearby/coupon/buy   需登录
 */
class NearbyVoucher extends BaseController
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    /**
     * 购买代金券
     * body: coupon_id, pay_type
     */
    public function buy()
    {
        $uid = $this->currentUid();
        if ($uid <= 0) {
            return app('json')->fail('请先登录');
        }

        $couponId = (int)$this->request->param('coupon_id', 0);
        $payType  = (string)$this->request->param('pay_type', 'weixin');

        if (!$couponId) {
            return app('json')->fail('参数错误');
        }

        $coupon = StoreCoupon::where('coupon_id', $couponId)
            ->where('status', 1)
            ->where('is_del', 0)
            ->find();

        if (!$coupon) {
            return app('json')->fail('代金券不存在或已下架');
        }

        // 库存检查
        if ($coupon['is_limited'] && $coupon['remain_count'] <= 0) {
            return app('json')->fail('代金券已售罄');
        }

        $payPrice = (float)$coupon['coupon_price'];
        $orderSn  = 'CV' . date('YmdHis') . rand(1000, 9999);

        // 创建购买订单
        NearbyCouponOrder::create([
            'order_sn'        => $orderSn,
            'uid'             => $uid,
            'mer_id'          => (int)$coupon['mer_id'],
            'coupon_id'       => $couponId,
            'pay_price'       => $payPrice,
            'pay_type'        => $payType,
            'paid'            => 0,
            'coupon_granted'  => 0,
            'is_del'          => 0,
            'create_time'     => time(),
            'update_time'     => time(),
        ]);

        // 模拟支付
        if ($payType === 'mock') {
            if (!systemConfig('pay_mock_open')) {
                return app('json')->fail('未开启模拟支付');
            }
            $this->doPaySuccess($orderSn, 'mock');
            return app('json')->success(['order_sn' => $orderSn, 'mock_paid' => true]);
        }

        try {
            $payService = new PayService($payType, [
                'order_sn'  => $orderSn,
                'pay_price' => $payPrice,
                'body'      => '代金券购买',
                'attach'    => 'coupon_buy',
            ], 'coupon_buy');

            $user = $this->currentUser();
            $payResult = $payService->pay($user);

            return app('json')->success([
                'order_sn'  => $orderSn,
                'pay_price' => $payPrice,
                'config'    => $payResult['config'] ?? $payResult,
            ]);
        } catch (\Exception $e) {
            Log::error('NearbyVoucher buy pay failed: ' . $e->getMessage());
            return app('json')->fail('支付配置失败: ' . $e->getMessage());
        }
    }

    /**
     * 支付成功后：标记订单 + 发放代金券给用户
     */
    public function doPaySuccess(string $orderSn, string $payType = 'weixin'): void
    {
        Db::transaction(function () use ($orderSn, $payType) {
            $affected = NearbyCouponOrder::getDB()
                ->where('order_sn', $orderSn)
                ->where('paid', 0)
                ->update([
                    'paid'        => 1,
                    'pay_type'    => $payType,
                    'pay_time'    => time(),
                    'update_time' => time(),
                ]);

            if (!$affected) {
                return; // 幂等：已处理
            }

            $order = NearbyCouponOrder::where('order_sn', $orderSn)->find();
            if (!$order || $order['coupon_granted']) {
                return;
            }

            $coupon = StoreCoupon::where('coupon_id', $order['coupon_id'])->find();
            if (!$coupon) {
                return;
            }

            // 计算有效期
            $startTime = null;
            $endTime   = null;
            if ($coupon['coupon_time'] > 0) {
                $startTime = date('Y-m-d H:i:s');
                $endTime   = date('Y-m-d H:i:s', strtotime("+{$coupon['coupon_time']} days"));
            } elseif ($coupon['use_start_time']) {
                $startTime = date('Y-m-d H:i:s', strtotime($coupon['use_start_time']));
                $endTime   = date('Y-m-d H:i:s', strtotime($coupon['use_end_time']));
            }

            // 发放代金券到用户账户
            Db::name('store_coupon_user')->insert([
                'coupon_id'     => $order['coupon_id'],
                'mer_id'        => $order['mer_id'],
                'uid'           => $order['uid'],
                'coupon_title'  => $coupon['title'],
                'coupon_price'  => $coupon['coupon_price'],
                'use_min_price' => $coupon['use_min_price'],
                'start_time'    => $startTime,
                'end_time'      => $endTime,
                'type'          => 'buy',
                'send_id'       => $order['coupon_id'],
                'status'        => 0,
                'is_fail'       => 0,
                'create_time'   => date('Y-m-d H:i:s'),
            ]);

            // 扣减库存
            if ($coupon['is_limited']) {
                StoreCoupon::getDB()
                    ->where('coupon_id', $order['coupon_id'])
                    ->where('remain_count', '>', 0)
                    ->dec('remain_count', 1)
                    ->update();
            }

            NearbyCouponOrder::getDB()
                ->where('order_sn', $orderSn)
                ->update(['coupon_granted' => 1, 'update_time' => time()]);
        });
    }

    protected function currentUid(): int
    {
        try {
            if ($this->request->isLogin()) {
                return (int)$this->request->uid();
            }
        } catch (\Throwable $e) {
        }
        return 0;
    }

    protected function currentUser(): ?User
    {
        try {
            if ($this->request->isLogin()) {
                $user = $this->request->userInfo();
                return $user instanceof User ? $user : null;
            }
        } catch (\Throwable $e) {
        }
        return null;
    }
}
