<?php

namespace app\common\repositories\system;

use think\facade\Db;

/**
 * 社区信息流：审核态下按 community_type / is_type 过滤列表 + 对应顶 Tab
 */
class AppEntryCommunityFeed
{
    /** @var array<string, array{label:string,sort:int,default_hide:int}> */
    public const TYPE_META = [
        'note_image' => ['label' => '图文笔记', 'sort' => 10, 'default_hide' => 0],
        'note_video' => ['label' => '视频笔记', 'sort' => 20, 'default_hide' => 0],
        'redpacket' => ['label' => '红包求助', 'sort' => 30, 'default_hide' => 1],
        'paid' => ['label' => '付费内容', 'sort' => 40, 'default_hide' => 1],
        'recruit' => ['label' => '招聘', 'sort' => 50, 'default_hide' => 1],
    ];

    /** type_key → 顶栏 tab_key（与 plant_grass resolveTabKey 一致） */
    public const TYPE_TAB_KEYS = [
        'redpacket' => ['rp_help', 'rp_task'],
        'recruit' => ['recruit'],
    ];

    /** type_key → 社区分类 cate_name 兜底 */
    public const TYPE_CATE_NAMES = [
        'redpacket' => ['红包求助', '红包任务'],
        'recruit' => ['招聘'],
    ];

