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

namespace app\common\repositories\system;

use app\common\dao\store\order\StoreOrderDao;
use app\common\model\store\order\StoreGroupOrder;
use app\common\model\store\order\StoreOrder;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\CityAreaRepository;
use app\common\repositories\store\order\StoreGroupOrderRepository;
use app\common\repositories\store\order\StoreOrderProductRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\user\UserRepository;
use app\common\repositories\user\UserVisitRepository;
use crmeb\services\SwooleTaskService;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;

class DataScreenRepository extends BaseRepository
{

    /*
      1. 今日交易商户数：今日发生交易的商户总数
      2. 今日交易商品数：今日发生交易的商品总数
      3. 今日交易新增用户数：今日交易用户中初次交易的用户数
      4. 今日交易新增用户支付额：今日交易用户中初次交易的用户支付总额
      5. 今日新老客户占比：今日新老交易客户数和客户交易金额分别占比（金额和数量可切换）
      6. 商户销售排行top10：今日商户销售金额和销售数量排行前10（销售金额和销售数量可切换）
      7. 今日订单支付金额：今日实时累计订单支付金额
      8. 全国地图：按照平台发出的商品收货地址展示全国各省市商品销售地图热度
      9. 本月销售情况统计：本月每日的销售额柱状图，横坐标为日期，竖坐标为销售额。
      10.订单支付情况：当日订单支付折线图，订单支付数和购买人数
      11. 实时订单：滚动显示实时订单数据：商品名称、订单支付时间、订单金额
      12. 商品销售排行top10：商品名称、销售数量、销售金额（销售金额和销售数量可切换）
    */

    public $unique;
    public $params;

    public $date = '';
    public $time = '';
    public $ficit = false;

    public function __construct(StoreOrderDao $dao)
    {
        $this->dao = $dao;
        $this->cache_key = env('APP_KEY','merchant').'_data_screen';
        $this->time = date('Y-m-d', time());
        if ($this->ficit) {
            $this->time = '2025-09-18';
            $this->date = '2025-09-18';
        }
    }

    protected function cache($fn)
    {
        if (!$this->unique || !$this->params) return $fn();
        $cache_key = $this->cache_key .'_' . $this->unique . '_' . $this->params;
        $res = Cache::get($cache_key);
        if ($res) return json_decode($res, true);
        $res = $fn();
        Cache::set($cache_key, json_encode($res), 60);
        return $res;
    }

    public function getFuncName()
    {
        $funcs = [
            'config',
            'today_pay_count_number',   // 1，2，3，4
            'today_pay_merchant',       // 1. 今日交易商户数：今日发生交易的商户总数
            'today_pay_product',        // 2. 今日交易商品数：今日发生交易的商品总数
            'today_pay_user_first',     // 3. 今日交易新增用户数：今日交易用户中初次交易的用户数
            'today_pay_first_number',   // 4. 今日交易新增用户支付额：今日交易用户中初次交易的用户支付总额
            'today_pay_new_old',        // 5. 今日新老客户占比：今日新老交易客户数和客户交易金额分别占比（金额和数量可切换）
            'today_pay_merchant_rank',  // 6. 商户销售排行top10：今日商户销售金额和销售数量排行前10（销售金额/销售数量; 当日/本周/本月数据可在后台设置，根据用户设置展示）
            'today_pay_number',         // 7. 今日订单支付金额：今日实时累计订单支付金额
            'city_ranking',             // 8. 全国地图：按照平台发出的商品收货地址展示全国各省市商品销售地图热度
            'month_pay_count',          // 9. 本月销售情况统计：本月每日的销售额柱状图，横坐标为日期，竖坐标为销售额
            'today_pay_count',          // 10.订单支付情况：当日订单支付折线图，订单支付数和购买人数，横坐标为当日时间每隔2小时，竖坐标为订单支付数和购买人数
            'today_pay_info',           // 11. 实时订单：滚动显示当日实时订单数据：商品名称、订单支付时间、订单金额
            'pay_product_rank',         // 12. 商品销售排行top10：商品名称、销售数量、销售金额（销售金额/销售数量; 当日/本周/本月数据可在后台设置，根据用户设置展示）
        ];
        return $funcs;
    }

