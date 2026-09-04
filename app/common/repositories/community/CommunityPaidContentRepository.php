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
use app\common\repositories\commission\CommissionConfigRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserRepository;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\services\pay\Pay;
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
        $community = Db::name('community')->where('community_id', $communityId)->field('is_type,video_link')->find();
        $isVideo = $community && (int)$community['is_type'] === 2;

        if ($isVideo) {
            $result['is_video'] = true;
            $result['video_paid_mode'] = (int)($result['video_paid_mode'] ?? 0);
            $result['video_trial_duration'] = (float)($result['video_trial_duration'] ?? 0);
            $result['video_duration'] = (float)($result['video_duration'] ?? 0);
            $result['video_link'] = $community['video_link'] ?? '';
            $result['is_unlocked'] = $isUnlocked;
            if (!$isUnlocked) {
                unset($result['paid_content']);
            }
            return $result;
        }

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
        if ($uid <= 0) return false;
        $data = $this->dao->search(['community_id' => $communityId])->find();
        if (!$data) return false;
        if ((int)$data['uid'] > 0 && (int)$data['uid'] === $uid) return true;

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
    public function unlock(int $communityId, int $buyerUid, string $payType = 'balance', string $returnUrl = '')
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
        $community = Db::name('community')->where('community_id', $communityId)->field('is_type')->find();
        $commissionType = ($community && (int)$community['is_type'] === 2) ? 'video' : 'paid';
        $platformRatio = $this->getCommissionRatio($commissionType);

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
            return ['paid' => true, 'order_no' => $orderNo, 'amount' => (float)$data['price']];
        }

        if ($payType === 'mock') {
            if (!systemConfig('pay_mock_open')) {
                throw new ValidateException('未开启模拟支付');
            }
            $this->paySuccess($orderNo);
            return ['paid' => true, 'order_no' => $orderNo, 'amount' => (float)$data['price'], 'mock' => true];
        }

        if (!in_array($payType, ['weixin', 'routine', 'alipay'], true)) {
            throw new ValidateException('请选择正确的支付方式');
        }

        return $this->createThirdPartyPay($order, $buyerUid, $payType, $returnUrl);
    }

    /**
     * 微信/支付宝发起支付
     */
    protected function createThirdPartyPay($order, int $buyerUid, string $payType, string $returnUrl = ''): array
    {
        $user = app()->make(UserRepository::class)->get($buyerUid);
        if (!$user) {
            throw new ValidateException('用户不存在');
        }

        $pay = app()->make(Pay::class);
        $orderParams = [
            'order_sn' => $order['order_no'],
            'pay_price' => $order['amount'],
            'attach' => 'community_paid',
            'body' => '解锁付费内容',
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
            'order_no' => $order['order_no'],
            'pay_type' => $payType,
            'amount' => (float)$order['amount'],
            'config' => $config,
        ];
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

        $user = app()->make(UserRepository::class)->get($uid);
        if ((float)($user['now_money'] ?? 0) < (float)$order['amount']) {
            throw new ValidateException('余额不足，请更换支付方式');
        }

        Db::transaction(function () use ($user, $order, $uid) {
            $user->now_money = bcsub((string)$user->now_money, (string)$order['amount'], 2);
            $user->save();

            app()->make(UserBillRepository::class)->decBill(
                $uid, 'now_money', 'pay_product', [
                    'link_id' => $order['id'],
                    'status' => 1,
                    'title' => '付费内容解锁',
                    'number' => $order['amount'],
                    'mark' => '余额支付' . floatval($order['amount']) . '元解锁付费内容',
                    'balance' => $user->now_money,
                ]
            );
        });

        $this->completePaidOrder($order);
    }

    /**
     * 标记订单已支付、更新统计、作者钱包入账
     */
    protected function completePaidOrder($order, bool $notify = true): void
    {
        $orderDao = app()->make(CommunityPaidOrderDao::class);
        $fresh = $orderDao->search(['order_no' => $order['order_no']])->find();
        if (!$fresh) {
            throw new ValidateException('订单不存在');
        }
        if ((int)$fresh['pay_status'] === 1) {
            return;
        }

        $sellerIncome = round((float)$fresh['amount'] * (1 - (float)$fresh['platform_ratio']), 2);

        Db::transaction(function () use ($orderDao, $fresh, $sellerIncome) {
            $orderDao->update($fresh['id'], [
                'pay_status' => 1,
                'pay_time' => date('Y-m-d H:i:s'),
                'seller_income' => $sellerIncome,
            ]);
            $this->dao->update($fresh['paid_content_id'], [
                'buy_count' => Db::raw('buy_count + 1'),
                'total_income' => Db::raw('total_income + ' . $fresh['amount']),
            ]);

            if ($sellerIncome > 0) {
                $sellerUid = (int)$fresh['seller_uid'];
                $seller = app()->make(UserRepository::class)->get($sellerUid);
                if ($seller) {
                    $seller->now_money = bcadd((string)$seller->now_money, (string)$sellerIncome, 2);
                    $seller->save();
                    app()->make(UserBillRepository::class)->incBill($sellerUid, 'now_money', 'paid_content_income', [
                        'link_id' => $fresh['id'],
                        'status' => 1,
                        'title' => '付费内容收益',
                        'number' => $sellerIncome,
                        'mark' => '付费内容解锁收入 ¥' . number_format($sellerIncome, 2, '.', ''),
                        'balance' => $seller->now_money,
                    ]);
                }
            }
        });

        if ($notify) {
            $paid = $this->dao->get($fresh['paid_content_id']);
            if ($paid) {
                $this->notifySellerUnlock($paid, (int)$fresh['buyer_uid'], (float)$fresh['amount']);
            }
        }
    }

    /**
     * 支付成功回调
     */
    public function paySuccess(string $orderNo)
    {
        $orderDao = app()->make(CommunityPaidOrderDao::class);
        $order = $orderDao->search(['order_no' => $orderNo])->find();
        if (!$order) throw new ValidateException('订单不存在');
        if ($order['pay_status'] == 1) return $order;

        $this->completePaidOrder($order);
        return $orderDao->search(['order_no' => $orderNo])->find();
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
                    $q->field('community_id,title,image,is_type,create_time');
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
            $isVideo = (int)($community['is_type'] ?? 1) === 2;
            $items[] = [
                'id' => (int)($arr['id'] ?? 0),
                'community_id' => (int)($arr['community_id'] ?? 0),
                'title' => $community['title'] ?? ($arr['title'] ?? '付费内容'),
                'image' => $image,
                'cover' => $image[0] ?? '',
                'is_type' => (int)($community['is_type'] ?? 1),
                'is_video' => $isVideo,
                'price' => (float)($arr['price'] ?? 0),
                'buy_count' => (int)($arr['buy_count'] ?? 0),
                'total_income' => (float)($arr['total_income'] ?? 0),
                'video_paid_mode' => (int)($arr['video_paid_mode'] ?? 0),
                'video_trial_duration' => (float)($arr['video_trial_duration'] ?? 0),
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
                    $q->field('community_id,title,image,is_type,uid');
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
                'is_type' => (int)($community['is_type'] ?? 1),
                'is_video' => (int)($community['is_type'] ?? 1) === 2,
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
     * 获取平台抽成比例（小数，如 0.1 = 10%）
     */
    public function getCommissionRatio(string $type): float
    {
        return app()->make(CommissionConfigRepository::class)->getRateDecimal($type);
    }

    /**
     * 构建列表/详情用的付费 type_data
     */
    public function buildTypeData(array $paid, int $communityUid, $userInfo = null): array
    {
        $isUnlocked = false;
        if ($userInfo) {
            $uid = is_object($userInfo) ? (int)$userInfo->uid : (int)$userInfo;
            if ((int)$paid['uid'] === $uid || $communityUid === $uid) {
                $isUnlocked = true;
            } else {
                $orderDao = app()->make(CommunityPaidOrderDao::class);
                $isUnlocked = $orderDao->search([
                    'community_id' => $paid['community_id'],
                    'buyer_uid' => $uid,
                    'pay_status' => 1,
                ])->count() > 0;
            }
        }
        return [
            'price' => $paid['price'],
            'buy_count' => $paid['buy_count'],
            'video_paid_mode' => (int)($paid['video_paid_mode'] ?? 0),
            'video_trial_duration' => (float)($paid['video_trial_duration'] ?? 0),
            'video_duration' => (float)($paid['video_duration'] ?? 0),
            'is_unlocked' => $isUnlocked,
        ];
    }
}
