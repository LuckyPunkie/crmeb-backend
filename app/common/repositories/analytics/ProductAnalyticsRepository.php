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
use app\common\dao\store\product\ProductDao;
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
class productAnalyticsRepository extends BaseRepository
{
    /**
     * StoreOrderRepository constructor.
     * @param StoreOrderDao $dao
     */
    public function __construct(ProductDao $dao)
    {
        $this->dao = $dao;
    }


    public static $formatMap = [
        'week' => '%m-%d',    // 按日期
        'month' => '%d',   // 按日期
        'year' => '%m',       // 按年月
        'quarter' => '%m',    // 按年月
        'lately7' => '%m-%d', // 按日期
        'lately30' => '%m-%d' // 按日期
    ];

    const CACHA_TIME = 120;

    public function top($date, $merId = '')
    {
        $adminId = app('request')->adminId() ?: '';
        [$cacheky, $cacheTags] = CacheService::setWithTags(
            CacheService::ANALYTICS_PRODUCT_TOP,
            compact('merId','date', 'adminId')
        );
        return Cache::tag($cacheTags)->remember($cacheky, function () use ($date, $merId) {
            $merId = $merId ? : '';
            $userVisitRepository = app()->make(UserVisitRepository::class);
            $storeCartRepository = app()->make(StoreCartRepository::class);
            $storeOrderRepository = app()->make(StoreOrderRepository::class);
            $userRelationRepository = app()->make(UserRelationRepository::class);
            $storeRefundOrderRepository = app()->make(StoreRefundOrderRepository::class);

            $visit = $userVisitRepository->search(['type' => 'product', 'date' => $date, 'mer_id' => $merId,])->count();
            $mom_visit = $userVisitRepository->search(['type' => 'product', 'mom_date' => $date, 'mer_id' => $merId,])
                ->count();

            $relation = $userRelationRepository->getSearch(['type' => 1,'date' => $date])->count();
            $mom_relation = $userRelationRepository->getSearch(['type' => 1,'mom_date' => $date])->count();

            $cart_num = $storeCartRepository->getSearch(['date' => $date,'is_pay' => 0, 'is_new' => 0,'is_fail' =>
                0])->sum('cart_num');
            $mom_cart_num = $storeCartRepository->getSearch(['mom_date' => $date,'is_pay' => 0, 'is_new' => 0,'is_fail' =>
                0])->sum('cart_num');

            $total_num = $storeOrderRepository->search(['date' => $date, 'mer_id' => $merId])->sum('total_num');
            $mom_total_num = $storeOrderRepository->search(['mom_date' => $date, 'mer_id' => $merId])->sum('total_num');

            $paid_num  = $storeOrderRepository->search(['date' => $date, 'mer_id' => $merId, 'paid' => 1])->sum('total_num');
            $mom_paid_num  = $storeOrderRepository->search(['mom_date' => $date, 'mer_id' => $merId, 'paid' => 1])->sum('total_num');

            $refund_num = $storeRefundOrderRepository->search(['date' => $date, 'mer_id' => $merId])->sum('refund_num');
            $mom_refund_num = $storeRefundOrderRepository->search(['mom_date' => $date, 'mer_id' => $merId])->sum('refund_num');
            $redata = [
                [
                    'title' => "浏览量",
                    'count' => $visit,
                    'mom'   => $mom_visit,
                    'statistic' => growthRate($visit,$mom_visit)
                ],
                [
                    'title' => "收藏量",
                    'count' => $relation,
                    'mom'   => $mom_relation,
                    'statistic' => growthRate($relation,$mom_relation)
                ],
                [
                    'title' => "加购数",
                    'count' => $cart_num,
                    'mom'   => $mom_cart_num,
                    'statistic' => growthRate($cart_num,$mom_cart_num)
                ],
                [
                    'title' => "下单数",
                    'count' => $total_num,
                    'mom'   => $mom_total_num,
                    'statistic' => growthRate($total_num,$mom_total_num)
                ],
                [
                    'title' => "支付数",
                    'count' => $paid_num,
                    'mom'   => $mom_paid_num,
                    'statistic' => growthRate($paid_num,$mom_paid_num)
                ],
                [
                    'title' => "退款数",
                    'count' => $refund_num,
                    'mom'   => $mom_refund_num,
                    'statistic' => growthRate($refund_num,$mom_refund_num)
                ],
            ];
            return $redata;
        }, self::CACHA_TIME);
    }

    public function lineChart($date = '', $merId = '')
    {
        $adminId = app('request')->adminId() ?: '';
        [$cacheky, $cacheTags] = CacheService::setWithTags(
            CacheService::ANALYTICS_PRODUCT_LINE_CHART,
            compact('merId','date', 'adminId')
        );
        return Cache::tag($cacheTags)->remember($cacheky, function () use ($date, $merId) {
            $merId = $merId ? : '';
            [$dates, $customFormat] = getStepLength($date);
            $redata =  [];
            foreach ($dates as $mo) {
                $redata[$mo] = [
                    'xaxis' => $mo,
                    'visit' => 0, //浏览量
                    'relation'=> 0, //收藏量
                    'total_num' => 0, //下单量
                    'paid_num' => 0, //支付量
                ];
            }
            $format = $customFormat ? $customFormat : self::$formatMap[$date] ?? '%m-%d';
            $this->getVisit($redata, $date, $format, $merId);
            $this->getRelation($redata, $date, $format, $merId);
            $this->getOrder($redata, $date, $format, $merId);
            return array_values($redata);
        }, self::CACHA_TIME);
    }

