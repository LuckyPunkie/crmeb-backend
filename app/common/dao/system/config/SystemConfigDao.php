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


namespace app\common\dao\system\config;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\config\SystemConfig;
use think\Collection;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * Class SystemConfigDao
 * @package app\common\dao\system\config
 * @author xaboy
 * @day 2020-03-27
 */
class SystemConfigDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return SystemConfig::class;
    }

    /**
     * 检查分类ID是否已存在
     *
     * 本函数用于确定给定的分类ID是否在系统中已存在。这可以通过查询配置表中是否存在对应的分类ID来实现。
     * 主要用于配置管理部分，确保新的分类ID不会与现有的ID冲突。
     *
     * @param int $classify_id 分类ID，用于唯一标识一个分类。
     * @return bool 返回true如果分类ID已存在，否则返回false。
     */
    public function classifyIdExists(int $classify_id)
    {
        // 调用fieldExists方法检查给定的分类ID是否存在于config_classify_id字段中
        return $this->fieldExists('config_classify_id', $classify_id);
    }

    /**
     * 检查配置中是否存在指定的键。
     *
     * 本函数用于确定配置数据集中是否包含一个特定的键。这在需要验证配置项是否存在时非常有用，
     * 例如，在尝试访问或修改配置之前。
     *
     * @param string $key 需要检查的配置键名。
     * @param int|null $except 可选参数，指定一个键值以排除在检查之外，默认为null。
     * @return bool 如果指定的键存在则返回true，否则返回false。
     */
    public function keyExists($key, ?int $except = null): bool
    {
        // 调用fieldExists方法来检查配置键是否存在于数据集中。
        // 这里将'config_key'作为字段名传递，用于指定检查的字段。
        return $this->fieldExists('config_key', $key, $except);
    }

    /**
     * 根据条件搜索系统配置信息
     *
     * 本函数用于构建一个搜索系统配置的查询。它根据传入的条件数组来筛选数据库中的配置项。
     * 可以根据配置名、配置键、分类ID、父分类ID和用户类型进行搜索。
     *
     * @param array $where 包含搜索条件的数组，其中键是条件名称，值是条件值。
     *                    支持的条件有：keyword（关键字），pid（父分类ID），config_classify_id（分类ID），user_type（用户类型）。
     * @return \Illuminate\Database\Query\Builder|static 返回构建好的查询对象，可以进一步调用其他查询方法或执行查询。
     */
    public function search(array $where)
    {
        // 从SystemConfig类中获取数据库实例
        $query = SystemConfig::getDB();

        // 如果条件数组中包含keyword且不为空，则按照关键字搜索配置名或配置键
        if (isset($where['keyword']) && $where['keyword'] !== '' )
            $query->whereLike('config_name|config_key', '%' . $where['keyword'] . '%');

        // 如果条件数组中包含pid且不为空，则按照父分类ID搜索
        if (isset($where['pid']) && $where['pid'] !== '')
            $query->where('config_classify_id', $where['pid']);

        // 如果条件数组中包含config_classify_id且不为空，则按照分类ID搜索
        if (isset($where['config_classify_id']) && $where['config_classify_id'] !== '')
            $query->where('config_classify_id', $where['config_classify_id']);

        // 如果条件数组中包含user_type且不为空，则按照用户类型搜索
        if (isset($where['user_type']) && $where['user_type'] !== '')
            $query->where('user_type', $where['user_type']);

        // 返回构建好的查询对象
        return $query;
    }

    /**
     * 根据配置分类ID和用户类型获取配置数据
     *
     * 本函数通过指定的配置分类ID（$cid）和用户类型（$user_type），从数据库中查询并返回相应的配置信息。
     * 查询条件包括配置的状态为启用（1）以及配置分类ID和用户类型与参数值匹配。查询结果将按照排序字段（sort）降序
     * 和配置ID（config_id）升序进行排序。
     *
     * @param int $cid 配置分类ID，用于筛选特定分类的配置。
     * @param int $user_type 用户类型，用于筛选适用于特定用户类型的配置。
     * @return array 返回符合条件的配置数据数组，包含多个配置项。
     */
    public function cidByConfig(int $cid, int $user_type)
    {
        // 使用SystemConfig类的数据库操作方法，根据配置分类ID、用户类型和状态查询配置数据
        // 并按照排序字段降序和配置ID升序返回查询结果
        return SystemConfig::getDB()->where('config_classify_id', $cid)->where('user_type', $user_type)->where('status', 1)
            ->order('sort DESC, config_id ASC')->select();
    }

    /**
     * 获取配置项的交集
     * 通过给定的分类ID和配置键列表，查询系统配置表中满足条件的配置项的类型和名称，并按配置键返回结果。
     * 此函数主要用于处理配置数据的筛选，确保返回的配置项既属于指定的分类ID（或分类ID列表），又包含在指定的键列表中，且状态为启用。
     *
     * @param mixed $cid 分类ID，可以是单个ID或ID数组
     * @param array $keys 配置键列表
     * @return array 返回格式为[key => [config_type, config_name]]的数组，其中key为配置键
     */
    public function intersectionKey($cid, $keys): array
    {
        // 根据分类ID的类型（数组或非数组），使用不同的条件查询方式
        return SystemConfig::where('config_classify_id', is_array($cid) ? 'IN' : '=', $cid)
            // 确保配置键在给定的列表中
            ->whereIn('config_key', $keys)
            // 只返回状态为启用的配置项
            ->where('status', 1)
            // 按配置键分组，返回配置类型和名称
            ->column('config_type,config_name', 'config_key');
    }



}
