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


use think\facade\Log;
use app\common\dao\store\order\StoreGroupOrderDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\store\coupon\StoreCouponUserRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserRepository;
use crmeb\jobs\CancelGroupOrderJob;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Queue;
use think\model\Relation;

/**
 * Class StoreGroupOrderRepository
 * @package app\common\repositories\store\order
 * @author xaboy
 * @day 2020/6/8
 * @mixin StoreGroupOrderDao
 */
class StoreGroupOrderRepository extends BaseRepository
{
    /**
     * StoreGroupOrderRepository constructor.
     * @param StoreGroupOrderDao $dao
     */
    public function __construct(StoreGroupOrderDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件数组 $where，以及分页信息 $page 和 $limit，从数据库中检索满足条件的数据列表。
     * 它首先计算满足条件的数据总数，然后根据分页信息和排序条件获取具体的数据页。
     * 数据返回时，会包含订单相关的详细信息，如订单ID、组订单ID、活动类型和支付价格，以及订单产品的详细信息。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页数据数量
     * @return array 返回包含数据总数和数据列表的数组
     */
    public function getList(array $where, $page, $limit)
    {
        // 根据条件搜索数据，此时不进行分页
        $query = $this->search($where,0);

        // 计算满足条件的数据总数
        $count = $query->count();

        // 设置查询时的关联加载，这里加载了订单列表，并指定了要加载的字段
        // 同时，订单列表中还进一步加载了订单产品和预售订单的详细信息
        $list = $query->with(['orderList' => function ($query) {
            $query->field('order_id,group_order_id,activity_type,pay_price')->with(['orderProduct','presellOrder']);
        }])->page($page, $limit)->order('create_time DESC')->select();

        // 返回包含数据总数和数据列表的数组
        return compact('count', 'list');
    }

    /**
     * 获取订单详情
     *
     * 本函数用于根据用户ID和订单ID获取特定订单的详细信息。
     * 可以选择是否加载订单相关的详细数据，如订单列表、商户信息等。
     * 这种加载是通过懒加载方式实现的，只有在标志位$flag为true时才会加载。
     *
     * @param int $uid 用户ID
     * @param int $id 订单组ID
     * @param bool $flag 标志位，控制是否加载详细数据
     * @return \think\Model|null 返回匹配条件的订单模型实例，如果没有找到则返回null
     */
    public function detail($uid, $id,$flag = true)
    {
        // 根据用户ID和订单ID查询订单信息，只查询未支付的订单
        return $this->search(['paid' => 0, 'uid' => $uid])->where('group_order_id', $id)
            ->with([
            'orderList' => function ($query) use ($flag) {
                // 当$flag为true时，指定查询订单的特定字段
                $query->when($flag, function ($query) {

                    $query->field('order_id,group_order_id,mer_id,order_sn,activity_type,pay_price,order_extend,order_type,is_virtual,merchant_take_info,merchant_take_id');
                });
                // 加载订单相关的商户信息，如果$flag为true，则只加载指定的商户字段，并附加服务类型信息
                $query->with([
                    'merchant' => function ($query) use ($flag) {
                        $flag && $query->field('mer_id,mer_name,service_phone')->append(['services_type'], true);
                    }, 'orderProduct', 'presellOrder','take'
                ]);
            }])->find();
    }


    /**
     * 查询用户的订单状态
     *
     * 本函数通过指定用户的ID和订单ID，检索并返回该用户的订单状态信息。
     * 主要用于在系统中查询用户的特定订单详情，特别是涉及到优惠券发放等附加信息时。
     *
     * @param int $uid 用户ID，用于指定查询的用户。
     * @param int $id 订单ID，用于指定查询的订单。
     * @return object 返回包含订单信息，如果找不到订单则为NULL。
     */
    public function status($uid, $id)
    {
        // 根据用户ID和订单ID查询订单信息，并附加'give_coupon'信息
        return $this->search(['uid' => $uid])->where('group_order_id', $id)->append(['give_coupon','orderList'], true)->find();
    }


    /**
     * 根据订单组ID获取已取消订单的详细信息
     *
     * 本函数旨在查询特定订单组中被取消的订单详情。它通过指定的订单组ID过滤数据，
     * 并返回满足条件（未支付且已删除）的第一个订单记录。查询结果包括订单的基本信息
     * 以及订单中的产品详情。
     *
     * @param int $id 订单组的ID
     * @return \think\Model|null 返回满足条件的订单模型对象，如果找不到符合条件的订单则返回null
     */
    public function getCancelDetail($id)
    {
        // 使用where方法指定查询条件（订单未支付且已删除），并指定订单组ID
        // 使用with方法加载订单列表中的产品信息
        // 最后使用find方法查询并返回满足条件的第一个订单记录
        return $this->search(['paid' => 0, 'is_del' => 1])->where('group_order_id', $id)->with(['orderList.orderProduct'])->find();
    }

    /**
     * 取消团单功能
     * 此函数用于取消未支付的团单。如果团单已支付，则无法取消。
     * @param int $id 团单ID
     * @param int|null $uid 用户ID，可选参数，用于指定操作用户
     * @throws ValidateException 如果团单不存在或已支付，则抛出验证异常
     */
    public function cancel($id, $uid = null)
    {
        // 查询未支付的团单信息，包括订单列表
        $groupOrder = $this->search(['paid' => 0, 'uid' => $uid ?? ''])->where('group_order_id', $id)->with(['orderList'])->find();
        // 如果团单不存在，则抛出异常
        if (!$groupOrder)
            throw new ValidateException('订单不存在');
        // 如果团单已支付，则抛出异常
        if ($groupOrder['paid'] != 0)
            throw new ValidateException('订单状态错误,无法删除');
        Log::info('取消订单ID：' . $id.',uid:'.$uid);
        // 使用事务处理来确保操作的完整性
        Db::transaction(function () use ($groupOrder, $id, $uid) {
            // 标记团单为已删除
            $groupOrder->is_del = 1;
            $orderStatus = [];

            // 如果团单涉及积分，退回积分给用户
            // 退回积分
            if ($groupOrder->integral > 0) {
                $make = app()->make(UserRepository::class);
                // 更新用户积分
                $make->update($groupOrder->uid, ['integral' => Db::raw('integral+' . $groupOrder->integral)]);
                // 记录积分变动
                app()->make(UserBillRepository::class)->incBill($groupOrder->uid, 'integral', 'cancel', [
                    'link_id' => $groupOrder['group_order_id'],
                    'status' => 1,
                    'title' => '退回积分',
                    'number' => $groupOrder['integral'],
                    'mark' => '订单自动关闭,退回' . intval($groupOrder->integral) . '积分',
                    'balance' => $make->get($groupOrder->uid)->integral
                ]);
            }

            // 遍历团单中的每个订单，进行处理
            // 订单记录
            $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
            foreach ($groupOrder->orderList as $order) {
                // 如果订单是预售订单，则将预售订单状态重置
                if ($order->activity_type == 3 && $order->presellOrder) {
                    $order->presellOrder->status = 0;
                    $order->presellOrder->save();
                }
                // 标记订单为已删除
                $order->is_del = 1;
                $order->save();
                // 记录订单状态变更
                $orderStatus[] = [
                    'order_id' => $order->order_id,
                    'order_sn' => $order->order_sn,
                    'type' => $storeOrderStatusRepository::TYPE_ORDER,
                    'change_message' => '取消订单',
                    'change_type' => $storeOrderStatusRepository::ORDER_STATUS_CANCEL,
                    'uid' => $uid ?:0 ,
                    'nickname' => $uid ? $order->user->nickname : '系统',
                    'user_type' => $uid ? $storeOrderStatusRepository::U_TYPE_USER : $storeOrderStatusRepository::U_TYPE_SYSTEM,
                ];
            }
            // 更新团单信息
            $groupOrder->save();
            // 批量创建订单状态日志
            $storeOrderStatusRepository->batchCreateLog($orderStatus);
        });

        // 将团单取消操作加入队列，异步处理
        Queue::push(CancelGroupOrderJob::class, $id);
    }


    /**
     * 检查是否为VIP优惠券
     *
     * 本函数用于确定传入的团购订单所使用的优惠券是否为VIP专属优惠券。
     * 它首先检查订单是否关联了优惠券ID，然后查询该优惠券的详细信息，最后判断该优惠券的类型是否为VIP专属。
     *
     * @param \App\Models\GroupOrder $groupOrder 团购订单对象，包含优惠券ID
     * @return bool 如果优惠券是VIP专属，则返回true；否则返回false。
     */
    public function isVipCoupon($groupOrder)
    {
        // 检查订单是否关联了优惠券ID，如果没有，则不是VIP优惠券，直接返回false
        if (!$groupOrder->coupon_id) {
            return false;
        }

        // 通过优惠券用户仓库查询优惠券ID，这一步是为了确认优惠券是否存在并有效
        $cid = app()->make(StoreCouponUserRepository::class)->query(['coupon_user_id' => $groupOrder->coupon_id])->value('coupon_id');

        // 如果查询到了优惠券ID，进一步通过优惠券仓库查询该优惠券的发送类型，以确定是否为VIP专属
        if ($cid) {
            return app()->make(StoreCouponRepository::class)->query(['coupon_id' => $cid])->value('send_type') === StoreCouponRepository::GET_COUPON_TYPE_SVIP;
        }

        // 如果没有查询到优惠券ID，或者查询的优惠券不是VIP专属，则返回false
        return false;
    }

    /**
     * 验证用户是否下了第一个订单
     *
     * 本函数用于判断指定用户的订单中是否存在已支付的第一个订单。
     * 如果存在已支付的订单，则说明用户已经下了第一个订单，返回0。
     * 如果不存在已支付的订单，则说明用户尚未下第一个订单，返回1。
     *
     * @param int $uid 用户ID
     *               用户的唯一标识符，用于查询订单信息。
     * @return int 返回0或1，表示用户是否已下第一个订单。
     *             0表示已下第一个订单，1表示尚未下第一个订单。
     */
    public function validateOrderIsFirst(int $uid)
    {
        // 查询已支付的订单数量
        // 如果返回值大于0，则说明存在已支付的订单，即用户已下第一个订单。
        // 如果返回值为0，则说明不存在已支付的订单，即用户尚未下第一个订单。
        return $this->search(['uid' => $uid, 'paid' => 1])->count() > 0 ? 0 : 1;
    }

}
