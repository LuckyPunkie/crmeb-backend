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

use app\common\dao\community\CommunityDao;
use app\common\dao\community\CommunityRedpacketDao;
use app\common\dao\community\CommunityRedpacketOrderDao;
use app\common\dao\community\CommunityRedpacketTaskDao;
use app\common\model\community\CommunityRedpacket;
use app\common\repositories\BaseRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 社区红包求助
 */
class CommunityRedpacketRepository extends BaseRepository
{
    /**
     * @var CommunityRedpacketDao
     */
    protected $dao;

    public function __construct(CommunityRedpacketDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取红包配置详情（含用户参与状态）
     */
    public function getDetail(int $communityId, $uid = null)
    {
        $data = $this->dao->search(['community_id' => $communityId])->find();
        if (!$data) throw new ValidateException('红包配置不存在');
        if ($uid) {
            $taskDao = app()->make(CommunityRedpacketTaskDao::class);
            $myTask = $taskDao->search(['redpacket_id' => $data['id'], 'uid' => $uid])->find();
            $data['my_task'] = $myTask;
        }
        return $data;
    }

    /**
     * 创建待支付红包求助（帖子先隐藏，支付成功后上线）
     */
    public function createPending(int $uid, array $data, int $communityId, string $totalAmount)
    {
        $orderNo = 'RP' . date('YmdHis') . rand(1000, 9999);

        return Db::transaction(function () use ($uid, $data, $communityId, $totalAmount, $orderNo) {
            $redpacket = $this->dao->create([
                'community_id' => $communityId,
                'uid' => $uid,
                'amount_per_person' => $data['amount_per_person'],
                'total_count' => $data['total_count'],
                'total_amount' => $totalAmount,
                'deadline' => $data['deadline'],
                'pay_status' => 0,
                'order_no' => $orderNo,
                'paid_amount' => 0,
            ]);

            // 强制写入支付字段：schema 缓存可能不含 pay_* 字段，模型 create 会被静默丢弃，默认 pay_status=1
            Db::execute(
                'UPDATE `eb_community_redpacket` SET `pay_status`=0, `order_no`=?, `paid_amount`=0 WHERE `id`=?',
                [$orderNo, (int)$redpacket['id']]
            );

            $orderDao = app()->make(CommunityRedpacketOrderDao::class);
            $orderDao->create([
                'order_no' => $orderNo,
                'redpacket_id' => $redpacket['id'],
                'community_id' => $communityId,
                'uid' => $uid,
                'amount' => $totalAmount,
                'pay_type' => 'balance',
                'pay_status' => 0,
            ]);

            return [
                'community_id' => $communityId,
                'redpacket_id' => $redpacket['id'],
                'order_no' => $orderNo,
                'total_amount' => (float)$totalAmount,
                'need_pay' => true,
            ];
        });
    }

    /**
     * 支付（开发期：全部走模拟支付，支付成功即到账平台并上架；正式接入后恢复真实扣款）
     */
    public function pay(int $uid, string $orderNo, string $payType = 'balance')
    {
        $orderDao = app()->make(CommunityRedpacketOrderDao::class);
        $order = $orderDao->search(['order_no' => $orderNo])->find();
        if (!$order) throw new ValidateException('订单不存在');
        if ((int)$order['uid'] !== $uid) throw new ValidateException('无权支付该订单');
        if ((int)$order['pay_status'] === 1) {
            app()->make(CommunityDao::class)->update((int)$order['community_id'], ['is_show' => 1]);
            return ['paid' => true, 'order_no' => $orderNo, 'amount' => (float)$order['amount']];
        }
        if ((int)$order['pay_status'] !== 0) throw new ValidateException('订单已关闭');

        $orderDao->update($order['id'], ['pay_type' => $payType ?: 'balance']);

        if ($payType === 'balance') {
            $this->payBalance($order, $uid);
            return ['paid' => true, 'order_no' => $orderNo, 'amount' => (float)$order['amount']];
        }

        if ($payType === 'mock') {
            if (!systemConfig('pay_mock_open')) {
                throw new ValidateException('未开启模拟支付');
            }
            $this->paySuccess($orderNo);
            return ['paid' => true, 'order_no' => $orderNo, 'amount' => (float)$order['amount'], 'mock' => true];
        }

        throw new ValidateException('请选择正确的支付方式');
    }

    /**
     * 余额支付预存（正式环境用；当前支付入口已走模拟，暂不调用）
     */
    protected function payBalance($order, int $uid): void
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

            app()->make(UserBillRepository::class)->decBill($uid, 'now_money', 'pay_product', [
                'link_id' => $order['id'],
                'status' => 1,
                'title' => '红包求助支付',
                'number' => $order['amount'],
                'mark' => '支付' . floatval($order['amount']) . '元红包求助（到平台）',
                'balance' => $user->now_money,
            ]);

            $this->paySuccess($order['order_no']);
        });
    }

    /**
     * 支付成功：订单已付、红包已付、帖子上线（当前视为到账平台）
     */
    public function paySuccess(string $orderNo)
    {
        $orderDao = app()->make(CommunityRedpacketOrderDao::class);
        $order = $orderDao->search(['order_no' => $orderNo])->find();
        if (!$order) throw new ValidateException('订单不存在');
        if ((int)$order['pay_status'] === 1) {
            app()->make(CommunityDao::class)->update((int)$order['community_id'], ['is_show' => 1]);
            return $order;
        }

        return Db::transaction(function () use ($orderDao, $order) {
            $now = date('Y-m-d H:i:s');
            $orderDao->update($order['id'], [
                'pay_status' => 1,
                'pay_time' => $now,
            ]);
            // 强制写支付字段，避免 schema 缓存丢字段
            Db::execute(
                'UPDATE `eb_community_redpacket` SET `pay_status`=1, `pay_type`=?, `order_no`=?, `paid_amount`=?, `pay_time`=? WHERE `id`=?',
                [
                    (string)($order['pay_type'] ?: 'mock'),
                    (string)$order['order_no'],
                    (string)$order['amount'],
                    $now,
                    (int)$order['redpacket_id'],
                ]
            );
            app()->make(CommunityDao::class)->update($order['community_id'], ['is_show' => 1]);
            return $orderDao->get($order['id']);
        });
    }

    /**
     * 领取红包任务
     */
    public function takeTask(int $communityId, int $uid)
    {
        $redpacket = $this->dao->search(['community_id' => $communityId])->find();
        if (!$redpacket) throw new ValidateException('红包配置不存在');
        if ((int)($redpacket['pay_status'] ?? 1) !== 1) throw new ValidateException('红包尚未支付，暂不可领取');
        if ($redpacket['status'] != 0) throw new ValidateException('红包活动已结束');
        if (strtotime($redpacket['deadline']) < time()) throw new ValidateException('求助已过期');
        if ($redpacket['taken_count'] >= $redpacket['total_count']) throw new ValidateException('任务名额已满', 10006);

        $taskDao = app()->make(CommunityRedpacketTaskDao::class);
        if ($taskDao->uidExists($redpacket['id'], $uid)) throw new ValidateException('已领取过该任务', 10005);

        $task = Db::transaction(function () use ($redpacket, $uid, $taskDao) {
            $task = $taskDao->create([
                'redpacket_id' => $redpacket['id'],
                'community_id' => $redpacket['community_id'],
                'uid' => $uid,
                'status' => 0,
                'take_time' => date('Y-m-d H:i:s'),
            ]);
            $this->dao->update($redpacket['id'], ['taken_count' => Db::raw('taken_count + 1')]);
            return $task;
        });

        // 通知发布者：有人领取
        $this->notifyRedpacketOwner(
            (int)$redpacket['uid'],
            $uid,
            'redpacket_take',
            '有人领取了你的红包任务',
            (int)$redpacket['community_id'],
            (int)($task['id'] ?? 0),
            '领取了你的红包任务'
        );

        return $task;
    }

    /**
     * 提交任务答案
     */
    public function submitTask(int $taskId, int $uid, array $data)
    {
        $taskDao = app()->make(CommunityRedpacketTaskDao::class);
        $task = $taskDao->get($taskId);
        if (!$task) throw new ValidateException('任务不存在');
        if ($task['uid'] != $uid) throw new ValidateException('无权操作');
        if (!in_array($task['status'], [0, 6])) throw new ValidateException('当前状态不可提交');

        $updateData = [
            'content' => $data['content'],
            'images' => isset($data['images']) ? json_encode($data['images']) : null,
            'status' => 1,
            'submit_time' => date('Y-m-d H:i:s'),
        ];
        $taskDao->update($taskId, $updateData);

        $redpacket = $this->dao->get($task['redpacket_id']);
        if ($redpacket) {
            $this->notifyRedpacketOwner(
                (int)$redpacket['uid'],
                $uid,
                'redpacket_submit',
                '有人提交了红包任务答案',
                (int)$redpacket['community_id'],
                $taskId,
                '提交了红包任务答案，请尽快审核'
            );
        }
    }

    /**
     * 审核任务（发布者确认/拒绝）
     */
    public function confirmTask(int $taskId, int $uid, bool $isValid, string $remark = '')
    {
        $taskDao = app()->make(CommunityRedpacketTaskDao::class);
        $task = $taskDao->get($taskId);
        if (!$task) throw new ValidateException('任务不存在');

        $redpacket = $this->dao->get($task['redpacket_id']);
        if ($redpacket['uid'] != $uid) throw new ValidateException('非发布者无权审核', 10007);
        if ($task['status'] != 1) throw new ValidateException('当前状态不可审核');

        Db::transaction(function () use ($taskDao, $taskId, $task, $redpacket, $isValid, $remark) {
            if ($isValid) {
                $taskDao->update($taskId, [
                    'status' => 2,
                    'review_remark' => $remark,
                    'review_time' => date('Y-m-d H:i:s'),
                ]);
                $this->dao->update($redpacket['id'], [
                    'completed_count' => Db::raw('completed_count + 1'),
                ]);

                $updated = $this->dao->get($redpacket['id']);
                if ($updated['completed_count'] >= $updated['total_count']) {
                    $this->dao->update($redpacket['id'], ['status' => 1]);
                }
            } else {
                $deadlinePassed = strtotime($redpacket['deadline']) < time();
                $newStatus = $deadlinePassed ? 3 : 6;
                $taskDao->update($taskId, [
                    'status' => $newStatus,
                    'review_remark' => $remark,
                    'review_time' => date('Y-m-d H:i:s'),
                ]);
                $this->dao->update($redpacket['id'], [
                    'taken_count' => Db::raw('taken_count - 1'),
                ]);
            }
        });

        // 驳回时通知领取者
        if (!$isValid) {
            $desc = '你的红包任务提交未通过审核';
            if ($remark !== '') {
                $desc .= '：' . mb_substr($remark, 0, 80);
            }
            $this->notifyRedpacketOwner(
                (int)$task['uid'],
                $uid,
                'redpacket_reject',
                '红包任务审核未通过',
                (int)$redpacket['community_id'],
                $taskId,
                $desc
            );
        }
    }

    /**
     * 红包相关通知
     */
    protected function notifyRedpacketOwner(
        int $toUid,
        int $fromUid,
        string $type,
        string $title,
        int $communityId,
        int $taskId,
        string $desc
    ): void {
        if ($toUid <= 0 || $toUid === $fromUid) {
            return;
        }
        try {
            $brief = \app\common\repositories\user\UserNotificationRepository::noteBriefById($communityId);
            $payload = \app\common\repositories\user\UserNotificationRepository::buildNoteContent([
                'community_id' => $communityId,
                'task_id' => $taskId,
                'title' => $brief['title'],
                'image' => $brief['image'],
                'content' => $desc,
                'text' => $desc,
            ]);
            app()->make(\app\common\repositories\user\UserNotificationRepository::class)
                ->createAndPush($toUid, $fromUid, $type, $title, $payload, 'community', $communityId);
        } catch (\Throwable $e) {
        }
    }

    /**
     * 我的红包列表
     */
    public function myList(int $uid, string $type, int $page, int $limit)
    {
        $taskDao = app()->make(CommunityRedpacketTaskDao::class);

        if ($type == 'publish') {
            $summaryCount = (int)$this->dao->search(['uid' => $uid])->count();
            $summaryAmount = (float)$this->dao->search(['uid' => $uid])->sum('total_amount');
            if ($summaryAmount <= 0) {
                $summaryAmount = (float)$this->dao->search(['uid' => $uid])->sum('paid_amount');
            }

            $query = $this->dao->search(['uid' => $uid])->with(['community'])->order('create_time DESC');
            $count = $query->count();
            $list = $query->page($page, $limit)->select();

            return [
                'count' => $count,
                'list' => $list,
                'summary' => [
                    'total_count' => $summaryCount,
                    'total_amount' => round($summaryAmount, 2),
                ],
            ];
        }

        // 我领取的
        $summaryCount = (int)$taskDao->search(['uid' => $uid])->count();
        $summaryAmount = (float)Db::name('community_redpacket_task')
            ->alias('t')
            ->leftJoin('community_redpacket r', 't.redpacket_id = r.id')
            ->where('t.uid', $uid)
            ->sum('r.amount_per_person');

        $redpacketIds = $taskDao->search(['uid' => $uid])->column('redpacket_id');
        if (empty($redpacketIds)) {
            return [
                'count' => 0,
                'list' => [],
                'summary' => [
                    'total_count' => 0,
                    'total_amount' => 0,
                ],
            ];
        }

        $query = CommunityRedpacket::getDB()
            ->where([['id', 'in', $redpacketIds]])
            ->with(['community', 'user' => function ($q) {
                $q->field('uid,nickname,avatar');
            }])
            ->order('create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();

        // 附带我的任务状态与单份金额，方便前端展示
        $taskMap = [];
        $tasks = $taskDao->search(['uid' => $uid])
            ->whereIn('redpacket_id', $redpacketIds)
            ->select();
        foreach ($tasks as $task) {
            $arr = is_array($task) ? $task : $task->toArray();
            $taskMap[(int)$arr['redpacket_id']] = $arr;
        }

        $items = [];
        foreach ($list as $row) {
            $arr = is_array($row) ? $row : $row->toArray();
            $task = $taskMap[(int)$arr['id']] ?? [];
            $user = $arr['user'] ?? [];
            $arr['amount'] = (float)($arr['amount_per_person'] ?? 0);
            $arr['nickname'] = $user['nickname'] ?? '';
            $arr['author_name'] = $user['nickname'] ?? '';
            $arr['submit_status'] = (int)($task['status'] ?? 0);
            $arr['note_status'] = (int)($task['status'] ?? 0);
            $arr['is_confirmed'] = (int)($task['status'] ?? 0);
            $arr['take_time'] = $task['take_time'] ?? ($task['create_time'] ?? '');
            $arr['create_time'] = $arr['take_time'] ?: ($arr['create_time'] ?? '');
            $items[] = $arr;
        }

        return [
            'count' => $count,
            'list' => $items,
            'summary' => [
                'total_count' => $summaryCount,
                'total_amount' => round($summaryAmount, 2),
            ],
        ];
    }

    /**
     * 任务参与用户列表
     */
    public function taskList(int $communityId, $status, int $page, int $limit)
    {
        $redpacket = $this->dao->search(['community_id' => $communityId])->find();
        if (!$redpacket) throw new ValidateException('红包配置不存在');

        $taskDao = app()->make(CommunityRedpacketTaskDao::class);
        $where = ['redpacket_id' => $redpacket['id']];
        if ($status !== null && $status !== '') {
            $where['status'] = (int)$status;
        }
        $query = $taskDao->search($where)->with(['user' => function ($query) {
            $query->field('uid,avatar,nickname');
        }])->order('create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
    }
}
