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

use app\common\repositories\community\CommunityPaidContentRepository;
use app\common\repositories\community\CommunityRepository;
use app\validate\api\CommunityPaidValidate;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

/**
 * 社区付费内容
 */
class CommunityPaidContent extends BaseController
{
    /**
     * @var CommunityPaidContentRepository
     */
    protected $repository;
    protected $communityRepository;

    public function __construct(App $app, CommunityPaidContentRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->communityRepository = app()->make(CommunityRepository::class);
        if (!systemConfig('community_status')) throw new ValidateException('未开启社区功能');
    }

    /**
     * 创建付费阅读笔记
     */
    public function create()
    {
        $data = $this->request->params([
            'title', 'free_content', 'paid_content', 'price',
            'trial_ratio', 'images', 'topic_id', 'topic_names', 'spu_id'
        ]);
        app()->make(CommunityPaidValidate::class)->check($data);

        $uid = $this->request->uid();

        // 校验免费预览 >= 10字符
        if (mb_strlen($data['free_content']) < 10) throw new ValidateException('免费预览内容不能少于10个字符');
        // 校验付费内容非空
        if (empty(strip_tags($data['paid_content']))) throw new ValidateException('付费内容不能为空');

        // 创建 community 记录
        $communityData = [
            'uid' => $uid,
            'title' => $data['title'],
            'content' => $data['free_content'],
            'is_type' => $this->communityRepository::COMMUNIT_TYPE_FONT,
            'community_type' => 2,
            'community_type_data' => json_encode([
                'price' => $data['price'],
                'trial_ratio' => $data['trial_ratio'] ?? 0,
            ]),
            'status' => 1,
            'is_show' => 1,
        ];
        if (!empty($data['images'])) $communityData['image'] = implode(',', $data['images']);
        if (!empty($data['topic_id'])) $communityData['topic_id'] = $data['topic_id'];
        if (!empty($data['topic_names'])) $communityData['topic_names'] = $data['topic_names'];
        // 付费预览区也参与话题解析
        $communityData['free_content'] = $data['free_content'];

        $communityId = $this->communityRepository->create($communityData);

        // 创建 paid_content 记录
        $paidData = [
            'community_id' => $communityId,
            'uid' => $uid,
            'price' => $data['price'],
            'trial_ratio' => $data['trial_ratio'] ?? 0,
            'free_content' => $data['free_content'],
            'paid_content' => $data['paid_content'],
        ];

        app()->make(\app\common\dao\community\CommunityPaidDao::class)->create($paidData);

        return app('json')->success(['community_id' => $communityId]);
    }

    /**
     * 更新付费阅读笔记（作者）
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
        if ((int)$community['community_type'] !== 2) {
            throw new ValidateException('非付费阅读内容');
        }

        $data = $this->request->params([
            'title', 'free_content', 'paid_content', 'price',
            'trial_ratio', 'images', 'topic_id', 'topic_names', 'spu_id'
        ]);
        app()->make(CommunityPaidValidate::class)->check($data);

        if (mb_strlen($data['free_content']) < 10) throw new ValidateException('免费预览内容不能少于10个字符');
        if (empty(strip_tags($data['paid_content']))) throw new ValidateException('付费内容不能为空');

        $paidDao = app()->make(\app\common\dao\community\CommunityPaidDao::class);
        $paid = $paidDao->search(['community_id' => $communityId])->find();
        if (!$paid) {
            throw new ValidateException('付费内容不存在');
        }

        // 已有购买记录时不允许改价
        $price = $data['price'];
        if ((int)($paid['buy_count'] ?? 0) > 0) {
            $price = $paid['price'];
        }

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            $title = mb_substr((string)$data['free_content'], 0, 30) ?: '付费内容';
        }

        $communityData = [
            'title' => $title,
            'content' => $data['free_content'],
            'community_type' => 2,
            'community_type_data' => json_encode([
                'price' => $price,
                'trial_ratio' => $data['trial_ratio'] ?? 0,
            ], JSON_UNESCAPED_UNICODE),
            'topic_names' => $data['topic_names'] ?? [],
            'free_content' => $data['free_content'],
        ];
        if (!empty($data['images'])) {
            $communityData['image'] = is_array($data['images'])
                ? implode(',', $data['images'])
                : (string)$data['images'];
        }
        if (!empty($data['topic_id'])) {
            $communityData['topic_id'] = $data['topic_id'];
        }

        $this->communityRepository->edit($communityId, $communityData);

        $paidDao->update($paid['id'], [
            'price' => $price,
            'trial_ratio' => $data['trial_ratio'] ?? 0,
            'free_content' => $data['free_content'],
            'paid_content' => $data['paid_content'],
        ]);

        return app('json')->success(['community_id' => $communityId]);
    }

    /**
     * 获取付费阅读详情
     */
    public function detail($id)
    {
        $uid = $this->request->isLogin() ? $this->request->uid() : null;
        $user = $uid ? $this->request->userInfo() : null;
        $data = $this->communityRepository->show((int)$id, $user);

        // 获取付费内容详情（含解锁状态判断：已购买/作者本人 → unlocked=true）
        $paidDetail = $this->repository->getDetail((int)$id, $uid);
        $data['paid_content'] = $paidDetail;
        $data['unlocked'] = $paidDetail['is_unlocked'] ?? false;
        unset($data['type_data']);

        return app('json')->success($data);
    }

    /**
     * 解锁付费内容
     */
    public function unlock($id)
    {
        $uid = $this->request->uid();
        $payType = $this->request->param('pay_type', 'balance');
        $result = $this->repository->unlock((int)$id, $uid, $payType);
        return app('json')->success($result);
    }

    /**
     * 检查是否已解锁
     */
    public function check($id)
    {
        $uid = $this->request->uid();
        $unlocked = $this->repository->checkUnlocked((int)$id, $uid);
        return app('json')->success(['unlocked' => $unlocked]);
    }

    /**
     * 我的付费收益
     */
    public function income()
    {
        $uid = $this->request->uid();
        [$page, $limit] = $this->getPage();
        $dateRange = $this->request->param('date_range', '');
        $dateRange = $dateRange ? explode(',', $dateRange) : [];
        $data = $this->repository->getIncome($uid, $dateRange, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 付费订单列表
     */
    public function orders()
    {
        $uid = $this->request->uid();
        [$page, $limit] = $this->getPage();
        $communityId = $this->request->param('community_id', null);
        $data = $this->repository->getOrders($uid, $communityId, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 我发布的付费笔记
     */
    public function published()
    {
        $uid = $this->request->uid();
        [$page, $limit] = $this->getPage();
        $data = $this->repository->getPublishedList($uid, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 我解锁的付费笔记
     */
    public function unlocked()
    {
        $uid = $this->request->uid();
        [$page, $limit] = $this->getPage();
        $data = $this->repository->getUnlockedList($uid, $page, $limit);
        return app('json')->success($data);
    }
}
