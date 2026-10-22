<?php

namespace crmeb\services\taoke;

/**
 * 联盟转链跟单标识（登录用户 uid）与各平台订单行匹配
 */
class TaokeUserTrackService
{
    public const PDD_UID_KEY = 'uid';
    public const PDD_SID_KEY = 'sid';

    public static function trackTokenForUser(int $uid): string
    {
        return $uid > 0 ? (string) $uid : '';
    }

    /**
     * 拼多多 custom_parameters（JSON 字符串）
     *
     * 拼多多要求 uid 是已授权备案的值，否则转链报 60001（普通买家不可能逐个备案）。
     * 所以 uid 固定用平台已备案的默认值，用户标识放不需要备案的 sid。
     */
    public static function pddCustomForUser(int $uid): string
    {
        $token = self::trackTokenForUser($uid);
        if ($token === '') {
            return '';
        }
        $registered = trim((string) config('taoke.pdd.default_custom_parameters')) ?: 'naimeng01';
        $decoded = json_decode($registered, true);
        $payload = is_array($decoded) ? $decoded : [self::PDD_UID_KEY => $registered];
        $payload[self::PDD_SID_KEY] = $token;

        return json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function applyKuaishouTrack(int $uid, array $context): array
    {
        if ($uid <= 0) {
            return $context;
        }
        $token = self::trackTokenForUser($uid);
        if ($token === '') {
            return $context;
        }
        if (trim((string) ($context['comments'] ?? '')) === '') {
            $context['comments'] = $token;
        }
        if (trim((string) ($context['customParameters'] ?? '')) === '') {
            $context['customParameters'] = self::pddCustomForUser($uid);
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function orderBelongsToUser(string $platform, array $row, int $uid): bool
    {
        if ($uid <= 0) {
            return false;
        }
        $token = self::trackTokenForUser($uid);
        if ($token === '') {
            return false;
        }

        switch ($platform) {
            case 'pdd':
                return self::matchPddCustom($row, $uid, $token);
            case 'jd':
                return self::matchScalarFields($row, ['subUnionId', 'sub_union_id', 'unionTag', 'positionId'], $token);
            case 'vip':
                return self::matchScalarFields($row, ['statParam', 'stat_param'], $token);
            case 'kuaishou':
                return self::matchScalarFields($row, ['comments', 'remark', 'channelCode'], $token);
            case 'taoke':
            case 'jutuike':
                return self::matchScalarFields($row, ['sid', 'SID', 'channel_code', 'channelCode'], $token);
            case 'taobao':
                return self::matchTaobao($row, $token);
            default:
                return false;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    protected static function matchPddCustom(array $row, int $uid, string $token): bool
    {
        $raw = $row['custom_parameters'] ?? $row['customParameters'] ?? '';
        if ($raw === '' || $raw === null) {
            return false;
        }
        // 新格式 {"uid":"naimeng01","sid":"<用户>"}；旧订单是 {"uid":"<用户>"}
        $keys = [self::PDD_SID_KEY, self::PDD_UID_KEY, 'user_id'];
        if (is_array($raw)) {
            foreach ($keys as $key) {
                if (isset($raw[$key]) && (string) $raw[$key] === $token) {
                    return true;
                }
            }

            return false;
        }
        $str = trim((string) $raw);
        if ($str === '') {
            return false;
        }
        $decoded = json_decode($str, true);
        if (is_array($decoded)) {
            foreach ($keys as $key) {
                if (isset($decoded[$key]) && (string) $decoded[$key] === $token) {
                    return true;
                }
            }

            return false;
        }
        if ($str === $token) {
            return true;
        }

        // JSON 被截断/转义时的兜底：必须带引号边界，避免 uid=1 误配 naimeng01、sid=12 误配 123
        return preg_match('/"(?:sid|uid)"\s*:\s*"?' . preg_quote($token, '/') . '(?:"|[,}]|$)/', $str) === 1;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     */
    protected static function matchScalarFields(array $row, array $keys, string $token): bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            $val = trim((string) $row[$key]);
            if ($val !== '' && $val === $token) {
                return true;
            }
        }
        if (isset($row['skuList']) && is_array($row['skuList'])) {
            foreach ($row['skuList'] as $sku) {
                if (is_array($sku) && self::matchScalarFields($sku, $keys, $token)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $row
     */
    protected static function matchTaobao(array $row, string $token): bool
    {
        if (self::matchScalarFields($row, [
            'relation_id',
            'special_id',
            'unid',
            'outer_code',
            'taobao_user_id',
        ], $token)) {
            return true;
        }

        $encoded = json_encode($row, JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            return false;
        }

        return str_contains($encoded, '"uid":"' . $token . '"')
            || str_contains($encoded, '"uid":' . $token);
    }

    /**
     * @param list<mixed> $list
     * @return list<array<string, mixed>>
     */
    public static function filterOrderRows(string $platform, array $list, int $uid): array
    {
        $out = [];
        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (self::orderBelongsToUser($platform, $row, $uid)) {
                $out[] = $row;
            }
        }

        return $out;
    }
}
