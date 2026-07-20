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
use app\common\model\user\LabelRule;

class LabelRuleDao extends BaseDao
{

    protected function getModel(): string
    {
        return LabelRule::class;
    }

    /**
     * 根据条件搜索标签规则。
     *
     * 该方法提供了一个灵活的方式来根据不同的条件搜索标签规则。它支持搜索关键字、类型和商家ID作为过滤条件。
     * 使用Laravel的查询构建器来构造查询，利用when方法根据条件动态添加where子句到查询中。
     *
     * @param array $where 包含搜索条件的数组，可能包含关键字、类型和商家ID。
     * @return \Illuminate\Database\Eloquent\Builder 返回一个构建好的查询构建器实例，用于进一步的查询或获取结果。
     */
    public function search(array $where)
    {
        // 初始化标签规则查询，确保查询包含至少一个标签存在条件
        return LabelRule::hasWhere('label')->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
            // 如果提供了关键字，则添加一个like条件来搜索标签名称
            $query->whereLike('UserLabel.label_name', "%{$where['keyword']}%");
        })->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
            // 如果提供了类型，则添加一个等于条件来筛选标签类型
            $query->where('LabelRule.type', intval($where['type']));
        })->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            // 如果提供了商家ID，则添加一个等于条件来筛选特定商家的标签规则
            $query->where('LabelRule.mer_id', intval($where['mer_id']));
        });
    }
}
