<?php

namespace app\common\repositories\user;

use app\common\repositories\wechat\WechatUserRepository;
use crmeb\services\pay\Pay;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;

/**
 * 找人付费查询：体验版（单次，绑定查询类型 + 关键词）/ 标准版（30天）/ 尊享版（终身）
 * 订单号前缀：FM
 * 事件：pay_success_find_me / pay.notify
 * 配置（后台「用户 → 找人查询配置」，classify_key=find_me）：
 *   find_me_banner 查询页 banner；find_me_once_price / find_me_month_price / find_me_life_price 价格；
 *   find_me_daily_limit 标准版 / 尊享版每天可查次数（同一类型 + 关键词当天重复查不计次，0 = 不限）
 */
class UserFindMeOrderRepository
{
    public const TABLE = 'user_find_me_order';
    public const ORDER_PREFIX = 'FM';
    public const ATTACH = 'find_me';

    public const PLAN_ONCE = 1;
    /** 2 = 原「365天无限制查询」，已下架（上线前无订单），编号保留不复用 */
    public const PLAN_MONTH = 3;
    public const PLAN_LIFE = 4;

    /** plan => [标识, 名称, 默认价格, 价格配置键（systemConfig 有值时覆盖默认价）, 卡片说明, 是否推荐] */
    protected const PLANS = [
        self::PLAN_ONCE  => ['once', '体验版', 9.9, 'find_me_once_price', "单次查询\n马上揭晓", false],
        self::PLAN_MONTH => ['month', '标准版', 99, 'find_me_month_price', "30天内免费查询\n聚焦关注", true],
        self::PLAN_LIFE  => ['life', '尊享版', 999, 'find_me_life_price', "终身免费查询\n一生安心", false],
    ];

    public const MONTH_SECONDS = 30 * 86400;
    /** 尊享版到期时间（2100-01-01），只用来区分有效期，前端不展示 */
    public const LIFE_EXPIRE = 4102444800;

    public const DEFAULT_DAILY_LIMIT = 1;

    /**
     * 查询页用：banner + 套餐 + 当前会员（标准版 / 尊享版）+ 已购套餐（含体验版，页面提示文案用）+ 每日次数
     */
    public function info(int $uid): array
    {
        $plans = [];
        foreach (self::PLANS as $plan => $row) {
            $plans[] = [
                'plan'  => $row[0],
                'title' => $row[1],
                'price' => $this->price($plan),
                'desc'  => $row[4],
                'hot'   => $row[5],
            ];
        }
        $member = $uid ? $this->member($uid) : null;
        return [
            'banner'      => (string)systemConfig('find_me_banner'),
            'plans'       => $plans,
            'member'      => $member,
            // 提示文案按它显示：有效会员优先，否则买过体验版也算付费用户；从没买过为 null（不显示）
            'bought'      => $member ?: ($uid && $this->boughtOnce($uid)
                ? ['plan' => 'once', 'title' => self::PLANS[self::PLAN_ONCE][1], 'expire' => 0]
                : null),
            'daily_limit' => $this->dailyLimit(),
        ];
    }

    /**
     * 当前有效会员：尊享版优先，其次未到期的标准版；都没有返回 null
     * @return array|null ['plan' => life|month, 'title' => ..., 'expire' => 时间戳（尊享版为 0）]
     */
    public function member(int $uid): ?array
    {
        if ($this->lifeActive($uid)) {
            return ['plan' => 'life', 'title' => self::PLANS[self::PLAN_LIFE][1], 'expire' => 0];
        }
        $expire = $this->monthExpire($uid);
        if ($expire > 0) {
            return ['plan' => 'month', 'title' => self::PLANS[self::PLAN_MONTH][1], 'expire' => $expire];
        }
        return null;
    }

    public function boughtOnce(int $uid): bool
    {
        return (bool)Db::name(self::TABLE)
            ->where('uid', $uid)
            ->where('plan', self::PLAN_ONCE)
            ->where('pay_status', 1)
            ->count();
    }

