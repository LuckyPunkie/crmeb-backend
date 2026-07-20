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

use app\common\repositories\community\CommunityResumeRepository;
use app\validate\api\CommunityResumeValidate;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

/**
 * 社区简历
 */
class CommunityResume extends BaseController
{
    /**
     * @var CommunityResumeRepository
     */
    protected $repository;

    public function __construct(App $app, CommunityResumeRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        if (!systemConfig('community_status')) throw new ValidateException('未开启社区功能');
    }

    /**
     * 简历文件解析（异步）
     */
    public function parse()
    {
        $uid = $this->request->uid();
        $resumeId = $this->request->param('resume_id');
        if (!$resumeId) throw new ValidateException('缺少简历ID');
        $taskId = $this->repository->parseResume((int)$resumeId, $uid);
        return app('json')->success(['task_id' => $taskId], '解析任务已提交');
    }

    /**
     * 创建简历
     */
    public function create()
    {
        $uid = $this->request->uid();
        $data = $this->request->params([
            'real_name', 'gender', 'birthday', 'phone', 'email',
            'education', 'work_years', 'city', 'expect_job', 'expect_salary',
            'education_history', 'work_history', 'self_evaluation', 'resume_file',
            'is_default'
        ]);
        app()->make(CommunityResumeValidate::class)->check($data);

        if (isset($data['education_history']) && is_array($data['education_history'])) {
            $data['education_history'] = json_encode($data['education_history']);
        }
        if (isset($data['work_history']) && is_array($data['work_history'])) {
            $data['work_history'] = json_encode($data['work_history']);
        }

        $resume = $this->repository->create($data, $uid);
        return app('json')->success(['resume_id' => $resume['id']]);
    }

    /**
     * 更新简历
     */
    public function update($id)
    {
        $uid = $this->request->uid();
        $data = $this->request->params([
            'real_name', 'gender', 'birthday', 'phone', 'email',
            'education', 'work_years', 'city', 'expect_job', 'expect_salary',
            'education_history', 'work_history', 'self_evaluation', 'resume_file',
            'is_default'
        ]);

        if (isset($data['education_history']) && is_array($data['education_history'])) {
            $data['education_history'] = json_encode($data['education_history']);
        }
        if (isset($data['work_history']) && is_array($data['work_history'])) {
            $data['work_history'] = json_encode($data['work_history']);
        }

        $this->repository->updateResume((int)$id, $uid, $data);
        return app('json')->success(['resume_id' => (int)$id]);
    }

    /**
     * 获取简历详情
     */
    public function detail($id)
    {
        $uid = $this->request->uid();
        $data = $this->repository->getDetail((int)$id, $uid);
        return app('json')->success($data);
    }

    /**
     * 我的简历列表
     */
    public function myList()
    {
        $uid = $this->request->uid();
        $data = $this->repository->myList($uid);
        return app('json')->success($data);
    }

    /**
     * 删除简历
     */
    public function delete($id)
    {
        $uid = $this->request->uid();
        $this->repository->deleteResume((int)$id, $uid);
        return app('json')->success('删除成功');
    }

    /**
     * 上传简历文件
     */
    public function upload()
    {
        $uid = $this->request->uid();
        $file = $this->request->file('file');
        if (!$file) throw new ValidateException('请上传文件');

        $ext = strtolower(pathinfo($file->getOriginalName(), PATHINFO_EXTENSION));
        if (!in_array($ext, ['doc', 'docx', 'pdf'])) {
            throw new ValidateException('仅支持 Word/PDF 格式');
        }

        $savePath = 'uploads/resume/' . date('Ymd');
        $saveName = \think\facade\Filesystem::disk('public')->putFile($savePath, $file);
        $url = '/storage/' . str_replace('\\', '/', $saveName);

        return app('json')->success(['url' => $url]);
    }

    /**
     * 设为默认简历
     */
    public function setDefault($id)
    {
        $uid = $this->request->uid();
        $this->repository->setDefault((int)$id, $uid);
        return app('json')->success('已设为默认');
    }
}
