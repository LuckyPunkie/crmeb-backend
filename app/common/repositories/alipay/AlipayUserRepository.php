<?php

namespace app\common\repositories\alipay;

use app\common\dao\alipay\AlipayUserDao;
use app\common\repositories\BaseRepository;
use think\facade\Log;

/**
 * 支付宝用户身份：一期只在付款时记下 open_id / union_id，不绑账号（二期支付宝登录再填 uid）
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
}
