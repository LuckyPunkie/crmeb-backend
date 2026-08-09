<?php

namespace app\controller\api\user;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\user\UserProfileRepository as repository;

class UserProfile extends BaseController
{
    protected $repository;

    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取当前用户社交档案
     * GET /api/user/profile
     */
    public function detail()
    {
        $uid = $this->request->uid();
        $userInfo = $this->request->userInfo();

        $profile = $this->repository->getByUid($uid);

        return app('json')->success([
            'uid'      => $uid,
            'sex'      => $userInfo['sex'] ?? 0,
            'birthday' => $userInfo['birthday'] ?? null,
            'profile'  => $profile,
        ]);
    }

    /**
     * 保存/更新当前用户社交档案
     * POST /api/user/profile/save
     */
    public function save()
    {
        $uid  = $this->request->uid();
        $sex  = $this->request->param('sex/d', -1);
        $data = $this->request->params([
            ['height', 0],
            ['weight', 0],
            ['birth_month', ''],
            ['zodiac', 0],
            ['education', 0],
            ['education_type', 0],
            ['job_title', ''],
            ['hometown_province', ''],
            ['hometown_city', ''],
            ['current_province', ''],
            ['current_city', ''],
            ['annual_income', 0],
            ['car_count', 0],
            ['house_count', 0],
            ['total_assets', 0],
            ['relationship_status', 0],
            ['dating_purpose', 0],
        ]);

        // 同步性别到 eb_user（1=男 2=女 3=保密）
        if ($sex === 1 || $sex === 2 || $sex === 3) {
            \app\common\model\user\User::where('uid', $uid)->update(['sex' => $sex]);
        }

        // 过滤掉值为 0 或空字符串的字段，允许部分更新
        $data = array_filter($data, function ($v) {
            return $v !== 0 && $v !== '';
        });

        if (!empty($data)) {
            $this->repository->save($uid, $data);
        }

        return app('json')->success('保存成功');
    }
}
