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


namespace app\common\dao\system\notice;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\notice\SystemNoticeLog;

/**
 * Class SystemNoticeLogDao
 * @package app\common\dao\system\notice
 * @author xaboy
 * @day 2020/11/6
 */
class SystemNoticeLogDao extends BaseDao
{

    /**
     * @return string
     * @author xaboy
     * @day 2020/11/6
     */
    protected function getModel(): string
    {
        return SystemNoticeLog::class;
    }

    /**
     * 标记通知为已读
     *
     * 本函数用于更新指定通知的阅读状态，将通知的阅读状态设置为已读，并记录阅读时间。
     * 主要针对商家后台的操作通知，通过通知ID和商家ID定位到特定的通知记录，进行状态更新。
     *
     * @param int $id 通知记录ID，用于唯一标识一条通知。
     * @param int $merId 商家ID，标识通知所属的商家。
     * @return bool 更新操作的结果，成功返回true，失败返回false。
     */
    public function read($id, $merId)
    {
        // 使用where子句指定通知记录ID和商家ID，更新通知的阅读状态和阅读时间
        return SystemNoticeLog::getDB()->where('notice_log_id', $id)->where('mer_id', $merId)->update(['is_read' => 1, 'read_time' => date('Y-m-d H:i:s')]);
    }


    /**
     * 获取未读通知数量
     *
     * 本函数用于查询指定商户ID对应的未读系统通知的数量。
     * 通过筛选is_read字段为0的记录，确保只统计未读的通知。
     *
     * @param int $merId 商户ID，用于指定查询哪个商户的通知数量。
     * @return int 返回未读通知的数量。
     */
    public function unreadCount($merId)
    {
        // 使用SystemNoticeLog类的getDB方法获取数据库实例，并链式调用where方法设置查询条件，最后调用count方法统计符合条件的记录数。
        return SystemNoticeLog::getDB()->where('mer_id', $merId)->where('is_read', 0)->count();
    }

    /**
     * 删除指定通知日志
     *
     * 本函数用于根据通知日志ID和商户ID删除相应的通知日志记录。
     * 参数$id代表通知日志的唯一标识，$merId代表商户的标识。
     * 函数返回删除操作的结果，通常是一个布尔值，表示删除是否成功。
     *
     * @param int $id 通知日志ID
     * @param int $merId 商户ID
     * @return bool 删除操作的结果，成功返回true，失败返回false
     */
    public function del($id, $merId)
    {
        // 使用SystemNoticeLog类的getDB方法获取数据库操作对象，并构造删除条件
        // 其中where('notice_log_id', $id)指定了通知日志ID，where('mer_id', $merId)指定了商户ID
        // 最后执行删除操作并返回结果
        return SystemNoticeLog::getDB()->where('notice_log_id', $id)->where('mer_id', $merId)->delete();
    }


    /**
     * 根据条件搜索系统通知日志
     *
     * 本函数用于查询系统通知日志表中的数据，根据传入的条件进行过滤。条件包括商家ID、阅读状态、通知创建时间范围和关键词。
     * 查询结果将返回符合所有条件的通知日志记录。
     *
     * @param array $where 查询条件数组，包含各种过滤条件如商家ID、阅读状态、日期和关键词。
     * @return \think\Paginator 返回查询结果的分页对象，包含系统通知日志的数据。
     */
    public function search(array $where)
    {
        // 初始化系统通知日志的数据库查询
        return SystemNoticeLog::getDB()->alias('A')
            // 加入系统通知表，以便查询通知的详细信息
            ->join('SystemNotice B', 'A.notice_id = B.notice_id')
            // 过滤条件：商家ID
            ->where('mer_id', $where['mer_id'])
            // 当传入阅读状态条件时，添加对应过滤条件
            ->when(isset($where['is_read']) && $where['is_read'] !== '', function ($query) use ($where) {
                $query->where('A.is_read', intval($where['is_read']));
            })
            ->when(isset($where['notice_id']) && $where['notice_id'] !== '', function ($query) use ($where) {
                $query->where('A.notice_id', intval($where['notice_id']));
            })
            ->when(isset($where['notice_log_id']) && $where['notice_log_id'] !== '', function ($query) use ($where) {
                $query->where('A.notice_log_id', intval($where['notice_log_id']));
            })
            // 当传入日期条件时，添加对应过滤条件，用于查询指定日期范围的通知
            ->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['date'], 'B.create_time');
            })
            // 当传入关键词条件时，添加对应过滤条件，用于查询包含指定关键词的通知
            ->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->whereLike('B.notice_title|B.notice_content', "%{$where['keyword']}%");
            })
            // 过滤条件：删除状态，只返回未删除的通知日志
            ->where('A.is_del', 0)
            ->where('B.is_del', 0);
    }


}
