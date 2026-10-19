<?php

namespace app\common\repositories\user;

use think\facade\Db;

/**
 * 资料字段动态筛选（profile_filter JSON，与硬编码筛选项 AND）
 */
class UserProfileFieldFilter
{
    /** 筛选面板已内置（勿走动态 schema） */
    public const BUILTIN_FIELD_KEYS = [
        'gender',
    ];

    /** 不参与筛选的敏感字段 */
    public const SENSITIVE_FIELD_KEYS = [
        UserProfileFieldRepository::ACCOUNT_PHONE_FIELD_KEY,
        UserProfileFieldRepository::REAL_NAME_FIELD_KEY,
        'id_card',
        'wechat_id',
        'douyin_id',
        'kuaishou_id',
        'weibo_id',
        'xiaohongshu_id',
        'plate_number',
        'contact_phone',
    ];

    /** 已由筛选面板硬编码 + UserProfileFilterTrait 处理的 bind_column */
    public const BUILTIN_BIND_COLUMNS = [
        'zodiac',
        'school_name',
        'job_title',
        'hometown_province',
        'hometown_city',
        'current_province',
        'current_city',
        'registered_province',
        'registered_city',
        'annual_income',
        'relationship_status',
        'marital_status',
        'dating_purpose',
        'total_assets',
        'want_kids',
        'smoking',
        'drinking',
        'tattoo',
        'only_child',
        'height',
        'weight',
        'education',
        'car_count',
        'house_count',
        'pets',
        'hobbies',
    ];

    public const FILTERABLE_TYPES = ['radio', 'checkbox', 'text', 'number', 'switch'];

    /** @var UserProfileFieldRepository */
    protected $fieldRepo;

    public function __construct(UserProfileFieldRepository $fieldRepo)
    {
        $this->fieldRepo = $fieldRepo;
    }

