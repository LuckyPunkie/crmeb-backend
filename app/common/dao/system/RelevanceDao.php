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


namespace app\common\dao\system;

use app\common\dao\BaseDao;
use app\common\model\system\Relevance;
use app\common\repositories\system\RelevanceRepository;

class RelevanceDao extends BaseDao
{

    protected function getModel(): string
    {
        return Relevance::class;
    }

    /**
     * 清空特定字段中指定ID和类型的记录。
     *
     * 此方法用于根据给定的ID和类型，从数据库中删除满足条件的记录。
     * 主要用于需要清理或重置数据库中某些特定类型数据的场景。
     *
     * @param int $id 需要清理的记录的ID。
     * @param mixed $type 需要清理的记录的类型，可以是单个类型名称或类型名称的数组。
     * @param string $field 指定的字段名称，默认为'left_id'，表示根据该字段进行查询和删除操作。
     * @return int 返回删除操作影响的行数。
     */
    public function clear(int $id, $type, string $field = 'left_id')
    {
        // 如果$type是字符串，则将其转换为数组，以支持多个类型同时清理。
        if (is_string($type)) $type = [$type];

        // 调用getModel方法获取模型实例，并通过该实例的getDb方法获取数据库连接。
        // 然后使用where和whereIn方法构建查询条件，最后执行删除操作并返回结果。
        return $this->getModel()::getDb()->where($field, $id)->whereIn('type', $type)->delete();
    }


    /**
     * 根据条件查询用户加入的社区
     *
     * 本函数用于构建一个查询用户所加入社区的查询语句。它首先筛选出状态为正常、显示为是、未删除的社区，
     * 然后根据用户ID和特定的类型条件来进一步限制查询结果。
     *
     * @param array $where 查询条件数组
     * @return \Illuminate\Database\Eloquent\Builder|static 返回构建好的查询构建器实例
     */
    public function joinUser($where)
    {
        // 初始化查询社区的构建器，并设置基本的查询条件
        $query = Relevance::hasWhere('community',function($query) use($where){
            // 筛选状态正常、显示为是、未删除的社区
            $query->where('status',1)->where('is_show',1)->where('is_del',0);
            // 如果条件中指定了社区类型，则进一步筛选特定类型的社区
            $query->when(isset($where['is_type']) && $where['is_type'] !== '',function($query) use($where){
                $query->where('is_type',$where['is_type']);
            });
        });
        // 设置查询条件，筛选出与用户ID关联且类型符合的关联记录
        $query->where('left_id',$where['uid'])->where('type',RelevanceRepository::TYPE_COMMUNITY_START);
        // 返回构建好的查询构建器
        return $query;
    }

}
