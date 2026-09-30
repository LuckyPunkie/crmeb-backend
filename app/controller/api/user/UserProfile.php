<?php

namespace app\controller\api\user;

use think\App;
use crmeb\basic\BaseController;
use crmeb\services\security\ContentSecurityService;
use app\common\repositories\user\UserProfileRepository as repository;
use app\common\repositories\user\UserProfileFieldRepository;
use app\common\repositories\user\UserWechatUnlockRepository;
use app\common\repositories\user\UserHomepageUnlockRepository;

class UserProfile extends BaseController
{
    protected $repository;

    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 付费解锁对方微信号
     * POST /api/user/wechat_unlock/:uid
     * body: pay_type=weixin|routine|alipay|balance|mock, return_url?
     */
    public function wechatUnlock($uid)
    {
        $buyerUid = $this->request->uid();
        $payType = (string)$this->request->param('pay_type', 'weixin');
        $returnUrl = (string)$this->request->param('return_url', '');
        $result = app()->make(UserWechatUnlockRepository::class)
            ->unlock((int)$uid, $buyerUid, $payType, $returnUrl);
        return app('json')->success($result);
    }

    /**
     * 检查是否已解锁对方微信号
     * GET /api/user/wechat_unlock_check/:uid
     */
    public function wechatUnlockCheck($uid)
    {
        $buyerUid = $this->request->uid();
        $unlocked = app()->make(UserWechatUnlockRepository::class)
            ->checkUnlocked((int)$uid, $buyerUid);
        return app('json')->success(['unlocked' => $unlocked]);
    }

    /**
     * 付费解锁对方主页
     * POST /api/user/homepage_unlock/:uid
     */
    public function homepageUnlock($uid)
    {
        $buyerUid = $this->request->uid();
        $payType = (string)$this->request->param('pay_type', 'weixin');
        $returnUrl = (string)$this->request->param('return_url', '');
        $result = app()->make(UserHomepageUnlockRepository::class)
            ->unlock((int)$uid, $buyerUid, $payType, $returnUrl);
        return app('json')->success($result);
    }

    /**
     * 检查是否已解锁对方主页
     * GET /api/user/homepage_unlock_check/:uid
     */
    public function homepageUnlockCheck($uid)
    {
        $buyerUid = $this->request->uid();
        $unlocked = app()->make(UserHomepageUnlockRepository::class)
            ->checkUnlocked((int)$uid, $buyerUid);
        return app('json')->success(['unlocked' => $unlocked]);
    }

