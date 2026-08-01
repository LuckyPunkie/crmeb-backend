<?php
namespace app\common\repositories\user;

use app\common\model\user\UserPushClient;
use app\common\repositories\BaseRepository;
use think\facade\Log;

class UserPushClientRepository extends BaseRepository
{
    /**
     * 绑定/更新用户推送 client_id
     */
    public function bind(int $uid, string $clientId, string $platform = ''): bool
    {
        $uid = (int)$uid;
        $clientId = trim($clientId);
        if ($uid <= 0 || $clientId === '') {
            return false;
        }
        $now = time();
        $platform = mb_substr(trim($platform), 0, 32);

        $row = UserPushClient::getDB()->where('client_id', $clientId)->find();
        if ($row) {
            UserPushClient::getDB()->where('id', $row['id'])->update([
                'uid' => $uid,
                'platform' => $platform ?: ($row['platform'] ?? ''),
                'update_time' => $now,
            ]);
            return true;
        }

        // 同一用户多设备：保留多条；同 cid 唯一
        UserPushClient::getDB()->insert([
            'uid' => $uid,
            'client_id' => $clientId,
            'platform' => $platform,
            'create_time' => $now,
            'update_time' => $now,
        ]);
        return true;
    }

    /**
     * 取用户全部 client_id
     */
    public function clientIdsByUid(int $uid): array
    {
        if ($uid <= 0) return [];
        $list = UserPushClient::getDB()->where('uid', $uid)->column('client_id');
        return array_values(array_unique(array_filter(array_map('strval', (array)$list))));
    }

    /**
     * 批量取多个 uid 的 client_id
     */
    public function clientIdsByUids(array $uids): array
    {
        $uids = array_values(array_unique(array_filter(array_map('intval', $uids))));
        if (!$uids) return [];
        $list = UserPushClient::getDB()->whereIn('uid', $uids)->column('client_id');
        return array_values(array_unique(array_filter(array_map('strval', (array)$list))));
    }

    public function unbind(int $uid, string $clientId = ''): void
    {
        $q = UserPushClient::getDB()->where('uid', $uid);
        if ($clientId !== '') {
            $q->where('client_id', $clientId);
        }
        $q->delete();
        Log::info('UserPushClient unbind uid=' . $uid . ' cid=' . $clientId);
    }
}
