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
use app\common\model\BaseModel;
use app\common\model\store\order\StoreOrder;
use app\common\model\store\order\StoreRefundOrder;
use app\common\model\user\User;
use app\common\repositories\system\merchant\MerchantRepository;
use think\db\BaseQuery;
use think\db\exception\DbException;

class StoreRefundOrderDao extends BaseDao
{

    protected function getModel(): string
    {
        return StoreRefundOrder::class;
    }

    /**
     * 根据条件搜索退款订单
     *
     * @param array $where 搜索条件
     * @return \think\db\Query 查询结果对象
     */
    public function search(array $where)
    {
        // 当is_trader条件存在时，特定条件查询
        $query = StoreRefundOrder::hasWhere('merchant', function ($query) use ($where) {
            // 分组权限
            if (app('request')->hasMacro('regionAuthority') && $region = app('request')->regionAuthority()) {
                $query->whereIn('mer_id', $region);
            }
            $query->when(isset($where['is_trader']) && $where['is_trader'] !== '', function ($query) use ($where) {
                $query->where('is_trader', $where['is_trader']);
            });
        });

        // 添加条件查询：商家ID
        $query->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            $query->where('StoreRefundOrder.mer_id', $where['mer_id']);
        });
        // 分组权限
        if (app('request')->hasMacro('regionAuthority') && $region = app('request')->regionAuthority()) {
            $query->whereIn('StoreRefundOrder.mer_id', $region);
        }
        // 添加条件查询：订单号
        $query->when(isset($where['order_sn']) && $where['order_sn'] !== '', function ($query) use ($where) {
            $ids = StoreOrder::where('order_sn', 'like', '%' . $where['order_sn'] . '%')->column('order_id');
            $query->where('order_id', 'in', $ids);
        });
        // 添加条件查询：退款订单号
        $query->when(isset($where['refund_order_sn']) && $where['refund_order_sn'] !== '', function ($query) use ($where) {
            $query->where('refund_order_sn', 'like', '%' . $where['refund_order_sn'] . '%');
        });
        // 添加条件查询：订单状态
        $query->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            $query->where('status', $where['status']);
        });
        // 添加条件查询：用户ID
        $query->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
            $query->where('uid', $where['uid']);
        });
        // 添加条件查询：退款订单ID
        $query->when(isset($where['id']) && $where['id'] !== '', function ($query) use ($where) {
            $query->where('refund_order_id', $where['id']);
        });
        // 添加条件查询：删除状态
        $query->when(isset($where['is_del']) && $where['is_del'] !== '', function ($query) use ($where) {
            $query->where('StoreRefundOrder.is_del', $where['is_del']);
        });
        // 添加条件查询：订单类型
        $query->when(isset($where['type']) && $where['type'] == 1, function ($query) {
            $query->whereIn('StoreRefundOrder.status', [0, 1, 2, 4]);
        });
        // 添加条件查询：退款类型
        $query->when(isset($where['type']) && $where['type'] == 2, function ($query) {
            $query->whereIn('StoreRefundOrder.status', [-1, 3, -10]);
        });
        // 添加条件查询：退款方式
        $query->when(isset($where['refund_type']) && $where['refund_type'] !== '', function ($query) use ($where) {
            $query->where('refund_type', $where['refund_type']);
        });
        // 添加条件查询：日期范围
        $query->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
            getModelTime($query, $where['date'], 'StoreRefundOrder.create_time');
        });
        $query->when(isset($where['mom_date']) && $where['mom_date'] !== '', function ($query) use ($where) {
            momTime($query, $where['mom_date'], 'StoreRefundOrder.create_time');
        });
        // 添加条件查询：订单ID
        $query->when(isset($where['order_id']) && $where['order_id'] !== '', function ($query) use ($where) {
            $query->where('order_id', $where['order_id']);
        });
        // 添加条件查询：配送单ID
        $query->when(isset($where['delivery_id']) && $where['delivery_id'] !== '', function ($query) use ($where) {
            $query->where('delivery_id', $where['delivery_id']);
        });
        // 添加条件查询：用户类型
        $query->when(isset($where['user_type']) && $where['user_type'] !== '', function ($query) use ($where) {
            $query->where('user_type', $where['user_type']);
        });
        // 添加条件查询：用户名
        $query->when(isset($where['username']) && $where['username'] !== '', function ($query) use ($where) {
            $uid = User::whereLike('nickname|phone|real_name', "%{$where['username']}%")->column('uid');
            $query->whereIn('uid', $uid);
        });

        $query->when(isset($where['phone']) && $where['phone'] !== '', function ($query) use ($where) {
            $uid = User::whereLike('phone', "%{$where['phone']}%")->column('uid');
            $query->whereIn('uid', $uid);
        });
        $query->when(isset($where['nickname']) && $where['nickname'] !== '', function ($query) use ($where) {
            $uid = User::whereLike('nickname', "%{$where['nickname']}%")->column('uid');
            $query->whereIn('uid', $uid);
        });
        $query->when(isset($where['real_name']) && $where['real_name'] !== '', function ($query) use ($where) {
            $uid = User::whereLike('real_name', "%{$where['real_name']}%")->column('uid');
            $query->whereIn('uid', $uid);
        });
        // 按创建时间降序排序
        return $query->order('create_time DESC');
    }

    /**
     * 根据ID获取单个订单信息
     *
     * 本函数通过指定的订单ID，查询并返回相应的订单数据。查询过程中，会同时加载订单相关的多个关联数据，
     * 包括退款产品、用户信息、订单产品等，以提供更完整的订单详情。
     *
     * @param int $id 订单的主键ID
     * @return \think\Model|null 返回查询到的订单模型实例，如果未找到则返回null
     */
    public function getOne($id)
    {
        // 使用动态模型方式查询订单，根据ID定位特定订单
        $res = $this->getModel()::where($this->getPk(), $id)
            // 加载订单关联的退款产品及其产品信息
            ->with([
                'refundProduct.product',
                // 加载订单的用户信息，但只获取uid、nickname和phone字段
                'user' => function ($query) {
                    $query->field('uid, nickname, phone');
                },
                // 加载订单关联的产品信息
                'order.orderProduct', 'platform'
            ])
            // 查找并返回订单数据
            ->find();
        // 添加附加信息'create_user'到返回结果中
        if ($res) $res->append(['create_user']);
        return $res;
    }

    /**
     * 检查数据库中是否存在满足条件的字段
     *
     * 本函数用于通过指定的条件查询数据库，以确定是否存在满足条件的字段。
     * 它首先获取模型对应的数据库实例，然后使用where方法应用条件，最后通过count方法统计满足条件的记录数。
     * 如果记录数大于0，则表示存在满足条件的字段；否则，表示不存在。
     *
     * @param array|string $where 查询条件，可以是数组或字符串形式。
     * @return bool 如果存在满足条件的字段返回true，否则返回false。
     */
    public function getFieldExists($where)
    {
        // 获取模型对应的数据库实例，并应用查询条件，然后统计满足条件的记录数
        return (($this->getModel()::getDB())->where($where)->count()) > 0;
    }


    /**
     * 删除用户的退款订单
     *
     * 本函数用于将特定用户的特定退款订单标记为已删除，并更新其状态时间。
     * 只有处于特定状态（3）的订单才能被删除。
     *
     * @param int $uid 用户ID，表示订单所属的用户。
     * @param int $id 退款订单ID，表示要删除的具体退款订单。
     * @return bool 返回更新操作的结果，成功为true，失败为false。
     * @throws DbException
     */
    public function userDel($uid, $id)
    {
        // 根据用户ID、退款订单ID和状态查询退款订单，并更新删除状态和删除时间
        return StoreRefundOrder::getDB()->where('uid', $uid)->where('refund_order_id', $id)->where('status', 3)->update(['is_del' => 1, 'status_time' => date('Y-m-d H:i:s')]);
    }


    /**
     * 获取超时的退款订单ID列表
     * 此函数用于查询在指定时间之前，满足特定退款状态的订单的退款订单ID。
     * 具体来说，它查询了两种情况：一是退款类型为1且状态为0的订单；
     * 二是退款类型为2且状态为2的订单。
     * @param int $time 用于比较的超时时间点，订单状态时间必须小于等于这个时间才算超时。
     * @return array 返回一个包含超时退款订单ID的数组。
     */
    public function getTimeOutIds($time)
    {
        // 从模型中获取数据库实例，并构造查询条件
        // 首先筛选status_time小于等于指定时间的记录
        return ($this->getModel()::getDB())->where('status_time', '<=', $time)
            // 然后通过嵌套的where子句，分别查询两种特定条件的订单
            ->where(function ($query) {
                // 第一种条件：退款类型为1，且状态为0
                $query->where(function ($query) {
                    $query->where('refund_type', 1)->where('status', 0);
                })->whereOr(function ($query) {
                    // 第二种条件：退款类型为2，且状态为2
                    $query->where('refund_type', 2)->where('status', 2);
                });
            })->column('refund_order_id');
        // 最终返回满足条件的订单的退款订单ID列表
    }


    /**
     * 用于调和数据更新的函数。
     * 此函数旨在将指定的调和ID列表中的所有调和ID更新为0。
     * 这是一个具体的操作数据库的函数，它依赖于getModel方法来获取数据库实例。
     *
     * @param array $reconciliation_id 调和ID的数组，这些ID将被更新为0。
     * @return int 返回影响的行数，表示更新操作的结果。
     */
    public function reconciliationUpdate($reconciliation_id)
    {
        // 通过模型获取数据库实例，并使用whereIn方法指定更新条件，然后更新reconciliation_id为0
        return ($this->getModel()::getDB())->whereIn('reconciliation_id', $reconciliation_id)->update(['reconciliation_id' => 0]);
    }


    /**
     * 根据订单ID数组计算退款总额
     *
     * 本函数通过查询数据库，计算指定订单ID列表中状态为3（假设代表已退款）的订单的退款总额。
     * 使用了whereIn和where方法来筛选订单ID和订单状态，sum方法用于计算退款金额总和。
     *
     * @param array $orderIds 订单ID数组，用于查询指定订单的退款金额
     * @return float 返回计算得到的退款总额
     */
    public function refundPirceByOrder(array $orderIds)
    {
        // 调用getModel方法获取模型实例，然后通过该实例的getDB方法获取数据库连接
        // 使用whereIn方法筛选出订单ID在指定数组中的记录，再使用where方法筛选出状态为3的记录
        // 最后使用sum方法计算这些记录的refund_price列的总和，并返回该值
        return $this->getModel()::getDB()->whereIn('order_id', $orderIds)->where('status', 3)->sum('refund_price');
    }


}
