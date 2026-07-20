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


namespace app\common\dao\system\admin;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\admin\Log;
use think\db\BaseQuery;
use app\common\model\system\admin\Admin;
use app\common\repositories\system\admin\AdminRepository;

/**
 * Class LogDao
 * @package app\common\dao\system\admin
 * @author xaboy
 * @day 2020-04-16
 */
class LogDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return Log::class;
    }

    /**
     * @param array $where
     * @param $merId
     * @return BaseQuery
     * @author xaboy
     * @day 2020-04-16
     */
    /**
     * 根据条件搜索日志记录
     *
     * 本函数用于查询特定条件下的日志记录。它支持多个查询条件，包括商家ID、日期、操作方法、管理员ID以及创建时间范围。
     * 通过灵活组合这些条件，可以精确地定位到特定的日志数据。
     *
     * @param array $where 查询条件数组，包含各种可能的搜索参数。
     * @param int $merId 商家ID，用于限定查询的日志所属商家。
     * @return \Illuminate\Database\Query\Builder|BaseQuery
     */
    public function search(array $where, $merId)
    {
        // 初始化查询，指定查询商家ID为$merId的日志数据
        $query = Log::getDB()->where('mer_id', $merId);

        // 如果指定了查询日期，则进一步限定查询日期范围
        $query->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
            // 调用辅助函数处理日期查询条件
            getModelTime($query, $where['date']);
        });

        // 如果指定了操作方法，则进一步限定查询特定操作方法的日志
        if (isset($where['method']) && $where['method'] !== '') {
            $query->where('method', $where['method']);
        }

        // 如果指定了管理员ID，则进一步限定查询特定管理员操作的日志
        if (isset($where['admin_id']) && $where['admin_id'] !== '') {
            $query->where('admin_id', $where['admin_id']);
        }
        // 如果指定了管理员ID，则进一步限定查询特定管理员操作的日志
        if (isset($where['keyword']) && $where['keyword'] !== '') {
            $id = Admin::whereLike('admin_id|real_name', "%{$where['keyword']}%")->column('admin_id');
            $query->whereIn('admin_id', $id);
        }

        // 如果同时指定了开始时间和结束时间，则进一步限定查询在指定时间范围内的日志
        if (isset($where['section_startTime']) && $where['section_startTime'] && isset($where['section_endTime']) && $where['section_endTime']) {
            $query->where('create_time', '>', $where['section_startTime'])->where('create_time', '<', $where['section_endTime']);
        }

        // 返回构建好的查询条件，可用于进一步的查询操作或数据获取
        return $query;
    }
}
