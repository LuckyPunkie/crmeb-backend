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
use app\common\model\system\config\SystemConfigClassify;
use think\Collection;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;

/**
 * Class SystemConfigClassifyDao
 * @package app\common\dao\system\config
 * @author xaboy
 * @day 2020-03-27
 */
class SystemConfigClassifyDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return SystemConfigClassify::class;
    }

    /**
     * 获取配置选项
     *
     * 本方法通过查询系统配置分类表，获取所有配置分类的父ID和分类名称，
     * 以数组形式返回查询结果，用于在前端展示配置选项列表或者供其他地方使用。
     *
     * @return array 返回查询结果，包含每个配置分类的父ID和分类名称。
     */
    public function getOptions()
    {
        // 根据config_classify_id查询系统配置分类表，获取pid和classify_name两列数据
        return SystemConfigClassify::column('pid,classify_name', 'config_classify_id');
    }

    /**
     * 获取顶级配置分类选项
     *
     * 本方法通过查询系统配置分类表，获取所有父级分类的名称及其对应的ID。
     * 这些信息通常用于构建配置分类的下拉列表，供用户选择。
     *
     * @return array 返回一个数组，其中键是配置分类ID，值是分类名称。
     */
    public function getTopOptions()
    {
        // 使用SystemConfigClassify类的静态方法getDB来获取数据库实例，并链式调用where和column方法进行查询
        // 查询条件为pid为0，即父级分类ID为0的记录，返回结果为classify_name列和config_classify_id列的映射关系
        return SystemConfigClassify::getDB()->where('pid', 0)->column('classify_name', 'config_classify_id');
    }

    /**
     * 根据条件搜索系统配置分类
     *
     * 本函数旨在根据提供的条件数组搜索系统配置分类。搜索支持两个条件：状态（status）和分类名称（classify_name）。
     * - 状态条件检查是否设置了 'status' 键，并且其值不为空。如果满足条件，查询将限制在指定的状态上。
     * - 分类名称条件检查是否设置了 'classify_name' 键，并且其值不为空。如果满足条件，查询将包括分类名称含有指定字符串的结果。
     *
     * @param array $where 包含搜索条件的数组。数组可以包含 'status' 和/或 'classify_name' 键来指定搜索条件。
     * @return \think\db\BaseQuery 返回一个构建器对象，用于进一步的查询操作或数据检索。
     */
    public function search(array $where)
    {
        // 使用SystemConfigClassify的数据库方法进行查询，并应用条件
        return SystemConfigClassify::getDB()->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            // 如果指定了状态，则添加状态条件到查询中
            $query->where('status', $where['status']);
        })->when(isset($where['classify_name']) && $where['classify_name'] !== '', function ($query) use ($where) {
            // 如果指定了分类名称，则添加分类名称条件到查询中，使用LIKE进行模糊匹配
            $query->where('classify_name', 'LIKE', "%{$where['classify_name']}%");
        })->order('sort desc,create_time asc');
    }

    /**
     * 获取所有系统配置分类
     *
     * 本方法通过调用SystemConfigClassify::getDB()->select()来获取数据库中所有系统配置分类的数据。
     * 它不接受任何参数，并返回一个包含所有系统配置分类的数据集合。
     *
     * @return array 返回包含所有系统配置分类的数据集合
     */
    public function all()
    {
        // 调用SystemConfigClassify类的静态方法getDB，返回数据库操作对象，然后执行select方法获取所有数据
        return SystemConfigClassify::getDB()->select();
    }

    /**
     * 计算系统配置分类的数量
     *
     * 本方法通过调用SystemConfigClassify类的getDB方法，进一步获取数据库操作对象，
     * 并执行count方法来统计系统配置分类的总数。该方法用于在需要了解系统配置分类
     * 数量的场景下调用，例如在后台管理系统中展示分类数量。
     *
     * @return int 返回系统配置分类的数量
     */
    public function count()
    {
        // 通过SystemConfigClassify类的静态方法getDB获取数据库操作对象，并调用其count方法进行计数
        return SystemConfigClassify::getDB()->count();
    }

    /**
     * 查询指定父ID下的子分类
     *
     * 本函数旨在通过指定的父分类ID，从系统配置分类表中查询其子分类。查询结果将根据指定的字段返回。
     * 这对于构建分类体系，例如文章分类、商品分类等，是非常有用的。通过递归调用，可以构建出完整的分类树。
     *
     * @param int $pid 父分类的ID，用于指定查询的父分类
     * @param string $field 需要返回的字段，默认为'config_classify_id,classify_name'，用于指定查询结果中包含的字段
     * @return array 返回查询结果的数组，每个元素代表一个子分类
     */
    public function children(int $pid, string $field = 'config_classify_id,classify_name')
    {
        // 使用SystemConfigClassify类的getDB方法获取数据库操作对象，然后通过where和field方法指定查询条件和返回字段，最后调用select方法执行查询
        return SystemConfigClassify::getDB()->where('pid', $pid)->where('status',1)->field($field)->order('sort desc, create_time asc')
            ->select();
    }

    /**
     * 检查是否存在指定ID的子项
     *
     * 本函数通过查询是否有一个字段的值匹配给定的ID来确定是否存在子项。
     * 这里的“子项”是相对于某种父项或容器而言的，具体的含义依赖于应用程序的上下文。
     *
     * @param int $id 要检查的子项的唯一标识符。这个ID应该是一个整数。
     * @return bool 如果存在匹配的子项，则返回true；否则返回false。
     */
    public function existsChild(int $id): bool
    {
        // 调用fieldExists方法检查是否存在指定ID的子项
        return $this->fieldExists('pid', $id);
    }

    /**
     * 检查指定的键是否存在于分类字段中。
     *
     * 此方法用于确定给定的键是否作为分类键存在于数据表中。这可以通过调用另一个方法`fieldExists`来实现，
     * 它是这个方法的实际执行者。这个方法的存在提供了一个更具体、更清晰的接口，用于查询特定类型的字段是否存在，
     * 即分类键。
     *
     * @param string $key 要检查的键名。
     * @param int|null $except 可选参数，用于指定需要排除检查的特定ID。
     * @return bool 如果键存在则返回true，否则返回false。
     */
    public function keyExists($key, ?int $except = null): bool
    {
        // 调用fieldExists方法来检查classify_key字段中是否存在指定的键值。
        return $this->fieldExists('classify_key', $key, $except);
    }

    /**
     * 检查给定的进程ID是否存在于指定的字段中。
     *
     * 此方法用于验证一个进程ID（PID）是否在某个特定的字段中出现，
     * 通常用于管理或监控进程的存在性。通过对比指定的字段值，
     * 可以确定给定的PID是否在运行中，或者是否已被记录。
     *
     * @param int $pid 要检查的进程ID。
     * @param int|null $except 可选参数，指定一个PID来排除检查，即不包括这个PID在内进行比较。
     * @return bool 如果指定的PID存在于字段中（且不等于$except参数），返回true；否则返回false。
     */
    public function pidExists(int $pid, ?int $except = null): bool
    {
        // 调用fieldExists方法来检查PID是否存在，同时排除$except参数指定的PID。
        return $this->fieldExists('config_classify_id', $pid, $except);
    }

    /**
     * 根据键名获取配置分类ID
     *
     * 本函数旨在通过分类键名，从数据库中查询并返回对应的配置分类ID。
     * 这对于需要根据分类键来获取配置分类ID的场景非常有用，比如在进行配置管理时。
     *
     * @param string $key 分类键名，用于查询数据库中对应的配置分类ID。
     * @return mixed 返回查询结果，如果找不到对应的分类ID，则返回null。
     */
    public function keyById(string $key)
    {
        // 使用SystemConfigClassify类的静态方法getDB来获取数据库操作对象，并通过where方法指定查询条件，最后使用value方法获取'config_classify_id'列的值
        return SystemConfigClassify::getDB()->where('classify_key', $key)->value('config_classify_id');
    }

    /**
     * 根据指定的关键字查询系统配置分类数据
     *
     * 本函数旨在通过关键字查询系统配置分类数据库中的特定记录。它使用了SystemConfigClassify类的getDB方法来获取数据库实例，
     * 并利用where方法指定查询条件，最后调用find方法来执行查询并返回结果。这个方法对于需要根据关键字获取特定配置分类信息的场景非常有用。
     *
     * @param string $key 配置分类的关键字。这个关键字用于在数据库中定位特定的配置分类记录。
     * @return array|object 返回查询结果。如果查询成功，结果将是一个包含配置分类信息的数组或对象；如果查询失败，可能返回空数组或false。
     */
    public function keyByData(string $key)
    {
        // 使用SystemConfigClassify类的getDB方法获取数据库实例，并构造查询语句，查询classify_key为$key$的记录
        return SystemConfigClassify::getDB()->where('classify_key', $key)->find();
    }

    /**
     * 获取系统配置分类列表
     *
     * 本方法通过调用SystemConfigClassify类的column方法，获取系统配置分类的名称、父ID和分类ID。
     * 主要用于在系统设置中展示配置分类的结构，帮助用户快速定位和配置系统参数。
     *
     * @return array 返回一个包含分类名称、父ID和分类ID的数组
     */
    public function getOption()
    {
        // 调用SystemConfigClassify类的column方法，查询并返回系统配置分类的指定列
        return SystemConfigClassify::column('classify_name,pid,config_classify_id');
    }
}
