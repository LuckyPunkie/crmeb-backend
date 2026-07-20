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
use app\common\model\store\StoreCategory as model;
use crmeb\traits\CategoresDao;

class StoreCategoryDao extends BaseDao
{

    use CategoresDao;

    protected function getModel(): string
    {
        return model::class;
    }

    /**
     * 获取所有符合条件的数据
     *
     * 本函数用于查询数据库中满足特定条件的所有记录。它支持三个参数来过滤结果：
     * - $mer_id: 商户ID，用于查询特定商户的数据。默认值为0，表示查询所有商户的数据。
     * - $status: 状态，用于查询特定状态的记录。如果此参数不为null，则查询结果将限制在指定状态的记录。否则，查询结果不受状态限制。
     * - $type: 类型，用于查询特定类型的记录。默认值为0，表示查询所有类型的记录。
     *
     * 查询结果将按照'sort'字段降序和主键降序排序。最后，查询结果将附加一个'has_product'字段。
     *
     * @param int $mer_id 商户ID
     * @param null $status 状态值，用于筛选数据
     * @param int $type 类型值，用于筛选数据
     * @return \think\Collection 查询结果，是一个包含数据的集合
     */
    public function getAll($mer_id = 0,$status = null, $type = 0)
    {
        // 通过getModel方法获取模型实例，并调用其getDB方法来获取数据库对象
        return $this->getModel()::getDB()
            // 根据$mer_id和$type条件构建查询
            ->where('mer_id', $mer_id)
            ->where('type',$type)
            // 如果$status不为null，则添加额外的状态条件
            ->when(($status !== null),function($query)use($status){
                $query->where($this->getStatus(),$status);
            })
            // 按照排序字段和主键降序排序
            ->order('sort DESC,'.$this->getPk().' DESC')
            // 执行查询并返回结果，附加'has_product'字段
            ->select()
            ->append(['has_product']);
    }


    /**
     * 查找给定ID的子分类ID列表
     *
     * 本函数通过查询数据库，找出路径中包含给定ID的分类记录，
     * 并返回这些记录的store_category_id列作为子分类ID的列表。
     * 使用了like查询来匹配路径中包含指定ID的情况，确保了灵活性。
     *
     * @param int $id 分类的唯一标识ID
     * @return array 包含子分类ID的数组
     */
    public function findChildrenId($id)
    {
        // 使用模型的数据库访问方法，查询路径中包含给定ID的分类记录的store_category_id列
        return model::getDB()->whereLike('path', '%/'. $id . '/%')->column('store_category_id');
    }

    /**
     * 根据给定的ID数组，查询所有子分类的ID。
     *
     * 此方法用于从数据库中检索指定分类ID的所有子分类ID。它通过检查每个给定ID的路径中是否包含该ID来确定子分类。
     * 使用了数据库的whereOr和like操作来实现这一逻辑。
     *
     * @param array $ids 分类ID数组，用于查询子分类。
     * @return array 返回一个包含所有子分类ID的数组，如果输入ID数组为空或不是数组，则返回空数组。
     */
    public function selectChildrenId(array $ids)
    {
        // 检查输入的ID数组是否为空或不是数组，如果是，则直接返回空数组
        if (!is_array($ids) || empty($ids))  return [];

        // 构建查询，使用whereOr和like来查询所有路径中包含给定ID的分类
        $query = model::getDB()->where(function($query) use($ids){
            foreach ($ids as $id) {
                // 对每个ID，查询路径中包含该ID的所有分类
                $query->whereOr('path', 'like','%/'. $id . '/%');
            }
        });

        // 返回查询结果中store_category_id列的数组
        return $query->column('store_category_id');
    }


    /**
     * 检查指定字段的值是否存在，同时支持排除特定值和商家ID的条件筛选。
     *
     * 此函数用于查询数据库中是否存在指定字段的值，同时允许排除某些特定值，并根据需要筛选特定商家的数据。
     * 主要用于在进行数据操作前验证数据的唯一性或存在性，以避免重复或错误的数据插入。
     *
     * @param int|null $merId 商家ID，用于筛选特定商家的数据。如果为null，则不进行商家ID的筛选。
     * @param string $field 要检查的字段名。
     * @param mixed $value 要检查的字段值。
     * @param mixed|null $except 排除的特定值，如果为null，则不进行排除筛选。
     * @return bool 返回一个布尔值，表示指定字段的值是否存在（不考虑排除条件）。
     */
    public function fieldExistsList(?int $merId,$field,$value,$except = null)
    {
        // 获取数据库实例，并根据条件动态构建查询语句
        return ($this->getModel()::getDB())->when($except ,function($query)use($field,$except){
            // 如果存在需要排除的值，则添加不等于排除值的条件
            $query->where($field,'<>',$except);
        })->when(($merId !== null) ,function($query)use($merId){
            // 如果提供了商家ID，则添加商家ID的条件
            $query->where('mer_id',$merId);
        })->where($field,$value);

    }


