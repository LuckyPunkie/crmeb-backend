<?php

namespace app\common\repositories\gift;

use app\common\dao\gift\GiftDao;
use app\common\dao\gift\GiftLogDao;
use app\common\dao\gift\GiftOrderDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserMessageRepository;
use app\common\repositories\user\UserRepository;
use app\common\repositories\wechat\WechatUserRepository;
use app\common\repositories\system\admin\AdminRepository;
use crmeb\services\pay\Pay;
use think\exception\ValidateException;
use think\facade\Db;

class GiftRepository extends BaseRepository
{
    public const SCENE_CHAT = 'chat_gift';
    public const SCENE_PROFILE = 'profile_gift';
    public const MSN_TYPE_GIFT = 10;
    public const FREEZE_DAYS = 7;

    /** @var GiftOrderDao */
    protected $orderDao;
    /** @var GiftLogDao */
    protected $logDao;

    public function __construct(GiftDao $dao, GiftOrderDao $orderDao, GiftLogDao $logDao)
    {
        $this->dao = $dao;
        $this->orderDao = $orderDao;
        $this->logDao = $logDao;
    }

    public function getOnShelfList(): array
    {
        return $this->dao->search(['status' => 1])
            ->order('sort_weight DESC,gift_id ASC')
            ->select()
            ->toArray();
    }

    public function getCommissionRate(string $sceneType): float
    {
        $type = $sceneType === self::SCENE_PROFILE ? 'profile_gift' : 'chat_gift';
        $config = Db::name('system_commission_config')->where('type', $type)->where('status', 1)->find();
        return $config ? (float)$config['ratio'] : 0.0;
    }

