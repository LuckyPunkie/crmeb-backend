<?php
// +----------------------------------------------------------------------
// | 扫码下单 - URL 签名
// +----------------------------------------------------------------------

namespace app\common\repositories\store\scanOrder;

class ScanOrderSign
{
    public static function secret(): string
    {
        $key = (string)systemConfig('scan_order_sign_key');
        if ($key === '') {
            $key = (string)(config('app.app_key') ?: config('app.key') ?: 'crmeb_scan_order');
        }
        return $key;
    }

    public static function make(int $merId, int $tableId): string
    {
        return substr(md5($merId . '_' . $tableId . '_' . self::secret()), 8, 16);
    }

    public static function check(int $merId, int $tableId, string $sign): bool
    {
        if ($sign === '') {
            return false;
        }
        return hash_equals(self::make($merId, $tableId), $sign);
    }

    /**
     * 扫码入口 HTTPS 链接（微信普通链接规则前缀建议配 /scanjump/）
     */
    public static function jumpUrl(int $merId, int $tableId): string
    {
        $siteUrl = rtrim((string)systemConfig('site_url'), '/');
        $sign = self::make($merId, $tableId);
        return $siteUrl . '/scanjump/' . $merId . '/' . $tableId . '?sign=' . $sign;
    }
}
