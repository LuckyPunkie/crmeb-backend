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

use app\common\repositories\community\CommunityRecruitRepository;
use app\common\repositories\community\CommunityRepository;
use app\validate\api\CommunityRecruitValidate;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

/**
 * 社区招聘
 */
class CommunityRecruit extends BaseController
{
    /**
     * @var CommunityRecruitRepository
     */
    protected $repository;
    protected $communityRepository;

    public function __construct(App $app, CommunityRecruitRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->communityRepository = app()->make(CommunityRepository::class);
        if (!systemConfig('community_status')) throw new ValidateException('未开启社区功能');
    }

    /**
     * 校验商家认证
     */
    protected function checkMerchantAuth()
    {
        $user = $this->request->userInfo();
        if (empty($user->mer_id) || $user->mer_id <= 0) {
            throw new ValidateException('非认证商家无权限', 10009);
        }
        return $user;
    }

    /**
     * 创建招聘笔记
     */
    public function create()
    {
        $user = $this->checkMerchantAuth();
        $data = $this->request->params([
            'title', 'content',
            'job_title', 'work_city', 'salary_range', 'job_desc',
            'job_require', 'hire_count', 'deadline', 'company_intro',
            'images', 'topic_id', 'topic_names'
        ]);
        app()->make(CommunityRecruitValidate::class)->check($data);

        // 正文优先用用户填写的 content（含 #话题），勿用 job_desc 覆盖
        $content = trim((string)($data['content'] ?? ''));
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            $title = (string)$data['job_title'];
        }

        // 创建 community 记录（与红包一致：话题走 topic_names）
        $communityData = [
            'uid' => $user->uid,
            'title' => $title,
            'content' => $content,
            'is_type' => $this->communityRepository::COMMUNIT_TYPE_FONT,
            'community_type' => 3,
            'community_type_data' => json_encode([
                'job_title' => $data['job_title'],
                'salary_range' => $data['salary_range'],
                'work_city' => $data['work_city'],
            ], JSON_UNESCAPED_UNICODE),
            'status' => 1,
            'is_show' => 1,
        ];
        if (!empty($data['images'])) {
            $communityData['image'] = is_array($data['images'])
                ? implode(',', $data['images'])
                : (string)$data['images'];
        }
        if (!empty($data['topic_id'])) {
            $communityData['topic_id'] = $data['topic_id'];
        }
        // 与红包一致：标签走 topic_names 单独入库
        $communityData['topic_names'] = $data['topic_names'] ?? [];

        $communityId = $this->communityRepository->create($communityData);

        // 创建 recruit 记录
        $recruitData = [
            'community_id' => $communityId,
            'mer_uid' => $user->uid,
            'job_title' => $data['job_title'],
            'work_city' => is_array($data['work_city']) ? json_encode($data['work_city']) : $data['work_city'],
            'salary_range' => $data['salary_range'],
            'job_desc' => $data['job_desc'],
            'job_require' => $data['job_require'],
            'hire_count' => (int)($data['hire_count'] ?? 0),
            'deadline' => $data['deadline'] ?? null,
            'company_intro' => $data['company_intro'] ?? '',
        ];

        app()->make(\app\common\dao\community\CommunityRecruitDao::class)->create($recruitData);

