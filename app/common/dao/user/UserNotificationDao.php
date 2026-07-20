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

namespace app\common\dao\user;

use app\common\dao\BaseDao;
use app\common\model\user\UserNotification;

class UserNotificationDao extends BaseDao
{
    protected function getModel(): string
    {
        return UserNotification::class;
    }

    public function getList(int $uid, int $page, int $limit, ?string $type = null)
    {
        $query = $this->getModel()::withSearch(['uid'], ['uid' => $uid]);
        if ($type) {
            $query = $query->withSearch(['type'], ['type' => $type]);
        }
        return $query->with(['fromUser'])->order('create_time', 'desc')->page($page, $limit)->select();
    }

    public function getCount(int $uid, ?string $type = null): int
    {
        $query = $this->getModel()::where('uid', $uid);
        if ($type) {
            $query->where('type', $type);
        }
        return (int)$query->count();
    }

    public function getUnreadCount(int $uid): int
    {
        return $this->getModel()::where('uid', $uid)->where('is_read', 0)->count();
    }

    public function markRead(int $notificationId, int $uid): int
    {
        return $this->getModel()::where('notification_id', $notificationId)
            ->where('uid', $uid)
            ->update(['is_read' => 1, 'read_time' => date('Y-m-d H:i:s')]);
    }
}
