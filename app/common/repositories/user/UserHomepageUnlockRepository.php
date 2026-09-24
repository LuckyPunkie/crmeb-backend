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

namespace app\common\repositories\user;

use app\common\repositories\wechat\WechatUserRepository;
use crmeb\services\pay\Pay;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 主页付费解锁
 * 订单号前缀：HP
 * 事件：pay_success_homepage_unlock / pay.notify
 */
class UserHomepageUnlockRepository
{
    public const TABLE = 'user_homepage_unlock';
    public const ORDER_PREFIX = 'HP';
    public const ATTACH = 'homepage_unlock';

    /**
     * buyer 是否已解锁 target 的主页
     */
    public function checkUnlocked(int $targetUid, int $buyerUid): bool
    {
        if ($targetUid <= 0 || $buyerUid <= 0 || $targetUid === $buyerUid) return false;
        return (bool)Db::name(self::TABLE)
            ->where('buyer_uid', $buyerUid)
            ->where('target_uid', $targetUid)
            ->where('pay_status', 1)
            ->count();
    }

    /**
     * 发起解锁：创建订单 + 走对应支付方式
     */
    public function unlock(int $targetUid, int $buyerUid, string $payType = 'weixin', string $returnUrl = ''): array
    {
        if ($targetUid <= 0) throw new ValidateException('参数错误');
        if ($targetUid === $buyerUid) throw new ValidateException('不能解锁自己');

        $target = Db::name('user')->where('uid', $targetUid)->field('uid,nickname')->find();
        if (!$target) throw new ValidateException('用户不存在');

        $profile = Db::name('user_profile')
            ->where('uid', $targetUid)
            ->field('homepage_unlock_price')
            ->find();
        $price = round((float)($profile['homepage_unlock_price'] ?? 0), 2);

        // 已解锁：直接返回，避免重复扣款
        if ($this->checkUnlocked($targetUid, $buyerUid)) {
            throw new ValidateException('已解锁，无需重复支付', 10008);
        }

        // 免费公开：直接落一条 pay_status=1，无需支付
        if ($price <= 0) {
            $this->markUnlocked($buyerUid, $targetUid, '', 0);
            return ['paid' => true, 'order_no' => '', 'amount' => 0.0];
        }

        // 清理该 (buyer, target) 的历史待支付单，避免 UNIQUE 冲突
        Db::name(self::TABLE)
            ->where('buyer_uid', $buyerUid)
            ->where('target_uid', $targetUid)
            ->where('pay_status', 0)
            ->delete();

        $orderNo = self::ORDER_PREFIX . date('YmdHis') . mt_rand(1000, 9999);

        if ($payType === 'balance') {
            return $this->payByBalance($buyerUid, $targetUid, $orderNo, $price);
        }

        if ($payType === 'mock') {
            if (!systemConfig('pay_mock_open')) {
                throw new ValidateException('未开启模拟支付');
            }
            Db::name(self::TABLE)->insert([
                'buyer_uid' => $buyerUid,
                'target_uid' => $targetUid,
                'order_no' => $orderNo,
                'amount' => $price,
                'pay_status' => 0,
            ]);
            $this->paySuccess($orderNo);
            return ['paid' => true, 'order_no' => $orderNo, 'amount' => $price, 'mock' => true];
        }

        if (!in_array($payType, ['weixin', 'routine', 'alipay'], true)) {
            throw new ValidateException('请选择正确的支付方式');
        }

        Db::name(self::TABLE)->insert([
            'buyer_uid' => $buyerUid,
            'target_uid' => $targetUid,
            'order_no' => $orderNo,
            'amount' => $price,
            'pay_status' => 0,
        ]);

        return $this->createThirdPartyPay($orderNo, $price, $buyerUid, $payType, $returnUrl);
    }

