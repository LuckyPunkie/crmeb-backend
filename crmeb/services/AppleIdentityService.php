<?php
namespace crmeb\services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Log;

/**
 * Sign in with Apple：校验苹果签发的 JWT（登录 identityToken、服务器通知 payload）
 * 文档：https://developer.apple.com/documentation/sign_in_with_apple/sign_in_with_apple_rest_api/verifying_a_user
 */
class AppleIdentityService
{
    const ISSUER = 'https://appleid.apple.com';
    const JWKS_URL = 'https://appleid.apple.com/auth/keys';
    const CACHE_KEY = 'apple_auth_jwks';

    /**
     * 校验签名 + iss + aud + exp，返回 payload
     * @throws ValidateException
     */
    public static function verify(string $jwt): \stdClass
    {
        $jwt = trim($jwt);
        if (substr_count($jwt, '.') !== 2) {
            throw new ValidateException('苹果授权凭证格式有误');
        }
        JWT::$leeway = 60;
        try {
            $payload = self::decode($jwt, false);
        } catch (\UnexpectedValueException $e) {
            // kid 不在缓存公钥里（苹果轮换密钥）时强制刷新一次
            if (strpos($e->getMessage(), '"kid"') === false) {
                throw new ValidateException('苹果授权校验失败: ' . $e->getMessage());
            }
            try {
                $payload = self::decode($jwt, true);
            } catch (\Throwable $e2) {
                throw new ValidateException('苹果授权校验失败: ' . $e2->getMessage());
            }
        } catch (\Throwable $e) {
            throw new ValidateException('苹果授权校验失败: ' . $e->getMessage());
        }

        if (($payload->iss ?? '') !== self::ISSUER) {
            throw new ValidateException('苹果授权校验失败: iss 不匹配');
        }
        $clientIds = (array)Config::get('apple.client_ids', []);
        if (!in_array($payload->aud ?? '', $clientIds, true)) {
            throw new ValidateException('苹果授权校验失败: aud 不匹配');
        }
        if (empty($payload->sub)) {
            throw new ValidateException('苹果授权校验失败: 缺少 sub');
        }
        return $payload;
    }

    protected static function decode(string $jwt, bool $refresh): \stdClass
    {
        return JWT::decode($jwt, JWK::parseKeySet(self::jwks($refresh), 'RS256'));
    }

    protected static function jwks(bool $refresh = false): array
    {
        if (!$refresh) {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached) && !empty($cached['keys'])) {
                return $cached;
            }
        }
        $ch = curl_init(self::JWKS_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        $data = $raw ? json_decode($raw, true) : null;
        if (!is_array($data) || empty($data['keys'])) {
            Log::error('Apple JWKS fetch failed: ' . ($err ?: mb_substr((string)$raw, 0, 200)));
            throw new ValidateException('获取苹果公钥失败');
        }
        Cache::set(self::CACHE_KEY, $data, (int)Config::get('apple.jwks_ttl', 43200));
        return $data;
    }
}
