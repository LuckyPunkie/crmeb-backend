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


namespace app\common\dao\user;


use app\common\dao\BaseDao;
use app\common\model\user\UserRecharge;
use app\common\repositories\store\order\StoreOrderRepository;
use think\db\BaseQuery;

/**
 * Class UserRechargeDao
 * @package app\common\dao\user
 * @author xaboy
 * @day 2020/6/2
 */
class UserRechargeDao extends BaseDao
{

    /**
     * @return string
     * @author xaboy
     * @day 2020/6/2
     */
    protected function getModel(): string
    {
        return UserRecharge::class;
    }

    /**
     * 创建用户充值订单ID
     *
     * 本函数用于生成用户充值订单的唯一标识。订单ID由固定前缀、当前时间戳和自增序列组成，
     * 保证了订单ID的唯一性。其中，自增序列基于当天已创建的充值订单数量，确保了每天的订单号不重复。
     *
     * @param int $uid 用户ID。用于区分不同用户的订单。
     * @return string 返回生成的订单ID。
     */
    public function createOrderId($uid)
    {
        // 统计当天已创建的用户充值订单数量
        $count = (int)UserRecharge::getDB()->where('uid', $uid)->where('create_time', '>=', date("Y-m-d"))->where('create_time', '<', date("Y-m-d", strtotime('+1 day')))->count();

        // 生成订单ID，格式为：类型前缀 + 当前时间戳 + 用户ID + 当天订单计数
        return StoreOrderRepository::TYPE_SN_USER_RECHARGE . date('YmdHis', time()) . ($uid . $count);
    }

    /**
     * 计算用户的充值总额
     *
     * 本函数用于查询指定用户的所有已支付充值订单的总价，并返回这个总价。
     * 充值订单的数据通过UserRecharge模型来访问和操作。
     *
     * @param int $uid 用户ID
     *        本参数指定需要查询充值总额的用户，通过用户ID在数据库中定位到相应的充值记录。
     * @return float 用户的充值总额
     *         函数返回一个浮点数，代表指定用户的所有已支付充值订单的总价。
     */
    public function userRechargePrice($uid)
    {
        // 使用UserRecharge模型的getDB方法获取数据库实例
        // 然后通过where语句筛选出uid为$uid且paid为1（表示已支付）的充值记录
        // 最后使用sum方法计算这些记录的price列的总和，即用户的充值总额
        return UserRecharge::getDB()->where('uid', $uid)->where('paid', 1)->sum('price');
    }


    /**
     * 根据条件查询用户充值记录，并进行数据关联和字段筛选。
     * 本函数用于构建查询用户充值记录的SQL语句，通过加入条件来筛选特定的充值记录。
     *
     * @param array $where 查询条件数组，包含keyword（关键字）、paid（支付状态）、date（日期）等条件。
     * @return \think\db\Query 返回构建好的查询对象，可用于进一步操作或获取数据。
     */
    public function searchJoinQuery(array $where)
    {
        // 使用UserRecharge模型获取数据库对象，并设置表别名为a
        return UserRecharge::getDB()->alias('a')
            // 加入用户表b，根据uid进行关联
            ->join('User b', 'a.uid = b.uid')
            // 指定查询的字段，包括充值相关字段和用户相关字段
            ->field('a.paid,a.order_id,a.recharge_id,b.nickname,b.avatar,a.price,a.give_price,a.recharge_type,a.pay_time,a.recharge_type')
            // 当keyword条件存在且不为空时，添加模糊搜索条件，搜索昵称或订单号
            ->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->whereLike('b.nickname|a.order_id', "%{$where['keyword']}%");
            })
            // 当paid条件存在且不为空时，按支付状态进行筛选
            ->when(isset($where['paid']) && $where['paid'] !== '', function ($query) use ($where) {
                $query->where('a.paid', $where['paid']);
            })
            ->when(isset($where['recharge_type']) && $where['recharge_type'] !== '', function ($query) use ($where) {
                if ($where['recharge_type'] == 1) {
                    $query->whereIn('a.recharge_type', ['h5','weixin','routine']);
                } else {
                    $query->whereIn('a.recharge_type', 'alipay');
                }

            })
            ->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
                $query->where('b.uid', $where['uid']);
            })
            ->when(isset($where['real_name']) && $where['real_name'] !== '', function ($query) use ($where) {
                $query->whereLike('b.real_name', "%{$where['real_name']}%");
            })
            ->when(isset($where['nickname']) && $where['nickname'] !== '', function ($query) use ($where) {
                $query->whereLike('b.nickname', "%{$where['nickname']}%");
            })
            ->when(isset($where['phone']) && $where['phone'] !== '', function ($query) use ($where) {
                $query->whereLike('b.phone', "%{$where['phone']}%");
            })
            // 当date条件存在且不为空时，根据指定的日期范围筛选充值记录
            ->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['date'], 'a.create_time');
            });
    }

    /**
     * 计算所有已支付充值的总金额
     *
     * 本函数通过查询用户充值表中支付状态为已支付的记录，计算这些记录的充值金额总和。
     * 这样做的目的是为了获取系统中已实际完成充值交易的用户资金总流入额。
     *
     * @return float 返回已支付充值记录的金额总和
     */
    public function totalPayPrice()
    {
        // 使用UserRecharge模型的getDB方法获取数据库实例，并通过where条件筛选出支付状态为1（已支付）的记录，然后计算这些记录的price列的总和
        return UserRecharge::getDB()->where('paid', 1)->sum('price');
    }


    /**
     * 计算已退款的总金额
     *
     * 本函数用于查询并返回所有已支付订单的退款总金额。
     * 它通过查询UserRecharge表中paid状态为1（表示已支付）的订单的refund_price列的总和来实现。
     *
     * @return float 已退款的总金额
     */
    public function totalRefundPrice()
    {
        // 使用getDB方法获取数据库实例，并通过where子句筛选出已支付的订单，然后计算refund_price列的总和
        return UserRecharge::getDB()->where('paid', 1)->sum('refund_price');
    }


    /**
     * 计算所有常规充值的总价。
     *
     * 本函数通过查询用户充值记录，筛选出充值类型为常规且已支付的订单，
     * 然后计算这些订单的充值金额总和。
     *
     * @return float 返回所有常规充值的总价。
     */
    public function totalRoutinePrice()
    {
        // 使用UserRecharge模型的getDB方法获取数据库实例，并链式调用where方法筛选出充值类型为常规且已支付的记录，最后调用sum方法计算价格总和
        return UserRecharge::getDB()->where('paid', 1)->where('recharge_type', 'routine')->sum('price');
    }


    /**
     * 计算微信支付的总金额
     *
     * 本函数通过查询用户充值记录，筛选出支付状态为已支付且充值方式为H5或微信的记录，
     * 然后计算这些记录的充值金额总和。这个函数主要用于统计通过微信支付的总收入。
     *
     * @return float 返回微信支付的总金额
     */
    public function totalWxPrice()
    {
        // 使用UserRecharge模型的getDB方法获取数据库实例，并链式调用where和whereIn方法设定查询条件，最后调用sum方法计算价格总和
        return UserRecharge::getDB()->where('paid', 1)->whereIn('recharge_type', ['h5', 'wechat'])->sum('price');
    }

}
