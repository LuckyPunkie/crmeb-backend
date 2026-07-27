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
 * 普通店铺主页点进盲盒 ≈ 该店分享了盲盒页面
 */
class BlindBoxShareRepository
{
    /** 归因缓存秒数（7 天，后续分销规则可再调） */
    public const BIND_TTL = 604800;

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
     * 登录用户绑定分享店铺
     */
    public function bind(int $uid, int $shareMerId): int
    {
        if ($uid <= 0) {
            throw new ValidateException('请先登录');
        }
        $this->assertShareMerchant($shareMerId);
        Cache::set($this->cacheKey($uid), $shareMerId, self::BIND_TTL);
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
                    Cache::set($this->cacheKey($uid), $shareMerId, self::BIND_TTL);
                }
                return $shareMerId;
            } catch (ValidateException $e) {
                $shareMerId = 0;
            }
        }
        return $this->getBound($uid);
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

    protected function cacheKey(int $uid): string
    {
        return self::CACHE_PREFIX . $uid;
    }
}
