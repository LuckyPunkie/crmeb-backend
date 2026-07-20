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
use app\common\model\user\UserMessage;

class UserMessageDao extends BaseDao
{
    protected function getModel(): string
    {
        return UserMessage::class;
    }

    public function getHistory(int $dialogId, int $page, int $limit)
    {
        return $this->getModel()::withSearch(['dialog_id'], ['dialog_id' => $dialogId])
            ->order('create_time', 'desc')
            ->page($page, $limit)
            ->select();
    }

    public function markAsRead(int $dialogId, int $toUid)
    {
        return $this->getModel()::where('dialog_id', $dialogId)
            ->where('to_uid', $toUid)
            ->where('is_read', 0)
            ->update(['is_read' => 1]);
    }

    public function getUnrepliedCount(int $fromUid, int $toUid): int
    {
        return $this->getModel()::where('from_uid', $fromUid)
            ->where('to_uid', $toUid)
            ->where('is_read', 0)
            ->count();
    }
}