    public function clearCache($func = '', $params = ['pid' => 0])
    {
        $cachekey = $this->cache_key. $func . '_'.json_encode($params);
        Cache::delete($cachekey);
        foreach ($this->getFuncName() as $func) {
            $cachekey = $this->cache_key. $func . '_'.json_encode($params);
            Cache::delete($cachekey);
        }
    }

    public function dataScreen($func = '', $params = [])
    {
        $funcs = $this->getFuncName();
        $this->unique = ($func == 'today_pay_count_number') ? '' : $func;
        $func = ($func != 'all')  ? $func : '';
        if ($func && !in_array($func,$funcs)) throw new ValidateException('方法不存在');
        $this->params = json_encode($params);
        if ($func) {
            $data[$func] = $this->{$func}($params);
        } else {
            foreach ($funcs as $func) {
                $data[$func] = $this->{$func}();
            }
        }
        return $data;
    }

    public function config()
    {
        $config = systemConfig(['sys_pay_product_rank','sys_pay_product_rank_type','sys_pay_merchant_rank','sys_pay_merchant_rank_type','data_screen_title']);
        return $config;
    }

    /**
     *  获取四个数据
     *  浏览量、访客数、今日订单数、新增用户数
     * @param $params
     * @return array
     * @author Qinii
     * @day 2023/12/11
     */
    public function today_pay_count_number($params = [])
    {
        // visitNum 浏览量
        $userVisitRepository = app()->make(UserVisitRepository::class);
        $date = $this->date;

        $today_pay_count_number['visit_num'] = (int)$userVisitRepository->dateVisitNum($date);
        $today_pay_count_number['visit_user_num'] = (int)$userVisitRepository->dateVisitUserNum($date);
//        $today_pay_count_number['today_pay_merchant']= $this->today_pay_merchant();
//        $today_pay_count_number['today_pay_product'] = $this->today_pay_product();
        $today_pay_count_number['today_pay_user_first'] = (int)app()->make(UserRepository::class)->newUserNum($date);
        $today_pay_count_number['today_pay_number'] = (int)$this->today_pay_number()['count'];
        return $today_pay_count_number;
    }

    /**
     * 1. 今日交易商户数：今日发生交易的商户总数
     * @author Qinii
     * @day 2023/11/25
     */
    public function today_pay_merchant($params = [])
    {
        return $this->cache(function() use($params) {
            $storeOrderRepository = app()->make(StoreOrderRepository::class);
            $query = $storeOrderRepository->getSearch([]);
            $today_pay_merchant = $query->whereDay('create_time', $this->time)->where('paid',1)->group('mer_id')
                ->count();
            return $today_pay_merchant;
        });
    }

    /**
     * 2. 今日交易商品数：今日发生交易的商品总数
     * @return array
     * @author Qinii
     * @day 2023/11/25
     */
    public function today_pay_product($params = [])
    {
        return $this->cache(function() use($params) {
            $query = StoreOrder::alias('O')->join('StoreOrderProduct OP','O.order_id = OP.order_id');
            $today_pay_product = $query->whereDay('O.create_time', $this->time)
                ->where('paid',1)
                ->group('OP.product_id')
                ->count();
            return $today_pay_product;
        });
    }

    /**
     * 3. 今日交易新增用户数：今日交易用户中初次交易的用户数
     * @author Qinii
     * @day 2023/11/27
     */
    public function today_pay_user_first($params = [])
    {
        return $this->cache(function() use($params) {
            $storeGroupOrderRepository = app()->make(StoreGroupOrderRepository::class);
            $today_pay_user_first = $storeGroupOrderRepository->search(['paid' => 1])
                ->whereDay('create_time', $this->time)
                ->where('is_first',1)
                ->count();
            return $today_pay_user_first;
        });
    }