    /**
     * 获取二级分类列表
     * 本函数用于查询并返回指定商户下的二级分类ID、分类名称和父分类ID。
     * 主要用于前端展示或进一步的数据处理，例如商品分类导航等。
     *
     * @param int $merId 商户ID，用于查询指定商户的分类数据。默认为0，表示查询所有商户的数据。
     * @return array 返回一个包含分类ID、分类名称和父分类ID的数组，每个元素代表一个二级分类。
     */
    public function getTwoLevel($merId = 0)
    {
        // 查询顶级分类的ID，这些分类属于指定的商户，且显示状态为开启，类型为0。
        $pid = model::getDB()->where('pid', 0)->where('is_show',1)->where('type',0)->where('mer_id', $merId)->order('sort DESC')->column('store_category_id');

        // 根据上一步查询得到的顶级分类ID，进一步查询其下的二级分类ID、分类名称和父分类ID。
        // 这里限制了查询结果的数量为20条，并按照排序降序返回。
        return model::getDB()->whereIn('pid', $pid)->where('is_show', 1)->where('mer_id', $merId)->limit(20)->order('sort DESC')->column('store_category_id,cate_name,pid');
    }

    /**
     * 获取指定父级ID和商户ID下的分类信息
     *
     * 此函数用于查询数据库中特定父分类ID和商户ID下的分类信息，并且只返回显示状态为正常的分类。
     * 结果按照排序降序返回分类的ID、名称和图片信息。
     *
     * @param integer $pid 父分类ID，用于指定查询的父分类。
     * @param integer $merId 商户ID，可选参数，默认为0，用于指定查询的商户分类。
     * @return array 返回一个包含分类ID、名称和图片的数组。
     */
    public function children($pid, $merId = 0)
    {
        // 通过模型获取数据库实例，然后进行条件查询：指定父ID、商户ID和显示状态为1的分类，按排序降序获取分类的ID、名称和图片信息。
        return model::getDB()->where('pid', $pid)->where('mer_id', $merId)->where('is_show', 1)->order('sort DESC')->column('store_category_id,cate_name,pic');
    }


    /**
     * 获取所有子分类的ID列表
     *
     * 本函数旨在通过给定的分类ID（可以是单个ID或ID数组），获取该分类及其所有子分类的ID列表。
     * 这对于需要对一系列分类进行操作，例如遍历或筛选，非常有用。
     *
     * @param int|array $id 分类ID，可以是单个ID或ID数组
     * @return array 包含所有子分类ID的数组，如果找不到任何子分类，则返回空数组
     */
    public function allChildren($id)
    {
        // 根据给定的分类ID（单个或数组），查询数据库中所有对应分类的路径
        $path = model::getDB()->where('store_category_id', is_array($id) ? 'IN' : '=', $id)->where('mer_id', 0)->column('path', 'store_category_id');

        // 如果查询结果为空，则直接返回空数组
        if (!count($path)) return [];

        // 继续查询所有路径包含之前查询到的分类路径的分类ID，并按排序降序排列
        // 这里使用了WHERE子句的回调功能来构建一个或多个LIKE条件，以匹配所有子分类的路径
        return model::getDB()->where(function ($query) use ($path) {
            foreach ($path as $k => $v) {
                $query->whereOr('path', 'LIKE', "$v$k/%");
            }
        })->where('mer_id', 0)->order('sort DESC')->column('store_category_id');
    }

    /**
     * 根据给定的一组子分类ID，获取所有相关分类的ID。
     * 此方法通过查询数据库，找出给定子分类ID列表中所有分类的路径，
     * 然后根据这些路径来获取所有父分类的ID。
     *
     * @param array $ids 子分类的ID列表
     * @return array 返回所有相关分类的ID列表
     */
    public function idsByAllChildren(array $ids)
    {
        // 查询数据库，找出所有属于给定子分类ID列表且mer_id为0的分类的路径
        $paths = model::getDB()->whereIn('store_category_id', $ids)->where('mer_id', 0)->column('path');

        // 如果没有找到任何路径，则直接返回空数组
        if (!count($paths)) return [];

        // 查询所有路径匹配且mer_id为0的分类ID，按排序降序排列
        // 这里使用了where函数内的循环，来构建一个或多个path匹配的条件
        return model::getDB()->where(function ($query) use ($paths) {
            foreach ($paths as $path) {
                // 对每个路径使用whereOr，确保任何匹配路径的分类都被选中
                $query->whereOr('path', 'LIKE', "$path%");
            }
        })->where('mer_id', 0)->order('sort DESC')->column('store_category_id');
    }

    /**
     * 获取最大级别
     * 此函数用于确定给定商户ID的最大级别。如果商户ID被提供并且有效，返回2；否则，返回3。
     *
     * @param int|null $merId 商户ID。如果提供，将用于确定级别的值。
     * @return int 返回商户的最大级别。可能的值为2（如果提供了有效的商户ID）或3（如果未提供商户ID）。
     */
    public function getMaxLevel($merId = null)
    {
        if($merId) return 2;
        return 3;
    }


    public function searchLevelAttr($query, $value)
    {
        $query->where('level', $value);
    }

    /**
     * 清除特定字段中具有指定ID的记录。
     *
     * 此方法通过提供的ID和字段名称，从数据库中删除符合条件的记录。
     * 它首先获取模型对应的数据库实例，然后使用提供的字段和ID构建删除条件，
     * 最后执行删除操作。
     *
     * @param int $id 主键ID，用于指定要删除的记录。
     * @param string $field 要用于删除条件的字段名称。
     */
    public function clear(int $id, string $field)
    {
        $this->getModel()::getDB()->where($field, $id)->delete();
    }

}
