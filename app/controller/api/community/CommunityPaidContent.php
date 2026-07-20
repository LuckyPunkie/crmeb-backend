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
            'trial_ratio', 'images', 'topic_id', 'spu_id'
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
     * 获取付费阅读详情
     */
    public function detail($id)
    {
        $uid = $this->request->isLogin() ? $this->request->userInfo()->uid : null;
        $data = $this->repository->getDetail((int)$id, $uid);
        return app('json')->success($data);
    }

    /**
     * 解锁付费内容
     */
    public function unlock($id)
    {
        $uid = $this->request->uid();
        $payType = $this->request->param('pay_type', 'balance');
        $order = $this->repository->unlock((int)$id, $uid, $payType);
        return app('json')->success([
            'paid' => false,
            'order_no' => $order['order_no'],
            'amount' => $order['amount'],
        ]);
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
}
