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


namespace app\common\dao\system\groupData;


use app\common\dao\BaseDao;
use app\common\model\system\groupData\SystemGroup;
use think\Collection;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;


/**
 * Class GroupDao
 * @package app\common\dao\system\groupData
 * @author xaboy
 * @day 2020-03-27
 */
class GroupDao extends BaseDao
{

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return SystemGroup::class;
    }

    /**
     * 获取所有系统分组的数据
     *
     * 本函数通过调用SystemGroup类的数据库操作方法，查询并返回所有系统分组的信息。
     * 这是对系统分组进行管理的一个基础操作，为后续的系统分组列表展示或进一步的分组数据处理提供数据支持。
     *
     * @return array 返回包含所有系统分组信息的数组
     */
    public function all()
    {
        // 调用SystemGroup类的数据库操作方法，执行查询操作并返回所有查询结果
        return SystemGroup::getDB()->select();
    }

    /**
     * 计算系统分组的数量
     *
     * 本方法通过查询系统分组数据表，获取当前系统分组的总数。
     * 使用这一方法可以快速获得系统分组的全局统计信息，而无需加载每个分组的详细数据。
     *
     * @return int 返回系统分组的数量。这是一个整数值，表示系统中现有的分组总数。
     */
    public function count()
    {
        // 通过SystemGroup类的静态方法getDB()获取数据库连接对象，并调用其count()方法来计算分组数量
        return SystemGroup::getDB()->count();
    }

    /**
     * 分页获取系统分组数据
     *
     * 本函数用于实现系统分组数据的分页查询。它调用SystemGroup类中的getDB方法来获取数据库实例，
     * 并进一步调用该实例的page方法进行分页查询。此方法对于处理大量系统分组数据时非常有用，
     * 可以有效地减少内存占用并提高数据检索速度。
     *
     * @param int $page 当前页码
     * @param int $limit 每页数据的数量
     * @return \think\Paginator 返回分页后的数据对象
     */
    public function page($page, $limit)
    {
        // 调用SystemGroup类的getDB方法获取数据库实例，并执行分页查询
        return SystemGroup::getDB()->page($page, $limit);
    }

    /**
     * 检查给定的键是否存在于特定字段中。
     *
     * 此方法重用父类方法来检查指定的键是否存在于'group_key'字段中。
     * 它提供了一个可选的参数来排除特定的键值对检查，这允许更灵活的查询。
     *
     * @param string $key 要检查的键。
     * @param int|null $except 排除检查的特定键值。如果为null，则不进行排除。
     * @return bool 如果键存在则返回true，否则返回false。
     */
    public function keyExists($key, ?int $except = null): bool
    {
        return parent::fieldExists('group_key', $key, $except);
    }

    /**
     * 根据指定的字段值获取系统分组的字段配置。
     *
     * 本函数通过查询系统分组表，根据给定的字段值（默认为group_id）查找对应的分组，
     * 并返回该分组的字段配置。字段配置以JSON格式存储在数据库中，这里通过查询结果
     * 的value方法获取到JSON字符串，然后使用json_decode将其解析为PHP数组返回。
     *
     * @param int|string $id 需要查询的字段值。这个值可以根据字段名（$field参数指定）来定位特定的分组。
     * @param string $field 指定用于查询的字段名。默认为'group_id'，可以根据需要查询其他的字段。
     * @return array 解析后的JSON字符串，表示分组的字段配置。如果查询结果为空或查询失败，则返回空数组。
     */
    public function fields($id, $field = 'group_id')
    {
        // 使用SystemGroup的数据库查询方法，根据字段值查询并返回字段配置的JSON字符串
        return json_decode(SystemGroup::getDB()->where($field, $id)->value('fields'), true);
    }

    /**
     * 根据键值查询系统分组的ID
     *
     * 本函数旨在通过给定的键值（$key）查询系统分组表中对应的分组ID。
     * 它使用了SystemGroup类的静态方法getDB来获取数据库连接，并构造了一个查询，
     * 该查询依据给定的键值查找group_key字段，并返回对应的group_id字段值。
     * 这种查询方式适用于只需要获取单一字段值的情况，可以提高查询效率。
     *
     * @param string $key 分组的键值，用于查询系统分组表中对应的分组ID。
     * @return mixed 返回查询结果，如果未找到匹配的记录，则返回null。
     */
    public function keyById(string $key)
    {
        // 通过group_key查询系统分组表中的group_id，并返回查询结果
        return SystemGroup::getDB()->where('group_key', $key)->value('group_id');
    }
}
