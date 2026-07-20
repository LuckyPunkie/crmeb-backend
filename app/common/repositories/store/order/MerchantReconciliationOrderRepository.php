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

namespace app\common\repositories\store\order;

use app\common\repositories\BaseRepository;
use app\common\dao\store\order\MerchantReconciliationOrderDao as dao;

class MerchantReconciliationOrderRepository extends BaseRepository
{
    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取订单ID列表
     *
     * 本函数通过调用DAO层的search方法，根据传入的$where条件查询订单信息，
     * 并返回查询结果中order_id列的内容作为订单ID列表。此功能主要用于
     * 需要根据特定条件批量获取订单ID的场景。
     *
     * @param string|array $where 查询条件，可以是字符串或数组形式的SQL WHERE子句条件。
     * @return array 返回符合条件的订单ID列表，如果无结果则返回空数组。
     */
    public function getIds($where)
    {
        // 调用DAO层的search方法进行查询，并指定返回结果中仅包含order_id列
        return $this->dao->search($where)->column('order_id');
    }
}
