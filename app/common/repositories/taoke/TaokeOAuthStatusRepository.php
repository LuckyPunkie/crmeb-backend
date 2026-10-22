<?php

namespace app\common\repositories\taoke;

use crmeb\services\taoke\TaokeEnvFile;

/**
 * 淘宝 / 快手 OAuth 到期信息（供后台 Tab 配置页展示与提醒）
 */
class TaokeOAuthStatusRepository
{
    /** tab_key => 配置平台键 */
    private const TAB_PLATFORM = [
        'taobao'   => 'taobao',
        'kuaishou' => 'kuaishou',
    ];

    private const PLATFORM_LABEL = [
        'taobao'   => '淘宝',
        'kuaishou' => '快手',
    ];

    /**
     * @return array<string, array|null>
     */
    public function platformStatuses(): array
    {
        $out = [];
        foreach (self::TAB_PLATFORM as $tabKey => $platform) {
            $out[$tabKey] = $this->buildPlatformStatus($platform);
        }
        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $list
     * @return array<int, array<string, mixed>>
     */
    public function mergeIntoTabList(array $list): array
    {
        $statuses = $this->platformStatuses();
        foreach ($list as &$row) {
            $tabKey = (string) ($row['tab_key'] ?? '');
            $row['oauth'] = $statuses[$tabKey] ?? null;
        }
        unset($row);

        return $list;
    }

    /**
     * 剩余天数 ≤ $warnDays 的平台（用于进入页面弹窗）
     *
     * @return list<array<string, mixed>>
     */
    public function expiringAlerts(int $warnDays = 7): array
    {
        $alerts = [];
        foreach ($this->platformStatuses() as $status) {
            if ($status === null || empty($status['warn'])) {
                continue;
            }
            $alerts[] = $status;
        }

        return $alerts;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildPlatformStatus(string $platform): ?array
    {
        if (!isset(self::PLATFORM_LABEL[$platform])) {
            return null;
        }

        $cfg = (array) config('taoke.' . $platform, []);
        // 到期时间运行时会被 refresh 改写，以 .env 文件为准（Swoole 下 config 是启动时的旧值）
        if ($platform === 'taobao') {
            $env = TaokeEnvFile::read(['TAOBAO_REFRESH_EXPIRE_AT', 'TAOBAO_SESSION_EXPIRE_AT']);
            $refreshAt = (int) ($env['TAOBAO_REFRESH_EXPIRE_AT'] ?? ($cfg['refresh_expire_at'] ?? 0));
            $accessAt = (int) ($env['TAOBAO_SESSION_EXPIRE_AT'] ?? ($cfg['session_expire_at'] ?? 0));
            $callback = (string) ($cfg['oauth_callback'] ?? '');
        } else {
            $env = TaokeEnvFile::read(['KUAISHOU_REFRESH_TOKEN_EXPIRE_AT', 'KUAISHOU_ACCESS_TOKEN_EXPIRE_AT']);
            $refreshAt = (int) ($env['KUAISHOU_REFRESH_TOKEN_EXPIRE_AT'] ?? ($cfg['refresh_token_expire_at'] ?? 0));
            $accessAt = (int) ($env['KUAISHOU_ACCESS_TOKEN_EXPIRE_AT'] ?? ($cfg['access_token_expire_at'] ?? 0));
            $callback = (string) ($cfg['oauth_callback'] ?? '');
        }

        // 展示以 refresh 为准；未写入时回退 access（便于发现 session 已过期）
        $expireAt = $refreshAt > 0 ? $refreshAt : $accessAt;
        $expireKind = $refreshAt > 0 ? 'refresh' : ($accessAt > 0 ? 'access' : '');

        $daysLeft = $this->daysLeft($expireAt);
        $warn = $daysLeft !== null && $daysLeft <= 7;

        return [
            'platform'           => $platform,
            'platform_label'     => self::PLATFORM_LABEL[$platform],
            'expire_at'          => $expireAt > 0 ? $expireAt : null,
            'expire_at_human'    => $expireAt > 0 ? date('Y-m-d H:i:s', $expireAt) : null,
            'expire_kind'        => $expireKind,
            'days_left'          => $daysLeft,
            'access_expire_at_human' => $accessAt > 0 ? date('Y-m-d H:i:s', $accessAt) : null,
            'refresh_expire_at_human' => $refreshAt > 0 ? date('Y-m-d H:i:s', $refreshAt) : null,
            'authorize_url'      => $this->authorizeUrlFromCallback($callback, $platform),
            'warn'               => $warn,
        ];
    }

    private function daysLeft(int $expireAt): ?int
    {
        if ($expireAt <= 0) {
            return null;
        }

        return (int) floor(($expireAt - time()) / 86400);
    }

    private function authorizeUrlFromCallback(string $callback, string $platform): string
    {
        $callback = trim($callback);
        if ($callback !== '') {
            if (preg_match('#/callback/?$#i', $callback)) {
                return preg_replace('#/callback/?$#i', '/authorize', rtrim($callback, '/'));
            }

            return rtrim($callback, '/') . '/authorize';
        }

        $host = 'https://0626tbcs.ohlegend.com';

        return $platform === 'kuaishou'
            ? $host . '/api/taoke/oauth/kuaishou/authorize'
            : $host . '/api/taoke/oauth/taobao/authorize';
    }
}
