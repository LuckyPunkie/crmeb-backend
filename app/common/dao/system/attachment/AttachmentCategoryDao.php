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


namespace app\common\dao\system\attachment;

use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\attachment\AttachmentCategory;
use think\Collection;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\Model;

/**
 * Class AttachmentCategoryDao
 * @package app\common\dao\system\attachment
 * @author xaboy
 * @day 2020-04-22
 */
class AttachmentCategoryDao extends BaseDao
{
    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return AttachmentCategory::class;
    }

    /**
     * 获取所有附件分类
     *
     * 本函数用于从数据库中检索所有附件分类。可以通过可选参数$mer_id筛选属于特定商户ID的分类。
     * 返回的结果集将按照排序字段'sort'降序排列。
     *
     * @param int $mer_id 商户ID，用于过滤分类。默认为0，表示获取所有商户的分类。
     * @return array 返回符合条件的附件分类列表。
     */
    public function getAll($mer_id = 0)
    {
        // 使用AttachmentCategory类的静态方法getDB来获取数据库对象
        // 然后通过where方法设置查询条件，order方法设置排序方式，最后调用select方法执行查询
        return AttachmentCategory::getDB()->where('mer_id', $mer_id)->order('sort DESC')->select();
    }

    /**
     * 通过 $attachmentCategoryEName 获取主键
     * @param string $attachmentCategoryEName 需要检测的数据
     * @return int
     * @author 张先生
     * @date 2020-03-30
     */
    public function getPkByAttachmentCategoryEName($attachmentCategoryEName)
    {
        return AttachmentCategory::getInstance()->where('attachment_category_enname', $attachmentCategoryEName)->value($this->getPk());
    }

    /**
     * 通过id 获取path
     * @param int $id 需要检测的数据
     * @return string
     * @author 张先生
     * @date 2020-03-30
     */
    public function getPathById($id)
    {
        return AttachmentCategory::getInstance()->where($this->getPk(), $id)->value('path');
    }

    /**
     * 通过id获取所有子集的id
     * @param int $id 需要检测的数据
     * @return array
     * @author 张先生
     * @date 2020-03-30
     */
    public function getIdListContainsPath($id)
    {
        return AttachmentCategory::getInstance()
            ->where($this->getPk(), $id)
            ->whereOrRaw("locate ('/{$id}/', path)")
            ->column($this->getPk());
    }

    /**
     * 获取所有选项
     * 此方法用于查询并返回所有附件分类的信息，特别适用于需要展示所有分类列表的场景。
     * 通过指定mer_id，可以筛选特定商家的附件分类，适用于多商家系统中数据隔离的场景。
     * 返回的结果以二维数组形式呈现，方便直接用于前端展示或进一步的数据处理。
     *
     * @param int $mer_id 商家ID，用于筛选特定商家的附件分类，默认为0，表示查询所有商家的分类。
     * @return array 返回包含附件分类ID和名称的二维数组，数组的键为附件分类ID，值为附件分类名称。
     */
    public function getAllOptions($mer_id = 0)
    {
        // 使用AttachmentCategory类的getDB方法获取数据库操作对象
        // 然后通过where方法指定查询条件，order方法指定排序方式
        // 最后使用column方法查询并返回指定字段的值，这里返回的是pid和attachment_category_name字段
        return AttachmentCategory::getDB()->where('mer_id', $mer_id)->order('sort DESC')->column('pid,attachment_category_name', 'attachment_category_id');
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
    public function get($id, $merId = 0)
    {
        // 通过模型获取数据库操作对象，并根据$id和$merId查询数据
        return ($this->getModel())::getDB()->where('mer_id', $merId)->find($id);
    }

    /**
     * 删除记录
     *
     * 本函数用于根据给定的ID和可选的merId删除数据库中的记录。
     * 它首先获取模型对应的数据库实例，然后构建一个查询条件，包括主键ID和mer_id（如果提供），
     * 最后执行删除操作。此函数返回删除操作的结果。
     *
     * @param int $id 主键ID，用于指定要删除的记录。
     * @param int $merId 商户ID，可选参数，用于指定特定商户的记录。默认为0，表示不区分商户。
     * @return int 返回删除操作影响的行数。
     */
    public function delete(int $id, $merId = 0)
    {
        // 获取模型对应的数据库实例，并构建删除条件
        return ($this->getModel())::getDB()->where($this->getPk(), $id)->where('mer_id', $merId)->delete();
    }

    /**
     * 更新附件路径。
     * 当旧路径被新路径替换时，此方法用于更新所有关联的附件路径。
     * 主要用于处理附件分类路径的更新，确保路径更新后仍符合系统规定的最多三级分类的要求。
     *
     * @param string $oldPath 旧的附件路径。
     * @param string $path 新的附件路径。
     *
     * @throws ValidateException 如果新路径超过三级分类限制，则抛出验证异常。
     */
    public function updatePath(string $oldPath, string $path)
    {
        // 使用like查询匹配以旧路径开头的所有附件分类记录，并选择需要的字段
        AttachmentCategory::getDB()->whereLike('path', $oldPath . '%')->field('attachment_category_id,path')->select()->each(function ($val) use ($oldPath, $path) {
            // 替换记录中的旧路径为新路径
            $newPath = str_replace($oldPath, $path, $val['path']);
            // 检查新路径是否超过三级分类的限制
            if (substr_count(trim($newPath, '/'), '/') > 1) {
                throw new ValidateException('素材分类最多添加三级');
            }
            // 更新附件分类的路径
            AttachmentCategory::getDB()->where('attachment_category_id', $val['attachment_category_id'])->update(['path' => $newPath]);
        });
    }
}