    public function lifeActive(int $uid): bool
    {
        return (bool)Db::name(self::TABLE)
            ->where('uid', $uid)
            ->where('plan', self::PLAN_LIFE)
            ->where('pay_status', 1)
            ->count();
    }

    /** 标准版到期时间戳，无有效标准版返回 0 */
    public function monthExpire(int $uid): int
    {
        $expire = (int)Db::name(self::TABLE)
            ->where('uid', $uid)
            ->where('plan', self::PLAN_MONTH)
            ->where('pay_status', 1)
            ->max('expire_time');
        return $expire > time() ? $expire : 0;
    }

    /** 标准版 / 尊享版每天可查次数，0 = 不限 */
    public function dailyLimit(): int
    {
        $configured = systemConfig('find_me_daily_limit');
        return is_numeric($configured) ? max(0, (int)$configured) : self::DEFAULT_DAILY_LIMIT;
    }

    /**
     * 能否查询：带了本人已支付、类型和关键词都一致的体验版订单（不受每日次数限制）；
     * 或标准版 / 尊享版有效且当天次数未用完（超出时抛错提示）
     * @return string once|member；都不满足返回空串
     */
    public function authorize(int $uid, string $key, string $value, string $orderNo = ''): string
    {
        if ($orderNo !== '' && Db::name(self::TABLE)
                ->where('order_no', $orderNo)
                ->where('uid', $uid)
                ->where('plan', self::PLAN_ONCE)
                ->where('pay_status', 1)
                ->where('field_key', $key)
                ->where('keyword', $value)
                ->count()) {
            return 'once';
        }
        if (!$this->member($uid)) {
            return '';
        }
        $limit = $this->dailyLimit();
        if ($limit > 0) {
            $used = $this->dailyQueries($uid);
            if (!in_array($key . '|' . $value, $used, true) && count($used) >= $limit) {
                throw new ValidateException('今日查询次数已用完，请明天再来');
            }
        }
        return 'member';
    }

    /** 会员查询成功后记一次（同一类型 + 关键词当天只记一次） */
    public function markDailyQuery(int $uid, string $key, string $value): void
    {
        $used = $this->dailyQueries($uid);
        $item = $key . '|' . $value;
        if (in_array($item, $used, true)) return;
        $used[] = $item;
        Cache::set($this->dailyCacheKey($uid), $used, 2 * 86400);
    }

    protected function dailyQueries(int $uid): array
    {
        $used = Cache::get($this->dailyCacheKey($uid));
        return is_array($used) ? $used : [];
    }

    protected function dailyCacheKey(int $uid): string
    {
        return 'find_me_daily:' . $uid . ':' . date('Ymd');
    }

    /**
     * 下单 + 走对应支付方式
     * @param string $planName once|month|life
     */
    public function create(int $uid, string $planName, string $key, string $keyword, string $payType = 'weixin', string $returnUrl = ''): array
    {
        $plan = $this->planByName($planName);
        $value = '';
        if ($plan === self::PLAN_ONCE) {
            $value = app()->make(UserFindMeRepository::class)->normalizeQuery($key, $keyword);
        } elseif ($this->lifeActive($uid)) {
            throw new ValidateException('您已是尊享版用户，可终身免费查询，无需重复购买');
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
     * 支付成功回调（订单号 FM 前缀）；标准版从当前有效期末尾顺延，尊享版写固定到期时间
     */
    public function paySuccess(string $orderNo): void
    {
        if ($orderNo === '') return;
        $order = Db::name(self::TABLE)->where('order_no', $orderNo)->find();
        if (!$order) throw new ValidateException('订单不存在');
        if ((int)$order['pay_status'] === 1) return;

        $update = ['pay_status' => 1, 'pay_time' => time()];
        if ((int)$order['plan'] === self::PLAN_MONTH) {
            $update['expire_time'] = max(time(), $this->monthExpire((int)$order['uid'])) + self::MONTH_SECONDS;
        } elseif ((int)$order['plan'] === self::PLAN_LIFE) {
            $update['expire_time'] = self::LIFE_EXPIRE;
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
