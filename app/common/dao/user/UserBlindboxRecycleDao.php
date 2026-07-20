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
use app\common\model\user\UserBlindboxRecycle;
use app\common\model\BaseModel;

class UserBlindboxRecycleDao extends BaseDao
{

    protected function getModel(): string
    {
        return UserBlindboxRecycle::class;
    }

    /**
     * 搜索条件
     * @param array $where
     * @return BaseModel
     */
    public function search(array $where)
    {
        return UserBlindboxRecycle::getDB()->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
            $query->where('uid', $where['uid']);
        })->when(isset($where['product_id']) && $where['product_id'] !== '', function ($query) use ($where) {
            $query->where('product_id', $where['product_id']);
        })->when(isset($where['cabinet_id']) && $where['cabinet_id'] !== '', function ($query) use ($where) {
            $query->where('cabinet_id', $where['cabinet_id']);
        })->when(isset($where['reward_type']) && $where['reward_type'] !== '' && $where['reward_type'] !== null, function ($query) use ($where) {
            $query->where('reward_type', $where['reward_type']);
        })->when(isset($where['mer_id']) && $where['mer_id'], function ($query) use ($where) {
            $query->where('mer_id', $where['mer_id']);
        })->when(isset($where['date_range']) && $where['date_range'], function ($query) use ($where) {
            [$start, $end] = explode('|', $where['date_range']);
            $query->where('create_time', '>=', $start)->where('create_time', '<', date('Y-m-d', strtotime($end . ' +1 day')));
        })->when(isset($where['day']), function ($query) use ($where) {
            getModelTime($query, $where, 'create_time');
        });
    }
}