    public function tablesReady(): bool
    {
        try {
            $prefix = config('database.connections.mysql.prefix');
            $table = $prefix . 'app_entry_community_feed';
            $rows = Db::query("SHOW TABLES LIKE '" . addslashes($table) . "'");
            return !empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function ensureSeed(): void
    {
        if (!$this->tablesReady()) {
            return;
        }
        if ((int)Db::name('app_entry_community_feed')->count() > 0) {
            return;
        }
        $now = time();
        foreach (self::TYPE_META as $key => $meta) {
            $h = (int)$meta['default_hide'];
            Db::name('app_entry_community_feed')->insert([
                'type_key' => $key,
                'label' => $meta['label'],
                'sort' => (int)$meta['sort'],
                'hide_when_review_ios' => $h,
                'hide_when_review_android' => $h,
                'hide_when_review_routine' => $h,
            ]);
        }
    }

    public function getAdminRows(): array
    {
        if (!$this->tablesReady()) {
            return $this->defaultAdminRows();
        }
        $this->ensureSeed();
        $rows = Db::name('app_entry_community_feed')->order('sort asc')->select()->toArray();
        if (!$rows) {
            return $this->defaultAdminRows();
        }
        return array_map([$this, 'normalizeRow'], $rows);
    }

    public function saveAdminRows(array $rows): void
    {
        if (!$this->tablesReady()) {
            return;
        }
        $this->ensureSeed();
        foreach ($rows as $row) {
            $key = (string)($row['type_key'] ?? '');
            if ($key === '' || !isset(self::TYPE_META[$key])) {
                continue;
            }
            Db::name('app_entry_community_feed')->where('type_key', $key)->update([
                'label' => (string)($row['label'] ?? self::TYPE_META[$key]['label']),
                'hide_when_review_ios' => !empty($row['hide_when_review_ios']) ? 1 : 0,
                'hide_when_review_android' => !empty($row['hide_when_review_android']) ? 1 : 0,
                'hide_when_review_routine' => !empty($row['hide_when_review_routine']) ? 1 : 0,
                'sort' => (int)($row['sort'] ?? self::TYPE_META[$key]['sort']),
            ]);
        }
    }

    protected function defaultAdminRows(): array
    {
        $out = [];
        foreach (self::TYPE_META as $key => $meta) {
            $h = (int)$meta['default_hide'];
            $out[] = [
                'type_key' => $key,
                'label' => $meta['label'],
                'sort' => (int)$meta['sort'],
                'hide_when_review_ios' => $h,
                'hide_when_review_android' => $h,
                'hide_when_review_routine' => $h,
            ];
        }
        return $out;
    }

    public function normalizeRow(array $row): array
    {
        $key = (string)($row['type_key'] ?? '');
        $meta = self::TYPE_META[$key] ?? ['label' => $key, 'sort' => 0, 'default_hide' => 0];
        $legacy = (int)$meta['default_hide'];
        return [
            'type_key' => $key,
            'label' => (string)($row['label'] ?? $meta['label']),
            'sort' => (int)($row['sort'] ?? $meta['sort']),
            'hide_when_review_ios' => isset($row['hide_when_review_ios'])
                ? (int)(!empty($row['hide_when_review_ios'])) : $legacy,
            'hide_when_review_android' => isset($row['hide_when_review_android'])
                ? (int)(!empty($row['hide_when_review_android'])) : $legacy,
            'hide_when_review_routine' => isset($row['hide_when_review_routine'])
                ? (int)(!empty($row['hide_when_review_routine'])) : $legacy,
        ];
    }

    /**
     * @return array{in_review:bool,hidden_type_keys:array,hidden_category_ids:array,hidden_tab_keys:array}
     */
    public function clientContext(string $platform, string $appVersion): array
    {
        $empty = [
            'in_review' => false,
            'hidden_type_keys' => [],
            'hidden_category_ids' => [],
            'hidden_tab_keys' => [],
        ];
        if (!$this->tablesReady() || $platform === '' || !in_array($platform, ['ios', 'android', 'routine'], true)) {
            return $empty;
        }
        $review = Db::name('app_entry_review')->where('id', 1)->find() ?: [];
        $reviewVersion = $this->reviewVersionForPlatform($review, $platform);
        $inReview = $reviewVersion !== '' && $appVersion !== '' && $appVersion === $reviewVersion;
        if (!$inReview) {
            return $empty;
        }
        $hiddenKeys = [];
        foreach ($this->getAdminRows() as $row) {
            if ($this->rowHiddenOnPlatform($row, $platform)) {
                $hiddenKeys[] = $row['type_key'];
            }
        }
        $hiddenKeys = array_values(array_unique($hiddenKeys));
        $tabKeys = [];
        foreach ($hiddenKeys as $key) {
            foreach (self::TYPE_TAB_KEYS[$key] ?? [] as $tk) {
                $tabKeys[] = $tk;
            }
        }
        $tabKeys = array_values(array_unique($tabKeys));
        $categoryIds = $this->resolveHiddenCategoryIds($hiddenKeys, $tabKeys);

        return [
            'in_review' => true,
            'hidden_type_keys' => $hiddenKeys,
            'hidden_category_ids' => $categoryIds,
            'hidden_tab_keys' => $tabKeys,
        ];
    }

    public function clientPayload(string $platform, string $appVersion): array
    {
        $ctx = $this->clientContext($platform, $appVersion);
        return [
            'in_review' => $ctx['in_review'],
            'hidden_type_keys' => $ctx['hidden_type_keys'],
            'hidden_category_ids' => $ctx['hidden_category_ids'],
            'hidden_tab_keys' => $ctx['hidden_tab_keys'],
        ];
    }

    public function isCategoryBlocked(int $categoryId, string $platform, string $appVersion): bool
    {
        if ($categoryId <= 0) {
            return false;
        }
        $ctx = $this->clientContext($platform, $appVersion);
        return in_array($categoryId, $ctx['hidden_category_ids'], true);
    }

    /**
     * @param \think\db\Query|\think\db\BaseQuery $query
     */
    public function applyToQuery($query, string $platform, string $appVersion): void
    {
        $ctx = $this->clientContext($platform, $appVersion);
        $hidden = $ctx['hidden_type_keys'];
        if (!$hidden) {
            return;
        }
        $parts = [];
        foreach ($hidden as $typeKey) {
            $expr = $this->sqlExprForTypeKey($typeKey);
            if ($expr !== '') {
                $parts[] = $expr;
            }
        }
        if ($parts) {
            $query->whereRaw('NOT (' . implode(' OR ', $parts) . ')');
        }
    }

    protected function sqlExprForTypeKey(string $typeKey): string
    {
        switch ($typeKey) {
            case 'note_image':
                return '(Community.community_type=0 AND Community.is_type=1)';
            case 'note_video':
                return '(Community.community_type=0 AND Community.is_type=2)';
            case 'redpacket':
                return '(Community.community_type=1)';
            case 'paid':
                return '(Community.community_type=2)';
            case 'recruit':
                return '(Community.community_type=3)';
            default:
                return '';
        }
    }

    protected function rowHiddenOnPlatform(array $row, string $platform): bool
    {
        switch ($platform) {
            case 'ios':
                return !empty($row['hide_when_review_ios']);
            case 'android':
                return !empty($row['hide_when_review_android']);
            case 'routine':
                return !empty($row['hide_when_review_routine']);
            default:
                return false;
        }
    }

    protected function reviewVersionForPlatform(array $review, string $platform): string
    {
        switch ($platform) {
            case 'ios':
                return trim((string)($review['review_version_ios'] ?? ''));
            case 'android':
                return trim((string)($review['review_version_android'] ?? ''));
            case 'routine':
                return trim((string)($review['review_version_routine'] ?? ''));
            default:
                return '';
        }
    }

    /**
     * @param string[] $hiddenTypeKeys
     * @param string[] $hiddenTabKeys
     * @return int[]
     */
    protected function resolveHiddenCategoryIds(array $hiddenTypeKeys, array $hiddenTabKeys): array
    {
        $names = [];
        foreach ($hiddenTypeKeys as $key) {
            foreach (self::TYPE_CATE_NAMES[$key] ?? [] as $n) {
                $names[] = $n;
            }
        }
        $names = array_values(array_unique($names));
        $ids = [];
        try {
            if ($hiddenTabKeys) {
                $byTab = Db::name('community_category')
                    ->whereIn('tab_key', $hiddenTabKeys)
                    ->column('category_id');
                foreach ($byTab as $id) {
                    $ids[] = (int)$id;
                }
            }
            if ($names) {
                $byName = Db::name('community_category')
                    ->whereIn('cate_name', $names)
                    ->column('category_id');
                foreach ($byName as $id) {
                    $ids[] = (int)$id;
                }
            }
        } catch (\Throwable $e) {
            return [];
        }
        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param array $list community category API rows
     */
    public function filterCategoryApiList(array $list, string $platform, string $appVersion): array
    {
        $ctx = $this->clientContext($platform, $appVersion);
        if (!$ctx['hidden_category_ids'] && !$ctx['hidden_tab_keys']) {
            return $list;
        }
        $hiddenIds = array_flip($ctx['hidden_category_ids']);
        $hiddenTabs = array_flip($ctx['hidden_tab_keys']);
        $hiddenNames = [];
        foreach ($ctx['hidden_type_keys'] as $typeKey) {
            foreach (self::TYPE_CATE_NAMES[$typeKey] ?? [] as $n) {
                $hiddenNames[] = $n;
            }
        }
        return array_values(array_filter($list, function ($item) use ($hiddenIds, $hiddenTabs, $hiddenNames) {
            $cid = (int)($item['category_id'] ?? 0);
            if ($cid && isset($hiddenIds[$cid])) {
                return false;
            }
            $tk = (string)($item['tab_key'] ?? '');
            if ($tk !== '' && isset($hiddenTabs[$tk])) {
                return false;
            }
            $name = (string)($item['cate_name'] ?? '');
            if ($name !== '' && in_array($name, $hiddenNames, true)) {
                return false;
            }
            return true;
        }));
    }
}
