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
use app\common\model\community\CommunityResume;

class CommunityResumeDao extends BaseDao
{
    protected function getModel(): string
    {
        return CommunityResume::class;
    }

    public function search(array $where)
    {
        return CommunityResume::getDB()
            ->when(isset($where['id']) && $where['id'] !== '', function ($query) use ($where) {
                $query->where('id', $where['id']);
            })
            ->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
                $query->where('uid', $where['uid']);
            })
            ->when(isset($where['is_default']) && $where['is_default'] !== '', function ($query) use ($where) {
                $query->where('is_default', $where['is_default']);
            });
    }
}
