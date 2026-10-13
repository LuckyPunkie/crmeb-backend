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
namespace app\common\repositories\analytics;

use think\facade\Db;
use think\facade\Cache;
use crmeb\services\CacheService;
use app\common\dao\store\order\StoreOrderDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\user\UserVisitRepository;
use app\common\repositories\user\UserRelationRepository;
use app\common\repositories\store\order\StoreCartRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\order\StoreRefundOrderRepository;

/**
 * 订单
 */
class orderAnalyticsRepository extends BaseRepository
{

    public static $formatMap = [
        'week' => '%m-%d',    // 按日期
        'month' => '%d',   // 按日期
        'year' => '%m',       // 按年月
        'quarter' => '%m',    // 按年月
        'lately7' => '%m-%d', // 按日期
        'lately30' => '%m-%d' // 按日期
    ];

    /**
     * StoreOrderRepository constructor.
     * @param StoreOrderDao $dao
     */
    public function __construct(StoreOrderDao $dao)
    {
        $this->dao = $dao;
    }


    const CACHA_TIME = 120;
    /**
     * 订单实付金额
     * 用券金额
     * 退款金额
     * 支付订单数
     * 退款订单数
     */
    public function top($date, $merId = '')
    {
        $adminId = app('request')->adminId() ?: '';
        [$cacheky, $cacheTags] = CacheService::setWithTags(
            CacheService::ANALYTICS_ORDER_TOP,
            compact('merId','date', 'adminId')
        );

        return Cache::tag($cacheTags)->remember($cacheky, function () use ($date, $merId) {
            $merId = $merId ? : '';
            $query = $this->dao->search(['date' => $date, 'mer_id' => $merId, 'paid' => 1]);
            $momQuery = $this->dao->search(['mom_date' => $date, 'mer_id' => $merId, 'paid' => 1]);
            //支付订单数 & 环比
            $payOrderNum = $query->count();
            $momPayOrderNum = $momQuery->count();

            //订单实付金额
            $payMoney = $query->sum('pay_price');
            $momPayMoney = $momQuery->sum('pay_price');

            //用券金额
            $couponMoney = $query
                ->where(function($query){
                    $query->where('coupon_price','>',0)->whereOr('platform_coupon_price > 0');
                })
                ->sum(Db::raw('platform_coupon_price + coupon_price'));

            $momCouponMoney = $momQuery
                ->where(function($query){
                    $query->where('coupon_price','>',0)->whereOr('platform_coupon_price > 0');
                })
                ->sum(Db::raw('platform_coupon_price + coupon_price'));


            $refundOrderRepository = app()->make(StoreRefundOrderRepository::class);
            $refundQuery = $refundOrderRepository->search(['date' => $date, 'mer_id' => $merId]);
            $momRefundQuery = $refundOrderRepository->search(['mom_date' => $date, 'mer_id' => $merId]);

            //退款金额
            $refundMoney = $refundQuery->where('StoreRefundOrder.status',3)->sum('refund_price');
            $momRefundMoney = $momRefundQuery->where('StoreRefundOrder.status',3)->sum('refund_price');
            //退款订单数
            $refundOrderNum = $refundQuery->where('StoreRefundOrder.status','<>',-1)->count();
            $momRefundOrderNum = $momRefundQuery->where('StoreRefundOrder.status','<>',-1)->count();
            $redata = [
                [
                    'title' => "支付订单数",
                    'count' => $payOrderNum,
                    'mom'   => $momPayOrderNum,
                    'statistic' => growthRate($payOrderNum,$momPayOrderNum)
                ],
                [
                    'title' => "订单实付金额",
                    'count' => $payMoney,
                    'mom'   => $momPayMoney,
                    'statistic' => growthRate($payMoney,$momPayMoney)
                ],
                [
                    'title' => "用券金额",
                    'count' => $couponMoney,
                    'mom'   => $momCouponMoney,
                    'statistic' => growthRate($couponMoney,$momCouponMoney)
                ],
                [
                    'title' => "退款金额",
                    'count' => $refundMoney,
                    'mom'   => $momRefundMoney,
                    'statistic' => growthRate($refundMoney,$momRefundMoney)
                ],
                [
                    'title' => "退款订单数",
                    'count' => $refundOrderNum,
                    'mom'   => $momRefundOrderNum,
                    'statistic' => growthRate($refundOrderNum,$momRefundOrderNum)
                ],
            ];
            return $redata;
        },self::CACHA_TIME);
    }


