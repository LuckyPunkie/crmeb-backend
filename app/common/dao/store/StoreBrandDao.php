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
use app\common\model\store\StoreBrand as model;
use crmeb\traits\CategoresDao;

class StoreBrandDao extends BaseDao
{

    use CategoresDao;

    protected function getModel(): string
    {
        return model::class;
    }


    /**
     * 获取所有品牌信息，包括品牌分类和品牌详情。
     * 此方法专门设计用于获取品牌列表，列表中包括一个特殊的项代表“其他”品牌。
     *
     * @return array 返回一个包含所有品牌信息的数组，其中最后一个元素是特殊的“其他”品牌项。
     */
    public function getAll()
    {
        // 根据品牌分类中的"is_show"字段为1来筛选品牌分类
        $query = $this->getModel()::hasWhere('brandCategory',function($query){
            $query->where('is_show',1);
        });
        // 筛选品牌表中"is_show"字段为1的品牌
        $query->where('is_show',1);
        // 按品牌的排序和创建时间降序排列，并获取所有符合条件的品牌数据
        $list = $query->order('sort DESC,create_time DESC')->select()->toArray();

        // 向品牌列表中添加一个特殊的“其他”品牌项
        array_push($list,[
            "brand_id" => 0,
            "brand_category_id" => 0,
            "brand_name" => "其他",
            "sort" => 999,
            "pic" => "",
            "is_show" => 1,
            "create_time" => "",
        ]);

        // 返回处理后的品牌列表
        return $list;
    }



    /**
     * 检查指定字段是否存在特定值。
     *
     * 该方法用于查询数据库中指定字段的值是否已存在。它支持排除特定值的查询，
     * 这使得可以在检查存在性时忽略特定的记录。
     *
     * @param string $field 要查询的字段名。
     * @param mixed $value 要查询的字段值。
     * @param mixed $except 可选参数，用于指定需要排除的值。
     * @return bool 如果找到匹配的记录，则返回true；否则返回false。
     */
    public function merFieldExists($field, $value, $except = null)
    {
        // 从模型中获取数据库实例。
        return ($this->getModel())::getDB()
                // 当$except有值时，添加一个不等于($field, '<>')的查询条件。
                ->when($except, function ($query, $except) use ($field) {
                    $query->where($field, '<>', $except);
                })
                // 添加等于($field, $value)的查询条件。
                ->where($field, $value)
                // 统计符合条件的记录数，如果大于0，则表示存在。
                ->count() > 0;
    }


    /**
     * 根据条件搜索品牌信息
     *
     * 本函数用于根据传入的条件数组搜索品牌的数据库记录。条件包括品牌分类ID、品牌名称和品牌ID列表。
     * 查询结果将按照排序和创建时间倒序返回。
     *
     * @param array $where 搜索条件数组，包含品牌分类ID、品牌名称和品牌ID列表等条件。
     * @return \think\db\Query 查询结果的查询对象，可用于进一步的查询操作或获取结果。
     */
    public function search(array $where)
    {
        // 获取数据库查询对象
        $query = $this->getModel()::getDB();

        // 如果条件数组中包含品牌分类ID，并且该ID不为空，则添加到查询条件中
        if(isset($where['brand_category_id']) && $where['brand_category_id'])
            $query->where('brand_category_id',$where['brand_category_id']);

        // 如果条件数组中包含品牌名称，并且该名称不为空，则添加到查询条件中，使用LIKE进行模糊匹配
        if(isset($where['brand_name']) && $where['brand_name'])
            $query->where('brand_name','like','%'.$where['brand_name'].'%');

        // 如果条件数组中包含品牌ID列表，并且该列表不为空，则添加到查询条件中，查询包含在列表中的品牌
        if((isset($where['ids']) && $where['ids']))
            $query->where($this->getPk(),'in',$where['ids']);

        // 对查询结果按照排序和创建时间进行倒序排序
        return $query->order('sort DESC,create_time desc');
    }

}
