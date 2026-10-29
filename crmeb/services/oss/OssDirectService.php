<?php
// +----------------------------------------------------------------------
// | 全站图片/视频直传 OSS
// | 签名：后端用 RAM 用户 AssumeRole 拿 15 分钟 STS 凭证（会话策略收窄到本人目录），
// |       在服务端签好 PostObject V4 policy + callback，客户端只做一次表单 POST
// | 回调：OSS 用 RSA 签名回调 /api/oss/callback，验签后落库
// | 分发：图片走图片 CDN（Referer 防盗链）；视频走视频 CDN，接口输出时现签 A 类鉴权
// | 配置与控制台清单：仓库根 .claude/docs/阿里云OSS-CDN控制台配置清单.md
// +----------------------------------------------------------------------

namespace crmeb\services\oss;

use think\facade\Cache;
use think\facade\Log;

class OssDirectService
{
    const CLIENT_API = 'api';
    const CLIENT_SYS = 'sys';
    const CLIENT_MER = 'mer';
    const CLIENT_SER = 'ser';

    const KIND_IMAGE = 'image';
    const KIND_VIDEO = 'video';

    /** 客户端可直传的目录，RAM 角色权限也只给这两个前缀 */
    const PREFIX_IMAGE = 'image/';
    const PREFIX_VIDEO_SOURCE = 'video/source/';
    const PREFIX_VIDEO_720P = 'video/720p/';

