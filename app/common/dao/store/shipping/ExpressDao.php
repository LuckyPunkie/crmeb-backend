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

namespace app\common\dao\store\shipping;

use app\common\dao\BaseDao;
use app\common\model\store\shipping\Express as model;

class ExpressDao  extends BaseDao
{
    /**
     * @Author:Qinii
     * @Date: 2020/5/13
     * @return string
     */
    protected function getModel(): string
    {
        return model::class;
    }

    /**
     * 检查指定字段的值是否已存在，可排除特定的值或ID。
     *
     * 此函数用于查询数据库中是否存在指定字段的值，同时允许排除特定的值或ID，
     * 以及限定只查询显示状态为是的记录。这在处理数据唯一性验证或存在性检查时非常有用。
     *
     * @param string $field 要检查的字段名。
     * @param mixed $value 要检查的字段值。
     * @param mixed $except 排除的值，如果不为空，则查询时不包括此值。
     * @param mixed $id 排除的ID，如果不为空，则查询时不包括此ID的记录。
     * @param bool $isUser 是否只查询显示状态为是的记录，true表示只查询显示状态为是的记录，false或null表示不作此限制。
     * @return bool 如果存在返回true，否则返回false。
     */
    public function merFieldExists($field, $value, $except = null, $id = null, $isUser = null)
    {
        return ($this->getModel())::getDB()->when($except, function ($query, $except) use ($field) {
                $query->where($field, '<>', $except);
            })->when($id, function ($query) use ($id) {
                $query->where($this->getPk(), '<>', $id);
            })->when($isUser, function ($query) {
                $query->where('is_show', 1);
            })->where($field, $value)->count() > 0;
    }

    /**
     * 根据条件搜索数据。
     *
     * 本函数用于根据提供的条件数组搜索相应的数据。支持的条件包括关键字、代码、显示状态和ID。
     * 搜索条件是可选的，只有在数组中提供了相应的键值对时，才会应用相应的过滤条件。
     *
     * @param array $where 搜索条件数组，包含可能的键：keyword（关键字）、code（代码）、is_show（显示状态）、id（ID）。
     * @return \Illuminate\Database\Eloquent\Builder|static 返回一个构建器对象，已应用搜索条件和排序。
     */
    public function search(array $where)
    {
        // 从模型获取数据库实例，并根据提供的条件应用相应的查询约束。
        $query = ($this->getModel()::getDB())
            // 如果提供了关键字，则在名称或代码字段中进行模糊搜索。
            ->when(isset($where['keyword']) && $where['keyword'], function ($query) use ($where) {
                $query->where('name|code', 'like', '%'.$where['keyword'].'%');
            })
            // 如果提供了代码，则精确匹配代码字段。
            ->where(isset($where['code']) && $where['code'], function ($query) use ($where) {
                $query->where('code', $where['code']);
            })
            // 如果提供了显示状态，则精确匹配显示状态字段。
            ->where(isset($where['is_show']) && $where['is_show'], function ($query) use ($where) {
                $query->where('is_show', $where['is_show']);
            })
            // 如果提供了ID，则精确匹配ID字段。
            ->where(isset($where['id']) && $where['id'], function ($query) use ($where) {
                $query->where('id', $where['id']);
            });

        // 返回应用了排序（按排序降序）的查询构建器。
        return $query->order('sort DESC');
    }

    public function options($merId, $where, $field = 'id,name')
    {
        $query = $this->getModel()::getDB()->where($where)->field($field);

        if($merId) {
            $query->whereFindInSet('open_mer', $merId);
        }

        return $query->select()->toArray();
    }

}
