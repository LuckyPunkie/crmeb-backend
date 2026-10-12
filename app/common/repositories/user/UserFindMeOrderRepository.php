<?php

namespace app\common\repositories\user;

use app\common\repositories\wechat\WechatUserRepository;
use crmeb\services\pay\Pay;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 找人付费查询：单次（绑定查询类型 + 关键词）/ 365天无限制
 * 订单号前缀：FM
 * 事件：pay_success_find_me / pay.notify
 */
class UserFindMeOrderRepository
{
    public const TABLE = 'user_find_me_order';
    public const ORDER_PREFIX = 'FM';
    public const ATTACH = 'find_me';

    public const PLAN_ONCE = 1;
    public const PLAN_YEAR = 2;

    /** plan => [标识, 文案, 默认价格, 价格配置键（systemConfig 有值时覆盖默认价）] */
    protected const PLANS = [
        self::PLAN_YEAR => ['year', '365天无限制查询', 999, 'find_me_year_price'],
        self::PLAN_ONCE => ['once', '单次查询', 9.9, 'find_me_once_price'],
    ];

    public const YEAR_SECONDS = 365 * 86400;

    /**
     * 查询页用：套餐 + 当前年卡到期时间
     */
    public function info(int $uid): array
    {
        $plans = [];
        foreach (self::PLANS as $plan => $row) {
            $plans[] = ['plan' => $row[0], 'title' => $row[1], 'price' => $this->price($plan)];
        }
        return [
            'plans'       => $plans,
            'year_expire' => $this->yearExpire($uid),
        ];
    }

    /** 年卡到期时间戳，无有效年卡返回 0 */
    public function yearExpire(int $uid): int
    {
        $expire = (int)Db::name(self::TABLE)
            ->where('uid', $uid)
            ->where('plan', self::PLAN_YEAR)
            ->where('pay_status', 1)
            ->max('expire_time');
        return $expire > time() ? $expire : 0;
    }

    /**
     * 能否查询：年卡有效，或带了本人已支付、类型和关键词都一致的单次订单
     */
    public function canSearch(int $uid, string $key, string $value, string $orderNo = ''): bool
    {
        if ($this->yearExpire($uid) > 0) {
            return true;
        }
        if ($orderNo === '') {
            return false;
        }
        return (bool)Db::name(self::TABLE)
            ->where('order_no', $orderNo)
            ->where('uid', $uid)
            ->where('plan', self::PLAN_ONCE)
            ->where('pay_status', 1)
            ->where('field_key', $key)
            ->where('keyword', $value)
            ->count();
    }

    /**
     * 下单 + 走对应支付方式
     * @param string $planName once|year
     */
    public function create(int $uid, string $planName, string $key, string $keyword, string $payType = 'weixin', string $returnUrl = ''): array
    {
        $plan = $this->planByName($planName);
        $value = '';
        if ($plan === self::PLAN_ONCE) {
            $value = app()->make(UserFindMeRepository::class)->normalizeQuery($key, $keyword);
        } elseif ($this->yearExpire($uid) > 0) {
            throw new ValidateException('365天无限制查询有效期内，无需重复购买');
        }
        $price = $this->price($plan);
        $orderNo = self::ORDER_PREFIX . date('YmdHis') . mt_rand(1000, 9999);
        $row = [
            'order_no'   => $orderNo,
            'uid'        => $uid,
            'plan'       => $plan,
            'field_key'  => $plan === self::PLAN_ONCE ? $key : '',
            'keyword'    => $value,
            'amount'     => $price,
            'pay_type'   => $payType,
            'pay_status' => 0,
        ];

        if ($payType === 'balance') {
            return $this->payByBalance($row);
        }
        if ($payType === 'mock') {
            if (!systemConfig('pay_mock_open')) {
                throw new ValidateException('未开启模拟支付');
            }
            Db::name(self::TABLE)->insert($row);
            $this->paySuccess($orderNo);
            return ['paid' => true, 'order_no' => $orderNo, 'amount' => $price, 'mock' => true];
        }
        if (!in_array($payType, ['weixin', 'routine', 'alipay'], true)) {
            throw new ValidateException('请选择正确的支付方式');
        }
        Db::name(self::TABLE)->insert($row);
        return $this->createThirdPartyPay($orderNo, $price, $uid, $payType, $plan, $returnUrl);
    }

