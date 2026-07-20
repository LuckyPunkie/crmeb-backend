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


namespace app\common\repositories\wechat;


use think\facade\Log;
use crmeb\services\wechat\MiniProgram;
use app\common\dao\wechat\WechatUserDao;
use crmeb\services\wechat\WechatResponse;
use crmeb\services\wechat\OfficialAccount;
use app\common\repositories\article\ArticleRepository;
use app\common\repositories\BaseRepository;
use app\common\repositories\user\UserRepository;
use crmeb\jobs\SendNewsJob;
use crmeb\services\WechatUserGroupService;
use crmeb\services\WechatUserTagService;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Queue;
use think\facade\Route;

/**
 * Class WechatUserRepository
 * @package app\common\repositories\wechat
 * @author xaboy
 * @day 2020-04-28
 * @mixin WechatUserDao
 */
class WechatUserRepository extends BaseRepository
{
    /**
     * WechatUserRepository constructor.
     * @param WechatUserDao $dao
     */
    public function __construct(WechatUserDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     *  获取小程序用户，是否需要绑定手机号
     * @param $code
     * @return array
     * @author Qinii
     * @day 2023/11/9
     */
    public function mpLoginType($code, $spread)
    {
        if (!$code )
            throw new ValidateException('授权失败,请获取code参数');
        $userInfoCong = Cache::get('eb_api_code_' . $code);
        if (!$userInfoCong) {
            try {
                $userInfoCong = MiniProgram::getUserInfo($code);
                Cache::set('eb_api_code_' . $code, $userInfoCong, 86400);
            } catch (Exception $e) {
                throw new ValidateException('获取session_key失败，请检查您的配置！'.$e->getMessage());
            }
        }
        $bindPhone = systemConfig('is_phone_login') == '1';
        $key = '';
        $wechat_phone_switch = systemConfig('wechat_phone_switch');

        if ($bindPhone) {
            $routineInfo = $this->dao->routineIdByWechatUser($userInfoCong['openid']);
            if (!$routineInfo){
                $info = ['session_key' => $userInfoCong['session_key'] ?? '', 'unionid' => $userInfoCong['unionid'] ?? ''];
                $routineInfo = $this->syncRoutineUser($userInfoCong['openid'], $info, false);
                $routineInfo = $routineInfo[0];
            }
            $user = app()->make(UserRepository::class)->getWhere(['wechat_user_id' => $routineInfo['wechat_user_id']]);
            if ($user && $user['phone'])
                $bindPhone = false;
            if ($bindPhone) {
                $uni = uniqid(true, false) . random_int(1, 100000000);
                $key = 'U' . md5(time() . $uni);
                Cache::set('u_try' . $key, ['id' => $routineInfo['wechat_user_id'], 'type' => $routineInfo['user_type'], 'spread' => $spread], 3600);
            }
        }
        return compact('bindPhone','key','wechat_phone_switch');
    }

    /**
     * 同步公众号用户
     * @param string $openId
     * @param array $userInfo
     * @param bool $mode
     * @return mixed|void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-04-28
     */

    public function syncUser(string $openId, array $userInfo, bool $mode = false, $createUser = true)
    {
        if (($mode && (!isset($userInfo['subscribe']) || !$userInfo['subscribe'])) || !isset($userInfo['openid']))
            return;
        $wechatUser = null;
        $userInfo['nickname'] = filter_emoji(($userInfo['nickname'] ?? '') ?: ('微信用户U' . substr(uniqid(true, true), -6)));
        if (isset($userInfo['unionid']))
            $wechatUser = $this->dao->unionIdByWechatUser($userInfo['unionid']);
        if (!$wechatUser)
            $wechatUser = $this->dao->openIdByWechatUser($openId);

        unset($userInfo['qr_scene'], $userInfo['qr_scene_str'], $userInfo['qr_scene_str'], $userInfo['subscribe_scene']);
        if (isset($userInfo['tagid_list']) && is_array($userInfo['tagid_list'])) {
            $userInfo['tagid_list'] = implode(',', $userInfo['tagid_list']);
        }
        return Db::transaction(function () use ($createUser, $mode, $userInfo, $wechatUser) {
            if ($wechatUser) {
                if ($mode) {
                    unset($userInfo['nickname']);
                }
                $wechatUser->save($userInfo);
            } else {
                $wechatUser = $this->dao->create($userInfo);
            }
            if (!$createUser) return [$wechatUser];
            /** @var UserRepository $userRepository */
            $userRepository = app()->make(UserRepository::class);
            $user = $userRepository->syncWechatUser($wechatUser);
            return [$wechatUser, $user];
        });
    }

    public function getUserByWechat(string  $openId, array $userInfo)
    {
        $wechatUser = null;
        if (isset($userInfo['unionid']))
            $wechatUser = $this->dao->unionIdByWechatUser($userInfo['unionid']);
        if (!$wechatUser)
            $wechatUser = $this->dao->openIdByWechatUser($openId);
        $userRepository = app()->make(UserRepository::class);
        $user = $userRepository->wechatUserIdBytUser($wechatUser->wechat_user_id);
        return [$wechatUser,$user];
    }

    /**
     * 同步小程序用户
     * @param string $routineOpenid
     * @param array $routine
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-11
     */
    public function  syncRoutineUser(string $routineOpenid, array $routine, $createUser = true)
    {
        $routineInfo = [];
        $nickname = $routine['nickName'] ?? '小程序用户';
        $routineInfo['nickname'] = $nickname; //姓名
        $routineInfo['sex'] = $routine['gender'] ?? 0;//性别
        $routineInfo['language'] = $routine['language'] ?? '';//语言
        $routineInfo['city'] = $routine['city'] ?? '';//城市
        $routineInfo['province'] = $routine['province'] ?? '';//省份
        $routineInfo['country'] = $routine['country'] ?? '';//国家
        $routineInfo['headimgurl'] = $routine['avatarUrl'] ?? '';//头像
        $routineInfo['routine_openid'] = $routineOpenid;//openid
        $routineInfo['session_key'] = $routine['session_key'] ?? '';//会话密匙
        $routineInfo['unionid'] = $routine['unionid'] ?? '';//用户在开放平台的唯一标识符
        $routineInfo['user_type'] = 'routine';//用户类型
        $wechatUser = null;
        if ($routineInfo['unionid']){
            $wechatUser = $this->dao->unionIdByWechatUser($routineInfo['unionid']);
        }
        if (!$wechatUser) {
            $wechatUser = $this->dao->routineIdByWechatUser($routineOpenid);
        }
        return Db::transaction(function () use ($createUser, $routineInfo, $wechatUser) {
            if ($wechatUser) {
                $wechatUser['nickname']   ? $routineInfo['nickname'] = !$wechatUser['nickname'] : '';
                $wechatUser['headimgurl'] ? $routineInfo['headimgurl'] = $wechatUser['headimgurl'] :'';
                $wechatUser->save($routineInfo);
            } else {
                $wechatUser = $this->dao->create($routineInfo);
            }
            if (!$createUser) return [$wechatUser];
            /** @var UserRepository $userRepository */
            $userRepository = app()->make(UserRepository::class);
            $user = $userRepository->syncWechatUser($wechatUser, 'routine');
            return [$wechatUser, $user];
        });
    }

    /**
     * 同步 app 用户
     * @param string $routineOpenid
     * @param array $routine
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-11
     */
    public function syncAppUser(string $unionId, array $userInfo, $type = 'wechat', $createUser = true)
    {
        $wechatInfo = [];
        $wechatInfo['nickname'] = filter_emoji($userInfo['nickName'] ?? ($userInfo['nickname'] ?? ''));//姓名
        $wechatInfo['sex'] = $userInfo['gender'] ?? 0;//性别
        $wechatInfo['city'] = $userInfo['city'] ?? '';//城市
        $wechatInfo['province'] = $userInfo['province'] ?? '';//省份
        $wechatInfo['country'] = $userInfo['country'] ?? '';//国家
        $wechatInfo['headimgurl'] = $userInfo['avatarUrl'] ?? ($userInfo['headimgurl'] ?? '');//头像
        $openid = (string)($userInfo['openId'] ?? ($userInfo['openid'] ?? ''));
        if ($openid !== '') {
            $wechatInfo['openid'] = $openid;
        }
        $wechatInfo['unionid'] = $unionId;//用户在开放平台的唯一标识符
        $wechatInfo['user_type'] = 'app';//用户类型
        $wechatUser = $this->dao->unionIdByWechatUser($unionId);

        return Db::transaction(function () use ($createUser, $type, $wechatInfo, $wechatUser) {
            if ($wechatUser) {
                unset($wechatInfo['nickname']);
                $wechatUser->save($wechatInfo);
            } else {
                $wechatUser = $this->dao->create($wechatInfo);
            }
            if (!$createUser) {
                return [$wechatUser];
            }
            /** @var UserRepository $userRepository */
            $userRepository = app()->make(UserRepository::class);
            $user = $userRepository->syncWechatUser($wechatUser, $type);
            return [$wechatUser, $user];
        });
    }

    /**
     * 获取列表
     * @param array $where
     * @param $page
     * @param $limit
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-04-29
     */
    public function getList(array $where, $page, $limit)
    {
        $query = $this->dao->search($where);
        $count = $query->count($this->dao->getPk());
        $list = $query->setOption('field', [])->field('uid,openid,nickname,headimgurl,sex,country,province,city,subscribe')
            ->page($page, $limit)->select()->each(function ($item) {
                $item['subscribe_time'] = $item['subscribe_time'] ? date('Y-m-d H:i', $item['subscribe_time']) : '';
                return $item;
            });
        return compact('count', 'list');
    }


    /**
     * 用户标签表单
     * @param $id
     * @return Form
     * @throws DataNotFoundException
     * @throws DbException
     * @throws FormBuilderException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-04-29
     */
    public function updateUserTagForm($id)
    {
        $wechatUserTagService = new WechatUserTagService();
        $lst = $wechatUserTagService->lst();
        $user = $this->dao->get($id);
        return Elm::createForm(Route::buildUrl('wechat/user/tag', ['id' => $id]), [
            Elm::select('tag_id', '用户标签：', explode(',', $user->tagid_list))->options(function () use ($lst) {
                $options = [];
                foreach ($lst as $item) {
                    $options[] = ['value' => $item['id'], 'label' => $item['name']];
                }
                return $options;
            })->multiple(true)->placeholder('请选择用户标签')
        ])->setTitle('编辑用户标签');
    }

    /**
     * 更新用户标签
     * @param $id
     * @param array $tags
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-04-29
     */
    public function updateTag($id, array $tags)
    {
        $user = $this->dao->get($id);
        $oTags = explode(',', $user->tagid_list);
        $user->save(['tagid_list' => implode(',', $tags)]);
        $wechatUserTagService = (new WechatUserTagService())->userTag();
        foreach ($oTags as $tag) {
            $wechatUserTagService->batchUntagUsers([$user->openid], $tag);
        }
        foreach ($tags as $tag) {
            $wechatUserTagService->batchTagUsers([$user->openid], $tag);
        }
    }


    /**
     * 用户分组表单
     * @param $id
     * @return Form
     * @throws DataNotFoundException
     * @throws DbException
     * @throws FormBuilderException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-04-29
     */
    public function updateUserGroupForm($id)
    {
        $wechatUserGroupService = new WechatUserGroupService();
        $lst = $wechatUserGroupService->lst();
        $user = $this->dao->get($id);
        return Elm::createForm(Route::buildUrl('wechat/user/group', ['id' => $id]), [
            Elm::select('group_id', '用户标签：', (string)$user->groupid)->options(function () use ($lst) {
                $options = [];
                foreach ($lst as $item) {
                    $options[] = ['value' => $item['id'], 'label' => $item['name']];
                }
                return $options;
            })->placeholder('请选择用户标签')
        ])->setTitle('编辑用户分组');
    }

    /**
     * 更新用户分组
     * @param $id
     * @param $groupid
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-04-29
     */
    public function updateGroup($id, $groupid)
    {
        $user = $this->dao->get($id);
        $user->save(['groupid' => $groupid]);
        $wechatUserGroupService = (new WechatUserGroupService())->userGroup();
        $wechatUserGroupService->moveUser($user->openid, $groupid);
    }


    /**
     * 发送图文消息
     * @param $id
     * @param array $ids
     * @author xaboy
     * @day 2020-05-11
     */
    public function sendNews($id, array $ids)
    {
        if (!count($ids)) return;
        /** @var ArticleRepository $make */
        $make = app()->make(ArticleRepository::class);
        $articles = $make->wechatNewIdByData($id);
        $news = [];
        foreach ($articles as $article) {
            $news[] = [
                'title' => $article['title'],
                'image' => $article['image_input'],
                'date' => $article['create_time'],
                'description' => $article['synopsis'],
                'id' => $article['article_id']
            ];
        }
        $make = app()->make(UserRepository::class);
        foreach ($ids as $_id) {
            $user = $make->get($_id);
            if ($this->dao->isSubscribeWechatUser($user->wechat_user_id)) {
                Queue::push(SendNewsJob::class, [$user->wechat_user_id, $news]);
            }
        }
    }

    /**
     *  关注公众号
     * @param string $openId
     * @author Qinii
     * @day 2024/4/28
     */
    public function subscribe(string $openId)
    {
        try{
            $this->dao->search([])->where('openid',$openId)->update(['subscribe' => 1]);
        }catch (Exception $e) {

        }
    }


    /**
     * 使用 App OAuth 的 access_token + openid 请求 sns/userinfo，由微信校验 token 并返回用户资料（需 snsapi_userinfo）。
     * 后端配置的 app_id/secret 须与客户端发起授权的移动应用或公众号一致。
     *
     * @return array 微信 sns/userinfo 原始字段（含 openid、nickname、unionid 等）
     */
    public function getUserInfoBySnsAccessToken(string $accessToken, string $openid): array
    {
        $accessToken = trim($accessToken);
        $openid = trim($openid);
        if ($accessToken === '' || $openid === '') {
            throw new ValidateException('access_token 或 openid 不能为空');
        }
        try {
            $oauth = OfficialAccount::instance()->application()->getOAuth();
            if (method_exists($oauth, 'scopes')) {
                $oauth->scopes(['snsapi_userinfo']);
            }
            if (method_exists($oauth, 'withOpenid')) {
                $oauth = $oauth->withOpenid($openid);
            }
            $socialiteUser = $oauth->userFromToken($accessToken);
            $raw = $socialiteUser->getRaw();
        } catch (ValidateException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('sns userinfo: ' . $e->getMessage());
            throw new ValidateException('拉取微信用户信息失败');
        }
        if (isset($raw['errcode']) && (int)$raw['errcode'] !== 0) {
            throw new ValidateException('微信授权无效: ' . ($raw['errmsg'] ?? 'unknown'));
        }
        if (empty($raw['openid']) || $raw['openid'] !== $openid) {
            throw new ValidateException('openid 与微信返回不一致');
        }

        return $raw;
    }

    public function wechantAuth($code)
    {
        $wechatInfo = $this->getAuthWechatInfo($code);
        if (!isset($wechatInfo['nickname'])) {
            $wechatInfo = OfficialAccount::instance()->user()->get($wechatInfo['openid'])->toArray();
            if (!isset($wechatInfo['nickname']))
                throw new ValidateException('OfficialAccount 授权失败');
            if (isset($wechatInfo['tagid_list']))
                $wechatInfo['tagid_list'] = implode(',', $wechatInfo['tagid_list']);
        } else {
            if (isset($wechatInfo['privilege'])) unset($wechatInfo['privilege']);
            if (!$this->dao->getwhere(['openid' => $wechatInfo['openid']])) {
                $wechatInfo['subscribe'] = 0;
            }
        }
        return $wechatInfo;
    }

    /**
     * 获取授权信息
     * @param string $code
     * @return array
     */
    public function getAuthWechatInfo($code)
    {
        try {
            Log::info('$code:'.$code);
            $userInfoConfig = OfficialAccount::tokenFromCode($code);
            //Log::info('$userInfoConfig:'.var_export($userInfoConfig,true));
            /**
             * array:6 [
             * "access_token" => "1_b6hDSiM6ufLX8kecBFQjZbtMvn6Jyxuv2mLOoHB-9Smx1x-r64NC3PP22uwT3RliCpo74XfqJVObB4l4uN7311KaO91pXC26rn2Hm4Qf6sGZ-yIV"
             * "expires_in" => 7200
             * "refresh_token" => "1_vfXARkT0wwzEP7hxuDPEj0p6ux7nXlRpHgPnC38pHSGZl6urS6ryTOf2T2Dh7v7UJSxj5q5E4n3mhqzJP3Ep8ld7RSEiQ-9hll6elvS7YQRh5yMr"
             * "openid" => "oOdvCvjvCG0FnCwcMdDD_xIODRO0"
             * "scope" => "snsapi_userinfo"
             * "unionid" => "oZEAhs7WSG2B6OnZVvkOkVbkAcQo"
             * ]
             */
        } catch (\Throwable $e) {
            \think\facade\Log::error(
                json_encode([
                    'error' => '授权失败：' . $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );
            throw new ValidateException('tokenFromCode 授权失败:'.$e->getMessage());
        }
        if (!isset($userInfoConfig['openid']) || !$userInfoConfig['openid']) {
            throw new ValidateException('openid获取失败');
        }
        return $userInfoConfig;
    }

    /**
     * 通过code获取授权信息
     * @param string $code
     * @return array|\EasyWeChat\Kernel\Support\Collection|object|\Psr\Http\Message\ResponseInterface|string
     */
    public function getUserInfoByCode(string $code)
    {
        if (!$code)
            throw new ValidateException('授权失败,参数有误');

        $userInfoConfig = Cache::get('eb_api_code_' . $code);
        if (!$userInfoConfig) {
            try {
                $userInfoConfig = MiniProgram::getUserInfo($code);
                Cache::set('eb_api_code_' . $code, $userInfoConfig, 86400);
            } catch (\Throwable $e) {
                throw new ValidateException('授权失败，请检查您的配置！:' . $e->getMessage());
            }
        }
        $userInfoConfig = new WechatResponse($userInfoConfig);
        if (!isset($userInfoConfig['openid']) || !$userInfoConfig['openid']) {
            throw new ValidateException('openid获取失败');
        }
        /**
         * array:3 [
         * "session_key" => "+3CGZQrFxLsnnmo84Gq6Vw=="
         * "openid" => "oIXNN5Y7153F3jiJArzEGMIcTgwY"
         * "unionid" => "oZEAhs7WSG2B6OnZVvkOkVbkAcQo"
         * ]
         */
        return $userInfoConfig->toArray();
    }

    /**
     * 解密获取用户信息
     * @param $userInfoConfig
     * @param $iv
     * @param $encryptedData
     * @return mixed
     */
    public function encryptorUserInfo($userInfoConfig, $iv, $encryptedData)
    {
        if (!$userInfoConfig)
            throw new ValidateException('授权失败,参数有误');
        $session_key = $userInfoConfig['session_key'] ?? '';
        if (!$session_key) {
            throw new ValidateException('获取session_key失败,参数有误');
        }
        try {
            //解密获取用户信息
            $userInfo = MiniProgram::decryptData($session_key, $iv, $encryptedData);
        } catch (\Exception $e) {
            $userInfo = [];
            if ($e->getCode() == '-41003') {
                throw new ValidateException('获取会话密匙失败');
            } else {
                throw new ValidateException($e->getMessage());
            }
        }
        /**
         * array:8 [
         * "nickName" => "被子你放开我"
         * "gender" => 0
         * "language" => "zh_CN"
         * "city" => ""
         * "province" => ""
         * "country" => ""
         * "avatarUrl" => "https://thirdwx.qlogo.cn/mmopen/vi_32/DYAIOgq83eoTQU7kLMUzhPIWXf2icliceUhhGkMibJLIRhuclpCzLTDvViabFkgQnqfl8ibb93Ff23INd1yDqtd4J1A/132"
         * "watermark" => array:2 [
         * "timestamp" => 1775202435
         * "appid" => "wx5fb1cc8edb3f8baa"
         * ]
         * ]
         */
        return $userInfo;
    }

    public function getOAuth($accessToken, $openid)
    {
        try {
            $oauth = OfficialAccount::instance()->application()->getOAuth();
            if (method_exists($oauth, 'scopes')) {
                $oauth->scopes(['snsapi_userinfo']);
            }
            if (method_exists($oauth, 'withOpenid')) {
                $oauth = $oauth->withOpenid($openid);
            }
            $socialiteUser = $oauth->userFromToken($accessToken);
            $raw = $socialiteUser->getRaw();
        } catch (ValidateException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('sns userinfo: ' . $e->getMessage());
            throw new ValidateException('拉取微信用户信息失败');
        }
        if (isset($raw['errcode']) && (int)$raw['errcode'] !== 0) {
            throw new ValidateException('微信授权无效: ' . ($raw['errmsg'] ?? 'unknown'));
        }
        if (empty($raw['openid']) || $raw['openid'] !== $openid) {
            throw new ValidateException('openid 与微信返回不一致');
        }
        return $raw;
    }


}
