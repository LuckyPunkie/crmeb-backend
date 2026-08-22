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
        if (!empty($profile['hobbies']) && is_string($profile['hobbies'])) {
            $decoded = json_decode($profile['hobbies'], true);
            $profile['hobbies'] = is_array($decoded) ? $decoded : [];
        } elseif (empty($profile['hobbies'])) {
            $profile['hobbies'] = [];
        }

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
        $uid   = $this->request->uid();
        $sex   = $this->request->param('sex/d', -1);
        $input = $this->request->param();

        // 同步性别到 eb_user（1=男 2=女 3=保密）
        if ($sex === 1 || $sex === 2 || $sex === 3) {
            \app\common\model\user\User::where('uid', $uid)->update(['sex' => $sex]);
        }

        // 仅处理请求里实际出现的字段，避免默认空串清空已有数据
        $intFields = [
            'height', 'weight', 'zodiac', 'education', 'education_type',
            'annual_income', 'car_count', 'house_count', 'total_assets',
            'relationship_status', 'dating_purpose',
            'marital_status', 'want_kids', 'smoking', 'drinking', 'tattoo', 'only_child',
            'hope_age_min', 'hope_age_max', 'hope_height_min', 'hope_education',
        ];
        $stringFields = [
            'birth_month', 'job_title',
            'hometown_province', 'hometown_city', 'current_province', 'current_city',
            'school_name', 'pets', 'about_me', 'hope_cities', 'hope_text',
            'cover_info', 'cover_about', 'cover_hope', 'cover_hobby',
            'hobby_photo_1', 'hobby_photo_2',
        ];
        // 允许空串写入（用于删除封面图等）
        $allowEmpty = [
            'cover_info', 'cover_about', 'cover_hope', 'cover_hobby',
            'hobby_photo_1', 'hobby_photo_2', 'about_me', 'hope_text',
            'hobbies', 'pets', 'school_name', 'hope_cities',
        ];
        // 允许写入 0
        $allowZero = ['car_count', 'house_count'];

        $filtered = [];
        foreach ($intFields as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = (int)$input[$key];
            if ($value !== 0 || in_array($key, $allowZero, true)) {
                $filtered[$key] = $value;
            }
        }
        foreach ($stringFields as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = $input[$key] === null ? '' : (string)$input[$key];
            if ($value !== '' || in_array($key, $allowEmpty, true)) {
                $filtered[$key] = $value;
            }
        }
        if (array_key_exists('hobbies', $input)) {
            $hobbies = $input['hobbies'];
            if (is_array($hobbies)) {
                $filtered['hobbies'] = $hobbies;
            } elseif (is_string($hobbies) && $hobbies !== '') {
                $decoded = json_decode($hobbies, true);
                $filtered['hobbies'] = is_array($decoded) ? $decoded : [];
            } else {
                $filtered['hobbies'] = [];
            }
        }

        if (!empty($filtered)) {
            $this->repository->save($uid, $filtered);
        }

        return app('json')->success('保存成功');
    }
}
