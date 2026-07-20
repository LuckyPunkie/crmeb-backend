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


namespace app\common\dao\user;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\user\Feedback;
use think\db\BaseQuery;

/**
 * Class FeedbackDao
 * @package app\common\dao\user
 * @author xaboy
 * @day 2020/5/28
 */
class FeedbackDao extends BaseDao
{

    /**
     * @return string
     * @author xaboy
     * @day 2020/5/28
     */
    protected function getModel(): string
    {
        return Feedback::class;
    }

    /**
     * 根据条件搜索反馈信息
     *
     * 本函数用于根据提供的条件数组搜索反馈信息。它支持多种条件搜索，包括用户ID、关键词、类型、状态、姓名和删除状态等。
     * 搜索条件是可选的，只有当条件存在且不为空时，才会应用相应的查询条件。
     *
     * @param array $where 搜索条件数组，包含各种可能的搜索参数。
     * @return \think\Collection 返回搜索结果集合。
     */
    public function search(array $where)
    {
        // 从Feedback类中获取数据库实例
        return Feedback::getDB()->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
            // 如果提供了用户ID，则按用户ID查询
            $query->where('uid', $where['uid']);
        })->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
            // 如果提供了关键词，则按内容、回复、备注、真实姓名和联系方式模糊查询
            $query->whereLike('content|reply|remake|realname|contact', '%'.$where['keyword'].'%');
        })->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
            // 如果提供了类型，则按类型查询
            $query->where('type',$where['type']);
        })->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            // 如果提供了状态，则按状态查询
            $query->where('status', $where['status']);
        })->when(isset($where['realname']) && $where['realname'] !== '', function ($query) use ($where) {
            // 如果提供了真实姓名，则按真实姓名模糊查询
            $query->where('realname','like', '%'.$where['realname'].'%');
        })->when(isset($where['is_del']) && $where['is_del'] !== '', function ($query) use ($where) {
            // 如果提供了删除状态，则按删除状态查询
            $query->where('is_del',$where['is_del']);
        })->order('create_time DESC'); // 按创建时间降序排序
    }

    /**
     * 检查给定的ID和UID是否存在对应的反馈信息。
     *
     * 本函数通过查询数据库来确定是否存在一条反馈信息，其主键（ID）与给定的ID匹配，
     * 并且用户ID（uid）与给定的UID匹配，同时该反馈信息未被删除（is_del为0）。
     * 这对于确保操作的反馈信息是有效和可用的非常重要。
     *
     * @param int $id 主键ID，用于查询特定的反馈信息。
     * @param int $uid 用户ID，用于查询属于特定用户的反馈信息。
     * @return bool 如果存在匹配的反馈信息，则返回true；否则返回false。
     */
    public function uidExists($id, $uid): bool
    {
        // 使用Feedback类的静态方法getDB来获取数据库实例，并构建查询条件，
        // 查询条件包括主键ID、用户ID和删除状态，最后通过count方法统计符合条件的记录数，
        // 如果记录数大于0，则表示存在匹配的反馈信息。
        return Feedback::getDB()->where($this->getPk(), $id)->where('uid', $uid)->where('is_del', 0)->count($this->getPk()) > 0;
    }

    /**
     * 检查指定ID的商品是否存在且未被删除。
     *
     * 该方法通过查询数据库来确定给定ID的商品是否存在，同时确保该商品的删除标记(is_del)为0，即未被删除。
     * 这是对商品管理中常见操作的封装，用于在进行进一步的操作前验证商品的有效性。
     *
     * @param int $id 商品的唯一标识ID。
     * @return bool 如果商品存在且未被删除，返回true；否则返回false。
     */
    public function merExists(int $id)
    {
        // 使用模型获取数据库实例，并构造查询条件：主键为$id且is_del为0，然后统计符合条件的记录数。
        // 如果记录数大于0，说明商品存在且未被删除，返回true；否则返回false。
        return $this->getModel()::getDB()->where($this->getPk(), $id)->where('is_del', 0)->count() > 0;
    }
}