    /**
     * 余额支付：扣款 + 写账单 + 标记解锁
     */
    protected function payByBalance(int $buyerUid, int $targetUid, string $orderNo, float $amount): array
    {
        if (!systemConfig('yue_pay_status') || !systemConfig('balance_func_status')) {
            throw new ValidateException('未开启余额支付');
        }
        $user = app()->make(UserRepository::class)->get($buyerUid);
        if ((float)($user['now_money'] ?? 0) < $amount) {
            throw new ValidateException('余额不足，请更换支付方式');
        }

        Db::transaction(function () use ($user, $amount, $buyerUid, $targetUid, $orderNo) {
            $user->now_money = bcsub((string)$user->now_money, (string)$amount, 2);
            $user->save();

            Db::name(self::TABLE)->insert([
                'buyer_uid' => $buyerUid,
                'target_uid' => $targetUid,
                'order_no' => $orderNo,
                'amount' => $amount,
                'pay_status' => 1,
            ]);

            app()->make(UserBillRepository::class)->decBill(
                $buyerUid, 'now_money', 'pay_product', [
                    'link_id' => 0,
                    'status' => 1,
                    'title' => '解锁主页',
                    'number' => $amount,
                    'mark' => '余额支付' . floatval($amount) . '元解锁主页',
                    'balance' => $user->now_money,
                ]
            );
        });

        return ['paid' => true, 'order_no' => $orderNo, 'amount' => $amount];
    }

    /**
     * 微信/支付宝发起支付
     */
    protected function createThirdPartyPay(string $orderNo, float $amount, int $buyerUid, string $payType, string $returnUrl = ''): array
    {
        $user = app()->make(UserRepository::class)->get($buyerUid);
        if (!$user) throw new ValidateException('用户不存在');

        $pay = app()->make(Pay::class);
        $orderParams = [
            'order_sn' => $orderNo,
            'pay_price' => $amount,
            'attach' => self::ATTACH,
            'body' => '解锁主页',
        ];

        switch ($payType) {
            case 'weixin':
                $openId = app()->make(WechatUserRepository::class)->idByOpenId($user['wechat_user_id']);
                if (!$openId) throw new ValidateException('请关联微信公众号');
                $config = $pay->pay($payType, $orderParams, $openId);
                break;
            case 'routine':
                $openId = app()->make(WechatUserRepository::class)->idByRoutineId($user['wechat_user_id']);
                if (!$openId) throw new ValidateException('请关联微信小程序');
                $config = $pay->pay($payType, $orderParams, $openId);
                break;
            case 'alipay':
                $orderParams['return_url'] = $returnUrl;
                $config = (new Pay('alipay'))->pay($payType, $orderParams, '');
                break;
            default:
                throw new ValidateException('请选择正确的支付方式');
        }

        return [
            'paid' => false,
            'order_no' => $orderNo,
            'pay_type' => $payType,
            'amount' => $amount,
            'config' => $config,
        ];
    }

    /**
     * 免费/无订单场景：直接落一条已解锁记录（幂等）
     */
    protected function markUnlocked(int $buyerUid, int $targetUid, string $orderNo, float $amount): void
    {
        $exists = Db::name(self::TABLE)
            ->where('buyer_uid', $buyerUid)
            ->where('target_uid', $targetUid)
            ->find();
        if ($exists) {
            if ((int)$exists['pay_status'] !== 1) {
                Db::name(self::TABLE)->where('id', $exists['id'])->update([
                    'pay_status' => 1,
                    'order_no' => $orderNo ?: $exists['order_no'],
                    'amount' => $amount,
                ]);
            }
            return;
        }
        Db::name(self::TABLE)->insert([
            'buyer_uid' => $buyerUid,
            'target_uid' => $targetUid,
            'order_no' => $orderNo,
            'amount' => $amount,
            'pay_status' => 1,
        ]);
    }

    /**
     * 支付成功回调（订单号 HP 前缀）
     */
    public function paySuccess(string $orderNo): void
    {
        if ($orderNo === '') return;
        $order = Db::name(self::TABLE)->where('order_no', $orderNo)->find();
        if (!$order) throw new ValidateException('订单不存在');
        if ((int)$order['pay_status'] === 1) return;

        Db::name(self::TABLE)->where('id', $order['id'])->update([
            'pay_status' => 1,
        ]);
    }
}
