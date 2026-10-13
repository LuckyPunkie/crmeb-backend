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
use app\common\model\store\order\StoreGroupOrder;

/**
 * Class StoreGroupOrderDao
 * @package app\common\dao\store\order
 * @author xaboy
 * @day 2020/6/9
 */
class StoreGroupOrderDao extends BaseDao
{

    /**
     * @return string
     * @author xaboy
     * @day 2020/6/9
     */
    protected function getModel(): string
    {
        return StoreGroupOrder::class;
    }

    /**
     * 计算用户未支付订单的数量
     *
     * 本函数用于查询并返回指定用户（如果提供了UID）的未支付订单数量。
     * 未支付订单是指那些标记为未删除（is_del = 0）且尚未付款（paid = 0）的订单。
     *
     * @param int|null $uid 用户ID。如果为null，则查询所有用户的未支付订单数量；如果提供了具体的UID，则只查询该用户的未支付订单数量。
     * @return int 返回未支付订单的数量。注意，这个数量仅仅是未删除且未支付的订单数量。
     * @throws \think\db\exception\DbException
     */
    public function orderNumber($uid = null)
    {
        // 使用search方法查询满足条件（未删除、未支付）的订单，并统计数量
        return $this->search(['uid' => $uid,'is_del' => 0,'paid' => 0],0)->count();
    }

    /**
     * 根据条件搜索店铺分组订单
     *
     * 此函数用于根据提供的条件搜索店铺分组订单。它支持搜索已支付、用户ID、是否为积分订单、以及是否已删除的订单。
     * 通过条件构造查询语句，并根据这些条件来过滤订单数据。
     *
     * @param array $where 搜索条件数组，包含paid、uid、is_del等键值对
     * @param null $is_points 是否为积分订单，null表示不筛选，true/false分别表示是/否
     * @return \think\db\BaseQuery 查询构建器或StoreGroupOrder实例，用于进一步的查询操作或数据获取
     */
    public function search(array $where,$is_points = null)
    {
        // 获取数据库实例并根据条件逐步构建查询语句
        $query = StoreGroupOrder::getDB()
            // 当paid字段在$where数组中且不为空时，添加where条件筛选支付状态
            ->when(isset($where['paid']) && $where['paid'] !== '', function ($query) use ($where) {
                $query->where('paid', $where['paid']);
            })
            // 当uid字段在$where数组中且不为空时，添加where条件筛选用户ID
            ->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
                $query->where('uid', $where['uid']);
            })
            ->when(isset($where['is_first']) && $where['is_first'] !== '', function ($query) use ($where) {
                $query->where('is_first', $where['is_first']);
            })
            // 当$is_points不为null时，根据$is_points的值添加where条件筛选是否为积分订单
            ->when(!is_null($is_points), function ($query) use ($is_points) {
                if ($is_points) {
                    $query->where('activity_type', 20);
                } else {
                    $query->where('activity_type', '<>',20);
                }
            })
            // 当is_del字段在$where数组中且不为空时，添加where条件筛选删除状态；否则，添加默认的where条件筛选未删除的订单
            ->when(isset($where['is_del']) && $where['is_del'] !== '', function ($query) use ($where) {
                $query->where('is_del', $where['is_del']);
            }, function ($query) {
                $query->where('is_del', 0);
            })
            ->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['date'], 'create_time');
            })
            ->when(isset($where['mom_date']) && $where['mom_date'] !== '', function ($query) use ($where) {
                momTime($query, $where['mom_date'], 'create_time');
            });
        // 返回按照创建时间降序排序的查询构建器，用于进一步的查询操作或数据获取
        return $query->order('create_time DESC');
    }

    /**
     * 获取超时订单ID列表
     * 此函数用于查询并返回在指定时间之前创建的、未支付的订单ID列表。如果指定了需要提醒的订单，则只返回尚未提醒的订单ID。
     *
     * @param int $time 查询的截止时间，以时间戳形式表示。
     * @param bool $is_remind 是否查询需要提醒的订单，默认为false，表示不查询需要提醒的订单。
     * @return array 返回符合条件的订单ID列表。
     */
    public function getTimeOutIds($time, $is_remind = false)
    {
        // 根据条件构造查询语句，查询未删除、未支付的订单
        return StoreGroupOrder::getDB()->where('is_del', 0)->where('paid', 0)
            // 如果需要提醒，添加额外的查询条件，查询未提醒的订单
            ->when($is_remind, function ($query) {
                $query->where('is_remind', 0);
            })
            // 查询创建时间早于等于指定时间的订单
            ->where('create_time', '<=', $time)
            // 返回满足条件的订单的group_order_id列
            ->column('group_order_id');
    }


    /**
     * 设置团购订单的提醒状态
     *
     * 本函数用于将指定团购订单的提醒状态设置为已提醒（1）。通过传入订单ID，查询到该订单后进行状态更新。
     * 主要用于在用户需要时，对特定团购订单设置提醒标记，以便系统或用户知道该订单已经进行过提醒操作。
     *
     * @param int $id 团购订单的ID
     * @return bool 更新操作的结果，成功返回true，失败返回false
     * @throws \think\db\exception\DbException
     */
    public function isRemind($id)
    {
        // 使用StoreGroupOrder类的静态方法getDB来获取数据库操作对象，并根据group_order_id为$id更新is_remind字段为1
        return StoreGroupOrder::getDB()->where('group_order_id', $id)->update(['is_remind' => 1]);
    }

    /**
     * 计算用户当前未支付订单的总金额
     *
     * 本函数通过查询数据库中指定用户的未支付订单，计算出这些订单的总支付价格。
     * 未支付订单是指支付类型（pay_type）为0的订单。
     *
     * @param int $uid 用户ID
     * @return float|int 返回用户未支付订单的总金额，如果没有未支付订单则返回0。
     */
    public function totalNowMoney($uid)
    {
        // 使用StoreGroupOrder的数据库实例，并指定查询条件为支付类型为0且用户ID为$uid的订单，计算这些订单的支付价格总和
        return StoreGroupOrder::getDB()->where('pay_type', 0)->where('uid', $uid)->sum('pay_price') ?: 0;
    }
}