    /**
     * 4. 今日交易新增用户支付额：今日交易用户中初次交易的用户支付总额
     * @author Qinii
     * @day 2023/11/27
     */
    public function today_pay_first_number($params = [])
    {
        return $this->cache(function() use($params) {
            $storeGroupOrderRepository = app()->make(StoreGroupOrderRepository::class);
            $query = $storeGroupOrderRepository->search(['paid' => 1])
                ->whereDay('create_time', $this->time)
                ->where('is_first',1);
            $pay_price = $query->sum('pay_price');
            $pay_postage = $query->sum('pay_postage');
            $today_pay_first_number = (int)bcadd($pay_price,$pay_postage,2);
            return $today_pay_first_number;
        });
    }

    /**
     * 5. 今日新老客户占比：今日新老交易客户数和客户交易金额分别占比（金额和数量可切换）
     * @author Qinii
     * @day 2023/11/27
     */
    public function today_pay_new_old($params = [])
    {
        return $this->cache(function() use($params) {
            $newQuery = StoreGroupOrder::alias('g')
                ->join('user u','u.uid = g.uid')
                ->where('paid',1)
                ->whereDay('g.create_time', $this->time)
                ->whereDay('u.create_time', $this->time)
//                ->field("sum(g.pay_price + g.pay_postage) as number,g.uid")
                ->group('g.uid');
            $new_count = $newQuery->count();

//            if ($new_count) {
//                $newList = $newQuery->select()->toArray();
//                $_number = array_column($newList,'number');
//                $new_number = array_reduce($_number, function($sum, $value) {
//                    return bcadd($sum,$value,2);
//                });
//            }

            $oldQuery = StoreGroupOrder::alias('g')
                ->join('user u','u.uid = g.uid')
                ->where('paid',1)
                ->whereDay('g.create_time', $this->time)
                ->whereTime('u.create_time','<',date('Y-m-d'))
//                ->field("sum(g.pay_price + g.pay_postage) as number,g.uid")
                ->group('g.uid');

            $old_count = $oldQuery->count();

//            if ($old_count) {
//                $oldList = $oldQuery->select()->toArray();
//                $_number = array_column($oldList,'number');
//                $old_number = array_reduce($_number, function($sum, $value) {
//                    return bcadd($sum,$value,2);
//                });
//            }
//            $count = $new_count + $old_count;
//            $number = bcadd($new_number,$old_number,2);
            $today_pay_new_old = [
//                'new_number' => $new_number,
                'new_count' => $new_count,
//                'new_rate_count' => $new_count > 0 ? (int)bcdiv($new_count,$count,2) * 100 : 0,
//                'new_rate_number'=> $new_number > 0 ? (int)bcdiv($new_number,$number,2) * 100 : 0,

//                'old_number' => $old_number,
                'old_count' => $old_count,
//                'old_rate_count'=> $old_count > 0 ? (int)bcdiv($old_count,$count,2) * 100 : 0,
//                'old_rate_number'=> $new_number > 0 ? (int)bcdiv($old_number,$number,2) * 100 : 0,
            ];
            return $today_pay_new_old;
        });
    }

