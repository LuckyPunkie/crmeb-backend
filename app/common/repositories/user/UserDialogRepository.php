<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace app\common\repositories\user;

use app\common\dao\user\UserDialogDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\service\StoreServiceUserRepository;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

/**
 * @mixin UserDialogDao
 */
class UserDialogRepository extends BaseRepository
{
    public function __construct(UserDialogDao $dao)
    {
        $this->dao = $dao;
    }

    public function getOrCreate(int $uid1, int $uid2)
    {
        return $this->dao->getOrCreate($uid1, $uid2);
    }

    /**
     * 获取消息列表（合并私信+客服）
     */
    public function dialogList(int $uid, int $page, int $limit, string $type = 'all')
    {
        $dialogQuery = Db::name('user_dialog')
            ->alias('d')
            ->field([
                'd.dialog_id', 'd.last_message', 'd.last_message_type',
                'd.last_message_time', 'd.last_sender_uid', 'd.relation_type',
                'd.is_clear_a', 'd.is_clear_b', 'd.is_black_a', 'd.is_black_b',
                'd.unread_a', 'd.unread_b', 'd.uid_a', 'd.uid_b',
            ])
            ->where(function ($q) use ($uid) {
                $q->where('d.uid_a', $uid)->whereOr('d.uid_b', $uid);
            })
            ->where('d.last_message_time', '>', '0000-00-00 00:00:00');

        switch ($type) {
            case 'inter_follow':
                $dialogQuery->where(function ($q) use ($uid) {
                    $q->where(function ($sq) use ($uid) {
                        $sq->where('d.uid_a', $uid)->where('d.relation_type', 1);
                    })->whereOr(function ($sq) use ($uid) {
                        $sq->where('d.uid_b', $uid)->where('d.relation_type', 1);
                    });
                });
                break;
            case 'follow':
                $dialogQuery->where(function ($q) use ($uid) {
                    $q->where(function ($sq) use ($uid) {
                        $sq->where('d.uid_a', $uid)->where('d.relation_type', 2);
                    })->whereOr(function ($sq) use ($uid) {
                        $sq->where('d.uid_b', $uid)->where('d.relation_type', 3);
                    });
                });
                break;
            case 'fans':
                $dialogQuery->where(function ($q) use ($uid) {
                    $q->where(function ($sq) use ($uid) {
                        $sq->where('d.uid_a', $uid)->where('d.relation_type', 3);
                    })->whereOr(function ($sq) use ($uid) {
                        $sq->where('d.uid_b', $uid)->where('d.relation_type', 2);
                    });
                });
                break;
            case 'merchant':
            case 'system':
                $dialogQuery->where('d.dialog_id', '-1');
                break;
        }

        $dialogQuery->where(function ($q) use ($uid) {
            $q->whereRaw('NOT (d.uid_a = ? AND d.is_black_b = 1)', [$uid]);
            $q->whereRaw('NOT (d.uid_b = ? AND d.is_black_a = 1)', [$uid]);
        });
        $dialogQuery->where(function ($q) use ($uid) {
            $q->whereRaw('NOT (d.uid_a = ? AND d.is_clear_a = 1)', [$uid]);
            $q->whereRaw('NOT (d.uid_b = ? AND d.is_clear_b = 1)', [$uid]);
        });

        $userCount = $dialogQuery->count();
        $list = $dialogQuery->order('d.last_message_time', 'desc')
            ->page($page, $limit)->select()->toArray();

        $chatUids = [];
        foreach ($list as $item) {
            $chatUids[] = ($item['uid_a'] == $uid) ? $item['uid_b'] : $item['uid_a'];
        }
        $userMap = [];
        if ($chatUids) {
            $rows = Db::name('user')->field('uid,nickname,avatar')
                ->whereIn('uid', $chatUids)->select()->toArray();
            foreach ($rows as $r) {
                $userMap[$r['uid']] = $r;
            }
        }

        $onlineMap = [];
        if ($chatUids) {
            $keys = array_map(fn($u) => 'online:' . $u, $chatUids);
            try {
                $vals = Cache::store('redis')->handler()->mget($keys);
                if ($vals) {
                    foreach ($chatUids as $i => $u) {
                        $onlineMap[$u] = !empty($vals[$i]) ? 1 : 0;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $result = [];
        $typeDesc = [1 => '', 2 => '[表情]', 3 => '[图片]', 9 => '[语音]', 100 => ''];
        foreach ($list as $item) {
            $chatUid = ($item['uid_a'] == $uid) ? $item['uid_b'] : $item['uid_a'];
            $isMeA = ($item['uid_a'] == $uid);
            $userInfo = $userMap[$chatUid] ?? null;
            if (!$userInfo) {
                continue;
            }

            $myRelation = $this->resolveRelation($item, $isMeA);

            $lastMsg = $item['last_message'] ?? '';
            if ($lastMsg && !in_array($item['last_message_type'], [1])) {
                $lastMsg = $item['last_message_type'] == 100 ? '' : ($typeDesc[$item['last_message_type']] ?? '');
            }

            $result[] = [
                'dialog_id'         => $item['dialog_id'],
                'type'              => 'user',
                'chat_uid'          => $chatUid,
                'avatar'            => $userInfo['avatar'] ?? '',
                'nickname'          => $userInfo['nickname'] ?? '',
                'online_status'     => $onlineMap[$chatUid] ?? 0,
                'last_message'      => $lastMsg,
                'last_message_type' => $item['last_message_type'],
                'last_message_time' => $item['last_message_time'],
                'unread_count'      => $isMeA ? $item['unread_a'] : $item['unread_b'],
                'relation_type'     => $myRelation,
                'is_blacked'        => $isMeA ? $item['is_black_b'] : $item['is_black_a'],
            ];
        }

        $count = $userCount;

        if ($type === 'all' || $type === 'merchant') {
            try {
                $merchantData = app()->make(StoreServiceUserRepository::class)
                    ->userMerchantList($uid, $page, $limit);

                $svcTypeDesc = [
                    1 => '', 2 => '[表情]', 3 => '[图片]', 4 => '[商品]', 5 => '[订单]',
                    6 => '[退款]', 7 => '[预售]', 8 => '[拼团]', 9 => '[语音]', 100 => '',
                ];
                foreach ($merchantData['list'] as $svc) {
                    $mer = $svc['merchant'] ?? [];
                    $last = $svc['last'] ?? [];
                    $msgType = (int)($last['msn_type'] ?? 1);
                    $lastMsg = $last['msn'] ?? '';
                    if ($lastMsg && !in_array($msgType, [1, 100], true)) {
                        $lastMsg = $svcTypeDesc[$msgType] ?? '';
                    }

                    $result[] = [
                        'dialog_id'         => 0,
                        'type'              => 'merchant',
                        'chat_uid'          => 0,
                        'mer_id'            => (int)$svc['mer_id'],
                        'avatar'            => $mer['mer_avatar'] ?? '',
                        'nickname'          => $mer['mer_name'] ?? '客服',
                        'online_status'     => 0,
                        'last_message'      => $lastMsg,
                        'last_message_type' => $msgType,
                        'last_message_time' => $svc['last_time'] ?? '',
                        'unread_count'      => (int)($svc['num'] ?? $svc['user_unread'] ?? 0),
                        'relation_type'     => 0,
                        'is_blacked'        => 0,
                    ];
                }

                usort($result, fn($a, $b) =>
                    strtotime($b['last_message_time'] ?? '2000-01-01')
                    <=> strtotime($a['last_message_time'] ?? '2000-01-01')
                );
                $result = array_slice($result, 0, $limit);
                $count = $userCount + (int)($merchantData['count'] ?? 0);
            } catch (\Throwable $e) {
                Log::warning('message dialog merchant list failed: ' . $e->getMessage());
            }
        }

        return compact('count') + ['list' => $result];
    }

    private function resolveRelation(array $row, bool $isMeA): int
    {
        if ($row['relation_type'] == 1) {
            return 1;
        }
        if ($isMeA) {
            if ($row['relation_type'] == 2) {
                return 2;
            }
            if ($row['relation_type'] == 3) {
                return 3;
            }
        } else {
            if ($row['relation_type'] == 2) {
                return 3;
            }
            if ($row['relation_type'] == 3) {
                return 2;
            }
        }
        return 0;
    }

    public function getChatUser(int $dialogId, int $myUid): array
    {
        $dialog = Db::name('user_dialog')->where('dialog_id', $dialogId)->find();
        if (!$dialog) {
            return [];
        }

        $chatUid = ($dialog['uid_a'] == $myUid) ? $dialog['uid_b'] : $dialog['uid_a'];

        $user = Db::name('user')->field('uid,nickname,avatar')->where('uid', $chatUid)->find();
        if (!$user) {
            return [];
        }

        $myRelation = $this->resolveRelation($dialog, ($dialog['uid_a'] == $myUid));
        try {
            $online = Cache::store('redis')->get('online:' . $chatUid) ? 1 : 0;
        } catch (\Throwable $e) {
            $online = 0;
        }

        return [
            'uid'           => $user['uid'],
            'nickname'      => $user['nickname'],
            'avatar'        => $user['avatar'],
            'relation_type' => $myRelation,
            'online_status' => $online,
        ];
    }
}
