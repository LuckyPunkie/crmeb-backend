<?php
namespace crmeb\services\security;
use think\facade\Db;
class UgcModerationTicket {
    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;
    public static function open(array $data): int {
        $entityType = (string)($data['entity_type'] ?? '');
        $entityId = (int)($data['entity_id'] ?? 0);
        if ($entityType !== '' && $entityId > 0) {
            $exists = Db::name('ugc_moderation_ticket')
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->where('status', self::STATUS_PENDING)
                ->value('id');
            if ($exists) {
                return (int)$exists;
            }
        }
        $now = time();
        $payload = [
            'uid' => (int)($data['uid'] ?? 0),
            'scene' => (string)($data['scene'] ?? ''),
            'media_type' => (string)($data['media_type'] ?? 'image'),
            'entity_type' => (string)($data['entity_type'] ?? ''),
            'entity_id' => (int)($data['entity_id'] ?? 0),
            'media_url' => mb_substr((string)($data['media_url'] ?? ''), 0, 500),
            'trace_id' => (string)($data['trace_id'] ?? ''),
            'machine_driver' => (string)($data['machine_driver'] ?? ''),
            'machine_summary' => mb_substr((string)($data['machine_summary'] ?? ''), 0, 500),
            'status' => self::STATUS_PENDING,
            'create_time' => $now,
            'update_time' => $now,
        ];
        try { return (int)Db::name('ugc_moderation_ticket')->insertGetId($payload); }
        catch (\Throwable $e) { return 0; }
    }
    public static function resolve(int $id, int $status, int $adminId = 0): bool {
        if ($id <= 0) return false;
        $now = time();
        return (bool)Db::name('ugc_moderation_ticket')->where('id', $id)->where('status', self::STATUS_PENDING)->update([
            'status' => $status,
            'admin_id' => $adminId,
            'resolve_time' => $now,
            'update_time' => $now,
        ]);
    }
    public static function adminList(array $where, int $page, int $limit): array {
        $q = Db::name('ugc_moderation_ticket');
        if (!empty($where['status']) && $where['status'] !== 'all') {
            $q->where('status', (int)$where['status']);
        }
        if (!empty($where['media_type']) && $where['media_type'] !== 'all') {
            $q->where('media_type', (string)$where['media_type']);
        }
        if (!empty($where['scene']) && $where['scene'] !== 'all') {
            $q->where('scene', (string)$where['scene']);
        }
        $count = (clone $q)->count();
        $list = $q->order('id desc')->page($page, $limit)->select()->toArray();
        return compact('count', 'list');
    }
}