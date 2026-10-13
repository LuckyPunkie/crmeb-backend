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

namespace app\controller\api\store\product;

use app\common\repositories\user\UserRepository;
use app\common\repositories\system\merchant\StoreGroupRepository;
use app\common\repositories\system\RelevanceRepository;

trait BindSpreadTrait
{
    protected function bindSpread($userInfo = null)
    {
        $pid = $this->request->param('pid');
        if(!$userInfo || !$pid) {
            return false;
        }

        return app()->make(UserRepository::class)->bindSpread($userInfo, intval($pid));
    }

    protected function getMerIdsByRegin($regionId)
    {
        // 获取区域下的所有门店分组ID
        $stroeGroupIds = app()->make(StoreGroupRepository::class)
            ->search([
                'path' => $regionId,
                'status' => 1,
            ])->column('store_group_id');
        array_push($stroeGroupIds, $regionId);
        // 获取门店分组下关联的店铺id
        $merIds = app()->make(RelevanceRepository::class)->getSearch([])
            ->whereIn('left_id', $stroeGroupIds)
            ->where('type', RelevanceRepository::STORE_GROUP)
            ->column('right_id');
        $merIds = array_unique($merIds);

        return $merIds;
    }
}
