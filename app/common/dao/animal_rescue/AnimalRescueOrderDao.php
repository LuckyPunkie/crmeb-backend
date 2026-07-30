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

namespace app\common\dao\animal_rescue;

use app\common\dao\BaseDao;
use app\common\model\animal_rescue\AnimalRescueOrder;

/**
 * 救助订单 DAO
 * Class AnimalRescueOrderDao
 * @package app\common\dao\animal_rescue
 */
class AnimalRescueOrderDao extends BaseDao
{
    /**
     * @return string
     */
    protected function getModel(): string
    {
        return AnimalRescueOrder::class;
    }

    /**
     * 搜索订单
     * @param array $where
     * @return \think\db\BaseQuery
     */
    public function search(array $where)
    {
        $query = AnimalRescueOrder::getDB();
        $query->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
            $query->where('uid', $where['uid']);
        })
        ->when(isset($where['post_id']) && $where['post_id'] !== '', function ($query) use ($where) {
            $query->where('post_id', $where['post_id']);
        })
        ->when(isset($where['paid']) && $where['paid'] !== '', function ($query) use ($where) {
            $query->where('paid', $where['paid']);
        })
        ->when(isset($where['order_sn']) && $where['order_sn'] !== '', function ($query) use ($where) {
            $query->where('order_sn', $where['order_sn']);
        });
        $query->order('create_time DESC');
        return $query;
    }
}