        return app('json')->success(['community_id' => $communityId]);
    }

    /**
     * 更新招聘笔记
     */
    public function update($id)
    {
        $user = $this->request->userInfo();
        $communityId = (int)$id;
        $community = $this->communityRepository->get($communityId);
        if (!$community || (int)$community['is_del'] === 1) {
            throw new ValidateException('招聘信息不存在');
        }
        if ((int)$community['uid'] !== (int)$user->uid) {
            throw new ValidateException('无权编辑');
        }
        if ((int)$community['community_type'] !== 3) {
            throw new ValidateException('非招聘内容');
        }

        $data = $this->request->params([
            'title', 'content',
            'job_title', 'work_city', 'salary_range', 'job_desc',
            'job_require', 'hire_count', 'deadline', 'company_intro',
            'images', 'topic_id', 'topic_names'
        ]);
        app()->make(CommunityRecruitValidate::class)->check($data);

        $content = trim((string)($data['content'] ?? ''));
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            $title = (string)$data['job_title'];
        }

        $communityData = [
            'title' => $title,
            'content' => $content,
            'community_type' => 3,
            'community_type_data' => json_encode([
                'job_title' => $data['job_title'],
                'salary_range' => $data['salary_range'],
                'work_city' => $data['work_city'],
            ], JSON_UNESCAPED_UNICODE),
        ];
        if (!empty($data['images'])) {
            $communityData['image'] = is_array($data['images'])
                ? implode(',', $data['images'])
                : (string)$data['images'];
        }
        // 话题始终交给 CommunityRepository::edit 解析同步（即使为空也要传，避免旧话题残留逻辑混乱）
        $communityData['topic_names'] = $data['topic_names'] ?? [];
        if (!empty($data['topic_id'])) {
            $communityData['topic_id'] = $data['topic_id'];
        }

        $this->communityRepository->edit($communityId, $communityData);

        $recruitDao = app()->make(\app\common\dao\community\CommunityRecruitDao::class);
        $recruit = $recruitDao->search(['community_id' => $communityId])->find();
        if (!$recruit) {
            throw new ValidateException('招聘岗位不存在');
        }
        $recruitDao->update($recruit['id'], [
            'job_title' => $data['job_title'],
            'work_city' => is_array($data['work_city']) ? json_encode($data['work_city'], JSON_UNESCAPED_UNICODE) : $data['work_city'],
            'salary_range' => $data['salary_range'],
            'job_desc' => $data['job_desc'],
            'job_require' => $data['job_require'],
            'hire_count' => (int)($data['hire_count'] ?? 0),
            'deadline' => $data['deadline'] ?? null,
            'company_intro' => $data['company_intro'] ?? '',
        ]);

        return app('json')->success(['community_id' => $communityId]);
    }

    /**
     * 获取招聘详情
     */
    public function detail($id)
    {
        $user = $this->request->isLogin() ? $this->request->userInfo() : null;
        $data = $this->communityRepository->show((int)$id, $user);

        // type_data → recruit（前端字段名）
        $data['recruit'] = $data['type_data'] ?? null;
        unset($data['type_data']);

        // work_city 统一成数组，避免前端拿到 JSON 字符串
        if (!empty($data['recruit']['work_city']) && is_string($data['recruit']['work_city'])) {
            $decoded = json_decode($data['recruit']['work_city'], true);
            if (is_array($decoded)) {
                $data['recruit']['work_city'] = $decoded;
            }
        }

        return app('json')->success($data);
    }

    /**
     * 投递简历
     */
    public function apply($id)
    {
        $uid = $this->request->uid();
        $resumeId = $this->request->param('resume_id');
        if (!$resumeId) throw new ValidateException('请选择简历');
        $apply = $this->repository->apply((int)$id, $uid, (int)$resumeId);
        return app('json')->success(['apply_id' => $apply['id']]);
    }

    /**
     * 我的招聘岗位列表（商家）
     */
    public function myList()
    {
        $user = $this->checkMerchantAuth();
        $status = $this->request->param('status', '');
        [$page, $limit] = $this->getPage();
        $data = $this->repository->myList($user->uid, $status, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 岗位应聘列表（商家）
     */
    public function applications($id)
    {
        $user = $this->checkMerchantAuth();
        $status = $this->request->param('status', '');
        [$page, $limit] = $this->getPage();
        $data = $this->repository->getApplications((int)$id, $user->uid, $status, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 商家全部应聘列表
     */
    public function merchantApplications()
    {
        $user = $this->checkMerchantAuth();
        $status = $this->request->param('status', '');
        $recruitId = $this->request->param('recruit_id', '');
        [$page, $limit] = $this->getPage();
        $data = $this->repository->getMerchantApplications($user->uid, $recruitId, $status, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 商家招聘统计
     */
    public function merchantStats()
    {
        $user = $this->checkMerchantAuth();
        $data = $this->repository->merchantStats($user->uid);
        return app('json')->success($data);
    }

    /**
     * 标记应聘状态（商家）
     */
    public function mark($applyId)
    {
        $user = $this->checkMerchantAuth();
        $status = $this->request->param('status', 1);
        $remark = $this->request->param('remark', '');
        if (!in_array($status, [1, 2, 3, 4])) throw new ValidateException('状态类型错误');
        $this->repository->markApply((int)$applyId, $user->uid, (int)$status, $remark);
        return app('json')->success('标记成功');
    }

    /**
     * 关闭/开启招聘
     */
    public function close($id)
    {
        $user = $this->checkMerchantAuth();
        $status = $this->request->param('status', 0);
        $this->repository->toggleStatus((int)$id, $user->uid, (int)$status);
        return app('json')->success('操作成功');
    }

    /**
     * 我的投递记录
     */
    public function myApplications()
    {
        $uid = $this->request->uid();
        $status = $this->request->param('status', '');
        [$page, $limit] = $this->getPage();
        $data = $this->repository->myApplications($uid, $status, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 投递详情
     */
    public function applicationDetail($id)
    {
        $uid = $this->request->uid();
        $data = $this->repository->applicationDetail((int)$id, $uid);
        return app('json')->success($data);
    }

    /**
     * 商家查看应聘者简历
     */
    public function applyResume($applyId)
    {
        $user = $this->checkMerchantAuth();
        $data = $this->repository->getApplyResume((int)$applyId, $user->uid);
        return app('json')->success($data);
    }
}
