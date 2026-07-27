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

namespace app\controller\api\community;

use app\common\repositories\community\CommunityRepository;
use app\common\repositories\community\CommunityRedpacketRepository;
use app\validate\api\CommunityRedpacketValidate;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

/**
 * 社区红包求助
 */
class CommunityRedpacket extends BaseController
{
    /**
     * @var CommunityRedpacketRepository
     */
    protected $repository;
    protected $communityRepository;

    public function __construct(App $app, CommunityRedpacketRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->communityRepository = app()->make(CommunityRepository::class);
        if (!systemConfig('community_status')) throw new ValidateException('未开启社区功能');
    }

    /**
     * 创建红包求助笔记（待支付，支付成功后上线）
     */
    public function create()
    {
        $data = $this->request->params([
            'title', 'content', 'images', 'topic_id', 'topic_names', 'spu_id',
            'amount_per_person', 'total_count', 'deadline'
        ]);
        app()->make(CommunityRedpacketValidate::class)->check($data);

        $uid = $this->request->uid();
        $totalAmount = bcmul($data['amount_per_person'], $data['total_count'], 2);
        if ($totalAmount > 10000) throw new ValidateException('预付总额不能超过10000元', 10012);

        // 检查截止时间：>=1小时，<=30天
        $deadline = strtotime($data['deadline']);
        if ($deadline - time() < 3600) throw new ValidateException('截止时间至少为1小时后');
        if ($deadline - time() > 2592000) throw new ValidateException('截止时间不能超过30天');

        // 创建 community 记录（未支付先隐藏）
        $communityData = [
            'uid' => $uid,
            'title' => $data['title'],
            'content' => $data['content'],
            'is_type' => $this->communityRepository::COMMUNIT_TYPE_FONT,
            'community_type' => 1,
            'community_type_data' => json_encode([
                'amount_per_person' => $data['amount_per_person'],
                'total_count' => $data['total_count'],
                'total_amount' => $totalAmount,
            ]),
            'status' => 1,
            'is_show' => 0,
        ];
        if (!empty($data['images'])) $communityData['image'] = implode(',', $data['images']);
        if (!empty($data['topic_id'])) $communityData['topic_id'] = $data['topic_id'];
        if (!empty($data['topic_names'])) $communityData['topic_names'] = $data['topic_names'];
        $communityData['spu_id'] = $data['spu_id'] ?? [];

        $communityId = $this->communityRepository->create($communityData);
        $result = $this->repository->createPending($uid, $data, $communityId, $totalAmount);

        return app('json')->success($result);
    }

    /**
     * 更新红包求助笔记（作者）
     */
    public function update($id)
    {
        $uid = $this->request->uid();
        $communityId = (int)$id;
        $community = $this->communityRepository->get($communityId);
        if (!$community || (int)$community['is_del'] === 1) {
            throw new ValidateException('内容不存在');
        }
        if ((int)$community['uid'] !== (int)$uid) {
            throw new ValidateException('无权编辑');
        }
        if ((int)$community['community_type'] !== 1) {
            throw new ValidateException('非红包求助内容');
        }

        $data = $this->request->params([
            'title', 'content', 'images', 'topic_id', 'topic_names', 'spu_id',
            'amount_per_person', 'total_count', 'deadline'
        ]);

        $redpacketDao = app()->make(\app\common\dao\community\CommunityRedpacketDao::class);
        $redpacket = $redpacketDao->search(['community_id' => $communityId])->find();
        if (!$redpacket) {
            throw new ValidateException('红包配置不存在');
        }

        $paid = (int)$redpacket['pay_status'] === 1;
        // 已支付后不允许改金额/人数，仅允许改文案、图片、话题、截止时间
        if (!$paid) {
            app()->make(CommunityRedpacketValidate::class)->check($data);
            $totalAmount = bcmul($data['amount_per_person'], $data['total_count'], 2);
            if ($totalAmount > 10000) throw new ValidateException('预付总额不能超过10000元', 10012);
        } else {
            if (empty($data['deadline'])) {
                $data['deadline'] = $redpacket['deadline'];
            }
            $data['amount_per_person'] = $redpacket['amount_per_person'];
            $data['total_count'] = $redpacket['total_count'];
            $totalAmount = $redpacket['total_amount'];
        }

        $deadline = strtotime($data['deadline']);
        if ($deadline === false) throw new ValidateException('截止时间格式错误');
        if ($deadline - time() < 3600) throw new ValidateException('截止时间至少为1小时后');
        if ($deadline - time() > 2592000) throw new ValidateException('截止时间不能超过30天');

        $title = trim((string)($data['title'] ?? ''));
        $content = trim((string)($data['content'] ?? ''));
        if ($title === '') {
            $title = mb_substr($content, 0, 30) ?: '红包求助';
        }

        $communityData = [
            'title' => $title,
            'content' => $content,
            'community_type' => 1,
            'community_type_data' => json_encode([
                'amount_per_person' => $data['amount_per_person'],
                'total_count' => $data['total_count'],
                'total_amount' => $totalAmount,
            ], JSON_UNESCAPED_UNICODE),
            'topic_names' => $data['topic_names'] ?? [],
        ];
        if (!empty($data['images'])) {
            $communityData['image'] = is_array($data['images'])
                ? implode(',', $data['images'])
                : (string)$data['images'];
        }
        if (!empty($data['topic_id'])) {
            $communityData['topic_id'] = $data['topic_id'];
        }
        if (isset($data['spu_id'])) {
            $communityData['spu_id'] = $data['spu_id'] ?? [];
        }

        $this->communityRepository->edit($communityId, $communityData);

        $rpUpdate = [
            'deadline' => $data['deadline'],
        ];
        if (!$paid) {
            $rpUpdate['amount_per_person'] = $data['amount_per_person'];
            $rpUpdate['total_count'] = $data['total_count'];
            $rpUpdate['total_amount'] = $totalAmount;
        }
        $redpacketDao->update($redpacket['id'], $rpUpdate);

        return app('json')->success(['community_id' => $communityId]);
    }