    /** 轮询订单是否已支付（仅本人订单） */
    public function isPaid(int $uid, string $orderNo): bool
    {
        return (bool)Db::name(self::TABLE)
            ->where('order_no', $orderNo)
            ->where('uid', $uid)
            ->where('pay_status', 1)
            ->count();
    }

    /**
     * 支付成功回调（订单号 FM 前缀）；年卡从当前有效期末尾顺延
     */
    public function paySuccess(string $orderNo): void
    {
        if ($orderNo === '') return;
        $order = Db::name(self::TABLE)->where('order_no', $orderNo)->find();
        if (!$order) throw new ValidateException('订单不存在');
        if ((int)$order['pay_status'] === 1) return;

        $update = ['pay_status' => 1, 'pay_time' => time()];
        if ((int)$order['plan'] === self::PLAN_YEAR) {
            $update['expire_time'] = max(time(), $this->yearExpire((int)$order['uid'])) + self::YEAR_SECONDS;
        }
        Db::name(self::TABLE)->where('id', $order['id'])->where('pay_status', 0)->update($update);
    }

    protected function payByBalance(array $row): array
    {
        if (!systemConfig('yue_pay_status') || !systemConfig('balance_func_status')) {
            throw new ValidateException('未开启余额支付');
        }
        $uid = (int)$row['uid'];
        $amount = (float)$row['amount'];
        $user = app()->make(UserRepository::class)->get($uid);
        if ((float)($user['now_money'] ?? 0) < $amount) {
            throw new ValidateException('余额不足，请更换支付方式');
        }
        $title = self::PLANS[$row['plan']][1];
        Db::transaction(function () use ($user, $amount, $uid, $row, $title) {
            $user->now_money = bcsub((string)$user->now_money, (string)$amount, 2);
            $user->save();
            Db::name(self::TABLE)->insert($row);
            $this->paySuccess($row['order_no']);
            app()->make(UserBillRepository::class)->decBill(
                $uid, 'now_money', 'pay_product', [
                    'link_id' => 0,
                    'status'  => 1,
                    'title'   => '找人' . $title,
                    'number'  => $amount,
                    'mark'    => '余额支付' . floatval($amount) . '元购买找人' . $title,
                    'balance' => $user->now_money,
                ]
            );
        });
        return ['paid' => true, 'order_no' => $row['order_no'], 'amount' => $amount];
    }

    protected function createThirdPartyPay(string $orderNo, float $amount, int $uid, string $payType, int $plan, string $returnUrl = ''): array
    {
        $user = app()->make(UserRepository::class)->get($uid);
        if (!$user) throw new ValidateException('用户不存在');

        $pay = app()->make(Pay::class);
        $orderParams = [
            'order_sn'  => $orderNo,
            'pay_price' => $amount,
            'attach'    => self::ATTACH,
            'body'      => '找人' . self::PLANS[$plan][1],
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
            'paid'     => false,
            'order_no' => $orderNo,
            'pay_type' => $payType,
            'amount'   => $amount,
            'config'   => $config,
        ];
    }

    protected function planByName(string $name): int
    {
        foreach (self::PLANS as $plan => $row) {
            if ($row[0] === $name) {
                return $plan;
            }
        }
        throw new ValidateException('请选择查询套餐');
    }

    protected function price(int $plan): float
    {
        $configured = systemConfig(self::PLANS[$plan][3]);
        $price = is_numeric($configured) && (float)$configured > 0 ? (float)$configured : (float)self::PLANS[$plan][2];
        return round($price, 2);
    }
}
