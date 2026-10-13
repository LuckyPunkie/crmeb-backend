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
use app\common\model\BaseModel;
use app\common\model\store\StoreAttrTemplate;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;

/**
 * Class StoreAttrTemplateDao
 * @package app\common\dao\store
 * @author xaboy
 * @day 2020-05-06
 */
class StoreAttrTemplateDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return StoreAttrTemplate::class;
    }

    /**
     * 根据条件搜索商店属性模板
     *
     * 本函数用于查询特定商家ID下的属性模板信息。支持通过关键词进行模糊搜索。
     *
     * @param string $merId 商家ID，用于限定查询的商家范围。
     * @param array $where 查询条件数组，可选参数，其中'keyword'键用于指定搜索关键词。
     * @return BaseQuery|\think\Paginator
     */
    public function search($merId, array $where = [])
    {
        return StoreAttrTemplate::getDB()->when(isset($where['keyword']),function($query) use($where){
            $query->whereLike('template_name',"%{$where['keyword']}%");
        })->where('mer_id', $merId)->order('attr_template_id DESC');
    }

    /**
     * 检查指定商户是否存在指定的ID
     *
     * 本函数通过调用merFieldExists方法来判断指定商户ID在特定字段中是否存在。
     * 主要用于验证商户ID的有效性，以及在某些情况下排除特定ID的检查。
     *
     * @param int $merId 商户的ID，用于标识特定的商户。
     * @param int $id 需要检查的ID，看它是否属于指定的商户。
     * @param mixed $except 可选参数，用于指定需要排除的ID，默认为null。
     * @return bool 如果指定的ID存在于商户中则返回true，否则返回false。
     */
    public function merExists(int $merId, int $id, $except = null)
    {
        // 调用merFieldExists方法来检查指定的ID是否存在于商户ID字段中
        return $this->merFieldExists($merId, $this->getPk(), $id, $except);
    }

    /**
     * 检查指定商家是否存在特定字段的特定值。
     *
     * 该方法用于查询数据库中是否存在特定条件的记录。具体来说，它首先检查传入的除外条件（$except），
     * 如果除外条件存在，则在查询时排除这些条件。然后，它查询指定商家（$merId）的记录中，
     * 指定字段（$field）的值是否等于传入的值（$value）。如果存在匹配的记录，则返回true，否则返回false。
     *
     * @param int $merId 商家ID，用于限定查询的商家范围。
     * @param string $field 要检查的字段名。
     * @param mixed $value 要检查的字段值。
     * @param mixed $except 除外条件，即在查询时不应该匹配的值。
     * @return bool 如果存在匹配的记录则返回true，否则返回false。
     */
    public function merFieldExists(int $merId, $field, $value, $except = null)
    {
        // 获取模型对应的数据库实例，并根据$except参数应用条件。
        return ($this->getModel())::getDB()->when($except, function ($query, $except) use ($field) {
                // 如果$except存在，则添加不等于($<>_)查询条件。
                $query->where($field, '<>', $except);
            })->where('mer_id', $merId)->where($field, $value)->count() > 0;
    }

    /**
     * 根据ID和商家ID获取数据
     *
     * 本函数用于从数据库中检索指定ID和商家ID对应的数据行。
     * 它首先通过调用getModel方法来获取模型实例，然后使用该实例的getDB方法来获得数据库操作对象。
     * 接着，通过where方法指定查询条件为商家ID为$merId，最后使用find方法根据$id查找数据。
     *
     * @param int $id 数据行的唯一标识ID
     * @param int $merId 商家的唯一标识ID，默认为0，表示系统默认商家
     * @return object 返回查询结果的对象，如果未找到则为null
     */
    public function get( $id, $merId = 0)
    {
        return ($this->getModel())::getDB()->where('mer_id', $merId)->find($id);
    }

    /**
     * 根据ID和商家ID删除记录
     *
     * 本函数用于删除指定ID的记录。它可以处理单个ID和多个ID的删除请求。
     * 删除操作会根据提供的商家ID对数据进行过滤，确保只删除属于该商家的数据。
     *
     * @param mixed $id 需要删除的记录的ID，可以是单个ID或ID数组
     * @param int $merId 商家ID，用于指定删除哪个商家的数据，默认为0，表示删除所有商家的数据
     * @return int 返回删除的记录数
     */
    public function delete($id, $merId = 0)
    {
        // 获取数据库实例并根据商家ID建立查询条件
        $query = ($this->getModel())::getDB()->where('mer_id', $merId);

        // 判断传入的ID类型，如果是数组，则使用whereIn方法删除多个记录；否则，直接使用where方法删除单个记录
        if (is_array($id)) {
            $query->where($this->getPk(), 'in',$id);
        } else {
            $query->where($this->getPk(), $id);
        }

        // 执行删除操作并返回删除的记录数
        return $query->delete();
    }


    /**
     * 获取指定商户的属性模板列表
     *
     * 本函数旨在通过指定的商户ID，从数据库中检索该商户的所有属性模板信息。
     * 返回的结果包括属性模板ID、模板名称和模板值。
     *
     * @param int $merId 商户ID，用于指定要查询的商户。
     * @return array 返回一个包含属性模板信息的数组，每个元素包含属性模板ID、名称和值。
     */
    public function getList($merId)
    {
        // 使用模型获取数据库实例，并设置查询条件为指定的商户ID，然后指定查询的字段，最后执行查询操作。
        return ($this->getModel())::getDB()->where('mer_id',$merId)->field('mer_id,attr_template_id,template_name,template_value')->select()
            ->each(function($item) {
                $template_value = $item['template_value'];
                if ($template_value) {
                    foreach ($template_value as &$item1) {
                        $detail = $item1['detail'];
                        $de = array_map(function($v) use($item1){return ['pic' => '', 'value' => $v];},$detail);
                        $item1['detail'] = $de;
                    }
                    $item['template_value'] = $template_value;
                }
            });
    }
}
