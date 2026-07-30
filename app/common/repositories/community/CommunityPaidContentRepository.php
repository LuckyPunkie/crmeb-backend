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

namespace app\common\repositories\community;

use app\common\dao\community\CommunityPaidDao;
use app\common\dao\community\CommunityPaidOrderDao;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 社区付费内容
 */
class CommunityPaidContentRepository extends BaseRepository
{
    /**
     * @var CommunityPaidDao
     */
    protected $dao;

    public function __construct(CommunityPaidDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取付费内容详情（根据权限分级返回）
     */
    public function getDetail(int $communityId, $uid = null)
    {
        $data = $this->dao->search(['community_id' => $communityId])->find();
        if (!$data) throw new ValidateException('付费内容不存在');

        $isUnlocked = false;
        if ($uid) {
            // 发布者自己始终可见
            if ($data['uid'] == $uid) {
                $isUnlocked = true;
            } else {
                // 检查是否已购买
                $orderDao = app()->make(CommunityPaidOrderDao::class);
                $isUnlocked = $orderDao->search([
                    'community_id' => $communityId,
                    'buyer_uid' => $uid,
                    'pay_status' => 1,
                ])->count() > 0;
            }
        }

        $result = $data->toArray();
        if (!$isUnlocked) {
            // 未购买：仅返回免费预览内容和付费元数据
            unset($result['paid_content']);
            $result['is_unlocked'] = false;
            $result['paid_meta'] = [
                'char_count' => mb_strlen($data['paid_content']),
                'image_count' => substr_count($data['paid_content'], '<img'),
            ];
        } else {
            $result['is_unlocked'] = true;
        }
        return $result;
    }

    /**
     * 检查是否已解锁
     */
    public function checkUnlocked(int $communityId, int $uid): bool
    {
        $data = $this->dao->search(['community_id' => $communityId])->find();
        if (!$data) return false;
        if ($data['uid'] == $uid) return true;

        $orderDao = app()->make(CommunityPaidOrderDao::class);
        return $orderDao->search([
            'community_id' => $communityId,
            'buyer_uid' => $uid,
            'pay_status' => 1,
        ])->count() > 0;
    }

    /**
     * 解锁付费内容（创建订单并处理支付）
     */
    public function unlock(int $communityId, int $buyerUid, string $payType = 'balance')
    {
        $data = $this->dao->search(['community_id' => $communityId])->find();
        if (!$data) throw new ValidateException('付费内容不存在');
        if ($data['uid'] == $buyerUid) throw new ValidateException('自己的内容无需购买');

        $orderDao = app()->make(CommunityPaidOrderDao::class);
        if ($orderDao->search([
            'community_id' => $communityId,
            'buyer_uid' => $buyerUid,
            'pay_status' => 1,
        ])->count() > 0) {
            throw new ValidateException('付费内容已购买', 10008);
        }

        $orderNo = 'PO' . date('YmdHis') . rand(1000, 9999);
        $platformRatio = $this->getCommissionRatio('paid');

        $order = $orderDao->create([
            'order_no' => $orderNo,
            'paid_content_id' => $data['id'],
            'community_id' => $communityId,
            'buyer_uid' => $buyerUid,
            'seller_uid' => $data['uid'],
            'amount' => $data['price'],
            'pay_type' => $payType,
            'pay_status' => 0,
            'platform_ratio' => $platformRatio,
        ]);

        if ($payType === 'balance') {
            $this->payBalanceForUnlock($order, $buyerUid);
            $this->notifySellerUnlock($data, $buyerUid, (float)$data['price']);
            return ['paid' => true, 'order_no' => $orderNo, 'amount' => (float)$data['price']];
        }

        // 模拟支付：后台开启且选择 mock
        if ($payType === 'mock') {
            if (!systemConfig('pay_mock_open')) {
                throw new ValidateException('未开启模拟支付');
            }
            $this->paySuccess($orderNo);
            $this->notifySellerUnlock($data, $buyerUid, (float)$data['price']);
            return ['paid' => true, 'order_no' => $orderNo, 'amount' => (float)$data['price'], 'mock' => true];
        }

        throw new ValidateException('请选择正确的支付方式');
    }

    /**
     * 通知作者：付费内容被解锁
     */
    protected function notifySellerUnlock($paid, int $buyerUid, float $amount): void
    {
        $sellerUid = (int)($paid['uid'] ?? 0);
        $communityId = (int)($paid['community_id'] ?? 0);
        if ($sellerUid <= 0 || $sellerUid === $buyerUid) {
            return;
        }
        try {
            $brief = \app\common\repositories\user\UserNotificationRepository::noteBriefById($communityId);
            $desc = '解锁了你的付费内容';
            if ($amount > 0) {
                $desc .= '，支付 ¥' . number_format($amount, 2, '.', '');
            }
            $payload = \app\common\repositories\user\UserNotificationRepository::buildNoteContent([
                'community_id' => $communityId,
                'title' => $brief['title'],
                'image' => $brief['image'],
                'content' => $desc,
                'text' => $desc,
                'amount' => $amount,
            ]);
            app()->make(\app\common\repositories\user\UserNotificationRepository::class)
                ->createAndPush($sellerUid, $buyerUid, 'paid_unlock', '付费内容被解锁', $payload, 'community', $communityId);
        } catch (\Throwable $e) {
        }
    }

    /**
     * 余额支付解锁：扣款 + 写账单 + 标记订单已支付
     */
    protected function payBalanceForUnlock($order, int $uid): void
    {
        if (!systemConfig('yue_pay_status') || !systemConfig('balance_func_status')) {
            throw new ValidateException('未开启余额支付');
        }

        $user = app()->make(\app\common\repositories\user\UserRepository::class)->get($uid);
        if ((float)($user['now_money'] ?? 0) < (float)$order['amount']) {
            throw new ValidateException('余额不足，请更换支付方式');
        }

        Db::transaction(function () use ($user, $order, $uid) {
            $user->now_money = bcsub((string)$user->now_money, (string)$order['amount'], 2);
            $user->save();

            app()->make(\app\common\repositories\user\UserBillRepository::class)->decBill(
                $uid, 'now_money', 'pay_product', [
                    'link_id' => $order['id'],
                    'status' => 1,
                    'title' => '付费内容解锁',
                    'number' => $order['amount'],
                    'mark' => '余额支付' . floatval($order['amount']) . '元解锁付费内容',
                    'balance' => $user->now_money,
                ]
            );

            $sellerIncome = round((float)$order['amount'] * (1 - (float)$order['platform_ratio']), 2);
            app()->make(CommunityPaidOrderDao::class)->update($order['id'], [
                'pay_status' => 1,
                'pay_time' => date('Y-m-d H:i:s'),
                'seller_income' => $sellerIncome,
            ]);
            $this->dao->update($order['paid_content_id'], [
                'buy_count' => Db::raw('buy_count + 1'),
                'total_income' => Db::raw('total_income + ' . $order['amount']),
            ]);
        });
    }

    /**
     * 支付成功回调
     */
    public function paySuccess(string $orderNo)
    {
        $orderDao = app()->make(CommunityPaidOrderDao::class);
        $order = $orderDao->search(['order_no' => $orderNo])->find();
        if (!$order) throw new ValidateException('订单不存在');
        if ($order['pay_status'] == 1) return $order; // 幂等

        return Db::transaction(function () use ($orderDao, $order) {
            $sellerIncome = round($order['amount'] * (1 - $order['platform_ratio']), 2);
            $orderDao->update($order['id'], [
                'pay_status' => 1,
                'pay_time' => date('Y-m-d H:i:s'),
                'seller_income' => $sellerIncome,
            ]);
            $this->dao->update($order['paid_content_id'], [
                'buy_count' => Db::raw('buy_count + 1'),
                'total_income' => Db::raw('total_income + ' . $order['amount']),
            ]);
            return $orderDao->get($order['id']);
        });
    }

    /**
     * 我的付费收益
     * 可提现余额 = 用户余额 eb_user.now_money（与余额页一致）
     */
    public function getIncome(int $uid, array $dateRange = [], int $page = 1, int $limit = 10)
    {
        $user = Db::name('user')->where('uid', $uid)->field('uid,now_money')->find();
        $withdrawable = $user ? (float)$user['now_money'] : 0.0;

        $orderDao = app()->make(CommunityPaidOrderDao::class);
        $baseWhere = ['seller_uid' => $uid, 'pay_status' => 1];

        $listQuery = $orderDao->search($baseWhere);
        if (!empty($dateRange[0])) {
            $listQuery->where('pay_time', '>=', $dateRange[0]);
        }
        if (!empty($dateRange[1])) {
            $listQuery->where('pay_time', '<=', $dateRange[1] . ' 23:59:59');
        }

        $unlockCount = $orderDao->search($baseWhere)->count();
        if (!empty($dateRange[0]) || !empty($dateRange[1])) {
            $countQuery = $orderDao->search($baseWhere);
            if (!empty($dateRange[0])) {
                $countQuery->where('pay_time', '>=', $dateRange[0]);
            }
            if (!empty($dateRange[1])) {
                $countQuery->where('pay_time', '<=', $dateRange[1] . ' 23:59:59');
            }
            $unlockCount = $countQuery->count();
        }

        $list = $listQuery->with([
            'community' => function ($q) {
                $q->field('community_id,title');
            },
            'buyer' => function ($q) {
                $q->field('uid,nickname,avatar');
            },
        ])->page($page, $limit)->order('pay_time DESC,id DESC')->select();

        $totalIncome = (float)$orderDao->search($baseWhere)->sum('seller_income');
        if ($totalIncome <= 0) {
            $totalIncome = (float)$this->dao->search(['uid' => $uid])->sum('total_income');
        }

        $todayStart = date('Y-m-d 00:00:00');
        $todayIncome = (float)$orderDao->search($baseWhere)
            ->where('pay_time', '>=', $todayStart)
            ->sum('seller_income');

        $items = [];
        foreach ($list as $row) {
            $arr = is_array($row) ? $row : $row->toArray();
            $buyer = $arr['buyer'] ?? [];
            $community = $arr['community'] ?? [];
            $amount = (float)($arr['amount'] ?? 0);
            $income = (float)($arr['seller_income'] ?? 0);
            $items[] = array_merge($arr, [
                'nickname' => $buyer['nickname'] ?? '匿名用户',
                'avatar' => $buyer['avatar'] ?? '',
                'title' => $community['title'] ?? '付费内容',
                'price' => $amount,
                'income' => $income,
                'commission' => round(max(0, $amount - $income), 2),
            ]);
        }

        return [
            'total_income' => $totalIncome,
            'withdrawable' => $withdrawable,
            'now_money' => $withdrawable,
            'today_income' => $todayIncome,
            'unlock_count' => $unlockCount,
            'count' => $unlockCount,
            'list' => $items,
        ];
    }

    /**
     * 付费订单列表
     */
    public function getOrders(int $uid, $communityId = null, int $page = 1, int $limit = 10)
    {
        $orderDao = app()->make(CommunityPaidOrderDao::class);
        $where = ['seller_uid' => $uid, 'pay_status' => 1];
        if ($communityId) {
            $where['community_id'] = $communityId;
        }
        $query = $orderDao->search($where)->order('pay_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
    }

    /**
     * 我发布的付费笔记（含汇总）
     */
    public function getPublishedList(int $uid, int $page = 1, int $limit = 10)
    {
        $orderDao = app()->make(CommunityPaidOrderDao::class);
        $paidCount = $this->dao->search(['uid' => $uid])->count();
        $totalIncome = (float)$this->dao->search(['uid' => $uid])->sum('total_income');
        if ($totalIncome <= 0) {
            $totalIncome = (float)$orderDao->search(['seller_uid' => $uid, 'pay_status' => 1])->sum('seller_income');
        }
        $buyerCount = (int)Db::name('community_paid_order')
            ->where('seller_uid', $uid)
            ->where('pay_status', 1)
            ->count('DISTINCT buyer_uid');

        $list = $this->dao->search(['uid' => $uid])
            ->with([
                'community' => function ($q) {
                    $q->field('community_id,title,image,create_time');
                },
            ])
            ->order('id DESC')
            ->page($page, $limit)
            ->select();

        $items = [];
        foreach ($list as $row) {
            $arr = is_array($row) ? $row : $row->toArray();
            $community = $arr['community'] ?? [];
            $image = $community['image'] ?? [];
            if (is_string($image)) {
                $image = $image === '' ? [] : explode(',', $image);
            }
            if (!is_array($image)) {
                $image = [];
            }
            $items[] = [
                'id' => (int)($arr['id'] ?? 0),
                'community_id' => (int)($arr['community_id'] ?? 0),
                'title' => $community['title'] ?? ($arr['title'] ?? '付费内容'),
                'image' => $image,
                'cover' => $image[0] ?? '',
                'price' => (float)($arr['price'] ?? 0),
                'buy_count' => (int)($arr['buy_count'] ?? 0),
                'total_income' => (float)($arr['total_income'] ?? 0),
                'create_time' => $community['create_time'] ?? ($arr['create_time'] ?? ''),
            ];
        }

        return [
            'total_income' => $totalIncome,
            'paid_count' => $paidCount,
            'buyer_count' => $buyerCount,
            'count' => $paidCount,
            'list' => $items,
        ];
    }

    /**
     * 我解锁的付费笔记（含汇总）
     */
    public function getUnlockedList(int $uid, int $page = 1, int $limit = 10)
    {
        $orderDao = app()->make(CommunityPaidOrderDao::class);
        $baseWhere = ['buyer_uid' => $uid, 'pay_status' => 1];
        $unlockCount = $orderDao->search($baseWhere)->count();
        $totalSpent = (float)$orderDao->search($baseWhere)->sum('amount');

        $list = $orderDao->search($baseWhere)
            ->with([
                'community' => function ($q) {
                    $q->field('community_id,title,image,uid');
                },
                'seller' => function ($q) {
                    $q->field('uid,nickname,avatar');
                },
            ])
            ->order('pay_time DESC,id DESC')
            ->page($page, $limit)
            ->select();

        $items = [];
        foreach ($list as $row) {
            $arr = is_array($row) ? $row : $row->toArray();
            $community = $arr['community'] ?? [];
            $seller = $arr['seller'] ?? [];
            $image = $community['image'] ?? [];
            if (is_string($image)) {
                $image = $image === '' ? [] : explode(',', $image);
            }
            $payTime = $arr['pay_time'] ?? ($arr['create_time'] ?? '');
            $items[] = [
                'id' => (int)($arr['id'] ?? 0),
                'order_no' => $arr['order_no'] ?? '',
                'community_id' => (int)($arr['community_id'] ?? 0),
                'title' => $community['title'] ?? '付费内容',
                'image' => $image,
                'cover' => $image[0] ?? '',
                'price' => (float)($arr['amount'] ?? 0),
                'amount' => (float)($arr['amount'] ?? 0),
                'author_uid' => (int)($seller['uid'] ?? ($community['uid'] ?? 0)),
                'author_nickname' => $seller['nickname'] ?? '匿名作者',
                'author_avatar' => $seller['avatar'] ?? '',
                'unlock_time' => $payTime,
                'pay_time' => $payTime,
            ];
        }

        return [
            'total_spent' => $totalSpent,
            'unlock_count' => $unlockCount,
            'count' => $unlockCount,
            'list' => $items,
        ];
    }

    /**
     * 获取平台抽成比例
     */
    public function getCommissionRatio(string $type): float
    {
        $config = Db::name('system_commission_config')->where('type', $type)->where('status', 1)->find();
        return $config ? (float)$config['ratio'] : 0.20;
    }
}
