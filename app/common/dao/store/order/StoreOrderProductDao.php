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
use app\common\model\store\order\StoreOrderProduct;
use think\facade\Db;
use app\common\repositories\store\order\OrderStatus;

/**
 * Class StoreOrderProductDao
 * @package app\common\dao\store\order
 * @author xaboy
 * @day 2020/6/10
 */
class StoreOrderProductDao extends BaseDao
{
    const ORDER_VERIFY_STATUS_ = 1;
    const ORDER_VERIFY_STATUS_SUCCESS = 3;
    /**
     * @return string
     * @author xaboy
     * @day 2020/6/10
     */
    protected function getModel(): string
    {
        return StoreOrderProduct::class;
    }

    /**
     * 根据用户ID和订单产品ID获取订单产品信息
     * 此函数用于查询特定用户订单中的特定产品详情，包括订单的基本信息。
     * @param int $id 订单产品ID，用于精确查询特定的订单产品。
     * @param int $uid 用户ID，用于查询特定用户下的订单产品。
     * @return StoreOrderProduct|null 返回符合查询条件的订单产品对象，如果未找到则返回null。
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function userOrderProduct($id, $uid)
    {
        // 使用StoreOrderProduct的数据库查询方法，根据订单产品ID和用户ID进行查询。
        // 同时，通过with方法加载订单信息，只查询状态为2的订单（表示已完成的订单），并只获取订单ID、订单号和商家ID。
        return StoreOrderProduct::getDB()->where('uid', $uid)->where('order_product_id', $id)->with(['orderInfo' => function ($query) {
            $query->field('order_id,order_sn,mer_id')->where('status', 2);
        }])->find();
    }

    /**
     * 计算未回复的售后产品数量
     *
     * 本函数用于查询特定订单中，未被回复的售后产品的数量。
     * 它通过筛选订单产品表中特定条件的记录，来得出未处理的售后产品数量。
     * 具体的筛选条件包括：订单ID、售后状态不为已退款、以及是否收到回复。
     *
     * @param string $orderId 订单ID
     * @return int 未回复的售后产品数量
     * @throws \think\db\exception\DbException
     */
    public function noReplyProductCount($orderId)
    {
        // 使用数据库查询工具，设定查询条件：特定订单ID、售后状态不为已退款、未回复
        // 然后计算满足条件的记录数量，返回这个数量作为未回复的售后产品数量。
        return StoreOrderProduct::getDB()->where('order_id', $orderId)->where('is_refund','<>','3')->where('is_reply', 0)
            ->count();
    }

