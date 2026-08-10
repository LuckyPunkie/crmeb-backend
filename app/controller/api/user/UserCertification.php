<?php

namespace app\controller\api\user;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\user\UserCertificationRepository as repository;
use think\facade\Db;

class UserCertification extends BaseController
{
    protected $repository;

    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取当前用户所有认证记录
     * GET /api/user/certification
     */
    public function list()
    {
        $uid  = $this->request->uid();
        $list = $this->repository->getByUid($uid);

        $seen = [];
        foreach ($list as &$item) {
            $item['images'] = $item['images'] ? json_decode($item['images'], true) : [];
            // 单条展示：已通过统一称 AI审核通过（人工总状态另取）
            if ((int)$item['status'] === 1) {
                $item['status_label'] = 'AI审核通过';
            } elseif ((int)$item['status'] === 2) {
                $item['status_label'] = '认证失败';
            } else {
                $item['status_label'] = '未认证';
            }
            $type = (string)($item['type'] ?? '');
            $item['is_latest'] = $type !== '' && !isset($seen[$type]);
            if ($item['is_latest']) {
                $seen[$type] = true;
            }
        }
        unset($item);

        $user = Db::name('user')->where('uid', $uid)->find() ?: [];
        $review = $this->repository->buildReviewDisplay($user);
        // 若用户级已人工复审，覆盖通过项文案
        if ((int)($review['profile_review_status'] ?? 0) === repository::REVIEW_MANUAL_PASS) {
            foreach ($list as &$item) {
                if ((int)$item['status'] === 1) {
                    $item['status_label'] = '人工复审';
                }
            }
            unset($item);
        }

        return app('json')->success($list);
    }

    /**
     * 当前登录用户审核状态
     * GET /api/user/review_status
     */
    public function reviewStatus()
    {
        $uid = $this->request->uid();
        $user = Db::name('user')->where('uid', $uid)->find() ?: [];
        return app('json')->success($this->repository->buildReviewDisplay($user));
    }

    /**
     * 公开查询某用户审核状态（无需登录）
     * GET /api/community/user/review_status/:uid
     */
    public function reviewStatusPublic(int $uid)
    {
        $user = Db::name('user')->where('uid', $uid)->whereNull('cancel_time')->find();
        if (!$user) {
            return app('json')->fail('用户不存在');
        }
        return app('json')->success($this->repository->buildReviewDisplay($user));
    }

    /**
     * 申请加急复审（无需登录）
     * POST /api/community/user/review_urgent/:uid
     * 或 POST /api/user/review_urgent/:uid（需登录）
     */
    public function applyUrgent(int $uid)
    {
        try {
            $data = $this->repository->applyUrgent($uid);
        } catch (\InvalidArgumentException $e) {
            return app('json')->fail($e->getMessage());
        }
        return app('json')->success($data);
    }

    /**
     * 接收前端 WebView 提取的学历信息，比对姓名后写库
     * POST /api/user/certification/chsi_verify
     */
    public function chsiVerify()
    {
        $uid    = $this->request->uid();
        $name   = trim($this->request->post('name', ''));
        $school = trim($this->request->post('school', ''));
        $major  = trim($this->request->post('major', ''));
        $level  = trim($this->request->post('level', ''));

        if (!$name) {
            return app('json')->fail('未获取到备案姓名');
        }

        $user = Db::name('user')->where('uid', $uid)->find();
        if (!$user) return app('json')->fail('用户不存在');

        $realName = trim($user['real_name'] ?? '');
        if (!$realName) {
            return app('json')->fail('请先完成实名认证再进行学历核验');
        }
        if ($name !== $realName) {
            return app('json')->fail('备案报告姓名与实名不一致');
        }

        $desc = "学信网核验通过：{$school} {$major}（{$level}）";
        try {
            $this->repository->save($uid, 'education', $desc, []);
        } catch (\InvalidArgumentException $e) {
            return app('json')->fail($e->getMessage());
        }

        return app('json')->success([
            'school' => $school,
            'major'  => $major,
            'level'  => $level,
        ], '学历核验成功');
    }

    /**
     * 提交/更新认证（自动视为 AI 通过）
     * POST /api/user/certification/save
     */
    public function save()
    {
        $uid         = $this->request->uid();
        $type        = $this->request->param('type', '');
        $description = $this->request->param('description', '');
        $images      = $this->request->param('images', []);

        if (!$type) {
            return app('json')->fail('认证类型不能为空');
        }

        if (!is_array($images)) {
            $images = $images ? json_decode($images, true) : [];
        }
        if (!is_array($images)) {
            $images = [];
        }
        $images = array_values(array_filter($images, static function ($url) {
            return is_string($url) && $url !== '';
        }));

        try {
            $this->repository->save($uid, $type, $description, $images);
        } catch (\InvalidArgumentException $e) {
            return app('json')->fail($e->getMessage());
        }

        return app('json')->success('提交成功，AI审核已通过');
    }
}
