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


use app\common\dao\store\order\StoreOrderStatusDao;
use app\common\dao\store\order\StoreRefundStatusDao;
use app\common\repositories\BaseRepository;

/**
 * Class StoreRefundStatusRepository
 * @package app\common\repositories\store\order
 * @author xaboy
 * @day 2020/6/12
 */
class StoreRefundStatusRepository extends BaseRepository
{
    //已发货
    const CHANGE_BACK_GOODS = 'back_goods';
    //创建退款单
    const CHANGE_CREATE = 'create';
    //删除记录
    const CHANGE_DELETE = 'delete';
    //退款申请已通过
    const CHANGE_REFUND_AGREE = 'refund_agree';
    //退款成功
    const CHANGE_REFUND_PRICE = 'refund_price';
    //订单退款已拒绝
    const CHANGE_REFUND_REFUSE = 'refund_refuse';
    //用户取消退款
    const CHANGE_REFUND_CANCEL = 'refund_cancel';
    /**
     * StoreRefundStatusRepository constructor.
     * @param StoreRefundStatusDao $dao
     */
    public function __construct(StoreRefundStatusDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 更新退款订单状态
     *
     * 本函数用于根据给定的参数更新退款订单的状态。它通过创建一个新的数据对象来反映状态的变更，
     * 并依赖于数据访问对象（DAO）来执行实际的数据操作。
     *
     * @param int $refund_order_id 退款订单的唯一标识符。用于确定哪个退款订单的状态需要更新。
     * @param string $change_type 状态变更的类型。表示退款订单经历了什么样的变化。
     * @param string $change_message 状态变更的详细信息或描述。提供关于状态变更的额外上下文。
     *
     * @return \app\common\dao\BaseDao|bool|\think\Model
     */
    public function status($refund_order_id, $change_type, $change_message)
    {
        // 将相关参数紧凑地封装到一个数组中，并通过数据访问对象（DAO）创建一个新的数据对象（通常是数据库记录）
        // 来反映退款订单的状态更新。
        return $this->dao->create(compact('refund_order_id', 'change_message', 'change_type'));
    }

    /**
     * 根据给定的ID进行搜索，并分页返回结果。
     *
     * 本函数旨在通过一个特定的ID值，从数据库中检索相关信息。
     * 它支持分页查询，以优化大型数据集的处理，避免一次性加载过多数据导致的性能问题。
     *
     * @param int $id 需要搜索的ID值。这是查询的主键。
     * @param int $page 当前的页码。用于指定要返回哪一页的结果。
     * @param int $limit 每页显示的记录数。用于控制分页查询时每页的条目数量。
     * @return array 返回一个包含 'count' 和 'list' 两个元素的数组。
     *               'count' 表示总记录数，'list' 是当前页的记录列表。
     */
    public function search($id, $page, $limit)
    {
        // 使用DAO（数据访问对象）根据ID执行搜索查询。
        $query = $this->dao->search($id);

        // 计算查询结果的总记录数。
        $count = $query->count();

        // 根据当前页码和每页记录数，从查询结果中获取分页后的数据列表。
        $list = $query->page($page, $limit)->select();

        // 将总记录数和分页后的数据列表作为一个数组返回。
        return compact('count', 'list');
    }
}
