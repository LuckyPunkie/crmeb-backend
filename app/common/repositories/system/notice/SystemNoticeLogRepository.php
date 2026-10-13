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


namespace app\common\repositories\system\notice;


use think\exception\ValidateException;
use app\common\dao\system\notice\SystemNoticeLogDao;
use app\common\repositories\BaseRepository;

/**
 * Class SystemNoticeLogRepository
 * @package app\common\repositories\system\notice
 * @author xaboy
 * @day 2020/11/6
 * @mixin SystemNoticeLogDao
 */
class SystemNoticeLogRepository extends BaseRepository
{
    public function __construct(SystemNoticeLogDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取通知列表
     *
     * 根据给定的条件和分页信息，从数据库中检索通知列表。此方法主要用于处理数据的查询和分页逻辑。
     *
     * @param array $where 查询条件，以键值对形式提供，用于指定查询的过滤条件。
     * @param int $page 当前页码，用于指定要返回的页码。
     * @param int $limit 每页的记录数，用于指定每页返回的通知数量。
     * @return array 返回包含通知数量和通知列表的数组。
     */
    public function getList(array $where, $page, $limit)
    {
        // 根据提供的条件进行查询
        $query = $this->dao->search($where);

        // 统计符合查询条件的通知总数
        $count = $query->count();

        // 获取指定页码和每页记录数的通知列表，按通知ID降序排列
        $list = $query->field('A.*,notice_title,type,type_str,B.create_time')->page($page, $limit)
            ->order('A.notice_log_id DESC')->select();

        // 返回包含通知数量和通知列表的数组
        return compact('count', 'list');
    }

    public function detail($id, $merId)
    {
        $res = $this->dao->search(['notice_log_id' => $id, 'mer_id' => $merId])->find();
        if (!$res) throw new ValidateException('数据不存在');
        return $res;
    }

}
