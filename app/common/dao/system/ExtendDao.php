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


namespace app\common\dao\system;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\Extend;

/**
 * Class ExtendDao
 * @package app\common\dao\system
 * @author xaboy
 * @day 2020-04-24
 */
class ExtendDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return Extend::class;
    }

    /**
     * 根据条件搜索扩展数据。
     *
     * 该方法用于根据传入的条件数组搜索数据库中的扩展数据。支持的条件包括关键字、类型、链接ID和商家ID。
     * 搜索功能通过链式调用实现，每种条件都是可选的，只有当条件存在且不为空时才会应用到查询中。
     *
     * @param array $where 搜索条件数组，包含关键字、类型、链接ID和商家ID等可能的条件。
     * @return object 返回构建好的查询对象，可用于进一步的查询操作或获取查询结果。
     */
    public function search(array $where)
    {
        // 获取数据库实例并开始构建查询条件
        return Extend::getDB()->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
            // 如果存在关键字，则使用LIKE查询条件
            $query->whereLike('extend_value', "%{$where['keyword']}%");
        })->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
            // 如果指定了类型，则添加类型查询条件
            $query->where('extend_type', $where['type']);
        })->when(isset($where['link_id']) && $where['link_id'] !== '', function ($query) use ($where) {
            // 如果指定了链接ID，则添加链接ID查询条件
            $query->where('link_id', (int)$where['link_id']);
        })->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            // 如果指定了商家ID，则添加商家ID查询条件
            $query->where('mer_id', (int)$where['mer_id']);
        });
    }

}