    const IMAGE_MIME = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
    ];

    const VIDEO_MIME = [
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
        'm4v' => 'video/x-m4v',
    ];

    /** STS 凭证有效期（AssumeRole 最短 900 秒） */
    const STS_DURATION = 900;
    /** 回调令牌有效期：大视频上传慢，回调可能在 policy 过期后很久才到 */
    const TOKEN_TTL = 21600;
    /** 视频鉴权时间戳按 10 分钟取整，同一时段返回同一地址，便于客户端缓存 */
    const AUTH_TS_STEP = 600;

    public static function config(): array
    {
        $keys = [
            'oss_direct_open', 'oss_direct_region', 'oss_direct_bucket', 'oss_direct_endpoint', 'oss_direct_internal_endpoint',
            'oss_direct_access_key_id', 'oss_direct_access_key_secret', 'oss_direct_role_arn',
            'oss_direct_img_cdn', 'oss_direct_video_cdn', 'oss_direct_video_auth_key', 'oss_direct_video_auth_ttl',
            'oss_direct_imm_project', 'oss_direct_mns_topic', 'oss_direct_image_max_mb', 'oss_direct_video_max_mb',
        ];
        $raw = (array)systemConfig($keys);
        $cfg = [];
        foreach ($keys as $k) {
            $cfg[substr($k, 11)] = trim((string)($raw[$k] ?? ''));
        }
        $cfg['img_cdn'] = rtrim($cfg['img_cdn'], '/');
        $cfg['video_cdn'] = rtrim($cfg['video_cdn'], '/');
        return $cfg;
    }

    /** 总开关打开且必填项齐全才算启用；否则各端走原上传接口 */
    public static function enabled(?array $cfg = null): bool
    {
        $cfg = $cfg ?? self::config();
        if ((string)$cfg['open'] !== '1') {
            return false;
        }
        foreach (['region', 'bucket', 'endpoint', 'access_key_id', 'access_key_secret', 'role_arn', 'img_cdn', 'video_cdn'] as $k) {
            if ($cfg[$k] === '') {
                return false;
            }
        }
        return true;
    }

    /** 视频 CDN 鉴权是否可用（中间件据此决定要不要改写输出） */
    public static function videoAuthEnabled(?array $cfg = null): bool
    {
        $cfg = $cfg ?? self::config();
        return $cfg['video_cdn'] !== '' && $cfg['video_auth_key'] !== '';
    }

    // ------------------------------------------------------------------
    // 签名
    // ------------------------------------------------------------------

    /**
     * 为一次上传签发表单字段
     * @param string $client api/sys/mer/ser
     * @param int $ownerId 用户 uid / 管理员 id / 客服 id
     * @param array{kind:string,ext:string,size?:int,pid?:int|string,name?:string} $file
     * @return array{host:string,key:string,url:string,form:array,expire:int}
     */
    public static function sign(string $client, int $ownerId, int $merId, array $file): array
    {
        $cfg = self::config();
        if (!self::enabled($cfg)) {
            throw new \RuntimeException('直传未开启');
        }
        $kind = (string)($file['kind'] ?? '');
        $ext = strtolower(ltrim((string)($file['ext'] ?? ''), '.'));
        $mimeMap = $kind === self::KIND_VIDEO ? self::VIDEO_MIME : ($kind === self::KIND_IMAGE ? self::IMAGE_MIME : []);
        if (!isset($mimeMap[$ext])) {
            throw new \RuntimeException('不支持的文件类型');
        }
        $maxMb = (int)($kind === self::KIND_VIDEO ? $cfg['video_max_mb'] : $cfg['image_max_mb']) ?: ($kind === self::KIND_VIDEO ? 300 : 20);
        $maxBytes = $maxMb * 1024 * 1024;
        if (!empty($file['size']) && (int)$file['size'] > $maxBytes) {
            throw new \RuntimeException(($kind === self::KIND_VIDEO ? '视频' : '图片') . '不能超过' . $maxMb . 'MB');
        }

        $ownerDir = self::ownerDir($client, $ownerId, $merId);
        $prefix = ($kind === self::KIND_VIDEO ? self::PREFIX_VIDEO_SOURCE : self::PREFIX_IMAGE) . $ownerDir . '/';
        $key = $prefix . date('Ymd') . '/' . date('His') . bin2hex(random_bytes(8)) . '.' . $ext;

        $sts = self::stsCredentials($cfg, $prefix);
        $now = time();
        $expire = min($now + self::STS_DURATION, $sts['expire'] - 60);
        $xOssDate = gmdate('Ymd\THis\Z', $now);
        $credential = $sts['ak'] . '/' . gmdate('Ymd', $now) . '/' . $cfg['region'] . '/oss/aliyun_v4_request';

        $token = self::makeToken([
            'c' => $client,
            'o' => $ownerId,
            'm' => $merId,
            'k' => $kind,
            'key' => $key,
            // 素材库分类：-1 不进素材库；0 默认分类（仅后台 / 商家后台生效）
            'p' => isset($file['pid']) && $file['pid'] !== '' ? (int)$file['pid'] : -1,
            'n' => mb_substr((string)($file['name'] ?? ''), 0, 100),
            'e' => $now + self::TOKEN_TTL,
        ], $cfg);
        $callbackBody = 'key=${object}&size=${size}&mime=${mimeType}'
            . ($kind === self::KIND_IMAGE ? '&w=${imageInfo.width}&h=${imageInfo.height}' : '')
            . '&t=' . $token;
        $callback = base64_encode(json_encode([
            'callbackUrl' => rtrim((string)systemConfig('site_url'), '/') . '/api/oss/callback',
            'callbackBody' => $callbackBody,
            'callbackBodyType' => 'application/x-www-form-urlencoded',
        ], JSON_UNESCAPED_SLASHES));

        $policy = base64_encode(json_encode([
            'expiration' => gmdate('Y-m-d\TH:i:s.000\Z', $expire),
            'conditions' => [
                ['bucket' => $cfg['bucket']],
                ['eq', '$key', $key],
                ['content-length-range', 1, $maxBytes],
                ['eq', '$success_action_status', '200'],
                ['eq', '$content-type', $mimeMap[$ext]],
                ['callback' => $callback],
                ['x-oss-signature-version' => 'OSS4-HMAC-SHA256'],
                ['x-oss-credential' => $credential],
                ['x-oss-security-token' => $sts['token']],
                ['x-oss-date' => $xOssDate],
            ],
        ], JSON_UNESCAPED_SLASHES));

        return [
            'host' => 'https://' . $cfg['bucket'] . '.' . $cfg['endpoint'],
            'key' => $key,
            'url' => self::publicUrl($key, $cfg),
            'expire' => $expire,
            'form' => [
                'key' => $key,
                'policy' => $policy,
                'x-oss-signature-version' => 'OSS4-HMAC-SHA256',
                'x-oss-credential' => $credential,
                'x-oss-date' => $xOssDate,
                'x-oss-signature' => self::signV4($policy, $sts['sk'], gmdate('Ymd', $now), $cfg['region']),
                'x-oss-security-token' => $sts['token'],
                'success_action_status' => '200',
                'Content-Type' => $mimeMap[$ext],
                'callback' => $callback,
            ],
        ];
    }

    protected static function ownerDir(string $client, int $ownerId, int $merId): string
    {
        switch ($client) {
            case self::CLIENT_API:
                return 'u' . $ownerId;
            case self::CLIENT_MER:
                return 'm' . $merId;
            case self::CLIENT_SYS:
                return 'a' . $ownerId;
            case self::CLIENT_SER:
                return 's' . $ownerId;
        }
        throw new \RuntimeException('未知上传端');
    }

    /** PostObject V4：signing key = HMAC 链(aliyun_v4+SK → 日期 → 地域 → oss → aliyun_v4_request)，签 base64 policy */
    public static function signV4(string $base64Policy, string $sk, string $date, string $region): string
    {
        $key = hash_hmac('sha256', $date, 'aliyun_v4' . $sk, true);
        $key = hash_hmac('sha256', $region, $key, true);
        $key = hash_hmac('sha256', 'oss', $key, true);
        $key = hash_hmac('sha256', 'aliyun_v4_request', $key, true);
        return hash_hmac('sha256', $base64Policy, $key);
    }

    /**
     * 按目录缓存 STS 凭证：同一用户连续传多张图只 AssumeRole 一次
     * @return array{ak:string,sk:string,token:string,expire:int}
     */
    protected static function stsCredentials(array $cfg, string $prefix): array
    {
        $cacheKey = 'oss_direct_sts:' . md5($cfg['role_arn'] . '|' . $cfg['bucket'] . '|' . $prefix);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['expire'] ?? 0) - time() > 300) {
            return $cached;
        }
        $policy = [
            'Version' => '1',
            'Statement' => [[
                'Effect' => 'Allow',
                'Action' => ['oss:PutObject'],
                'Resource' => ['acs:oss:*:*:' . $cfg['bucket'] . '/' . $prefix . '*'],
            ]],
        ];
        $res = AliyunRpcClient::call('sts.' . $cfg['region'] . '.aliyuncs.com', '2015-04-01', 'AssumeRole', [
            'RoleArn' => $cfg['role_arn'],
            'RoleSessionName' => 'upload-' . substr(md5($prefix), 0, 16),
            'DurationSeconds' => self::STS_DURATION,
            'Policy' => $policy,
        ], $cfg['access_key_id'], $cfg['access_key_secret']);
        $c = $res['Credentials'] ?? [];
        if (empty($c['AccessKeyId']) || empty($c['SecurityToken'])) {
            throw new \RuntimeException('AssumeRole 未返回凭证');
        }
        $cred = [
            'ak' => (string)$c['AccessKeyId'],
            'sk' => (string)$c['AccessKeySecret'],
            'token' => (string)$c['SecurityToken'],
            'expire' => strtotime((string)$c['Expiration']) ?: time() + self::STS_DURATION,
        ];
        Cache::set($cacheKey, $cred, max(60, $cred['expire'] - time() - 300));
        return $cred;
    }

    // ------------------------------------------------------------------
    // 回调令牌：签名时把上传人、目录等写死在 callbackBody 里，防止客户端篡改
    // ------------------------------------------------------------------

    protected static function tokenSecret(array $cfg): string
    {
        return hash('sha256', 'oss_direct_callback|' . $cfg['access_key_secret']);
    }

    protected static function makeToken(array $payload, array $cfg): string
    {
        $body = rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
        return $body . '.' . hash_hmac('sha256', $body, self::tokenSecret($cfg));
    }

    public static function parseToken(string $token): ?array
    {
        $cfg = self::config();
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', $parts[0], self::tokenSecret($cfg)), $parts[1])) {
            return null;
        }
        $payload = json_decode((string)base64_decode(strtr($parts[0], '-_', '+/')), true);
        if (!is_array($payload) || (int)($payload['e'] ?? 0) < time()) {
            return null;
        }
        return $payload;
    }

    // ------------------------------------------------------------------
    // OSS 回调验签
    // ------------------------------------------------------------------

    /**
     * @param string $path 请求路径（不含 query），如 /api/oss/callback
     * @param string $query 原始 query string，没有传空
     */
    public static function verifyOssCallback(string $authorization, string $pubKeyUrlB64, string $path, string $query, string $body): bool
    {
        $pubKeyUrl = (string)base64_decode($pubKeyUrlB64);
        if (!preg_match('#^https?://gosspublic\.alicdn\.com/#', $pubKeyUrl)) {
            return false;
        }
        $signature = (string)base64_decode($authorization);
        if ($signature === '') {
            return false;
        }
        $pubKey = self::ossPublicKey($pubKeyUrl);
        if ($pubKey === '') {
            return false;
        }
        $authStr = rawurldecode($path) . ($query !== '' ? '?' . $query : '') . "\n" . $body;
        return openssl_verify($authStr, $signature, $pubKey, OPENSSL_ALGO_MD5) === 1;
    }

    protected static function ossPublicKey(string $url): string
    {
        $cacheKey = 'oss_callback_pubkey:' . md5($url);
        $key = (string)Cache::get($cacheKey, '');
        if ($key !== '') {
            return $key;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
        $key = (string)curl_exec($ch);
        curl_close($ch);
        if (strpos($key, 'PUBLIC KEY') === false) {
            Log::error('OSS 回调公钥下载失败: ' . $url);
            return '';
        }
        Cache::set($cacheKey, $key, 86400);
        return $key;
    }

    // ------------------------------------------------------------------
    // 地址
    // ------------------------------------------------------------------

    /** 未签名的访问地址：视频走视频 CDN（输出时由中间件签名），其余走图片 CDN */
    public static function publicUrl(string $key, ?array $cfg = null): string
    {
        $cfg = $cfg ?? self::config();
        $base = strpos($key, 'video/') === 0 && !self::isSnapshot($key) ? $cfg['video_cdn'] : $cfg['img_cdn'];
        return $base . '/' . ltrim($key, '/');
    }

    protected static function isSnapshot(string $url): bool
    {
        return strpos($url, 'x-oss-process=video/snapshot') !== false;
    }

    /** 视频截帧（OSS 原生 video/snapshot），走图片 CDN；封面、审核抽帧都用它 */
    public static function snapshotUrl(string $videoKey, int $ms = 1000, int $width = 720, ?array $cfg = null): string
    {
        $cfg = $cfg ?? self::config();
        return $cfg['img_cdn'] . '/' . ltrim($videoKey, '/')
            . '?x-oss-process=video/snapshot,t_' . max(0, $ms) . ',f_jpg,w_' . $width . ',m_fast';
    }

    /** 转码产物路径：video/source/u1/20261028/x.mov → video/720p/u1/20261028/x.mp4 */
    public static function transcodedKey(string $sourceKey): string
    {
        $rest = preg_replace('#^' . preg_quote(self::PREFIX_VIDEO_SOURCE, '#') . '#', '', $sourceKey);
        return self::PREFIX_VIDEO_720P . preg_replace('#\.[^./]+$#', '', $rest) . '.mp4';
    }

    /** 本站 CDN 地址 → OSS 对象路径；不是本站 CDN 的返回空 */
    public static function keyFromUrl(string $url, ?array $cfg = null): string
    {
        $cfg = $cfg ?? self::config();
        foreach ([$cfg['img_cdn'], $cfg['video_cdn']] as $base) {
            if ($base !== '' && stripos($url, $base . '/') === 0) {
                $path = (string)parse_url($url, PHP_URL_PATH);
                return ltrim($path, '/');
            }
        }
        return '';
    }

    /** CDN A 类鉴权：auth_key=时间戳-rand-uid-md5("/路径-时间戳-rand-uid-KEY") */
    public static function signVideoPath(string $key, ?array $cfg = null): string
    {
        $cfg = $cfg ?? self::config();
        $path = '/' . ltrim($key, '/');
        $ts = intdiv(time(), self::AUTH_TS_STEP) * self::AUTH_TS_STEP;
        $hash = md5($path . '-' . $ts . '-0-0-' . $cfg['video_auth_key']);
        return $cfg['video_cdn'] . $path . '?auth_key=' . $ts . '-0-0-' . $hash;
    }
}
