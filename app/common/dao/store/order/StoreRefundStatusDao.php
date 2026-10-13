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


namespace app\common\dao\store\order;


use app\common\dao\BaseDao;
use app\common\model\store\order\StoreRefundStatus;

class StoreRefundStatusDao extends BaseDao
{

    protected function getModel(): string
    {
        return StoreRefundStatus::class;
    }

    /**
     * 根据退款订单ID查询退款状态
     *
     * 本函数旨在通过退款订单ID从数据库中检索相应的退款状态信息。
     * 它使用了StoreRefundStatus类的getDB方法来获取数据库对象，并基于此对象构建一个查询，
     * 该查询专门针对refund_order_id字段与传入ID匹配的记录。
     *
     * @param int $id 退款订单的唯一标识符
     * @return \think\db\Query 查询结果，返回一个查询对象，可用于进一步的查询操作或获取数据
     */
    public function search($id)
    {
        // 根据传入的ID查询退款状态
        return $query = StoreRefundStatus::getDB()->where('refund_order_id', $id);
    }

}
