<?php

namespace crmeb\services\security;

use think\facade\Cache;
use think\facade\Db;

class WechatMediaPolicy
{
    public const ACTION_PASS = 'pass';
    public const ACTION_MANUAL = 'manual';
    public const ACTION_REJECT = 'reject';

    protected const CACHE_KEY = 'ugc:wechat_media_policy:v1';

    public static function labels(): array
    {
        return [100, 20001, 20002, 20006, 21000];
    }

    public static function suggests(): array
    {
        return ['pass', 'review', 'risky'];
    }

    public static function actionFor(int $label, string $suggest): string
    {
        $suggest = strtolower(trim($suggest));
        if (!in_array($suggest, self::suggests(), true)) {
            return self::ACTION_MANUAL;
        }
        $map = self::loadMap();
        $key = $label . ':' . $suggest;
        $action = (string)($map[$key] ?? '');
        if ($action === '') {
            return self::defaultActionFor($label, $suggest);
        }
        return $action;
    }

    protected static function defaultActionFor(int $label, string $suggest): string
    {
        if ($suggest === 'review') {
            return self::ACTION_MANUAL;
        }
        if ($suggest === 'risky') {
            return self::ACTION_REJECT;
        }
        if ($label === 100) {
            return self::ACTION_PASS;
        }
        return self::ACTION_MANUAL;
    }

    public static function aggregateActions(array $actions): string
    {
        if (in_array(self::ACTION_REJECT, $actions, true)) {
            return self::ACTION_REJECT;
        }
        if (in_array(self::ACTION_MANUAL, $actions, true)) {
            return self::ACTION_MANUAL;
        }
        return self::ACTION_PASS;
    }

    public static function taskStatusForAction(string $action): int
    {
        switch ($action) {
            case self::ACTION_REJECT:
                return ContentSecurityService::TASK_REJECT;
            case self::ACTION_MANUAL:
                return ContentSecurityService::TASK_REVIEW;
            default:
                return ContentSecurityService::TASK_PASS;
        }
    }

    public static function flushCache(): void
    {
        Cache::delete(self::CACHE_KEY);
    }

    public static function allRows(): array
    {
        try {
            return Db::name('ugc_wechat_media_policy')->order('label asc,suggest asc')->select()->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function saveGrid(array $cells): void
    {
        $now = time();
        $allowed = [self::ACTION_PASS, self::ACTION_MANUAL, self::ACTION_REJECT];
        foreach ($cells as $cell) {
            $label = (int)($cell['label'] ?? 0);
            $suggest = strtolower(trim((string)($cell['suggest'] ?? '')));
            $action = (string)($cell['action'] ?? '');
            if (!in_array($label, self::labels(), true) || !in_array($suggest, self::suggests(), true)) {
                continue;
            }
            if (!in_array($action, $allowed, true)) {
                continue;
            }
            Db::name('ugc_wechat_media_policy')->where('label', $label)->where('suggest', $suggest)->update([
                'action' => $action,
                'update_time' => $now,
            ]);
        }
        self::flushCache();
    }

    protected static function loadMap(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }
        $map = [];
        try {
            foreach (Db::name('ugc_wechat_media_policy')->select()->toArray() as $row) {
                $map[(int)$row['label'] . ':' . (string)$row['suggest']] = (string)$row['action'];
            }
        } catch (\Throwable $e) {
        }
        Cache::set(self::CACHE_KEY, $map, 3600);
        return $map;
    }
}