    /**
     * 支付红包预存（对齐付费解锁）
     */
    public function pay()
    {
        $uid = $this->request->uid();
        $orderNo = (string)$this->request->param('order_no', '');
        $payType = (string)$this->request->param('pay_type', 'balance');
        if ($orderNo === '') throw new ValidateException('缺少订单号');
        $result = $this->repository->pay($uid, $orderNo, $payType);
        return app('json')->success($result);
    }

    /**
     * 获取红包求助详情
     */
    public function detail($id)
    {
        $user = $this->request->isLogin() ? $this->request->userInfo() : null;
        $data = $this->communityRepository->show((int)$id, $user);

        // 把 type_data（红包配置）重命名为 redpacket，my_task 提到顶层
        $typeData = $data['type_data'] ?? null;
        if ($typeData) {
            $data['my_task'] = $typeData['my_task'] ?? null;
            unset($typeData['my_task']);
            $data['redpacket'] = $typeData;
        } else {
            $data['redpacket'] = null;
            $data['my_task'] = null;
        }
        unset($data['type_data']);

        return app('json')->success($data);
    }

    /**
     * 领取红包任务
     */
    public function take($id)
    {
        $uid = $this->request->uid();
        $task = $this->repository->takeTask((int)$id, $uid);
        return app('json')->success(['task_id' => $task['id']]);
    }

    /**
     * 提交任务答案
     */
    public function submit($taskId)
    {
        $uid = $this->request->uid();
        $data = $this->request->params(['content', 'images']);
        if (empty($data['content'])) throw new ValidateException('请填写提交内容');
        $this->repository->submitTask((int)$taskId, $uid, $data);
        return app('json')->success('提交成功，等待审核');
    }

    /**
     * 审核任务（发布者确认/拒绝）
     */
    public function confirm($taskId)
    {
        $uid = $this->request->uid();
        $isValid = $this->request->param('is_valid', 0) == 1;
        $remark = $this->request->param('remark', '');
        $this->repository->confirmTask((int)$taskId, $uid, $isValid, $remark);
        return app('json')->success('审核完成');
    }

    /**
     * 我的红包求助列表
     */
    public function myList()
    {
        $type = $this->request->param('type', 'publish');
        [$page, $limit] = $this->getPage();
        $uid = $this->request->uid();
        $data = $this->repository->myList($uid, $type, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 任务参与用户列表
     */
    public function taskList($id)
    {
        $status = $this->request->param('status', '');
        [$page, $limit] = $this->getPage();
        $data = $this->repository->taskList((int)$id, $status, $page, $limit);
        return app('json')->success($data);
    }
}
