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
use app\common\model\animal_rescue\AnimalRescuePost;

/**
 * 救助帖子 DAO
 * Class AnimalRescuePostDao
 * @package app\common\dao\animal_rescue
 */
class AnimalRescuePostDao extends BaseDao
{
    /**
     * @return string
     */
    protected function getModel(): string
    {
        return AnimalRescuePost::class;
    }

    /**
     * 搜索帖子
     * @param array $where
     * @return \think\db\BaseQuery
     */
    public function search(array $where)
    {
        $query = AnimalRescuePost::getDB();
        $query->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
            $query->whereLike('title|content|animal_name', "%{$where['keyword']}%");
        })
        ->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
            $query->where('type', $where['type']);
        })
        ->when(isset($where['city_id']) && $where['city_id'] !== '', function ($query) use ($where) {
            $query->where('city_id', $where['city_id']);
        })
        ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            if (is_array($where['status'])) {
                $query->whereIn('status', $where['status']);
            } else {
                $query->where('status', $where['status']);
            }
        })
        ->when(isset($where['is_show']) && $where['is_show'] !== '', function ($query) use ($where) {
            $query->where('is_show', $where['is_show']);
        })
        ->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
            $query->where('uid', $where['uid']);
        })
        ->when(isset($where['animal_type']) && $where['animal_type'] !== '', function ($query) use ($where) {
            $query->where('animal_type', $where['animal_type']);
        })
        ->when(isset($where['is_del']) && $where['is_del'] !== '', function ($query) use ($where) {
            $query->where('is_del', $where['is_del']);
        })
        ->when(isset($where['post_id']) && $where['post_id'] !== '', function ($query) use ($where) {
            $query->where('post_id', $where['post_id']);
        })
        ->when(isset($where['fund_status']) && $where['fund_status'] !== '', function ($query) use ($where) {
            $query->where('fund_status', $where['fund_status']);
        })
        ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            $query->where('mer_id', $where['mer_id']);
        });
        $query->order('create_time DESC');
        return $query;
    }

    /**
     * 查询帖子是否存在
     */
    public function exists(int $id): bool
    {
        return $this->getModel()::getDB()->where('is_del', 0)->where($this->getPk(), $id)->count() > 0;
    }

    /**
     * 检查帖子是否属于某用户
     */
    public function uidExists(int $id, int $uid): bool
    {
        return $this->getModel()::getDB()->where('uid', $uid)->where($this->getPk(), $id)->count() > 0;
    }

    /**
     * 获取各类型帖子数量
     */
    public function getCategoryCount(): array
    {
        $data = $this->getModel()::getDB()
            ->where('is_del', 0)
            ->where('is_show', 1)
            ->where('status', 1)
            ->field('type, count(post_id) as count')
            ->group('type')
            ->select()
            ->toArray();
        $result = ['total' => 0, 'rescue' => 0, 'adoption' => 0, 'cloud' => 0];
        foreach ($data as $item) {
            $result['total'] += $item['count'];
            if ($item['type'] == 1) $result['rescue'] = $item['count'];
            if ($item['type'] == 2) $result['adoption'] = $item['count'];
            if ($item['type'] == 3) $result['cloud'] = $item['count'];
        }
        return $result;
    }
}