    /**
     * 根据条件查询用户退款产品信息
     *
     * 本函数用于查询与用户退款相关的商品信息。它支持根据产品ID、用户ID、订单ID以及退款状态进行过滤。
     * 这些条件可以通过函数的参数进行定制，允许灵活地查询特定的退款产品数据。
     *
     * @param array $ids 产品ID列表，用于查询指定ID的产品信息
     * @param int $uid 用户ID，用于查询指定用户的相关产品信息
     * @param string|null $orderId 订单ID，可选，用于查询指定订单中的产品信息
     * @param int $refund_switch 退款开关，可选，用于筛选退款状态为开的产品信息
     * @return array 返回符合条件的退款产品信息列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function userRefundProducts(array $ids, $uid, $orderId = null,$refund_switch = 1)
    {
        // 获取数据库实例
        return StoreOrderProduct::getDB()
            // 当提供产品ID列表时，筛选出在列表中的产品
            ->when($ids,function($query) use($ids){
                $query->whereIn('order_product_id', $ids);
            })
            // 当提供订单ID时，筛选出属于该订单的产品
            ->when($orderId, function ($query) use($orderId) {
                 $query->where('order_id', $orderId);
            })
            // 当提供用户ID时，筛选出属于该用户的产品
            ->when($uid, function ($query) use($uid) {
                 $query->where('uid', $uid);
            })
            // 当提供退款开关时，筛选出退款开关为开启的产品
            ->when($refund_switch, function ($query) use($refund_switch) {
                $query->where('refund_switch', $refund_switch);
            })
            // 筛选出退款数量大于0的产品
            ->where('refund_num', '>', 0)->select();
    }


    /**
     * 根据指定条件分组统计商品订单数量及详情
     * 本函数用于查询指定日期、商家ID下的订单产品分组统计信息，返回每个产品的订单总数和产品详情。
     *
     * @param string $date 查询的日期范围，用于筛选指定日期内的订单。
     * @param int|null $merId 商家ID，用于筛选指定商家的订单。
     * @param int $limit 返回结果的数量限制，默认为7条。
     * @return array 返回符合查询条件的商品订单分组统计信息列表。
     */
    public function orderProductGroup($date, $merId = null, $limit = 7)
    {
        $query = StoreOrderProduct::getDB()->alias('A')->leftJoin('StoreOrder B', 'A.order_id = B.order_id')
            // 选择计算总订单数量和产品ID、购物车信息的字段
            ->field(Db::raw('sum(A.product_num) as total,A.product_id,cart_info'));
        // 分组权限
        if (app('request')->hasMacro('regionAuthority') && $region = app('request')->regionAuthority()) {
            $query->whereIn('B.mer_id', $region);
        }
        // 从StoreOrderProduct表中获取数据库对象
        return $query->withAttr('cart_info', function ($val) { // 使用withAttr处理cart_info字段，将其解析为数组
                return json_decode($val, true);
            })
            // 根据$date参数条件，添加查询时间范围的条件
            ->when($date, function ($query, $date) {
                getModelTime($query, $date, 'B.pay_time');
            })
            // 根据$merId参数条件，添加查询特定商家的条件
            ->when($merId, function ($query, $merId) {
                $query->where('B.mer_id', $merId);
            })
            // 筛选已支付的订单
            ->where('B.paid', 1)
            // 按产品ID分组
            ->group('A.product_id')
            // 限制返回结果的数量
            ->limit($limit)
            // 按总订单数量降序排序
            ->order('total DESC')
            // 执行查询并返回结果
            ->select();
    }

    /**
     * 计算指定日期内的商品销售总数
     *
     * 本函数通过查询数据库，统计指定日期内所有已支付订单的商品数量总和。
     * 使用左连接查询订单产品表和订单表，确保即使某些订单没有产品信息也能被包含在查询结果中。
     * 查询条件包括订单的支付时间在指定日期内且订单状态为已支付。
     *
     * @param string $date 指定的日期，用于查询该日期内的订单商品数量。
     * @return int 返回指定日期内的商品销售总数。
     */
    public function dateProductNum($date)
    {
        // 使用数据库查询语句，统计指定日期内已支付订单的商品总数
        return StoreOrderProduct::getDB()->alias('A')->leftJoin('StoreOrder B', 'A.order_id = B.order_id')->when($date, function ($query, $date) {
            // 分组权限
            if (app('request')->hasMacro('regionAuthority') && $region = app('request')->regionAuthority()) {
                $query->whereIn('B.mer_id', $region);
            }
            // 当传入日期时，条件查询订单的支付时间在指定日期内
            getModelTime($query, $date, 'B.pay_time');
        })->where('B.paid', 1)->sum('A.product_num');
    }


    /**
     *  用户购买活动商品数量
     * @param int $activityId
     * @param int $uid
     * @param int $orderType
     * @return int
     * @author Qinii
     * @day 2020-10-23
     */
    public function getUserPayCount(array $unique,$startWhere,$endWhere)
    {
        $query = StoreOrderProduct::hasWhere('orderInfo',function($query){
            //未支付
            $query->where('is_del',0)->whereOr(function($query){
                $query->where('paid',1)->where('is_del',1);
            });
        });
        $count = $query
            ->where('is_refund', '=', 0)
            ->whereTime('StoreOrderProduct.create_time', '>=', $startWhere)
            ->whereTime('StoreOrderProduct.create_time', '<=',$endWhere)
            ->where('product_type', '=', 1)
            ->where('product_sku', 'in', $unique)
            ->sum('product_num');
        return $count;
    }