    /**
     * 6. 商户销售排行top10：今日商户销售金额和销售数量排行前10（销售金额和销售数量可切换）
     * @author Qinii
     * @day 2023/11/27
     */
    public function today_pay_merchant_rank($params = [])
    {
        return $this->cache(function() use($params) {
            $date = systemConfig('sys_pay_merchant_rank') ?($this->ficit ? $this->date : systemConfig('sys_pay_merchant_rank')): $this->date;
            if (systemConfig('sys_pay_merchant_rank_type')) {
                $today_pay_merchant_rank['type'] = '个';
                $valueField = 'sum(total_num)';
            } else {
                $today_pay_merchant_rank['type'] = '元';
                $valueField = 'sum(pay_price + pay_postage)';
            }

            $storeOrderRepository = app()->make(StoreOrderRepository::class);
            $query = $storeOrderRepository->search(['paid' => 1])
                ->when($date, function($query) use($date) {
                    getModelTime($query, $date,'StoreOrder.create_time');
                });
            $query->whereDay('StoreOrder.create_time', $this->time)
                ->setOption('field',[])
                ->field("{$valueField} as value,StoreOrder.mer_id,Merchant.mer_name name,Merchant.mer_id")
                ->group('StoreOrder.mer_id');
            $list = $query->orderRaw("{$valueField} DESC")->limit(20)->select();
            foreach ($list as &$item) {
                $item['value'] = (int)$item['value'];
            }
            $today_pay_merchant_rank['data'] = $list;

            return $today_pay_merchant_rank;
        });
    }

    /**
     * 7. 今日订单支付金额：今日实时累计订单支付金额
     * @author Qinii
     * @day 2023/11/27
     */
    public function today_pay_number($params = [])
    {
        return $this->cache(function() use($params) {
            $storeOrderRepository = app()->make(StoreOrderRepository::class);
            $query = $storeOrderRepository->getSearch([])
                ->whereDay('create_time', $this->time)
                ->where('paid', 1);
            $list = $query->field("sum(pay_price) as number,count(*) as count,paid,order_id")
                ->select()->toArray();
            $today_pay_number = $list[0] ?? ['number' => '0.00', 'count' => '0'];
            return $today_pay_number;
        });
    }

    /**
     * 8. 全国地图：按照平台发出的商品收货地址展示全国各省市商品销售地图热度
     * @author Qinii
     * @day 2023/11/28
     */
    public function city_ranking($params = [])
    {
        return $this->cache(function() use($params){
            $pid = $params['pid'] ?? 0;
            $cityAreaRepository = app()->make(CityAreaRepository::class);
            $address = $cityAreaRepository->getSearch([])->where('parent_id', $pid)->column('id,name,code');
            if (isset($address['0']['name']) && $address['0']['name'] == '市辖区') {
                $address = $cityAreaRepository->getSearch([])->where('parent_id', $address['0']['id'])->column('id,name,code');
            }
            $city_ranking = [];
            $ids = array_column($address, 'id');
            $recordRepository = app()->make(RecordRepository::class);
            $query = $recordRepository->getSearch(['type' => $recordRepository::TYPE_ADDRESS_RECORD]);
            $record = $query->whereIn('link_id', $ids)->column('num', 'link_id');
            foreach ($address as $item) {
                if (isset($record[$item['id']])) {
                    $city_ranking[] = [
                        'id' => $item['id'],
                        'name' => $item['name'],
                        'value' => (int)$record[$item['id']],
                        'code' => $item['code'],
                    ];
                }
            }
            return $city_ranking;
        });
    }

    /**
     * 9. 本月销售情况统计：本月每日的销售额柱状图，横坐标为日期，竖坐标为销售额。
     * @author Qinii
     * @day 2023/11/28
     */
    public function month_pay_count($params = [])
    {
        return $this->cache(function() use($params) {
            $dates = getDatesBetweenTwoDays(getStartModelTime('month'), $this->time, 'd');
            $storeGroupOrderRepository = app()->make(StoreGroupOrderRepository::class);
            $field = Db::raw('from_unixtime(unix_timestamp(create_time),\'%d\') as day,sum(pay_price) as total_sum');
            $query = $storeGroupOrderRepository->search(['paid' => 1])->whereMonth('create_time');
            $month = $query->field($field)->group('day')->select()->toArray();
            $month = array_combine(array_column($month, 'day'), array_column($month, 'total_sum'));
            $month_pay_count = [];
            foreach ($dates as $date) {
                $total_sum = $month[$date] ?? 0;
                if ($date !== date('d') && $this->ficit && !$total_sum) {
                    $key  = 'dataScreen_month_pay_count_' .$date;
                    $total_sum = Cache::get($key);
                    if (!$total_sum) {
                        $total_sum = bcadd(rand(100,9999), 1,2);
                        Cache::set($key,$total_sum, 3600);
                    }
                }
                $month_pay_count[] = [
                    'day' => (string)$date,
                    'total_sum' => $total_sum,
                ];
            }
            return $month_pay_count;
        });
    }

