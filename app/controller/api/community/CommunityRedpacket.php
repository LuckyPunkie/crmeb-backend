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
     * 创建红包求助笔记
     */
    public function create()
    {
        $data = $this->request->params([
            'title', 'content', 'images', 'topic_id', 'spu_id',
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

        // 创建 community 记录
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
            'is_show' => 1,
        ];
        if (!empty($data['images'])) $communityData['image'] = implode(',', $data['images']);
        if (!empty($data['topic_id'])) $communityData['topic_id'] = $data['topic_id'];

        $communityId = $this->communityRepository->create($communityData);

        // 创建 redpacket 记录
        $redpacketData = [
            'community_id' => $communityId,
            'uid' => $uid,
            'amount_per_person' => $data['amount_per_person'],
            'total_count' => $data['total_count'],
            'total_amount' => $totalAmount,
            'deadline' => $data['deadline'],
        ];

        $redpacket = app()->make(\app\common\dao\community\CommunityRedpacketDao::class)->create($redpacketData);

        return app('json')->success([
            'community_id' => $communityId,
            'total_amount' => $totalAmount,
            'redpacket_id' => $redpacket['id'],
        ]);
    }

    /**
     * 获取红包求助详情
     */
    public function detail($id)
    {
        $uid = $this->request->isLogin() ? $this->request->userInfo()->uid : null;
        $data = $this->repository->getDetail((int)$id, $uid);
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
