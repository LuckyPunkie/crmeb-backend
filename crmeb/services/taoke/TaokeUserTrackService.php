<?php

namespace crmeb\services\taoke;

/**
 * 联盟转链跟单标识（登录用户 uid）与各平台订单行匹配
 */
class TaokeUserTrackService
{
    public const PDD_UID_KEY = 'uid';

    public static function trackTokenForUser(int $uid): string
    {
        return $uid > 0 ? (string) $uid : '';
    }

    /** 拼多多 custom_parameters（JSON 字符串） */
    public static function pddCustomForUser(int $uid): string
    {
        $token = self::trackTokenForUser($uid);
        if ($token === '') {
            return '';
        }

        return json_encode([self::PDD_UID_KEY => $token], JSON_UNESCAPED_UNICODE);
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
        if (is_array($raw)) {
            foreach ([self::PDD_UID_KEY, 'uid', 'user_id'] as $key) {
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
            foreach ([self::PDD_UID_KEY, 'uid', 'user_id'] as $key) {
                if (isset($decoded[$key]) && (string) $decoded[$key] === $token) {
                    return true;
                }
            }
        }
        if ($str === $token) {
            return true;
        }

        return str_contains($str, '"uid":"' . $token . '"')
            || str_contains($str, '"uid":' . $token)
            || str_contains($str, (string) $uid);
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
