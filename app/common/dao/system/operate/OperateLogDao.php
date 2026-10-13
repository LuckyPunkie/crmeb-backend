<?php

namespace app\common\dao\system\operate;

use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\operate\OperateLog;

class OperateLogDao extends BaseDao
{
    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return OperateLog::class;
    }

    /**
     * 根据条件搜索数据。
     *
     * 该方法通过传入的$where数组条件来查询数据库。它支持多种条件组合查询，包括类型、类别、相关性标题、操作者昵称、商家ID、时间范围、相关性ID和相关性类型等。
     * 每个条件都是可选的，只有当条件的值被设置且不为空时，才会添加到查询中。
     *
     * @param array $where 查询条件数组，包含各种可能的搜索条件。
     * @return \yii\db\ActiveQuery 返回构建的查询对象，可以进一步调用其他查询方法，如获取数据等。
     */
    public function search($where)
    {
        // 获取模型对应的数据库对象
        return $this->getModel()::getDb()
            // 当类型条件存在且不为空时，添加到查询中
            ->when(isset($where['type']) && $where['type'] != '', function ($query) use ($where) {
                $query->where('type', $where['type']);
            })
            // 当类别条件存在且不为空时，添加到查询中
            ->when(isset($where['category']) && $where['category'] != '', function ($query) use ($where) {
                $query->where('category', $where['category']);
            })
            // 当相关性标题条件存在且不为空时，以LIKE形式添加到查询中
            ->when(isset($where['relevance_title']) && $where['relevance_title'] != '', function ($query) use ($where) {
                $query->whereLike('relevance_title', "%{$where['relevance_title']}%");
            })
            // 当操作者昵称条件存在且不为空时，以LIKE形式添加到查询中
            ->when(isset($where['operator_nickname']) && $where['operator_nickname'] != '', function ($query) use ($where) {
                $query->whereLike('operator_nickname', "%{$where['operator_nickname']}%");
            })
            // 当商家ID条件存在且不为空时，添加到查询中
            ->when(isset($where['mer_id']) && $where['mer_id'] != '', function ($query) use ($where) {
                $query->where('mer_id', $where['mer_id']);
            })
            // 当日期条件存在且不为空时，调用外部函数处理时间范围查询
            ->when(isset($where['date']) && $where['date'] != '', function ($query) use ($where) {
                getModelTime($query, $where['date']);
            })
            // 当相关性ID条件存在且不为空时，添加到查询中
            ->when(isset($where['relevance_id']) && $where['relevance_id'] != '', function ($query) use ($where) {
                $query->where('relevance_id', $where['relevance_id']);
            })
            // 当相关性类型条件存在且不为空时，如果条件为数组，则使用whereIn查询，否则直接等于查询
            ->when(isset($where['relevance_type']) && $where['relevance_type'] != '', function ($query) use ($where) {
                if (is_array($where['relevance_type'])) {
                    $query->whereIn('relevance_id', $where['relevance_id']);
                } else {
                    $query->where('relevance_id', $where['relevance_id']);
                }
            })
            // 当相关性标题条件存在且不为空时，添加到查询中（此段代码可能为重复或错误，因前面已处理过相同条件）
            ->when(isset($where['relevance_title']) && $where['relevance_title'] != '', function ($query) use ($where) {
                $query->where('relevance_title', $where['relevance_title']);
            });
    }
}
