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
        $passMax = (int)$row['pass_max'];
        $reviewMax = (int)$row['review_max'];
        // 无 Confidence（常见于 nonLabel）：通过上限设为 0 表示「无分值不进通过」，走人工区间
        if ($confidence === null) {
            if ($passMax <= 0 && $reviewMax > 0) {
                return self::ACTION_MANUAL;
            }
            $confidence = 0.0;
        }
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

    /** baselineCheck 表5 label 中文释义 */
    public static function labelTitleCn(string $label): string
    {
        $map = [
            'nonLabel' => '未命中风险（正常）',
            'pornographic_adultContent' => '成人色情',
            'pornographic_adultContent_tii' => '成人色情（文字）',
            'sexual_suggestiveContent' => '低俗性感',
            'sexual_partialNudity' => '肢体裸露',
            'political_politicalFigure' => '涉政人物',
            'political_politicalFigure_name_tii' => '涉政人物姓名（文字）',
            'political_politicalFigure_metaphor_tii' => '涉政人物隐喻（文字）',
            'political_TVLogo' => '电视台台标',
            'political_map' => '地图',
            'political_outfit' => '特殊着装',
            'political_prohibitedPerson' => '落马人物',
            'political_prohibitedPerson_tii' => '落马人物（文字）',
            'political_taintedCelebrity' => '劣迹艺人',
            'political_taintedCelebrity_tii' => '劣迹艺人（文字）',
            'political_flag' => '旗帜',
            'political_historicalNihility' => '历史虚无主义',
            'political_historicalNihility_tii' => '历史虚无主义（文字）',
            'political_religion_tii' => '宗教（文字）',
            'political_racism_tii' => '种族主义（文字）',
            'political_badge' => '徽章',
            'violent_explosion' => '爆炸',
            'violent_gunKnives' => '枪支刀具',
            'violent_gunKnives_tii' => '枪支刀具（文字）',
            'violent_armedForces' => '武装力量',
            'violent_crowding' => '人群聚集',
            'violent_horrificContent' => '惊悚血腥',
            'violent_horrific_tii' => '惊悚血腥（文字）',
            'contraband_drug' => '毒品',
            'contraband_drug_tii' => '毒品（文字）',
            'contraband_gamble' => '赌博',
            'contraband_gamble_tii' => '赌博（文字）',
            'fraud_videoAbuse' => '视频滥用法',
            'fraud_playerAbuse' => '播放器滥用法',
        ];
        return (string)($map[$label] ?? $label);
    }

    public static function allRows(): array {
        try {
            $rows = Db::name('ugc_aliyun_baseline_label')->order('sort asc,id asc')->select()->toArray();
            foreach ($rows as &$row) {
                $label = (string)($row['label'] ?? '');
                $row['title_cn'] = self::labelTitleCn($label);
            }
            unset($row);
            return $rows;
        }
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