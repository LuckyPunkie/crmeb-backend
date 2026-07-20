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
use app\common\model\system\attachment\Attachment;
use crmeb\services\UploadService;
use think\db\BaseQuery;
use think\db\exception\DbException;
use think\Exception;
use think\facade\Log;

/**
 * Class AttachmentDao
 * @package app\common\dao\system\attachment
 * @author xaboy
 * @day 2020-04-16
 */
class AttachmentDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return Attachment::class;
    }

    /**
     * 根据条件搜索附件信息。
     *
     * 本函数用于构建一个搜索附件的数据库查询。它允许根据不同的条件进行筛选，
     * 包括用户类型、上传类型、附件分类ID和附件名称。查询结果将按照创建时间降序排列。
     *
     * @param array $where 包含搜索条件的数组。数组的键是条件的名称，值是条件的值。
     *                    支持的条件有：user_type, upload_type, attachment_category_id, attachment_name。
     *                    如果条件的值为空或不存在，则该条件将被忽略。
     * @return \yii\db\Query 查询对象，包含了所有的搜索条件和排序规则。可以进一步调用其他方法，如执行查询或获取数据。
     */
    public function search(array $where)
    {
        // 初始化查询，从附件表中按创建时间降序排列
        $query = Attachment::getDB()->order('create_time DESC');

        // 如果指定了用户类型，则添加到查询条件中
        if (isset($where['user_type'])) $query->where('user_type', (int)$where['user_type']);
        // 如果指定了上传类型，则添加到查询条件中
        if (isset($where['upload_type'])) $query->where('upload_type', (int)$where['upload_type']);
        if (isset($where['attachment_type']) && $where['attachment_type'] != '') $query->where('attachment_type', $where['attachment_type']);
        // 如果指定了附件分类ID，并且ID不为空，则添加到查询条件中
        if (isset($where['attachment_category_id']) && $where['attachment_category_id'])
            $query->where('attachment_category_id', (int)$where['attachment_category_id']);
        // 如果指定了附件名称，并且名称不为空，则添加到查询条件中，使用LIKE进行模糊匹配
        if (isset($where['attachment_name']) && $where['attachment_name'])
            $query->whereLike('attachment_name', "%{$where['attachment_name']}%");

        // 再次设置创建时间降序排列，以确保排序规则的一致性
        $query->order('create_time DESC');

        // 返回构建好的查询对象
        return $query;
    }

    /**
     * 删除特定条件下的记录。
     *
     * 本函数用于根据用户类型和ID删除数据库中的记录。它首先获取模型对应的数据库实例，
     * 然后构建一个查询条件，包括用户类型和ID，最后执行删除操作。
     *
     * @param int $id 主键ID，表示要删除的具体记录的ID。
     * @param int $userType 用户类型，可选参数，默认为0。用于指定要删除的记录的用户类型。
     * @return int 返回删除操作影响的行数。
     */
    public function delete(int $id, $userType = 0)
    {
        // 获取模型对应的数据库实例，并构建删除条件，最后执行删除操作
        return ($this->getModel())::getDB()->where('user_type', $userType)->where($this->getPk(), $id)->delete();
    }

    /**
     * 批量删除指定ID的记录，并删除对应的附件。
     *
     * 此方法首先尝试从数据库中选取待删除的记录，然后根据这些记录逐个删除对应的附件。
     * 如果附件是存储在本地，则直接删除；如果是外部存储（如OSS），则调用相应的删除接口。
     * 最后，实际从数据库中删除这些记录。
     *
     * @param array $ids 需要删除的记录ID数组。
     * @param int $userType 用户类型标识，默认为0，用于限定删除记录的用户类型。
     * @return int 返回实际删除的记录数。
     */
    public function batchDelete(array $ids, $userType = 0)
    {
        // 根据提供的ID数组查询数据库，获取相关记录。
        $rest = ($this->getModel())::getDB()->whereIn($this->getPk(), $ids)->select();
        // 如果查询结果为空或错误，则直接返回空。
        if (is_null($rest) || count($rest) < 0) return '';

        $uploadConfig = systemConfig('upload_type') ?? 1;
        // 将查询结果分组，每组最多30条，以优化后续的附件删除操作。
        $arr = array_chunk($rest->toArray(), 30);
        foreach ($arr as $data) {
            foreach ($data as $datum) {
                if($uploadConfig == $datum['upload_type']) {
                    try {
                        // 根据上传类型处理附件路径，准备删除操作。
                        if ($datum['upload_type'] < 1) {
                            $url = systemConfig('site_url');
                            $info = str_replace($url, '', $datum['attachment_src']);
                            $key = public_path() . $info;
                        } else {
                            $info = parse_url($datum['attachment_src']);
                            $key = ltrim($info['path'], '/');
                        }
                        // 创建上传服务实例，根据类型执行附件删除。
                        $upload = UploadService::create($datum['upload_type']);
                        $upload->delete($key);
                    } catch (Exception $e) {
                        // 如果删除附件失败，记录日志。
                        Log::info('删除存储图片失败,类型：' . $datum['upload_type'] . ',KEY:' . $key);
                    }
                }
            }
        }
        // 实际从数据库中删除记录，返回删除的记录数。
        return ($this->getModel())::getDB()->where('user_type', $userType)->whereIn($this->getPk(), $ids)->delete();
    }

    /**
     * 检查给定ID的数据是否存在。
     *
     * 本函数通过查询数据库来确定是否存在具有特定ID的记录。它使用了链式调用，
     * 先获取模型实例，然后获取数据库实例，最后通过指定主键ID和用户类型来执行计数查询。
     * 如果查询结果的计数大于0，则表示存在该ID的记录；否则，表示不存在。
     *
     * @param int $id 需要检查的记录的ID。这是主键值，用于唯一标识一条记录。
     * @param int $userType 可选参数，用于指定用户的类型。默认值为0，表示所有用户类型。
     *                      这可以根据具体业务需求来过滤数据。
     * @return bool 如果记录存在，则返回true；如果记录不存在，则返回false。
     */
    public function exists(int $id, $userType = 0)
    {
        // 通过模型获取数据库实例，并使用主键ID和可选的用户类型执行查询，然后检查查询结果的数量是否大于0。
        return ($this->getModel())::getDB()->where($this->getPk(), $id)->count() > 0;
    }

    /**
     * 批量更新指定ID用户的特定数据
     *
     * 本函数用于根据提供的用户ID数组和更新数据数组，批量更新符合用户类型条件的用户数据。
     * 主要适用于需要对一批用户数据进行相同操作的场景，如批量修改用户状态、权限等。
     *
     * @param array $ids 用户ID数组，指定需要更新数据的用户
     * @param array $data 更新的数据数组，包含需要修改的字段及其新值
     * @param int $user_type 用户类型标志，默认为0，用于筛选特定类型的用户进行更新
     * @return int 返回影响的行数，即被成功更新的用户数量
     */
    public function batchChange(array $ids, array $data, int $user_type = 0)
    {
        // 通过模型获取数据库实例，并构造更新查询条件
        // 其中，where('user_type', $user_type) 筛选用户类型，whereIn($this->getPk(), $ids) 筛选指定ID的用户
        // 最后，执行更新操作并返回影响的行数
        return ($this->getModel())::getDB()->where('user_type', $user_type)->whereIn($this->getPk(), $ids)->update($data);
    }

    /**
     * 清理附件缓存。
     *
     * 此方法用于删除用户类型为-1的附件记录。这可能是为了回收空间、修复错误的用户类型数据，
     * 或者在系统更新用户类型逻辑时清理不再需要的附件记录。
     *
     * @return int 返回删除的记录数。这可以让调用者知道清理操作的影响范围。
     */
    public function clearCache()
    {
        // 使用Attachment类的数据库访问对象，并构造一个条件查询：删除用户类型为-1的附件记录。
        return Attachment::getDB()->where('user_type', -1)->delete();
    }

}