    /**
     * 交友资料字段的选项元数据（供筛选/资料页拉取，运营在后台可编辑）
     * GET /api/user/profile/field_options
     */
    public function fieldOptions()
    {
        $fieldRepo = app()->make(UserProfileFieldRepository::class);
        $fields = $fieldRepo->enabledFields();
        $map = [];
        foreach ($fields as $f) {
            $key = (string)($f['field_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $opts = $f['options'] ?? [];
            if (!is_array($opts)) {
                $opts = [];
            }
            $map[$key] = [
                'field_key'   => $key,
                'title'       => (string)($f['title'] ?? ''),
                'type'        => (string)($f['type'] ?? ''),
                'bind_column' => (string)($f['bind_column'] ?? ''),
                'options'     => array_values(array_map('strval', $opts)),
            ];
        }
        return app('json')->success(['fields' => $map]);
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

        $fieldRepo = app()->make(UserProfileFieldRepository::class);
        $accountPhone = trim((string)($userInfo['phone'] ?? ''));
        $fields = $fieldRepo->attachValues($fieldRepo->enabledFields(), $profile, [
            'account_phone' => $accountPhone,
            'account_real_name' => trim((string)($userInfo['real_name'] ?? '')),
        ]);

        return app('json')->success([
            'uid'      => $uid,
            'phone'    => $accountPhone,
            'real_name' => trim((string)($userInfo['real_name'] ?? '')),
            'card_id'   => trim((string)($userInfo['card_id'] ?? '')),
            'sex'      => $userInfo['sex'] ?? 0,
            'birthday' => $userInfo['birthday'] ?? null,
            'profile'  => $profile,
            'fields'   => $fields,
        ]);
    }

    /**
     * 保存/更新当前用户社交档案
     * POST /api/user/profile/save
     */
    public function save()
    {
        $uid   = $this->request->uid();
        $identityLocked = app()->make(\app\common\repositories\user\UserCertificationRepository::class)
            ->isIdentityVerified($uid);
        $sex   = $this->request->param('sex/d', -1);
        $input = $this->request->param();

        // 同步性别到 eb_user（1=男 2=女 3=保密）
        if ($sex === 1 || $sex === 2 || $sex === 3) {
            \app\common\model\user\User::where('uid', $uid)->update(['sex' => $sex]);
        }

        // 仅处理请求里实际出现的字段，避免默认空串清空已有数据
        $intFields = [
            'height',
            'weight',
            'zodiac',
            'education',
            'education_type',
            'annual_income',
            'car_count',
            'house_count',
            'total_assets',
            'relationship_status',
            'marital_status',
            'want_kids',
            'smoking',
            'drinking',
            'tattoo',
            'only_child',
            'hope_age_min',
            'hope_age_max',
            'hope_height_min',
            'hope_education',
        ];
        // dating_purpose：VARCHAR，多选 CSV（如 "1,2"）
        $csvFields = ['dating_purpose'];
        $stringFields = [
            'birth_month',
            'job_title',
            'wechat_id',
            'hometown_province',
            'hometown_city',
            'current_province',
            'current_city',
            'registered_province',
            'registered_city',
            'school_name',
            'pets',
            'about_me',
            'hope_cities',
            'hope_text',
            'cover_info',
            'cover_about',
            'cover_hope',
            'cover_hobby',
            'hobby_photo_1',
            'hobby_photo_2',
        ];
        // 允许空串写入（用于删除封面图等）
        $allowEmpty = [
            'cover_info',
            'cover_about',
            'cover_hope',
            'cover_hobby',
            'hobby_photo_1',
            'hobby_photo_2',
            'about_me',
            'hope_text',
            'hobbies',
            'pets',
            'school_name',
            'hope_cities',
            'wechat_id',
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
        if (array_key_exists('wechat_id', $filtered)) {
            $filtered['wechat_id'] = mb_substr(trim((string)$filtered['wechat_id']), 0, 64);
        }
        if (array_key_exists('wechat_unlock_price', $input)) {
            $price = round(max(0, min(9999, (float)$input['wechat_unlock_price'])), 2);
            $filtered['wechat_unlock_price'] = $price;
        }
        if (array_key_exists('homepage_unlock_price', $input)) {
            $price = round(max(0, min(9999, (float)$input['homepage_unlock_price'])), 2);
            $filtered['homepage_unlock_price'] = $price;
        }
        // 全网粉丝：仅自然数；空串/null 表示清空不展示
        if (array_key_exists('network_fans', $input)) {
            $raw = $input['network_fans'];
            if ($raw === null || $raw === '') {
                $filtered['network_fans'] = null;
            } else {
                if (is_string($raw) && !preg_match('/^\d+$/', trim($raw))) {
                    return app('json')->fail('全网粉丝仅支持填写自然数');
                }
                $n = (int)$raw;
                if ($n < 0) {
                    return app('json')->fail('全网粉丝仅支持填写自然数');
                }
                $filtered['network_fans'] = min($n, 999999999);
            }
        }

        // 动态字段：field_values = { field_key: value }
        $extraPatch = [];
        if (array_key_exists('field_values', $input) && is_array($input['field_values'])) {
            $allowedBind = array_flip(array_merge($intFields, $stringFields, $csvFields, ['wechat_unlock_price', 'homepage_unlock_price', 'network_fans']));
            $fieldRepo = app()->make(UserProfileFieldRepository::class);
            $metaMap = [];
            foreach ($fieldRepo->enabledFields() as $meta) {
                $metaMap[$meta['field_key']] = $meta;
            }
            foreach ($input['field_values'] as $key => $value) {
                $key = (string)$key;
                if ($key === '' || !isset($metaMap[$key])) {
                    continue;
                }
                if ($key === UserProfileFieldRepository::ACCOUNT_PHONE_FIELD_KEY) {
                    continue;
                }
                if ($key === UserProfileFieldRepository::REAL_NAME_FIELD_KEY) {
                    if ($identityLocked) {
                        continue;
                    }
                    $rn = mb_substr(trim((string)$value), 0, 32);
                    \app\common\model\user\User::where('uid', $uid)->update(['real_name' => $rn]);
                    continue;
                }
                if ($key === 'id_card' && $identityLocked) {
                    continue;
                }
                $meta = $metaMap[$key];
                $bind = (string)($meta['bind_column'] ?? '');
                if ($bind === 'wechat_id') {
                    $filtered['wechat_id'] = mb_substr(trim((string)$value), 0, 64);
                    continue;
                }
                if ($bind !== '' && isset($allowedBind[$bind])) {
                    // dating_purpose 等 CSV 类型字段：labels → index+1 CSV
                    if (in_array($bind, $csvFields, true)) {
                        $opts = is_array($meta['options'] ?? null) ? $meta['options'] : [];
                        $labels = is_array($value) ? $value : (array)$value;
                        $ids = [];
                        foreach ($labels as $lb) {
                            $lb = (string)$lb;
                            // 数字字符串直接用（老数据/前端兜底）
                            if (ctype_digit($lb)) {
                                $ids[] = (int)$lb;
                                continue;
                            }
                            $idx = array_search($lb, $opts, true);
                            if ($idx !== false) $ids[] = (int)$idx + 1;
                        }
                        $ids = array_values(array_unique(array_filter($ids)));
                        $filtered[$bind] = implode(',', $ids);
                        continue;
                    }
                    // 预留：其它 bind 列直接写入主表
                    $filtered[$bind] = is_array($value)
                        ? json_encode(array_values($value), JSON_UNESCAPED_UNICODE)
                        : mb_substr(trim((string)$value), 0, 255);
                    continue;
                }
                if (($meta['type'] ?? '') === 'checkbox') {
                    if (is_array($value)) {
                        $extraPatch[$key] = array_values(array_map('strval', $value));
                    } elseif (is_string($value) && $value !== '') {
                        $decoded = json_decode($value, true);
                        $extraPatch[$key] = is_array($decoded) ? array_values($decoded) : [$value];
                    } else {
                        $extraPatch[$key] = [];
                    }
                } else {
                    $extraPatch[$key] = mb_substr(trim((string)($value ?? '')), 0, 255);
                }
            }
        }

        $openid = (string)($this->request->userInfo()->wechat->routine_openid ?? '');
        // 自我介绍 / 期望 是文字；cover_* / hobby_photo_* 是图片地址，走图片审核
        foreach (['about_me', 'hope_text'] as $bioField) {
            if (!empty($filtered[$bioField])) {
                ContentSecurityService::checkText(
                    (string)$filtered[$bioField],
                    ContentSecurityService::SCENE_PROFILE,
                    'profile_' . $bioField,
                    $uid,
                    $uid,
                    $openid
                );
            }
        }

        if (!empty($filtered)) {
            $this->repository->save($uid, $filtered);
        }

        // 主页封面图先发后审，违规时清除对应图片
        $coverImages = [];
        foreach (['cover_info', 'cover_about', 'cover_hope', 'cover_hobby', 'hobby_photo_1', 'hobby_photo_2'] as $coverField) {
            if (!empty($filtered[$coverField])) {
                $coverImages[] = (string)$filtered[$coverField];
            }
        }
        ContentSecurityService::dispatchMediaCheck('profile_cover', (int)$uid, (int)$uid, $openid, $coverImages, ContentSecurityService::SCENE_PROFILE);
        if (!empty($extraPatch)) {
            $this->repository->mergeExtraFields($uid, $extraPatch);
        }

        return app('json')->success('保存成功');
    }
}
