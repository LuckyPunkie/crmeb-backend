<?php

namespace app\controller\api\user;

use think\App;
use crmeb\basic\BaseController;
use crmeb\services\security\ContentSecurityService;
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
            $type = (string)($item['type'] ?? '');
            if (in_array($type, ['identity', 'realname', 'real_name'], true)) {
                if ((int)$item['status'] === 1) {
                    $item['status_label'] = '已认证';
                } elseif ((int)$item['status'] === 2) {
                    $item['status_label'] = '认证失败';
                } else {
                    $item['status_label'] = '未认证';
                }
            } elseif ((int)$item['status'] === 1) {
                $item['status_label'] = 'AI审核通过';
            } elseif ((int)$item['status'] === 2) {
                $item['status_label'] = '认证失败';
            } else {
                $item['status_label'] = '未认证';
            }
            $item['is_latest'] = $type !== '' && !isset($seen[$type]);
            if ($item['is_latest']) {
                $seen[$type] = true;
            }
        }
        unset($item);

        $user = Db::name('user')->where('uid', $uid)->find() ?: [];
        $review = $this->repository->buildReviewDisplay($user);
        // 若用户级已人工复审，覆盖通过项文案（不含实名三要素）
        if ((int)($review['profile_review_status'] ?? 0) === repository::REVIEW_MANUAL_PASS) {
            foreach ($list as &$item) {
                $t = (string)($item['type'] ?? '');
                if (in_array($t, ['identity', 'realname', 'real_name'], true)) {
                    continue;
                }
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

        if (!$name || !$school) {
            return app('json')->fail('未获取到完整学历信息');
        }

        $desc = "学信网核验：{$name} {$school} {$major}（{$level}）";
        try {
            $this->repository->save($uid, 'education', $desc, []);
        } catch (\InvalidArgumentException $e) {
            return app('json')->fail($e->getMessage());
        }

        // 同步写入用户表
        Db::name('user')->where('uid', $uid)->update([
            'edu_school' => $school,
            'edu_major'  => $major,
            'edu_level'  => $level,
        ]);

        return app('json')->success([
            'name'   => $name,
            'school' => $school,
            'major'  => $major,
            'level'  => $level,
        ], '学历核验成功');
    }

    /**
     * 运营商三要素实名核验
     * POST /api/user/certification/identity_verify
     */
    public function identityVerify()
    {
        $uid = $this->request->uid();
        $realName = trim((string)$this->request->post('real_name', ''));
        $idCard = trim((string)$this->request->post('id_card', ''));

        try {
            $data = $this->repository->verifyIdentity($uid, $realName, $idCard);
        } catch (\InvalidArgumentException $e) {
            return app('json')->fail($e->getMessage());
        }

        if (!$data['passed']) {
            return app('json')->fail($data['message'], $data);
        }

        return app('json')->success($data, $data['message'] ?: '实名认证成功');
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

        $openid = (string)($this->request->userInfo()->wechat->routine_openid ?? '');
        if (!empty($description)) {
            ContentSecurityService::checkText(
                (string)$description,
                ContentSecurityService::SCENE_PROFILE,
                'certification',
                0,
                $uid,
                $openid
            );
        }

        try {
            $this->repository->save($uid, $type, $description, $images);
        } catch (\InvalidArgumentException $e) {
            return app('json')->fail($e->getMessage());
        }

        // 认证图片先发后审，违规时按后台驳回处理（改状态、撤标签、通知）；记录按 uid+类型覆盖，需清旧任务
        if ($images) {
            $certId = (int)Db::name('user_certification')->where('uid', $uid)->where('type', $type)->value('id');
            ContentSecurityService::dispatchMediaCheck('certification', $certId, (int)$uid, $openid, $images,
                ContentSecurityService::SCENE_PROFILE, true);
        }

        return app('json')->success('提交成功，AI审核已通过');
    }
}
