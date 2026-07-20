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

namespace app\common\dao\store;

use app\common\dao\BaseDao;
use app\common\model\store\StoreBrandCategory as model;
use crmeb\traits\CategoresDao;

class StoreBrandCategoryDao extends BaseDao
{

    use CategoresDao;

    protected function getModel(): string
    {
        return model::class;
    }
    public function getMaxLevel()
    {
        return 2;
    }

    /**
     * 获取所有符合条件的数据
     *
     * 此方法用于查询数据库中所有满足指定条件的数据。它允许指定商家ID和状态来过滤结果。
     * 默认情况下，它将返回所有商家ID为0且状态为任意值的数据。可以通过传递不同的参数来修改查询条件。
     *
     * @param int $mer_id 商家ID，用于指定查询特定商家的数据。默认为0，表示查询所有商家。
     * @param int|null $status 数据的状态，用于指定查询特定状态的数据。如果为null，则不按状态过滤。
     * @return array 返回查询结果的数组，包含所有符合条件的数据。
     */
    public function getAll($mer_id = 0,$status = null)
    {
        // 获取模型实例并链式调用数据库查询方法
        return $this->getModel()::getDB()->when(($status !== null),function($query)use($status){
            // 如果指定了状态，则在查询中添加状态过滤条件
            $query->where($this->getStatus(),$status);
        })->order('sort DESC')->select();
    }


    /**
     * 检查指定字段在指定条件下的存在性。
     *
     * 该方法用于确定数据库中是否存在满足特定条件的记录。
     * 具体来说，它检查给定字段的值是否在数据库中唯一存在，
     * 可以通过排除特定值来细化查询条件。
     *
     * @param int $merId 商户ID，用于限定查询的范围。
     * @param string $field 要检查的字段名。
     * @param mixed $value 字段应该具有的值。
     * @param mixed $except [可选]需要排除的值，防止查询到该值。
     * @return bool 如果存在满足条件的记录，则返回true；否则返回false。
     */
    public function merFieldExists(int $merId, $field, $value, $except = null)
    {
        // 获取数据库实例
        return ($this->getModel())::getDB()
                // 当$except有值时，添加排除条件
                ->when($except, function ($query, $except) use ($field) {
                    $query->where($field, '<>', $except);
                })
                // 检查字段值是否存在
                ->where($field, $value)->count() > 0;
    }


    /**
     * 根据指定字段和值获取所有不包括特定ID的数据
     *
     * 此方法用于从数据库中检索所有符合特定字段值条件的记录，
     * 可选地，可以排除某些特定的ID。这在需要获取满足某一条件的
     * 所有数据，但又需要排除某些特定数据的情况下非常有用。
     *
     * @param string $field 要查询的字段名
     * @param mixed $value 字段应该等于的值
     * @param mixed $except 可选参数，指定需要排除的ID
     * @return \Illuminate\Database\Eloquent\Collection|static[] 返回符合查询条件的数据集合
     */
    public function getAllByField( $field, $value, $except = null)
    {
        // 获取数据库实例
        return ($this->getModel())::getDB()
                // 如果提供了$except参数，则添加一个排除条件
                ->when($except, function ($query, $except) use ($field) {
                    // 对应于排除条件的查询语句
                    $query->where($field, '<>', $except);
                })
                // 添加主要的查询条件
                ->where($field, $value);
    }

    /**
     * 获取展示状态为启用的分类选项
     *
     * 本函数用于查询数据库中is_show字段为1的记录，这些记录代表了需要在前端展示的分类。
     * 查询结果按照sort字段降序排序，并以store_brand_category_id为键，pid和cate_name为值返回结果集。
     * 这样处理的目的是为了在前端形成一个易于使用的分类选项列表，列表中的每个项包含了分类的ID、父ID和名称。
     *
     * @return array 返回一个数组，数组的键是store_brand_category_id，值是一个包含pid和cate_name的数组。
     */
    public function options()
    {
        // 查询数据库中is_show为1的记录，按照sort降序排序，返回pid和cate_name列
        return model::getDB()->where('is_show', 1)->order('sort DESC')->column('pid,cate_name', 'store_brand_category_id');
    }
}
