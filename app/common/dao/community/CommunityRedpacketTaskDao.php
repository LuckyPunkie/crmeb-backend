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

namespace app\common\dao\community;

use app\common\dao\BaseDao;
use app\common\model\community\CommunityRedpacketTask;

class CommunityRedpacketTaskDao extends BaseDao
{
    protected function getModel(): string
    {
        return CommunityRedpacketTask::class;
    }

    public function search(array $where)
    {
        return CommunityRedpacketTask::getDB()
            ->when(isset($where['redpacket_id']) && $where['redpacket_id'] !== '', function ($query) use ($where) {
                $query->where('redpacket_id', $where['redpacket_id']);
            })
            ->when(isset($where['community_id']) && $where['community_id'] !== '', function ($query) use ($where) {
                $query->where('community_id', $where['community_id']);
            })
            ->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
                $query->where('uid', $where['uid']);
            })
            ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                $query->where('status', $where['status']);
            });
    }

    public function uidExists(int $redpacketId, int $uid)
    {
        return $this->getModel()::getDB()->where('redpacket_id', $redpacketId)->where('uid', $uid)->count() > 0;
    }
}
