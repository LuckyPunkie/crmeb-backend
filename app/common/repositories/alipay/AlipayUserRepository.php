<?php

namespace app\common\repositories\alipay;

use app\common\dao\alipay\AlipayUserDao;
use app\common\model\alipay\AlipayUser;
use app\common\model\user\User;
use app\common\repositories\BaseRepository;
use app\common\repositories\user\UserRepository;
use think\facade\Db;
use think\facade\Log;

/**
 * 支付宝用户身份：付款时记下 open_id / union_id（不绑账号，防 A 登录用 B 的支付宝付款认错人）；
 * 支付宝小程序授权登录时才绑 uid
 * @mixin AlipayUserDao
 */
class AlipayUserRepository extends BaseRepository
{
    public function __construct(AlipayUserDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 按 app_id + open_id 记一条身份，已有则补 union_id；返回 alipay_user_id，失败返回 0（不影响支付流程）
     * @param string $appId
     * @param string $userType routine=支付宝小程序 app=App
     * @param string $openId
     * @param string $unionId 回调里没有，传空不会覆盖已存的
     * @return int
     */
    public function sync(string $appId, string $userType, string $openId, string $unionId = ''): int
    {
        if ($appId === '' || $openId === '') {
            return 0;
        }
        try {
            $user = $this->dao->getByOpenId($appId, $openId);
            if (!$user) {
                try {
                    $user = $this->dao->create([
                        'app_id' => $appId,
                        'user_type' => $userType,
                        'open_id' => $openId,
                        'union_id' => $unionId,
                    ]);
                    return (int)$user['alipay_user_id'];
                } catch (\Throwable $e) {
                    // 并发下另一请求刚插入（唯一键 app_id + open_id），回读即可
                    $user = $this->dao->getByOpenId($appId, $openId);
                    if (!$user) {
                        throw $e;
                    }
                }
            }
            if ($unionId !== '' && $user['union_id'] !== $unionId) {
                $user->save(['union_id' => $unionId]);
            }
            return (int)$user['alipay_user_id'];
        } catch (\Throwable $e) {
            Log::error('AlipayUser sync failed: ' . $appId . ' ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 身份已绑定的用户；本条没绑但同 union_id 的其他支付宝应用绑过，就沿用那个账号并补绑
     * @param int $alipayUserId
     * @return User|null
     */
    public function boundUser(int $alipayUserId): ?User
    {
        $identity = $this->dao->get($alipayUserId);
        if (!$identity) {
            return null;
        }
        $userRepository = app()->make(UserRepository::class);
        $uid = (int)$identity['uid'];
        if ($uid && ($user = $userRepository->get($uid))) {
            return $user;
        }
        if ($identity['union_id'] !== '') {
            $uid = (int)AlipayUser::getDB()->where('union_id', $identity['union_id'])
                ->where('alipay_user_id', '<>', $alipayUserId)->where('uid', '>', 0)->value('uid');
            if ($uid && ($user = $userRepository->get($uid))) {
                $identity->save(['uid' => $uid]);
                return $user;
            }
        }
        return null;
    }

    /**
     * 给未绑定的支付宝身份建新账号并绑上；并发重复请求时返回先建好的那个
     * @param int $alipayUserId
     * @return User
     */
    public function createUser(int $alipayUserId): User
    {
        return Db::transaction(function () use ($alipayUserId) {
            $userRepository = app()->make(UserRepository::class);
            $identity = AlipayUser::getDB()->where('alipay_user_id', $alipayUserId)->lock(true)->find();
            if ($identity['uid'] && ($user = $userRepository->get((int)$identity['uid']))) {
                return $user;
            }
            $user = $userRepository->create('alipay', [
                'account' => 'zfb' . $alipayUserId . time(),
                'pwd' => $userRepository->encodePassword($userRepository->defaultPwd()),
                'nickname' => $identity['nickname'] ?: '支付宝用户' . random_int(1000, 9999),
                'avatar' => $identity['avatar'],
                'spread_uid' => 0,
                'is_promoter' => 0,
                'last_time' => date('Y-m-d H:i:s'),
            ]);
            AlipayUser::getDB()->where('alipay_user_id', $alipayUserId)->update(['uid' => $user['uid']]);
            return $user;
        });
    }

    /**
     * 支付宝身份改绑到指定账号（手机号认回老账号时用）
     * @param int $alipayUserId
     * @param int $uid
     * @return void
     */
    public function bindUid(int $alipayUserId, int $uid): void
    {
        AlipayUser::getDB()->where('alipay_user_id', $alipayUserId)->update(['uid' => $uid]);
    }

    /**
     * 注销账号时解绑，之后同一支付宝再登录会建新账号
     * @param int $uid
     * @return void
     */
    public function unbindUid(int $uid): void
    {
        AlipayUser::getDB()->where('uid', $uid)->update(['uid' => 0]);
    }
}