    public function lineChart($date = '', $merId = '')
    {
        $adminId = app('request')->adminId() ?: '';
        [$cacheky, $cacheTags] = CacheService::setWithTags(
            CacheService::ANALYTICS_ORDER_LINE_CHART,
            compact('merId','date', 'adminId')
        );

        return Cache::tag($cacheTags)->remember($cacheky, function () use ($date, $merId) {
            $merId = $merId ? : '';
            [$dates, $customFormat] = getStepLength($date);
            $format = $customFormat ? $customFormat : self::$formatMap[$date] ?? '%m-%d';

            $field = Db::raw("from_unixtime(unix_timestamp(StoreOrder.create_time),'{$format}') as month,sum(pay_price) as pay_price,count(*) as order_num");
            $res = $this->dao->search(['date' => $date, 'mer_id' => $merId, 'paid' => 1])
                ->field($field)
                ->group("month")
                ->select()->toArray();
            $field = Db::raw("from_unixtime(unix_timestamp(StoreRefundOrder.create_time),'{$format}') as month,sum(refund_price) as refund_price,count(*) as refund_num");
            $refundOrderRepository = app()->make(StoreRefundOrderRepository::class);
            $refundRes = $refundOrderRepository->search(['date' => $date, 'mer_id' => $merId])
                ->field($field)
                ->group("month")
                ->select()->toArray();
            $data = [];
            foreach ($res as $re) {
                $data[$re['month']] = ['xaxis' => $re['month'], 'pay_price' => $re['pay_price'], 'order_num' => $re['order_num'],];
            }
            foreach ($refundRes as $re) {
                if (isset($data[$re['month']])){
                    $data[$re['month']]['refund_price'] = $re['refund_price'];
                    $data[$re['month']]['refund_num'] = $re['refund_num'];
                } else {
                    $data[$re['month']] = [
                        'xaxis' => $re['month'],
                        'pay_price' => 0,
                        'order_num'=> 0,
                        'refund_price' =>$re['refund_price'],
                        'refund_num' => $re['refund_num'],
                    ];
                }
            }
            $redata =  [];
            foreach ($dates as $mo) {
                $redata[] = isset($data[$mo]) ? $data[$mo] : [
                    'xaxis' => $mo,
                    'pay_price' => 0,
                    'order_num'=> 0,
                    'refund_price' => 0,
                    'refund_num' => 0,
                ];
            }
            return $redata;
        }, self::CACHA_TIME);
    }

    public function typePieCahrt($date = '', $merId = '')
    {
        /**
         * 获取订单数量
         * 发货类型(1:发货 2: 送货 3: 虚拟,4电子面单，5同城 6 卡密自动发货)
         */
        $adminId = app('request')->adminId() ?: '';
        [$cacheky, $cacheTags] = CacheService::setWithTags(
            CacheService::ANALYTICS_ORDER_DELIVER_PIE,
            compact('merId','date', 'adminId')
        );
        return Cache::tag($cacheTags)->remember($cacheky, function () use ($date, $merId) {
            $merId = $merId ? : '';
        //1 快递 2 配送 3 虚拟
        //按发货方式：1快递订单、2配送订单、4核销订单、3虚拟发货、6自动发货
            $where = ['date' => $date, 'mer_id' => $merId, 'paid' => 1];
            $count1 = $this->dao->search($where + ['filter_delivery' => 1])->count('*');
            $count2 = $this->dao->search($where + ['filter_delivery' => 2])->count('*');
            $count3 = $this->dao->search($where + ['filter_delivery' => 3])->count('*');
            $count4 = $this->dao->search($where + ['filter_delivery' => 4])->count('*');
            $count5 = $this->dao->search($where + ['filter_delivery' => 5])->count('*');

            return [
                ['name' => '快递发货', 'value' => $count1,],
                ['name' => '配送订单', 'value' => $count2,],
                ['name' => '虚拟发货', 'value' => $count3,],
                ['name' => '核销订单', 'value' => $count4,],
                ['name' => '自动发货', 'value' => $count5,],
            ];
        }, self::CACHA_TIME);
    }

    public function delivePieCahrt($date = '', $merId = '')
    {
        /**
         * 获取订单数量
         * 普通、秒杀、预售、砍价、拼团、积分、套餐、新人
         */
        $adminId = app('request')->adminId() ?: '';
        [$cacheky, $cacheTags] = CacheService::setWithTags(
            CacheService::ANALYTICS_ORDER_TYPE_PIE,
            compact('merId','date', 'adminId')
        );
        return Cache::tag($cacheTags)->remember($cacheky, function () use ($date, $merId) {
            $merId = $merId ? : '';
            $query = $this->dao->search(['date' => $date, 'mer_id' => $merId, 'paid' => 1]);
            $count = $query->where('activity_type',0)->count('*');
            $count1 = $query->where('activity_type',1)->count('*');
            $count2 = $query->where('activity_type',2)->count('*');
            $count3 = $query->where('activity_type',3)->count('*');
            $count4 = $query->where('activity_type',4)->count('*');
            $count10 = $query->where('activity_type',10)->count('*');
            $countfirst = $this->dao->joinGroupOrder(['date' => $date, 'mer_id' => $merId, 'is_first' => 1])
                ->field('StoreOrder.*')
                ->count();
            $count20 = $this->dao->joinGroupOrder(['date' => $date, 'mer_id' => $merId,])
                ->where('StoreOrder.activity_type',20)
                ->count('*');

            return [
                ['name' => '普通订单', 'value' => $count, ],
                ['name' => '秒杀订单', 'value' => $count1,],
                ['name' => '预售订单', 'value' => $count2,],
                ['name' => '砍价订单', 'value' => $count3,],
                ['name' => '拼团订单', 'value' => $count4,],
                ['name' => '积分订单', 'value' => $count20,],
                ['name' => '套餐订单', 'value' => $count10,],
                ['name' => '新人首单', 'value' => $countfirst,]
            ];
        }, self::CACHA_TIME);
    }


}