    /**
     * 10.订单支付情况：当日订单支付折线图，订单支付数和购买人数
     * @author Qinii
     * @day 2023/11/28
     */
    public function today_pay_count($params = [])
    {
        return $this->cache(function() use($params) {
            $h = date('H');
            $j = ($h <= 12) ? 0 : 1;
            $i = '0';
            do {
                $dates[] = (string)($i < 10 ? '0' . $i : $i);
                $i++;
                $i = $i + $j;
            } while ($h >= $i);
            $storeOrderRepository = app()->make(StoreOrderRepository::class);
            $field = Db::raw('from_unixtime(unix_timestamp(create_time),\'%H\') as hours,count(order_id) order_count,count(distinct uid) as user_count');
            $query = $storeOrderRepository->getSearch([])->where('paid',1)->whereDay('create_time');
            $orderList = $query->field($field)->order('hours ASC')->group('hours')->select()->toArray();
            $orderList = array_combine(array_column($orderList, 'hours'), $orderList);

            $today_pay_count = [];
            foreach ($dates as $date) {
                if ($j) {
                    $_k = $date + $j;
                    $k = (string)($_k < 10 ? '0' . $_k : $_k);
                    $arr = [
                        'hours' => (string)$date.'~'.$k,
                        'user_count' => $orderList[$date]['user_count'] ?? 0,
                        'order_count' => $orderList[$date]['order_count'] ?? 0,
                    ];
                    if (isset($orderList[$k])) {
                        $arr['user_count']  = $arr['user_count'] + $orderList[$k]['user_count'];
                        $arr['order_count'] = $arr['order_count'] + $orderList[$k]['order_count'];
                    }
                } else {
                    $arr = [
                        'hours' => (string)$date,
                        'user_count' => $orderList[$date]['user_count'] ?? 0,
                        'order_count' => $orderList[$date]['order_count'] ?? 0,
                    ];
                }
                $today_pay_count[] = $arr;
            }
            return $today_pay_count;
        });
    }

    /**
     * 11. 实时订单：滚动显示实时订单数据：商品名称、订单支付时间、订单金额
     * @author Qinii
     * @day 2023/11/28
     */
    public function today_pay_info($params = [])
    {
        return $this->cache(function() use($params) {
            $storeOrderProductRepository = app()->make(StoreOrderProductRepository::class);
            $today_pay_info = $storeOrderProductRepository->getProductRate(0, $this->date, 'paytime', 10, false);
            return $today_pay_info;
        });
    }

    /**
     * 12. 今天/近7天 商品销售排行top10：商品名称、销售数量、销售金额（销售金额和销售数量可切换）
     * @author Qinii
     * @day 2023/11/28
     */
    public function pay_product_rank($params = [])
    {
        return $this->cache(function() use($params) {
            $date = systemConfig('sys_pay_product_rank') ?($this->ficit ? $this->date : systemConfig('sys_pay_product_rank')): $this->date;
            $type =systemConfig('sys_pay_product_rank_type') == 0 ? 'number' : 'count';
            $storeOrderProductRepository = app()->make(StoreOrderProductRepository::class);
            $pay_product_rank = $storeOrderProductRepository->getProductRate(0, $date, $type, 20);
            return $pay_product_rank;
        });
    }
}
