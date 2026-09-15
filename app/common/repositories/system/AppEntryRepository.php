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
        $review = Db::name('app_entry_review')->where('id', 1)->find();
        if (!$review) {
            Db::name('app_entry_review')->insert([
                'id' => 1,
                'review_version_ios' => '',
                'review_version_android' => '',
                'review_version_routine' => '',
                'update_time' => time(),
            ]);
            $review = Db::name('app_entry_review')->where('id', 1)->find();
        }
        return [
            'review' => [
                'review_version_ios' => $review['review_version_ios'] ?? '',
                'review_version_android' => $review['review_version_android'] ?? '',
                'review_version_routine' => $review['review_version_routine'] ?? '',
            ],
            'slots' => Db::name('app_entry_slot')->order('sort asc')->select()->toArray(),
            'groups' => Db::name('app_entry_group')->order('sort asc')->select()->toArray(),
            'items' => Db::name('app_entry_item')->order('sort asc')->select()->toArray(),
        ];
    }

    public function saveAdminPayload(array $payload): void
    {
        $this->assertTables();
        $review = $payload['review'] ?? [];
        Db::startTrans();
        try {
            Db::name('app_entry_review')->where('id', 1)->update([
                'review_version_ios' => trim((string)($review['review_version_ios'] ?? '')),
                'review_version_android' => trim((string)($review['review_version_android'] ?? '')),
                'review_version_routine' => trim((string)($review['review_version_routine'] ?? '')),
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
                    'hide_when_review' => !empty($it['hide_when_review']) ? 1 : 0,
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
            if ($submittedItemIds) {
                Db::name('app_entry_item')->whereNotIn('item_id', $submittedItemIds)->delete();
            } else {
                Db::name('app_entry_item')->where('item_id', '>', 0)->delete();
            }

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
            if ($inReview && !empty($it['hide_when_review'])) {
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

        $data = [
            'in_review' => $inReview,
            'review_version' => $reviewVersion,
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
