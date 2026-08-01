<?php
namespace crmeb\services;

use app\common\repositories\user\UserPushClientRepository;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Log;

/**
 * uni-push（个推）离线推送
 * 文档：https://docs.getui.com/getui/server/rest_v2/push/
 */
class UniPushService
{
    /**
     * 向多个 uid 推送收款通知
     */
    public static function pushScanPayToUids(array $uids, array $payload): void
    {
        if (!self::enabled()) {
            Log::info('UniPush skipped: not enabled/configured');
            return;
        }
        $uids = array_values(array_unique(array_filter(array_map('intval', $uids))));
        if (!$uids) return;

        try {
            $cids = app()->make(UserPushClientRepository::class)->clientIdsByUids($uids);
            if (!$cids) {
                Log::info('UniPush skipped: no client_id for uids=' . json_encode($uids));
                return;
            }
            $title = (string)($payload['title'] ?? '收款到账');
            $body = (string)($payload['voice_text'] ?? $payload['message'] ?? '收到一笔扫码付款');
            self::pushToCids($cids, $title, $body, [
                'type' => 'scan_pay',
                'data' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::error('UniPush pushScanPayToUids failed: ' . $e->getMessage());
        }
    }

    public static function enabled(): bool
    {
        $cfg = Config::get('unipush');
        if (empty($cfg['enable'])) return false;
        return !empty($cfg['app_id']) && !empty($cfg['app_key']) && !empty($cfg['master_secret']);
    }

    /**
     * 按 cid 列表推送（单推循环，兼容性最好）
     */
    public static function pushToCids(array $cids, string $title, string $body, array $payload = []): int
    {
        $token = self::getAuthToken();
        if (!$token) return 0;

        $appId = (string)Config::get('unipush.app_id');
        $ok = 0;
        foreach ($cids as $cid) {
            $cid = trim((string)$cid);
            if ($cid === '') continue;
            try {
                $url = 'https://restapi.getui.com/v2/' . $appId . '/push/single/cid';
                $req = [
                    'request_id' => 'sp' . date('YmdHis') . mt_rand(1000, 9999) . substr(md5($cid), 0, 6),
                    'audience' => ['cid' => [$cid]],
                    'push_message' => [
                        'notification' => [
                            'title' => mb_substr($title, 0, 40),
                            'body' => mb_substr($body, 0, 100),
                            'click_type' => 'payload',
                            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                    // 厂商通道：保证杀进程也能出系统通知
                    'push_channel' => [
                        'android' => [
                            'ups' => [
                                'notification' => [
                                    'title' => mb_substr($title, 0, 40),
                                    'body' => mb_substr($body, 0, 100),
                                    'click_type' => 'payload',
                                    'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                                ],
                            ],
                        ],
                        'ios' => [
                            'type' => 'notify',
                            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                            'aps' => [
                                'alert' => [
                                    'title' => mb_substr($title, 0, 40),
                                    'body' => mb_substr($body, 0, 100),
                                ],
                                'sound' => 'default',
                                'content-available' => 0,
                            ],
                            'auto_badge' => '+1',
                        ],
                    ],
                ];
                $res = self::httpJson('POST', $url, $req, [
                    'token: ' . $token,
                ]);
                if (($res['code'] ?? 0) == 0) {
                    $ok++;
                } else {
                    Log::warning('UniPush cid fail: ' . json_encode($res, JSON_UNESCAPED_UNICODE));
                }
            } catch (\Throwable $e) {
                Log::error('UniPush cid exception: ' . $e->getMessage());
            }
        }
        Log::info('UniPush done ok=' . $ok . '/' . count($cids));
        return $ok;
    }

    protected static function getAuthToken(): string
    {
        $cacheKey = 'unipush_auth_token';
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $appId = (string)Config::get('unipush.app_id');
        $appKey = (string)Config::get('unipush.app_key');
        $master = (string)Config::get('unipush.master_secret');
        $ts = (string)(int)(microtime(true) * 1000);
        $sign = hash('sha256', $appKey . $ts . $master);

        $url = 'https://restapi.getui.com/v2/' . $appId . '/auth';
        $res = self::httpJson('POST', $url, [
            'sign' => $sign,
            'timestamp' => $ts,
            'appkey' => $appKey,
        ]);
        $token = (string)($res['data']['token'] ?? '');
        if ($token === '') {
            Log::error('UniPush auth failed: ' . json_encode($res, JSON_UNESCAPED_UNICODE));
            return '';
        }
        $ttl = (int)Config::get('unipush.token_ttl', 3600 * 20);
        Cache::set($cacheKey, $token, max(600, $ttl));
        return $token;
    }

    protected static function httpJson(string $method, string $url, array $body = [], array $extraHeaders = []): array
    {
        $ch = curl_init($url);
        $headers = array_merge(['Content-Type: application/json;charset=utf-8'], $extraHeaders);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new \RuntimeException('curl error: ' . $err);
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : ['code' => -1, 'msg' => $raw];
    }
}
