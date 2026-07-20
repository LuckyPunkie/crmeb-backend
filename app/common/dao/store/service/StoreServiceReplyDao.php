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


namespace app\common\dao\store\service;


use app\common\dao\BaseDao;
use app\common\model\store\service\StoreServiceReply;

/**
 * Class StoreServiceDao
 * @package app\common\dao\store\service
 * @author xaboy
 * @day 2020/5/29
 */
class StoreServiceReplyDao extends BaseDao
{

    /**
     * @return string
     * @author xaboy
     * @day 2020/5/29
     */
    protected function getModel(): string
    {
        return StoreServiceReply::class;
    }

    /**
     * 根据条件搜索商店服务回复信息。
     *
     * 该方法通过接收一个包含搜索条件的数组，动态地构造数据库查询语句，以筛选出符合条件的商店服务回复记录。
     * 支持的搜索条件包括：商家ID（mer_id）、关键词（keyword）和状态（status）。
     *
     * @param array $where 搜索条件数组，包含可能的字段：mer_id、keyword、status。
     * @return \Illuminate\Database\Query\Builder|StoreServiceReply 查询构造器或者StoreServiceReply实例。
     */
    public function search(array $where)
    {
        // 获取数据库查询构建器
        return StoreServiceReply::getDB()
            // 当'mer_id'字段存在且不为空时，添加where条件筛选商家ID
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('mer_id', $where['mer_id']);
            })
            // 当'keyword'字段存在且不为空时，添加like条件筛选关键词
            ->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->whereLike('keyword', "%{$where['keyword']}%");
            })
            // 当'status'字段存在且不为空时，添加where条件筛选状态
            ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                $query->where('status', $where['status']);
            });
    }

    /**
     * 根据有效数据查询关键词
     * 本函数旨在通过特定的关键词和商家ID，从数据库中检索有效的关键词信息。
     * 这里的“有效”指的是关键词处于激活状态（status=1）且属于指定的商家（mer_id）。
     *
     * @param string $key 关键词，用于搜索匹配。
     * @param int $merId 商家ID，用于限定搜索范围。
     * @return array|null 返回匹配条件的关键词数据数组，如果找不到则返回null。
     */
    public function keywordByValidData($key, $merId)
    {
        // 从StoreServiceReply的数据库实例中查询
        // 使用where函数构建复杂的查询条件，首先搜索包含关键词的关键字
        // 然后搜索关键词列表中包含该关键词的数据
        // 最后限定查询条件为状态为1且属于指定商家ID的数据
        return StoreServiceReply::getDB()->where(function ($query) use ($key) {
            // 搜索关键词字段中包含$key的数据
            $query->where('keyword', 'like', "%{$key}%")
                // 搜索关键词以逗号分隔的列表中包含$key的数据
                ->whereFieldRaw('CONCAT(\',\',`keyword`,\',\')', 'LIKE', '%,' . $key . ',%', 'OR');
        })->where('status', 1)->where('mer_id', $merId)->find();
    }
}