    protected function getOrder(&$redata, $date, $format, $merId = '')
    {
        $storeOrderRepository = app()->make(StoreOrderRepository::class);
        $field = Db::raw("from_unixtime(unix_timestamp(StoreOrder.create_time),'{$format}') as month,sum(total_num) as total_num");
        $query = $storeOrderRepository->search(['date' => $date, 'mer_id' => $merId])->field($field);

        $res = $query->group("month")->select()->toArray();
        foreach ($res as $re) {
            if(isset( $redata[$re['month']])) {
                $redata[$re['month']]['total_num'] = (int)$re['total_num'];
            }
        }

        $ress = $query->where('paid',1)->group("month")->select()->toArray();
        foreach ($ress as $rr) {
            if(isset( $redata[$re['month']])) {
                $redata[$rr['month']]['paid_num'] = (int)$rr['total_num'];
            }
        }
    }

    protected function getRelation(&$redata, $date, $format, $merId = '')
    {
        $userRelationRepository = app()->make(UserRelationRepository::class);
        $field = Db::raw("from_unixtime(unix_timestamp(create_time),'{$format}') as month,count(*) as count");
        $res = $userRelationRepository->getSearch(['type' => 1,'date' => $date])
            ->field($field)
            ->group("month")
            ->select()->toArray();
        foreach ($res as $re) {
            if(isset( $redata[$re['month']])) {
                $redata[$re['month']]['relation'] = $re['count'];
            }
        }
    }

    protected function getVisit(&$redata, $date, $format, $merId = '')
    {
        $field = Db::raw("from_unixtime(unix_timestamp(UserVisit.create_time),'{$format}') as month,count(*) as count");
        $userVisitRepository = app()->make(UserVisitRepository::class);
        $res = $userVisitRepository->search(['type' => 'product', 'date' => $date, 'mer_id' => $merId,])
            ->field($field)
            ->group("month")
            ->select()->toArray();
        foreach ($res as $re) {
            if(isset( $redata[$re['month']])) {
                $redata[$re['month']]['visit'] = $re['count'];
            }
        }
    }

    public function catePieCahrt($merId = '')
    {
        $adminId = app('request')->adminId() ?: '';
        [$cacheky, $cacheTags] = CacheService::setWithTags(
            CacheService::ANALYTICS_PRODUCT_CATEGORY_CHART,
            compact('merId', 'adminId')
        );

        return Cache::tag($cacheTags)->remember($cacheky, function () use ($merId) {
            $merId = $merId ? : '';
            $field = Db::raw("count(*) as count,cate_id");
            $query = $this->dao->getSearch([])->with(['storeCategory']);
            // 分组权限
            if (app('request')->hasMacro('regionAuthority') && $region = app('request')->regionAuthority()) {
                $query->whereIn('mer_id', $region);
            }
            $res = $query
                ->field($field)
                ->group('cate_id')
                ->order('count DESC')
                ->select()->toArray();
            $redata = [];
            foreach($res as $re) {
                if ($re['storeCategory']) {
                    $redata[] = [
                        'name' => $re['storeCategory']['cate_name'],
                        'value' => $re['count']
                    ];
                }
            }
            return $redata;
        }, self::CACHA_TIME);
    }


    public function typePieCahrt($merId = '')
    {
        $adminId = app('request')->adminId() ?: '';
        [$cacheky, $cacheTags] = CacheService::setWithTags(
            CacheService::ANALYTICS_PRODUCT_TYPE_CHART,
            compact('merId', 'adminId')
        );
        return Cache::tag($cacheTags)->remember($cacheky, function () use ($merId) {
            $merId = $merId ? : '';
            //0.实体商品，1.虚拟商品，2 网盘，3 卡密 4 预约商品
            $nams = ['普通商品', '虚拟商品', '网盘商品', '卡密商品', '预约商品',];
            $where = $merId ? ['mer_id' => $merId] : [];
            $field = Db::raw("count(*) as count,type");
            $query = $this->dao->getSearch($where);
            // 分组权限
            if (app('request')->hasMacro('regionAuthority') && $region = app('request')->regionAuthority()) {
                $query->whereIn('mer_id', $region);
            }
            $res = $query
                ->where('product_type',0)
                ->field($field)
                ->group('type')
                ->order('type ASC')
                ->select()->toArray();
            $redata = [];
            foreach($res as $re) {
                $redata[] = [
                    'name' => $nams[$re['type']],
                    'value' => $re['count']
                ];
            }
            return $redata;
        }, self::CACHA_TIME);
    }

}
