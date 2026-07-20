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
use app\common\model\community\CommunityRecruit;

class CommunityRecruitDao extends BaseDao
{
    protected function getModel(): string
    {
        return CommunityRecruit::class;
    }

    public function search(array $where)
    {
        return CommunityRecruit::getDB()
            ->when(isset($where['community_id']) && $where['community_id'] !== '', function ($query) use ($where) {
                $query->where('community_id', $where['community_id']);
            })
            ->when(isset($where['mer_uid']) && $where['mer_uid'] !== '', function ($query) use ($where) {
                $query->where('mer_uid', $where['mer_uid']);
            })
            ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                $query->where('status', $where['status']);
            });
    }
}
