<?php

namespace app\common\repositories\system;

use think\facade\Cache;
use think\facade\Db;
use think\exception\ValidateException;

class AppEntryRepository
{
    public const CACHE_TAG = 'app_entry_config';

    public function tablesReady(): bool
    {
        try {
            $prefix = config('database.connections.mysql.prefix');
            $table = $prefix . 'app_entry_item';
            $rows = Db::query("SHOW TABLES LIKE '" . addslashes($table) . "'");
            return !empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getAdminPayload(): array
    {
        $this->assertTables();
        app()->make(AppEntryCommunityFeed::class)->ensureSeed();
        $review = Db::name('app_entry_review')->where('id', 1)->find();
        if (!$review) {
            Db::name('app_entry_review')->insert([
                'id' => 1,
                'review_version_ios' => '',
                'review_version_android' => '',
                'review_version_routine' => '',
                'feature_gift_enabled' => 1,
                'hide_gift_when_review_ios' => 0,
                'hide_gift_when_review_android' => 0,
                'hide_gift_when_review_routine' => 0,
                'update_time' => time(),
            ]);
            $review = Db::name('app_entry_review')->where('id', 1)->find();
        }
        return [
            'review' => $this->formatAdminReview($review ?: []),
            'slots' => Db::name('app_entry_slot')->order('sort asc')->select()->toArray(),
            'groups' => Db::name('app_entry_group')->order('sort asc')->select()->toArray(),
            'items' => array_map(
                [$this, 'normalizeAdminItem'],
                Db::name('app_entry_item')->order('sort asc')->select()->toArray()
            ),
            'community_feed' => app()->make(AppEntryCommunityFeed::class)->getAdminRows(),
        ];
    }

    public function saveAdminPayload(array $payload): void
    {
        $this->assertTables();
        $review = $payload['review'] ?? [];
        Db::startTrans();
        try {
            $reviewRow = $this->formatAdminReview($review);
            Db::name('app_entry_review')->where('id', 1)->update([
                'review_version_ios' => $reviewRow['review_version_ios'],
                'review_version_android' => $reviewRow['review_version_android'],
                'review_version_routine' => $reviewRow['review_version_routine'],
                'feature_gift_enabled' => $reviewRow['feature_gift_enabled'],
                'hide_gift_when_review_ios' => $reviewRow['hide_gift_when_review_ios'],
                'hide_gift_when_review_android' => $reviewRow['hide_gift_when_review_android'],
                'hide_gift_when_review_routine' => $reviewRow['hide_gift_when_review_routine'],
                'update_time' => time(),
            ]);

            foreach ($payload['slots'] ?? [] as $slot) {
                $slotKey = $slot['slot_key'] ?? '';
                if ($slotKey === '') {
                    continue;
                }
                Db::name('app_entry_slot')->where('slot_key', $slotKey)->update([
                    'section_title' => (string)($slot['section_title'] ?? ''),
                    'tab_label' => (string)($slot['tab_label'] ?? ''),
                ]);
            }

            $submittedGroupIds = [];
            $groupIdMap = [];
            foreach ($payload['groups'] ?? [] as $g) {
                $row = [
                    'slot_key' => $g['slot_key'],
                    'title' => $g['title'] ?? '',
                    'sort' => (int)($g['sort'] ?? 0),
                ];
                $oldKey = $g['group_id'] ?? null;
                if (!empty($g['group_id']) && is_numeric($g['group_id'])) {
                    $gid = (int)$g['group_id'];
                    Db::name('app_entry_group')->where('group_id', $gid)->update($row);
                    $submittedGroupIds[] = $gid;
                    $groupIdMap[$oldKey] = $gid;
                } else {
                    $newId = (int)Db::name('app_entry_group')->insertGetId($row);
                    $submittedGroupIds[] = $newId;
                    if ($oldKey !== null && $oldKey !== '') {
                        $groupIdMap[$oldKey] = $newId;
                    }
                }
            }
            if ($submittedGroupIds) {
                Db::name('app_entry_group')->whereNotIn('group_id', $submittedGroupIds)->delete();
            } else {
                Db::name('app_entry_group')->where('group_id', '>', 0)->delete();
            }

            $submittedItemIds = [];
            $now = time();
            foreach ($payload['items'] ?? [] as $it) {
                $groupId = $it['group_id'] ?? null;
                if ($groupId !== null && $groupId !== '' && !is_numeric($groupId)) {
                    $groupId = $groupIdMap[$groupId] ?? null;
                } elseif ($groupId !== null && $groupId !== '') {
                    $groupId = (int)$groupId;
                } else {
                    $groupId = null;
                }
                $row = [
                    'slot_key' => $it['slot_key'],
                    'group_id' => $groupId,
                    'item_key' => $it['item_key'] ?? '',
                    'name' => $it['name'] ?? '',
                    'subtitle' => $it['subtitle'] ?? '',
                    'icon' => $it['icon'] ?? '',
                    'tone' => $it['tone'] ?? '',
                    'url' => $it['url'] ?? '',
                    'link_type' => $it['link_type'] ?: 'path',
                    'action_key' => $it['action_key'] ?? '',
                    'is_enabled' => !isset($it['is_enabled']) || !empty($it['is_enabled']) ? 1 : 0,
                    'hide_when_review_ios' => !empty($it['hide_when_review_ios']) ? 1 : 0,
                    'hide_when_review_android' => !empty($it['hide_when_review_android']) ? 1 : 0,
                    'hide_when_review_routine' => !empty($it['hide_when_review_routine']) ? 1 : 0,
                    'hide_when_review' => (
                        !empty($it['hide_when_review_ios'])
                        || !empty($it['hide_when_review_android'])
                        || !empty($it['hide_when_review_routine'])
                    ) ? 1 : 0,
                    'sort' => (int)($it['sort'] ?? 0),
                    'update_time' => $now,
                ];
                if (!empty($it['item_id'])) {
                    Db::name('app_entry_item')->where('item_id', (int)$it['item_id'])->update($row);
                    $submittedItemIds[] = (int)$it['item_id'];
                } else {
                    $row['create_time'] = $now;
                    $submittedItemIds[] = (int)Db::name('app_entry_item')->insertGetId($row);
                }
            }
            // 入口项不再物理删除：下架项 is_enabled=0 仍保留；未保存的新增行若从列表去掉则从未入库

            app()->make(AppEntryCommunityFeed::class)->saveAdminRows($payload['community_feed'] ?? []);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        $this->clearCache();
    }

    public function initDefaults(bool $force = false): array
    {
        $this->assertTables();
        $count = (int)Db::name('app_entry_item')->count();
        if ($count > 0 && !$force) {
            return ['skipped' => true, 'message' => '已有入口数据，未覆盖'];
        }
        if ($force) {
            Db::name('app_entry_item')->where('item_id', '>', 0)->delete();
            Db::name('app_entry_group')->where('group_id', '>', 0)->delete();
        }
        foreach (AppEntryDefaultSeed::slots() as $slot) {
            $key = $slot['slot_key'];
            if (Db::name('app_entry_slot')->where('slot_key', $key)->find()) {
                Db::name('app_entry_slot')->where('slot_key', $key)->update($slot);
            } else {
                Db::name('app_entry_slot')->insert($slot);
            }
        }
        $seed = AppEntryDefaultSeed::groupsAndItems();
        $groupIdMap = [];
        foreach ($seed['groups'] as $g) {
            $oldId = $g['group_id'];
            unset($g['group_id']);
            $newId = Db::name('app_entry_group')->insertGetId($g);
            $groupIdMap[$oldId] = $newId;
        }
        $now = time();
        foreach ($seed['items'] as $it) {
            if ($it['group_id'] !== null) {
                $it['group_id'] = $groupIdMap[$it['group_id']] ?? null;
            }
            $it['create_time'] = $now;
            $it['update_time'] = $now;
            Db::name('app_entry_item')->insert($it);
        }
        app()->make(AppEntryCommunityFeed::class)->ensureSeed();
        $this->clearCache();
        return ['skipped' => false, 'message' => '初始化完成'];
    }

    public function clientConfig(string $platform, string $appVersion): array
    {
        $this->assertTables();
        if ((int)Db::name('app_entry_item')->count() === 0) {
            $this->initDefaults(false);
        }
        $ver = (int)Cache::get('app_entry_config_ver', 0);
        $cacheKey = self::CACHE_TAG . ':' . $ver . ':' . $platform . ':' . md5($appVersion);
        $cached = Cache::get($cacheKey);
        if ($cached !== null && is_array($cached)) {
            return $cached;
        }
        $review = Db::name('app_entry_review')->where('id', 1)->find() ?: [];
        $reviewVersion = $this->reviewVersionForPlatform($review, $platform);
        $inReview = $reviewVersion !== '' && $appVersion !== '' && $appVersion === $reviewVersion;

        $slotsMeta = Db::name('app_entry_slot')->order('sort asc')->select()->toArray();
        $groupsAll = Db::name('app_entry_group')->order('sort asc')->select()->toArray();
        $itemsAll = Db::name('app_entry_item')->order('sort asc')->select()->toArray();

        $groupsBySlot = [];
        foreach ($groupsAll as $g) {
            $groupsBySlot[$g['slot_key']][] = $g;
        }
        $itemsBySlot = [];
        $itemsByGroup = [];
        foreach ($itemsAll as $it) {
            if (!$this->itemEnabled($it)) {
                continue;
            }
            if ($inReview && $this->itemHiddenInReview($it, $platform)) {
                continue;
            }
            if ($it['group_id']) {
                $itemsByGroup[$it['group_id']][] = $it;
            } else {
                $itemsBySlot[$it['slot_key']][] = $it;
            }
        }

        $slots = [];
        foreach ($slotsMeta as $meta) {
            $key = $meta['slot_key'];
            $slotGroups = [];
            foreach ($groupsBySlot[$key] ?? [] as $g) {
                $gi = $itemsByGroup[$g['group_id']] ?? [];
                if (!$gi) {
                    continue;
                }
                $slotGroups[] = [
                    'group_id' => (int)$g['group_id'],
                    'title' => $g['title'],
                    'items' => array_map([$this, 'formatClientItem'], $gi),
                ];
            }
            $flat = $itemsBySlot[$key] ?? [];
            $slots[] = [
                'slot_key' => $key,
                'section_title' => $meta['section_title'] ?? '',
                'groups' => $slotGroups,
                'items' => array_map([$this, 'formatClientItem'], $flat),
            ];
        }

        $giftVisible = $this->giftFeatureVisible($review, $platform, $inReview);
        $communityFeed = app()->make(AppEntryCommunityFeed::class)->clientPayload($platform, $appVersion);

        $data = [
            'in_review' => $inReview,
            'review_version' => $reviewVersion,
            'features' => [
                'gift' => $giftVisible,
            ],
            'community_feed' => $communityFeed,
            'slots' => $slots,
        ];
        Cache::set($cacheKey, $data, 600);
        return $data;
    }

    protected function formatClientItem(array $it): array
    {
        return [
            'item_id' => (int)$it['item_id'],
            'item_key' => $it['item_key'] ?? '',
            'name' => $it['name'] ?? '',
            'subtitle' => $it['subtitle'] ?? '',
            'icon' => AppEntryDefaultSeed::resolveIcon($it['item_key'] ?? '', $it['icon'] ?? ''),
            'tone' => $it['tone'] ?? '',
            'url' => $it['url'] ?? '',
            'link_type' => $it['link_type'] ?: 'path',
            'action_key' => $it['action_key'] ?? '',
        ];
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

    public function normalizeAdminItem(array $it): array
    {
        $legacy = !empty($it['hide_when_review']) ? 1 : 0;
        $it['hide_when_review_ios'] = isset($it['hide_when_review_ios'])
            ? (int)(!empty($it['hide_when_review_ios']))
            : $legacy;
        $it['hide_when_review_android'] = isset($it['hide_when_review_android'])
            ? (int)(!empty($it['hide_when_review_android']))
            : $legacy;
        $it['hide_when_review_routine'] = isset($it['hide_when_review_routine'])
            ? (int)(!empty($it['hide_when_review_routine']))
            : $legacy;
        $it['hide_when_review'] = (
            $it['hide_when_review_ios']
            || $it['hide_when_review_android']
            || $it['hide_when_review_routine']
        ) ? 1 : 0;
        $it['is_enabled'] = !isset($it['is_enabled']) || !empty($it['is_enabled']) ? 1 : 0;
        return $it;
    }

    protected function itemEnabled(array $it): bool
    {
        if (!array_key_exists('is_enabled', $it)) {
            return true;
        }
        return (int)$it['is_enabled'] === 1;
    }

    protected function itemHiddenInReview(array $it, string $platform): bool
    {
        $it = $this->normalizeAdminItem($it);
        switch ($platform) {
            case 'ios':
                return !empty($it['hide_when_review_ios']);
            case 'android':
                return !empty($it['hide_when_review_android']);
            case 'routine':
                return !empty($it['hide_when_review_routine']);
            default:
                return !empty($it['hide_when_review']);
        }
    }

    public function formatAdminReview(array $review): array
    {
        return [
            'review_version_ios' => trim((string)($review['review_version_ios'] ?? '')),
            'review_version_android' => trim((string)($review['review_version_android'] ?? '')),
            'review_version_routine' => trim((string)($review['review_version_routine'] ?? '')),
            'feature_gift_enabled' => !isset($review['feature_gift_enabled']) || !empty($review['feature_gift_enabled']) ? 1 : 0,
            'hide_gift_when_review_ios' => !empty($review['hide_gift_when_review_ios']) ? 1 : 0,
            'hide_gift_when_review_android' => !empty($review['hide_gift_when_review_android']) ? 1 : 0,
            'hide_gift_when_review_routine' => !empty($review['hide_gift_when_review_routine']) ? 1 : 0,
        ];
    }

    protected function giftFeatureVisible(array $review, string $platform, bool $inReview): bool
    {
        $r = $this->formatAdminReview($review);
        if (empty($r['feature_gift_enabled'])) {
            return false;
        }
        if (!$inReview) {
            return true;
        }
        switch ($platform) {
            case 'ios':
                return empty($r['hide_gift_when_review_ios']);
            case 'android':
                return empty($r['hide_gift_when_review_android']);
            case 'routine':
                return empty($r['hide_gift_when_review_routine']);
            default:
                return true;
        }
    }

    public function clearCache(): void
    {
        Cache::inc('app_entry_config_ver');
    }

    protected function assertTables(): void
    {
        if (!$this->tablesReady()) {
            throw new ValidateException('App入口表未安装，请先执行 .claude/sql/app_entry_schema.sql');
        }
    }
}
