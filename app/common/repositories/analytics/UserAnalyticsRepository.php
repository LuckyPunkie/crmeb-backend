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
use app\common\dao\user\UserDao;
use app\common\dao\store\order\StoreOrderDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\user\UserRepository;
use app\common\repositories\user\UserVisitRepository;
use app\common\repositories\user\UserOrderRepository;
use app\common\repositories\user\UserRelationRepository;
use app\common\repositories\store\order\StoreCartRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\order\StoreGroupOrderRepository;
use app\common\repositories\store\order\StoreRefundOrderRepository;

/**
 * 订单
 */
class UserAnalyticsRepository extends BaseRepository
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
     * @param UserDao $dao
     */
    public function __construct(UserDao $dao)
    {
        $this->dao = $dao;
    }


    const CACHA_TIME = 120;

    public function top($date, $merId = '')
    {
        [$cacheky, $cacheTags] = CacheService::setWithTags(
            CacheService::ANALYTICS_ORDER_TOP,
            compact('merId','date')
        );
        /**
         * 用户数量
         * 新增用户
         * 下单用户
         * 活跃用户
         * 付费会员
         * 新增付费会员
         */
        return Cache::tag($cacheTags)->remember($cacheky, function () use ($date, $merId) {

            //用户数量
            $count = $this->dao->search([])->count();

            //新增用户
            $newCount = $this->dao->search(['date' => $date])->count();
            $momNewCount = $this->dao->search(['mom_date' => $date])->count();

            //下单用户
            $storeOrderRepository = app()->make(StoreGroupOrderRepository::class);
            $paidCount = $storeOrderRepository->search(['date' => $date, 'paid' => 1], 0)->group('uid')->count();
            $momPaidCount = $storeOrderRepository->search(['mom_date' => $date, 'paid' => 1], 0)->group('uid')->count();

            //活跃用户
            $userVisitRepository = app()->make(UserVisitRepository::class);
            $auCount = $userVisitRepository->search(['date' => $date])->group('uid')->count();
            $momAuCount = $userVisitRepository->search(['mom_date' => $date])->group('uid')->count();

            //付费会员
            $svipCount = $this->dao->search(['is_svip' => 1])->count();

            //新增付费会员
            $userOrderRepository = app()->make(UserOrderRepository::class);
            $firstSvip = $userOrderRepository->search(['date' => $date])
                ->where('other','first')
                ->whereLike('order_type',"s-%")
                ->count();

            $momFirstSvip = $userOrderRepository->search(['mom_date' => $date])
                ->where('other','first')
                ->whereLike('order_type',"s-%")
                ->count();

            $redata = [
                [
                    'title' => "用户数量",
                    'count' => $count,
                    'mom'   => 0,
                    'statistic' => 0
                ],
                [
                    'title' => "新增用户",
                    'count' => $newCount,
                    'mom'   => $momNewCount,
                    'statistic' => growthRate($newCount,$momNewCount)
                ],
                [
                    'title' => "下单用户",
                    'count' => $paidCount,
                    'mom'   => $momPaidCount,
                    'statistic' => growthRate($paidCount,$momPaidCount)
                ],
                [
                    'title' => "活跃用户",
                    'count' => $auCount,
                    'mom'   => $momAuCount,
                    'statistic' => growthRate($auCount,$momAuCount)
                ],
                [
                    'title' => "付费会员",
                    'count' => $svipCount,
                    'mom'   => 0,
                    'statistic' => 0
                ],
                [
                    'title' => "新增付费会员",
                    'count' => $firstSvip,
                    'mom'   => $momFirstSvip,
                    'statistic' => growthRate($firstSvip,$momFirstSvip)
                ],
            ];
            return $redata;
        },self::CACHA_TIME);
    }




    public function lineChart($type, $date = '')
    {
        [$cacheky, $cacheTags] = CacheService::setWithTags(
            CacheService::ANALYTICS_USER_LINE_CHART,
            compact('type','date')
        );

        return Cache::tag($cacheTags)->remember($cacheky, function () use ($date, $type) {
            [$dates, $customFormat] = getStepLength($date);
            $format = $customFormat ? $customFormat : self::$formatMap[$date] ?? '%m-%d';
            $redata =  [];
            foreach ($dates as $mo) {
                $redata[$mo] = ['xaxis' => $mo, 'count' => 0,];
            }

            switch ($type) {
                case 0:
                    $this->getNewUser($redata,$date,$format);
                    break;
                case 1:
                    $this->getAuUser($redata,$date,$format);
                    break;
                case 2:
                    $this->getNewSvip($redata,$date,$format);
                    break;
                default:
                    break;

            }
            return array_values($redata);
        }, self::CACHA_TIME);
    }



    public function getNewSvip(&$redata, $date,$format)
    {
        $userOrderRepository = app()->make(UserOrderRepository::class);
        $field = Db::raw("from_unixtime(unix_timestamp(UserOrder.create_time),'{$format}') as month, COUNT(*) as count");
        $res = $userOrderRepository->search(['date' => $date])
            ->field($field)
            ->where('other','first')
            ->whereLike('order_type',"s%")
            ->group('month')
            ->select()->toArray();
        foreach ($res as $re) {
            if(isset($redata[$re['month']])) {
                $redata[$re['month']]['count'] = $re['count'];
            }
        }
    }

    public function getAuUser(&$redata, $date,$format)
    {
        $userVisitRepository = app()->make(UserVisitRepository::class);
        $field = Db::raw("from_unixtime(unix_timestamp(UserVisit.create_time),'{$format}') as month, COUNT(DISTINCT UserVisit.uid) as count");
        $res = $userVisitRepository->search(['date' => $date])
            ->field($field)
            ->group('month')
            ->select()->toArray();

        foreach ($res as $re) {
            if(isset($redata[$re['month']])) {
                $redata[$re['month']]['count'] = $re['count'];
            }
        }
    }

    public function getNewUser(&$redata, $date,$format)
    {
        $field = Db::raw("from_unixtime(unix_timestamp(User.create_time),'{$format}') as month,count(*) as count");
        $res = $this->dao->search(['date' => $date])
            ->field($field)
            ->group("month")
            ->select()->toArray();
        foreach ($res as $re) {
            if(isset( $redata[$re['month']])) {
                $redata[$re['month']]['count'] = $re['count'];
            }
        }
    }

    public function typePieCahrt($date)
    {
        [$cacheky, $cacheTags] = CacheService::setWithTags(
            CacheService::ANALYTICS_USER_BAR_CHART,
            compact('date')
        );
        return Cache::tag($cacheTags)->remember($cacheky, function () use ($date) {
            [$dates, $customFormat] = getStepLength($date);
            $format = $customFormat ? $customFormat : self::$formatMap[$date] ?? '%m-%d';
            $redata =  [];
            foreach ($dates as $mo) {
                $redata[$mo] = ['xaxis' => $mo, 'old' => 0, 'new' => 0];
            }

            $storeOrderRepository = app()->make(StoreGroupOrderRepository::class);
            $field = Db::raw("from_unixtime(unix_timestamp(create_time),'{$format}') as month, COUNT(DISTINCT uid) as count");
            $res = $storeOrderRepository->search(['date' => $date, 'paid' => 1, 'is_first' => 0],0)
                ->field($field)
                ->group('month')
                ->select()->toArray();

            foreach ($res as $re) {
                if(isset($redata[$re['month']])) {
                    $redata[$re['month']]['old'] = $re['count'];
                }
            }

            $ress = $storeOrderRepository->search(['date' => $date, 'paid' => 1, 'is_first' => 1],0)
                ->field($field)
                ->group('month')
                ->select()->toArray();

            foreach ($ress as $r) {
                if(isset($redata[$r['month']])) {
                    $redata[$r['month']]['new'] = $r['count'];
                }
            }
            return array_values($redata);
        },self::CACHA_TIME);
    }
}
