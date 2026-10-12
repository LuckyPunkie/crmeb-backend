<?php
namespace crmeb\services\security;
use think\facade\Cache;
use think\facade\Db;
class AliyunBaselinePolicy {
    public const ACTION_PASS = 'pass';
    public const ACTION_MANUAL = 'manual';
    public const ACTION_REJECT = 'reject';
    protected const CACHE_KEY = 'ugc:aliyun_baseline_label:v1';
    public static function verdictForResults(array $results): string {
        $worst = self::ACTION_PASS;
        foreach ($results as $row) {
            $label = (string)($row['Label'] ?? $row['label'] ?? '');
            if ($label === '') continue;
            $conf = isset($row['Confidence']) ? (float)$row['Confidence'] : null;
            $action = self::actionForLabel($label, $conf);
            if ($action === self::ACTION_REJECT) return self::ACTION_REJECT;
            if ($action === self::ACTION_MANUAL) $worst = self::ACTION_MANUAL;
        }
        return $worst;
    }
    public static function actionForLabel(string $label, ?float $confidence): string {
        $label = self::normalizeLabel($label);
        $row = self::loadMap()[$label] ?? null;
        if (!$row) return self::ACTION_MANUAL;
        if ($confidence === null) $confidence = 0.0;
        $passMax = (int)$row['pass_max'];
        $reviewMax = (int)$row['review_max'];
        if ($confidence <= $passMax) return self::ACTION_PASS;
        if ($confidence <= $reviewMax) return self::ACTION_MANUAL;
        return self::ACTION_REJECT;
    }
    protected static function normalizeLabel(string $label): string {
        if (preg_match('/^(.+)_lib$/', $label, $m) && $m[1] !== 'nonLabel') {
            return $m[1];
        }
        return $label;
    }
    public static function flushCache(): void { Cache::delete(self::CACHE_KEY); }
    public static function allRows(): array {
        try { return Db::name('ugc_aliyun_baseline_label')->order('sort asc,id asc')->select()->toArray(); }
        catch (\Throwable $e) { return []; }
    }
    public static function saveRows(array $rows): void {
        $now = time();
        foreach ($rows as $row) {
            $label = trim((string)($row['label'] ?? ''));
            if ($label === '') continue;
            Db::name('ugc_aliyun_baseline_label')->where('label', $label)->update([
                'pass_max' => max(0, min(100, (int)($row['pass_max'] ?? 20))),
                'review_max' => max(0, min(100, (int)($row['review_max'] ?? 40))),
                'title' => (string)($row['title'] ?? ''),
                'update_time' => $now,
            ]);
        }
        self::flushCache();
    }
    protected static function loadMap(): array {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) return $cached;
        $map = [];
        try {
            foreach (Db::name('ugc_aliyun_baseline_label')->select()->toArray() as $row) {
                $map[(string)$row['label']] = $row;
            }
        } catch (\Throwable $e) {}
        Cache::set(self::CACHE_KEY, $map, 3600);
        return $map;
    }
}