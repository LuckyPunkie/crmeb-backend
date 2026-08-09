<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
namespace app\common\repositories\store;

use app\common\model\system\merchant\Merchant;
use app\common\repositories\system\merchant\MerchantRepository;
use think\exception\ValidateException;
use think\facade\Cache;

/**
 * 盲盒入口分享归因
 * 普通店铺完成页/主页点进盲盒 ≈ 该店分享了盲盒页面（分享者 X）
 */
class BlindBoxShareRepository
{
    /** 0 = 不过期，直到用户点击其他商家 banner 覆盖绑定 */
    public const BIND_TTL = 0;

    public const CACHE_PREFIX = 'blindbox_share_mer:';

    /**
     * 校验商户可作为分享来源
     */
    public function assertShareMerchant(int $merId): array
    {
        if ($merId <= 0) {
            throw new ValidateException('分享店铺无效');
        }
        $merchant = app()->make(MerchantRepository::class)->get($merId);
        if (!$merchant || (int)$merchant['is_del'] === 1) {
            throw new ValidateException('分享店铺不存在');
        }
        if ((int)$merchant['status'] !== 1 || (int)$merchant['mer_state'] !== 1) {
            throw new ValidateException('分享店铺已打烊');
        }
        return is_array($merchant) ? $merchant : $merchant->toArray();
    }

    /**
     * 登录用户绑定分享店铺（长期有效，再次 bind 覆盖）
     */
    public function bind(int $uid, int $shareMerId): int
    {
        if ($uid <= 0) {
            throw new ValidateException('请先登录');
        }
        $this->assertShareMerchant($shareMerId);
        $this->setBindCache($uid, $shareMerId);
        return $shareMerId;
    }

    /**
     * 读取已绑定的分享店铺
     */
    public function getBound(int $uid): int
    {
        if ($uid <= 0) {
            return 0;
        }
        return (int)Cache::get($this->cacheKey($uid), 0);
    }

    /**
     * 解析最终归因：优先入参，其次缓存绑定
     */
    public function resolve(int $uid, int $shareMerId = 0): int
    {
        if ($shareMerId > 0) {
            try {
                $this->assertShareMerchant($shareMerId);
                if ($uid > 0) {
                    $this->setBindCache($uid, $shareMerId);
                }
                return $shareMerId;
            } catch (ValidateException $e) {
                $shareMerId = 0;
            }
        }
        return $this->getBound($uid);
    }

    /**
     * 分享商家 → 可发一级佣金的用户 uid（店主小程序账号）
     */
    public function resolveShareUid(int $shareMerId): int
    {
        if ($shareMerId <= 0) {
            return 0;
        }
        try {
            $this->assertShareMerchant($shareMerId);
        } catch (ValidateException $e) {
            return 0;
        }
        $uid = (int)\think\facade\Db::name('user')
            ->where('mer_id', $shareMerId)
            ->where('status', 1)
            ->order('uid ASC')
            ->value('uid');
        if ($uid > 0) {
            return $uid;
        }
        $phone = (string)Merchant::getDB()->where('mer_id', $shareMerId)->value('mer_phone');
        if ($phone !== '' && function_exists('isPhone') && isPhone($phone)) {
            $uid = (int)\think\facade\Db::name('user')->where('phone', $phone)->where('status', 1)->value('uid');
        }
        return $uid > 0 ? $uid : 0;
    }

    /**
     * 当前 C 端用户是否绑定普通商家（非盲盒店）
     * 优先 user.mer_id，其次店铺 mer_phone 匹配
     */
    public function resolveOrdinaryMerchantIdByUid(int $uid): int
    {
        if ($uid <= 0) {
            return 0;
        }
        $user = \think\facade\Db::name('user')
            ->where('uid', $uid)
            ->where('status', 1)
            ->field('uid,mer_id,phone')
            ->find();
        if (!$user) {
            return 0;
        }

        $merId = (int)($user['mer_id'] ?? 0);
        if ($merId > 0) {
            $merchant = Merchant::getDB()
                ->where('mer_id', $merId)
                ->where('is_del', 0)
                ->field('mer_id,is_blindbox,status')
                ->find();
            if ($merchant && (int)($merchant['is_blindbox'] ?? 0) !== 1) {
                return (int)$merchant['mer_id'];
            }
            return 0;
        }

        $phone = (string)($user['phone'] ?? '');
        if ($phone === '' || (function_exists('isPhone') && !isPhone($phone))) {
            return 0;
        }
        $merchant = Merchant::getDB()
            ->where('mer_phone', $phone)
            ->where('is_del', 0)
            ->where('is_blindbox', '<>', 1)
            ->order('mer_id ASC')
            ->field('mer_id,is_blindbox')
            ->find();
        return $merchant ? (int)$merchant['mer_id'] : 0;
    }

    /**
     * 平台盲盒店（唯一账号）
     */
    public function getPlatformBlindboxMerchant(): ?array
    {
        $merchant = Merchant::getDB()
            ->where('is_blindbox', 1)
            ->where('is_del', 0)
            ->where('status', 1)
            ->order('mer_id ASC')
            ->find();
        if (!$merchant) {
            return null;
        }
        return is_array($merchant) ? $merchant : $merchant->toArray();
    }

    /**
     * 商家免费开盒中奖率（0-100，默认 0=不中），全站共用，存于平台盲盒店字段
     */
    public function getFreeWinRate(): int
    {
        $merchant = $this->getPlatformBlindboxMerchant();
        if (!$merchant) {
            return 0;
        }
        return max(0, min(100, (int)($merchant['blindbox_mer_free_win_rate'] ?? 0)));
    }

    /**
     * 平台后台保存共用中奖率
     */
    public function setFreeWinRate(int $rate): int
    {
        $rate = max(0, min(100, $rate));
        $merchant = $this->getPlatformBlindboxMerchant();
        if (!$merchant) {
            throw new ValidateException('请先配置平台盲盒店铺');
        }
        app()->make(MerchantRepository::class)->update((int)$merchant['mer_id'], [
            'blindbox_mer_free_win_rate' => $rate,
        ]);
        return $rate;
    }

    /**
     * 店铺主页盲盒入口信息
     */
    public function entryInfo(int $merId): array
    {
        $merchant = $this->assertShareMerchant($merId);
        $hasBlindbox = Merchant::getDB()
            ->where('is_blindbox', 1)
            ->where('is_del', 0)
            ->where('status', 1)
            ->where('mer_state', 1)
            ->count() > 0;

        return [
            'show_entry' => $hasBlindbox,
            'share_mer_id' => (int)$merchant['mer_id'],
            'is_blindbox_shop' => (int)($merchant['is_blindbox'] ?? 0) === 1,
            'jump_path' => '/pages/blindbox/index',
            'bind_ttl' => self::BIND_TTL,
        ];
    }

    protected function setBindCache(int $uid, int $shareMerId): void
    {
        if (self::BIND_TTL > 0) {
            Cache::set($this->cacheKey($uid), $shareMerId, self::BIND_TTL);
        } else {
            Cache::set($this->cacheKey($uid), $shareMerId);
        }
    }

    protected function cacheKey(int $uid): string
    {
        return self::CACHE_PREFIX . $uid;
    }
}