    public function createOrder(int $senderId, int $receiverId, int $giftId, string $sceneType, string $payMethod): array
    {
        if ($senderId === $receiverId) {
            throw new ValidateException('不能给自己送礼物');
        }
        if (!in_array($sceneType, [self::SCENE_CHAT, self::SCENE_PROFILE], true)) {
            throw new ValidateException('场景类型无效');
        }

        $gift = $this->dao->get($giftId);
        if (!$gift || (int)$gift['status'] !== 1) {
            throw new ValidateException('礼物不存在或已下架');
        }

        $receiver = app()->make(UserRepository::class)->get($receiverId);
        if (!$receiver) {
            throw new ValidateException('接收用户不存在');
        }

        $price = (float)$gift['gift_price'];
        $commissionRate = $this->getCommissionRate($sceneType);
        $commissionAmount = round($price * $commissionRate / 100, 2);
        $receiverIncome = round($price - $commissionAmount, 2);

        $order = $this->orderDao->create([
            'order_no' => $this->orderDao->createOrderNo($senderId),
            'gift_id' => $giftId,
            'gift_name' => $gift['gift_name'],
            'gift_icon' => $gift['gift_icon'],
            'gift_price' => $price,
            'scene_type' => $sceneType,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'pay_amount' => $price,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'receiver_income' => $receiverIncome,
            'pay_method' => $payMethod,
            'pay_status' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return is_array($order) ? $order : $order->toArray();
    }

    public function pay(string $orderNo, int $uid, string $payType = 'balance', string $returnUrl = '', bool $isApp = false): array
    {
        $order = $this->orderDao->search(['order_no' => $orderNo])->find();
        if (!$order) {
            throw new ValidateException('订单不存在');
        }
        if ((int)$order['sender_id'] !== $uid) {
            throw new ValidateException('无权操作此订单');
        }
        if ((int)$order['pay_status'] === 1) {
            return ['paid' => true, 'order_no' => $orderNo];
        }

        $this->orderDao->update($order['order_id'], ['pay_method' => $payType]);

        if ($payType === 'balance') {
            $this->payBalance($order);
            $this->paySuccess($orderNo);
            return ['paid' => true, 'order_no' => $orderNo];
        }

        if ($payType === 'mock') {
            if (!systemConfig('pay_mock_open')) {
                throw new ValidateException('未开启模拟支付');
            }
            $this->paySuccess($orderNo);
            return ['paid' => true, 'order_no' => $orderNo, 'mock' => true];
        }

        $user = app()->make(UserRepository::class)->get($uid);
        $pay = app()->make(Pay::class);
        $body = '赠送礼物-' . $order['gift_name'];
        $orderParams = [
            'order_sn' => $orderNo,
            'pay_price' => $order['pay_amount'],
            'attach' => 'gift_order',
            'body' => $body,
        ];

        switch ($payType) {
            case 'weixin':
                $openId = app()->make(WechatUserRepository::class)->idByOpenId($user['wechat_user_id']);
                if (!$openId) {
                    throw new ValidateException('请关联微信公众号');
                }
                $config = $pay->pay($payType, $orderParams, $openId);
                break;
            case 'routine':
                $openId = app()->make(WechatUserRepository::class)->idByRoutineId($user['wechat_user_id']);
                if (!$openId) {
                    throw new ValidateException('请关联微信小程序');
                }
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
            'config' => $config,
        ];
    }

    protected function payBalance($order): void
    {
        if (!systemConfig('yue_pay_status') || !systemConfig('balance_func_status')) {
            throw new ValidateException('未开启余额支付');
        }
        $uid = (int)$order['sender_id'];
        $user = app()->make(UserRepository::class)->get($uid);
        if ((float)$user['now_money'] < (float)$order['pay_amount']) {
            throw new ValidateException('余额不足，请更换支付方式');
        }

        Db::transaction(function () use ($user, $order, $uid) {
            $user->now_money = bcsub((string)$user->now_money, (string)$order['pay_amount'], 2);
            $user->save();
            app()->make(UserBillRepository::class)->decBill($uid, 'now_money', 'pay_product', [
                'link_id' => $order['order_id'],
                'status' => 1,
                'title' => '赠送礼物',
                'number' => $order['pay_amount'],
                'mark' => '余额支付赠送礼物「' . $order['gift_name'] . '」',
                'balance' => $user->now_money,
            ]);
        });
    }

    public function paySuccess(string $orderNo): array
    {
        $order = $this->orderDao->search(['order_no' => $orderNo])->find();
        if (!$order) {
            throw new ValidateException('订单不存在');
        }
        if ((int)$order['pay_status'] === 1) {
            return is_array($order) ? $order : $order->toArray();
        }

        return Db::transaction(function () use ($order, $orderNo) {
            $orderArr = is_array($order) ? $order : $order->toArray();
            $now = date('Y-m-d H:i:s');

            $this->orderDao->update($orderArr['order_id'], [
                'pay_status' => 1,
                'pay_time' => $now,
            ]);

            $receiverId = (int)$orderArr['receiver_id'];
            $income = (float)$orderArr['receiver_income'];
            if ($income > 0) {
                $receiver = app()->make(UserRepository::class)->get($receiverId);
                app()->make(UserBillRepository::class)->incBill($receiverId, 'brokerage', 'gift_income', [
                    'link_id' => $orderArr['order_id'],
                    'status' => 0,
                    'title' => '收到礼物',
                    'number' => $income,
                    'mark' => '收到礼物「' . $orderArr['gift_name'] . '」，7天后可提现',
                    'balance' => $receiver ? (float)$receiver['brokerage_price'] : 0,
                ]);
            }

            $message = $this->sendGiftMessage($orderArr);
            if ($message) {
                $this->orderDao->update($orderArr['order_id'], [
                    'dialog_id' => (int)($message['dialog_id'] ?? 0),
                    'message_id' => (int)($message['message_id'] ?? 0),
                ]);
            }

            return $this->orderDao->get($orderArr['order_id'])->toArray();
        });
    }

    protected function sendGiftMessage(array $order): ?array
    {
        $senderId = (int)$order['sender_id'];
        $receiverId = (int)$order['receiver_id'];
        $payload = json_encode([
            'gift_id' => (int)$order['gift_id'],
            'gift_name' => $order['gift_name'],
            'gift_icon' => $order['gift_icon'],
            'gift_price' => (float)$order['gift_price'],
            'order_id' => (int)$order['order_id'],
            'scene_type' => $order['scene_type'],
        ], JSON_UNESCAPED_UNICODE);

        return app()->make(UserMessageRepository::class)->sendMessage($senderId, $receiverId, [
            'msn_type' => self::MSN_TYPE_GIFT,
            'msn' => $payload,
        ]);
    }

    public function getOrderStatus(string $orderNo, int $uid): array
    {
        $order = $this->orderDao->search(['order_no' => $orderNo])->find();
        if (!$order || (int)$order['sender_id'] !== $uid) {
            throw new ValidateException('订单不存在');
        }
        return [
            'order_no' => $order['order_no'],
            'pay_status' => (int)$order['pay_status'],
            'message_id' => (int)$order['message_id'],
            'dialog_id' => (int)$order['dialog_id'],
        ];
    }

    public function getReceivedList(int $uid, int $page, int $limit): array
    {
        $query = $this->orderDao->search(['receiver_id' => $uid, 'pay_status' => 1])
            ->with(['sender' => function ($q) {
                $q->field('uid,nickname,avatar');
            }])
            ->order('pay_time DESC,order_id DESC');

        $count = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();
        $items = [];
        foreach ($list as $row) {
            $sender = $row['sender'] ?? [];
            $items[] = $this->formatOrderItem($row, $sender, 'sender');
        }

        $totalIncome = (float)$this->orderDao->search(['receiver_id' => $uid, 'pay_status' => 1])->sum('receiver_income');
        $frozenAmount = (float)Db::name('user_bill')
            ->where('uid', $uid)
            ->where('category', 'brokerage')
            ->where('type', 'gift_income')
            ->where('status', 0)
            ->sum('number');

        return [
            'count' => $count,
            'list' => $items,
            'summary' => [
                'total_income' => $totalIncome,
                'frozen_amount' => $frozenAmount,
                'gift_count' => $count,
            ],
        ];
    }

    public function getSentList(int $uid, int $page, int $limit): array
    {
        $query = $this->orderDao->search(['sender_id' => $uid, 'pay_status' => 1])
            ->with(['receiver' => function ($q) {
                $q->field('uid,nickname,avatar');
            }])
            ->order('pay_time DESC,order_id DESC');

        $count = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();
        $items = [];
        $totalSpent = (float)$this->orderDao->search(['sender_id' => $uid, 'pay_status' => 1])->sum('pay_amount');
        foreach ($list as $row) {
            $receiver = $row['receiver'] ?? [];
            $items[] = $this->formatOrderItem($row, $receiver, 'peer');
        }
        return [
            'count' => $count,
            'list' => $items,
            'summary' => [
                'total_spent' => $totalSpent,
                'gift_count' => $count,
            ],
        ];
    }

    protected function formatOrderItem(array $row, array $peer, string $peerKey): array
    {
        $payTime = $row['pay_time'] ?? ($row['created_at'] ?? '');
        $availableTime = $payTime ? date('Y-m-d H:i:s', strtotime($payTime . ' + ' . self::FREEZE_DAYS . ' days')) : '';
        $incomeStatus = 'frozen';
        if ($payTime && strtotime($availableTime) <= time()) {
            $incomeStatus = 'available';
        }
        $bill = Db::name('user_bill')
            ->where('uid', $row['receiver_id'])
            ->where('category', 'brokerage')
            ->where('type', 'gift_income')
            ->where('link_id', $row['order_id'])
            ->find();
        if ($bill && (int)$bill['status'] === 1) {
            $incomeStatus = 'available';
        }

        return [
            'order_id' => (int)$row['order_id'],
            'order_no' => $row['order_no'],
            'gift_id' => (int)$row['gift_id'],
            'gift_name' => $row['gift_name'],
            'gift_icon' => $row['gift_icon'],
            'gift_price' => (float)$row['gift_price'],
            'pay_amount' => (float)$row['pay_amount'],
            'receiver_income' => (float)$row['receiver_income'],
            'scene_type' => $row['scene_type'],
            'pay_method' => $row['pay_method'],
            'pay_time' => $payTime,
            'income_status' => $incomeStatus,
            'available_time' => $availableTime,
            'peer_uid' => (int)($peer['uid'] ?? 0),
            'peer_nickname' => $peer['nickname'] ?? '',
            'peer_avatar' => $peer['avatar'] ?? '',
        ];
    }

    // ---------- Admin ----------

    public function adminList(array $where, int $page, int $limit): array
    {
        $query = $this->dao->search($where)->order('sort_weight DESC,gift_id DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();
        return compact('count', 'list');
    }

    public function adminCreate(array $data, int $adminId): int
    {
        if (Db::name('gift')->where('gift_name', $data['gift_name'])->count() > 0) {
            throw new ValidateException('礼物名称已存在');
        }
        $row = $this->dao->create([
            'gift_name' => $data['gift_name'],
            'gift_icon' => $data['gift_icon'] ?? '',
            'gift_price' => $data['gift_price'],
            'description' => $data['description'] ?? '',
            'sort_weight' => (int)($data['sort_weight'] ?? 0),
            'status' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);
        $giftId = is_array($row) ? $row['gift_id'] : $row->gift_id;
        $this->logDao->addLog($giftId, 'create', $adminId, null, $data);
        return (int)$giftId;
    }

    public function adminUpdate(int $giftId, array $data, int $adminId): void
    {
        $gift = $this->dao->get($giftId);
        if (!$gift) {
            throw new ValidateException('礼物不存在');
        }
        $before = $gift->toArray();
        if (isset($data['gift_name']) && $data['gift_name'] !== $before['gift_name']) {
            $exists = $this->dao->search(['gift_name' => $data['gift_name']])
                ->where('gift_id', '<>', $giftId)->count();
            if ($exists > 0) {
                throw new ValidateException('礼物名称已存在');
            }
        }
        $update = array_intersect_key($data, array_flip([
            'gift_name', 'gift_icon', 'gift_price', 'description', 'sort_weight',
        ]));
        $update['updated_at'] = date('Y-m-d H:i:s');
        $update['updated_by'] = $adminId;
        $this->dao->update($giftId, $update);
        $this->logDao->addLog($giftId, 'update', $adminId, $before, $update);
    }

    public function adminSetStatus(int $giftId, int $status, int $adminId): void
    {
        $gift = $this->dao->get($giftId);
        if (!$gift) {
            throw new ValidateException('礼物不存在');
        }
        $this->dao->update($giftId, [
            'status' => $status ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $adminId,
        ]);
        $this->logDao->addLog($giftId, $status ? 'on_shelf' : 'off_shelf', $adminId, ['status' => $gift['status']], ['status' => $status]);
    }

    public function adminDelete(int $giftId, int $adminId): void
    {
        $gift = $this->dao->get($giftId);
        if (!$gift) {
            throw new ValidateException('礼物不存在');
        }
        $hasOrder = $this->orderDao->search(['gift_id' => $giftId])->count() > 0;
        if ($hasOrder) {
            throw new ValidateException('该礼物已有订单，只能下架不能删除');
        }
        $before = $gift->toArray();
        $this->dao->delete($giftId);
        $this->logDao->addLog($giftId, 'delete', $adminId, $before, null);
    }

    public function getCommissionConfig(): array
    {
        $chat = Db::name('system_commission_config')->where('type', 'chat_gift')->find();
        $profile = Db::name('system_commission_config')->where('type', 'profile_gift')->find();
        return [
            'chat_gift_rate' => $chat ? (float)$chat['ratio'] : 0,
            'profile_gift_rate' => $profile ? (float)$profile['ratio'] : 0,
            'logs' => $this->getCommissionLogs(30),
        ];
    }

    public function getCommissionLogs(int $limit = 30): array
    {
        $rows = Db::name('gift_commission_log')
            ->order('id', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
        $sceneMap = [
            self::SCENE_CHAT => '聊天礼物',
            self::SCENE_PROFILE => '主页礼物',
        ];
        return array_map(function ($row) use ($sceneMap) {
            $before = (float)$row['before_rate'];
            $after = (float)$row['after_rate'];
            return [
                'id' => (int)$row['id'],
                'created_at' => $row['created_at'] ?? '',
                'scene_type' => $row['scene_type'],
                'scene_label' => $sceneMap[$row['scene_type']] ?? $row['scene_type'],
                'operator_name' => $row['operator_name'] ?: '系统管理员',
                'before_rate' => $before,
                'after_rate' => $after,
                'before_rate_text' => number_format($before, 2) . '%',
                'after_rate_text' => number_format($after, 2) . '%',
                'change_direction' => $after > $before ? 'up' : ($after < $before ? 'down' : 'same'),
                'ip' => $row['ip'] ?? '',
            ];
        }, $rows);
    }

    public function saveCommissionConfig(float $chatRate, float $profileRate, int $adminId, string $ip = ''): void
    {
        $operatorName = '系统管理员';
        if ($adminId > 0) {
            $admin = app()->make(AdminRepository::class)->get($adminId);
            if ($admin) {
                $operatorName = $admin['real_name'] ?: ($admin['account'] ?? '系统管理员');
            }
        }
        foreach ([self::SCENE_CHAT => $chatRate, self::SCENE_PROFILE => $profileRate] as $type => $rate) {
            if ($rate < 0 || $rate > 100) {
                throw new ValidateException('抽佣比例须在 0~100 之间');
            }
            $row = Db::name('system_commission_config')->where('type', $type)->find();
            $beforeRate = $row ? (float)$row['ratio'] : 0;
            if (round($beforeRate, 2) === round($rate, 2)) {
                continue;
            }
            if ($row) {
                Db::name('system_commission_config')->where('type', $type)->update([
                    'ratio' => $rate,
                ]);
            } else {
                Db::name('system_commission_config')->insert([
                    'type' => $type,
                    'ratio' => $rate,
                    'status' => 1,
                ]);
            }
            Db::name('gift_commission_log')->insert([
                'scene_type' => $type,
                'operator_id' => $adminId,
                'operator_name' => $operatorName,
                'before_rate' => $beforeRate,
                'after_rate' => $rate,
                'ip' => $ip,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function getDialogGiftAmounts(int $uid, array $dialogIds, string $sort): array
    {
        if (!$dialogIds) {
            return [];
        }
        $map = [];
        if ($sort === 'unread_gift_amount') {
            $rows = Db::name('gift_order')->alias('o')
                ->join('user_message m', 'm.message_id = o.message_id')
                ->where('o.receiver_id', $uid)
                ->where('o.pay_status', 1)
                ->whereIn('o.dialog_id', $dialogIds)
                ->where('m.is_read', 0)
                ->where('m.msn_type', self::MSN_TYPE_GIFT)
                ->field('o.dialog_id, SUM(o.pay_amount) as amount')
                ->group('o.dialog_id')
                ->select()
                ->toArray();
        } else {
            $rows = Db::name('gift_order')
                ->where('receiver_id', $uid)
                ->where('pay_status', 1)
                ->whereIn('dialog_id', $dialogIds)
                ->field('dialog_id, SUM(pay_amount) as amount')
                ->group('dialog_id')
                ->select()
                ->toArray();
        }
        foreach ($rows as $r) {
            $map[(int)$r['dialog_id']] = (float)$r['amount'];
        }
        return $map;
    }
}
