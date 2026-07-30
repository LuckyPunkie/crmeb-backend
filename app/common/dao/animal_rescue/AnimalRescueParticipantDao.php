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
use app\common\model\animal_rescue\AnimalRescueParticipant;

/**
 * 参与记录 DAO
 * Class AnimalRescueParticipantDao
 * @package app\common\dao\animal_rescue
 */
class AnimalRescueParticipantDao extends BaseDao
{
    /**
     * @return string
     */
    protected function getModel(): string
    {
        return AnimalRescueParticipant::class;
    }

    /**
     * 搜索参与记录
     * @param array $where
     * @return \think\db\BaseQuery
     */
    public function search(array $where)
    {
        $query = AnimalRescueParticipant::getDB();
        $query->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
            $query->where('uid', $where['uid']);
        })
        ->when(isset($where['post_id']) && $where['post_id'] !== '', function ($query) use ($where) {
            $query->where('post_id', $where['post_id']);
        })
        ->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
            $query->where('type', $where['type']);
        })
        ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            $query->where('status', $where['status']);
        });
        $query->order('create_time DESC');
        return $query;
    }
}