    public function parseProfileFilter(array $where): array
    {
        $raw = $where['profile_filter'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($raw) ? $raw : [];
    }

    public function hasProfileFilterInput(array $where): bool
    {
        $pf = $this->parseProfileFilter($where);
        foreach ($pf as $v) {
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            return true;
        }
        return false;
    }

    /**
     * @return list<array>
     */
    public function filterSchema(): array
    {
        $out = [];
        foreach ($this->fieldRepo->filterableFieldRows() as $row) {
            $widget = $this->resolveWidget($row);
            if ($widget === null) {
                continue;
            }
            $key = (string)($row['field_key'] ?? '');
            $opts = is_array($row['options'] ?? null) ? $row['options'] : [];
            $out[] = [
                'field_key'   => $key,
                'title'       => (string)($row['title'] ?? ''),
                'type'        => (string)($row['type'] ?? ''),
                'widget'      => $widget,
                'options'     => array_values(array_map('strval', $opts)),
                'bind_column' => (string)($row['bind_column'] ?? ''),
                'placeholder' => (string)($row['placeholder'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * @return int[]|null uid 列表；null 表示无动态条件
     */
    public function filterUids(array $profileFilter): ?array
    {
        if (!$profileFilter) {
            return null;
        }

        $metaMap = [];
        foreach ($this->fieldRepo->filterableFieldRows() as $row) {
            $metaMap[$row['field_key']] = $row;
        }

        $query = Db::name('user_profile');
        $applied = false;

        foreach ($profileFilter as $key => $rawValue) {
            $key = (string)$key;
            if ($key === '' || !isset($metaMap[$key])) {
                continue;
            }
            if ($this->isEmptyFilterValue($rawValue)) {
                continue;
            }
            $this->applyCondition($query, $metaMap[$key], $rawValue);
            $applied = true;
        }

        if (!$applied) {
            return null;
        }

        return $query->column('uid');
    }

    public function canEnableFilter(array $row): bool
    {
        $key = (string)($row['field_key'] ?? '');
        if ($key === '' || in_array($key, self::BUILTIN_FIELD_KEYS, true) || in_array($key, self::SENSITIVE_FIELD_KEYS, true)) {
            return false;
        }
        $bind = (string)($row['bind_column'] ?? '');
        if ($bind !== '' && in_array($bind, self::BUILTIN_BIND_COLUMNS, true)) {
            return false;
        }
        $type = (string)($row['type'] ?? '');
        if (!in_array($type, self::FILTERABLE_TYPES, true)) {
            return false;
        }
        return $this->widgetTypeForField($row) !== null;
    }

    /** 按字段 type 决定筛选控件；不依赖 is_filter（保存前校验用） */
    protected function widgetTypeForField(array $row): ?string
    {
        $type = (string)($row['type'] ?? '');
        if ($type === 'radio' || $type === 'switch') {
            return 'single';
        }
        if ($type === 'checkbox') {
            return 'multi';
        }
        if ($type === 'text' || $type === 'number') {
            return 'text';
        }
        return null;
    }

    protected function resolveWidget(array $row): ?string
    {
        if ((int)($row['status'] ?? 0) !== 1 || (int)($row['is_filter'] ?? 0) !== 1) {
            return null;
        }
        if (!$this->canEnableFilter($row)) {
            return null;
        }
        return $this->widgetTypeForField($row);
    }

    protected function isEmptyFilterValue($rawValue): bool
    {
        if ($rawValue === null || $rawValue === '') {
            return true;
        }
        if (is_array($rawValue) && !array_filter($rawValue, fn($v) => $v !== '' && $v !== null)) {
            return true;
        }
        return false;
    }

    protected function applyCondition($query, array $meta, $rawValue): void
    {
        $type = (string)($meta['type'] ?? '');
        $bind = (string)($meta['bind_column'] ?? '');
        $key = (string)($meta['field_key'] ?? '');
        $opts = is_array($meta['options'] ?? null) ? $meta['options'] : [];

        if ($type === 'radio') {
            $intVal = $this->normalizeOptionIndex($rawValue, $opts);
            if ($bind !== '' && !in_array($bind, self::BUILTIN_BIND_COLUMNS, true)) {
                $query->where($bind, $intVal);
                return;
            }
            $label = $this->normalizeOptionLabel($rawValue, $opts);
            $idx = $this->normalizeOptionIndex($rawValue, $opts);
            if ($label !== '') {
                $this->whereExtraRadioValue($query, $key, $label, $idx);
            }
            return;
        }

        if ($type === 'switch') {
            $on = in_array((string)$rawValue, ['1', 'true', 'on'], true);
            $val = $on ? '1' : '0';
            if ($bind !== '' && !in_array($bind, self::BUILTIN_BIND_COLUMNS, true)) {
                $query->where($bind, (int)$val);
                return;
            }
            $this->whereExtraEquals($query, $key, $val);
            return;
        }

        if ($type === 'checkbox') {
            $labels = $this->normalizeMultiLabels($rawValue, $opts);
            if (!$labels) {
                return;
            }
            if ($bind !== '' && $bind === 'dating_purpose') {
                return;
            }
            if ($bind !== '' && !in_array($bind, self::BUILTIN_BIND_COLUMNS, true)) {
                $ids = $this->labelsToOptionIds($labels, $opts);
                if ($ids) {
                    $conds = array_map(fn($id) => "FIND_IN_SET('" . (int)$id . "', `" . $bind . "`)", $ids);
                    $query->whereRaw('(' . implode(' OR ', $conds) . ')');
                }
                return;
            }
            $query->where(function ($q) use ($key, $labels) {
                foreach ($labels as $lb) {
                    $q->whereOr(function ($sq) use ($key, $lb) {
                        $this->whereExtraJsonContains($sq, $key, $lb);
                    });
                }
            });
            return;
        }

        if ($type === 'text' || $type === 'number') {
            $text = trim((string)$rawValue);
            if ($text === '') {
                return;
            }
            if ($bind !== '' && !in_array($bind, self::BUILTIN_BIND_COLUMNS, true)) {
                $query->whereLike($bind, '%' . addcslashes($text, '%_\\') . '%');
                return;
            }
            $escaped = addcslashes($text, '%_\\');
            $path = '$.' . str_replace("'", '', $key);
            $query->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(`extra_fields`, ?)) LIKE ?",
                [$path, '%' . $escaped . '%']
            );
        }
    }

    protected function normalizeOptionIndex($rawValue, array $opts): int
    {
        if (is_numeric($rawValue)) {
            return max(0, (int)$rawValue);
        }
        $label = trim((string)$rawValue);
        if ($label === '') {
            return 0;
        }
        $idx = array_search($label, $opts, true);
        return $idx === false ? 0 : (int)$idx + 1;
    }

    protected function normalizeOptionLabel($rawValue, array $opts): string
    {
        if (is_numeric($rawValue)) {
            $i = (int)$rawValue - 1;
            return isset($opts[$i]) ? (string)$opts[$i] : '';
        }
        return trim((string)$rawValue);
    }

    /** @return list<string> */
    protected function normalizeMultiLabels($rawValue, array $opts): array
    {
        $items = is_array($rawValue) ? $rawValue : explode(',', (string)$rawValue);
        $labels = [];
        foreach ($items as $item) {
            if (is_numeric($item)) {
                $i = (int)$item - 1;
                if (isset($opts[$i])) {
                    $labels[] = (string)$opts[$i];
                }
                continue;
            }
            $lb = trim((string)$item);
            if ($lb !== '') {
                $labels[] = $lb;
            }
        }
        return array_values(array_unique($labels));
    }

    /** @return list<int> */
    protected function labelsToOptionIds(array $labels, array $opts): array
    {
        $ids = [];
        foreach ($labels as $lb) {
            $idx = array_search($lb, $opts, true);
            if ($idx !== false) {
                $ids[] = (int)$idx + 1;
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    protected function whereExtraEquals($query, string $fieldKey, string $value): void
    {
        $path = '$.' . str_replace("'", '', $fieldKey);
        $query->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(`extra_fields`, ?)) = ?', [$path, $value]);
    }

    /** extra_fields 单选：兼容存文案或存选项序号（1-based 字符串） */
    protected function whereExtraRadioValue($query, string $fieldKey, string $label, int $index): void
    {
        $path = '$.' . str_replace("'", '', $fieldKey);
        $query->where(function ($q) use ($path, $label, $index) {
            $q->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(`extra_fields`, ?)) = ?', [$path, $label]);
            if ($index > 0) {
                $q->whereOr(function ($q2) use ($path, $index) {
                    $q2->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(`extra_fields`, ?)) = ?', [$path, (string)$index]);
                });
            }
        });
    }

    protected function whereExtraJsonContains($query, string $fieldKey, string $label): void
    {
        $path = '$.' . str_replace("'", '', $fieldKey);
        $jsonVal = json_encode($label, JSON_UNESCAPED_UNICODE);
        $query->whereRaw('JSON_CONTAINS(`extra_fields`, ?, ?)', [$jsonVal, $path]);
    }
}
