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

use app\common\dao\community\CommunityRedpacketDao;
use app\common\dao\community\CommunityRedpacketTaskDao;
use app\common\repositories\BaseRepository;
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
     * 领取红包任务
     */
    public function takeTask(int $communityId, int $uid)
    {
        $redpacket = $this->dao->search(['community_id' => $communityId])->find();
        if (!$redpacket) throw new ValidateException('红包配置不存在');
        if ($redpacket['status'] != 0) throw new ValidateException('红包活动已结束');
        if (strtotime($redpacket['deadline']) < time()) throw new ValidateException('求助已过期');
        if ($redpacket['taken_count'] >= $redpacket['total_count']) throw new ValidateException('任务名额已满', 10006);

        $taskDao = app()->make(CommunityRedpacketTaskDao::class);
        if ($taskDao->uidExists($redpacket['id'], $uid)) throw new ValidateException('已领取过该任务', 10005);

        return Db::transaction(function () use ($redpacket, $uid, $taskDao) {
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

        return Db::transaction(function () use ($taskDao, $taskId, $task, $redpacket, $isValid, $remark) {
            if ($isValid) {
                $taskDao->update($taskId, [
                    'status' => 2,
                    'review_remark' => $remark,
                    'review_time' => date('Y-m-d H:i:s'),
                ]);
                $this->dao->update($redpacket['id'], [
                    'completed_count' => Db::raw('completed_count + 1'),
                ]);

                // 检查是否全部完成
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
                // 释放名额
                $this->dao->update($redpacket['id'], [
                    'taken_count' => Db::raw('taken_count - 1'),
                ]);
            }
        });
    }

    /**
     * 我的红包列表
     */
    public function myList(int $uid, string $type, int $page, int $limit)
    {
        if ($type == 'publish') {
            $query = $this->dao->search(['uid' => $uid])->with(['community'])->order('create_time DESC');
        } else {
            $taskDao = app()->make(CommunityRedpacketTaskDao::class);
            $redpacketIds = $taskDao->search(['uid' => $uid])->column('redpacket_id');
            $query = $this->dao->getWhere([['id', 'in', $redpacketIds]])->with(['community'])->order('create_time DESC');
        }
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
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