    /**
     * 根据关键词和用户ID获取用户支付商品的查询构建器
     *
     * 此方法用于构建一个查询用户支付商品的查询构建器。它允许根据关键词过滤商品，
     * 专门筛选出产品类型为0的商品，并限定查询的商品属于指定的用户。
     *
     * @param string|null $keyword 搜索关键词，用于过滤商品名称包含该关键词的商品
     * @param int $uid 用户ID，用于限定查询的商品属于该用户
     * @return \Illuminate\Database\Eloquent\Builder|StoreOrderProduct 查询构建器，用于进一步的查询操作或数据获取
     */
    public function getUserPayProduct(?string  $keyword, int $uid)
    {
        // 初始化查询构建器，针对StoreOrderProduct表中spu列进行条件查询
        $query = StoreOrderProduct::hasWhere('spu',function($query) use($keyword){
            // 当关键词存在时，添加模糊搜索条件
            $query->when($keyword, function ($query) use($keyword) {
               $query->whereLike('store_name',"%{$keyword}%");
            });
            // 筛选产品类型为0的商品
            $query->where('product_type',0);
        });
        // 限定查询的商品属于指定的用户，并且产品类型为0
        $query->where('uid', $uid)->where('StoreOrderProduct.product_type',0);
        // 返回构建好的查询构建器
        return  $query;
    }



    /**
     *  统计已支付的订单 商品相关信息
     * @param int $mer_id
     * @param $type 统计类型 金额/数量/支付时间
     * @param $date 统计时间段
     * @param $limit
     * @return mixed
     * @author Qinii
     * @day 2023/11/28
     */
    public function getProductRate(int $mer_id, $date = '', $type = 'number', $limit = 10, string $group = 'P.product_id')
    {
        $query = StoreOrderProduct::getDB()->alias('P')->leftJoin('StoreOrder O', 'O.order_id = P.order_id')
            ->leftJoin('StoreProduct sp', 'sp.product_id = P.product_id')
            ->with(['product' => function ($query) {
                $query->field('product_id,store_name,image');
            }])->where('O.paid', 1);
        switch($type){
            case 'number':
                $field = "P.product_id,O.pay_price as number,sum(P.product_num) as count,P.create_time, P.order_id,O.mer_id";
                break;
            case 'count':
                $field = "P.product_id,sum(P.product_num) as count,O.pay_price as number,P.create_time, P.order_id,O.mer_id";
                break;
            case 'paytime':
                $field = 'P.product_id,O.pay_time paytime,O.pay_price as number,P.create_time, P.order_id,O.mer_id';
                break;
            default:
                $field = 'P.*';
                break;
        }
        $query->when($mer_id, function ($query) use($mer_id) {
            $query->where('O.mer_id', $mer_id);
        })->when($date, function($query) use ($date) {
            getModelTime($query, $date,'P.create_time');
        })->whereNotNull('sp.product_id');

        return $query->field($field)->when(!empty($group), function ($query) use ($group) {
            $query->group($group);
        })->order("$type DESC,P.create_time DESC")->limit($limit)->select();
    }

    public function getReservationSum($productId, $date, $reservationId)
    {
        // 使用数据库查询语句，统计指定日期内已支付订单的商品总数
        $stock = StoreOrderProduct::getDB()->alias('A')
            ->leftJoin('StoreOrder B', 'A.order_id = B.order_id')
            ->where(function($query){
               $query->where('is_del',0)->whereOr(function($query){
                    $query->where('is_del',1)->where('paid',1);
                });
            })
            ->where('B.status','<>',OrderStatus::ORDER_STATUS_REFUND)
            ->where('A.product_id', $productId)
            ->where('A.reservation_date', $date)
            ->where('A.reservation_id', $reservationId)
            ->sum('A.product_num');
        return $stock;
    }
}
