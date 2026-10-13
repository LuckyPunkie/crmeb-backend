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

namespace app\common\repositories\store\product;

use think\exception\ValidateException;
use app\common\repositories\BaseRepository;
use app\common\dao\store\product\ProductReservationDao as dao;
use app\common\repositories\store\order\StoreOrderProductRepository;
use app\common\repositories\user\UserAddressRepository;
use app\common\repositories\delivery\DeliveryTrait;
use app\common\repositories\system\merchant\MerchantRepository;

class ProductReservationRepository extends BaseRepository
{
    use DeliveryTrait;

    //可售日期(1:每天,2:自定义时间)
    const SALE_TIME_TYPE_DAY = 1;
    const SALE_TIME_TYPE_CUSTOM = 2;

    //1 允许 1 不允许
    const ADVANCE_TYPE_NOT_ALLOW = 0;
    const ADVANCE_TYPE_ALLOW = 1;

    const RANGE_TYPE_METHOD = [
        1 => 'checkRadius',
        2 => 'checkRegion',
        3 => 'checkFence',
    ];

    /**
     * ProductRepository constructor.
     * @param dao $dao
     */
    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    public function showMonth(int $productId, array $where)
    {
        /**
         * 先获取本月的日历
         * 获取商品可预约的类型
         * 根据类型计算可预约时间
         * 组合预约的时间和时间内的数量信息
         */
        $detail = $this->dao->search(['product_id' => $productId])->find();
        if (!$detail) throw new ValidateException('数据不存在');
        $date = $where['date'];
        [$firstDay, $lastDay] = $this->getMonth($date);
        if ($detail['sale_time_type'] == self::SALE_TIME_TYPE_DAY) {
            $start = $firstDay;
            $end = $lastDay;
            $weeks = ['1', '2', '3', '4', '5', '6', '7'];
        } elseif ($detail['sale_time_type'] == self::SALE_TIME_TYPE_CUSTOM) {
            $start = $detail['sale_time_start_day'];
            $end = $detail['sale_time_end_day'];
            $weeks = $detail['sale_time_week'];
        }
        $days = [];
        $today =  date('Y-m-d', time());
        $currentDay = $firstDay < $today ? $today : $firstDay;
        $show_num = $detail['show_reservation_days'] - 1;
        $showDay = date('Y-m-d', strtotime("+ $show_num Day"));
        $lastDay = $showDay <= $lastDay ? $showDay : $lastDay;
        while ($currentDay <= $lastDay) {
            $week = date('N', strtotime($currentDay));
            if ($currentDay >= $start && $currentDay <= $end && in_array($week, $weeks)) {
                $days[] = [
                    'date'  => date('Y-m-d', strtotime($currentDay)),
                    'day'  => date('d', strtotime($currentDay)),
                    'weeke' => $week,
                ];
            }
            $currentDay = date('Y-m-d', strtotime($currentDay . ' +1 day'));
        }
        return ['date' => $date, 'days' => $days];
    }

    public function getMonth($date)
    {
        // 解析传入的日期，获取年月
        $dateObj = new \DateTime($date);
        $year = $dateObj->format('Y');
        $month = $dateObj->format('m');

        // 获取当月第一天和最后一天
        $firstDay = date('Y-m-01', strtotime("$year-$month-01"));
        $lastDay = date('Y-m-t', strtotime("$year-$month-01"));
        return [$firstDay, $lastDay];
    }


    public function showDay(int $productId, array $where)
    {
        /**
         * 获取预约时间信息
         * 获取时间段
         * 获取每个时间段的库存等
         */
        $detail = $this->dao->search(['product_id' => $productId])->find();
        $date = $where['date'] . '-' . $where['day'];
        $week = date('N', strtotime($date));
        if (!$detail) throw new ValidateException('数据不存在');
        $show_num = $detail['show_reservation_days'];
        $showDay = date('Y-m-d', strtotime("+ $show_num Day"));
        if ($date > $showDay)
            throw new ValidateException('[1]当前日期不可预约');
        if ($detail['sale_time_type'] == self::SALE_TIME_TYPE_CUSTOM) {
            $start = $detail['sale_time_start_day'];
            $end = $detail['sale_time_end_day'];
            $weeks = $detail['sale_time_week'];
            if ($date < $start || $date > $end || !in_array($week, $weeks)) {
                throw new ValidateException('[2]当前日期不可预约');
            }
        }
        $firstTime = date('H:i', time());
        if ($date == date('Y-m-d', time()) && $detail['is_advance']) {
            $advance_time = $detail['advance_time'] * 60 * 60;
            $firstTime = date('H:i', time() + $advance_time);
        }
        $show = $detail['show_num_type'];

        $valueReservationRepository = app()->make(ProductAttrValueReservationRepository::class);
        $productRepository = app()->make(StoreOrderProductRepository::class);
        $list = $valueReservationRepository->getSearch(['attr_value_id' => $where['sku_id']])
            //->when($date == date('Y-m-d', time()) ,function($query) use($firstTime){
            //    $query->where('start_time','>=', $firstTime);
            //})
            ->field('start_time,end_time,stock,attr_value_id,attr_reservation_id')->select();

        foreach ($list as &$item) {
            $stock = $productRepository->getReservationSum($productId, $date, $item['attr_reservation_id']);
            $item['stock'] = $item['stock'] - $stock;
            $disable = $item['stock'] <= 0 ? true : false;
            if (!$disable && $date == date('Y-m-d', time())) {
                $disable = ($item['start_time'] >= $firstTime) ? false : true;
            }
            $item['disable'] = $disable;
        }
        return compact('show', 'list');
    }

    public function checkRange(int $uid, array $params)
    {
        // 获取用户地址信息
        $address = app()->make(UserAddressRepository::class)->get($params['address_id'], $uid);
        if (!$address) {
            throw new ValidateException('用户地址不存在');
        }

        $res = $this->checkRangeByType($params['mer_id'], $address->toArray());
        return ['in_range' => $res];
    }

    public function checkRangeByType(int $merId, array $address)
    {
        $merReservationInfo = merchantConfig($merId, ['range_type', 'radius', 'region', 'fence']);
        $merReservationInfo['mer_id'] = $merId;
        if (!$merReservationInfo['range_type']) {
            throw new ValidateException('商户预约设置错误，请商家先配置服务范围');
        }
        $checkMethod = self::RANGE_TYPE_METHOD[$merReservationInfo['range_type']];

        return $this->$checkMethod($address, $merReservationInfo);
    }
    /**
     * 配送半径验证
     *
     * @param array $address
     * @param array $merReservationInfo
     * @return boolean
     */
    public function checkRadius(array $address, array $merReservationInfo): bool
    {
        $merchant = app()->make(MerchantRepository::class)->detail($merReservationInfo['mer_id'], null);
        if (!$merchant) {
            throw new ValidateException('商户不存在，无法进行预约');
        }
        // 根据经纬度计算距离
        $distance = $this->getAddressDistance($merchant['lat'], $merchant['long'], $address);
        // 配送半径验证
        if ($merReservationInfo['radius'] < $distance) {
            return false;
        }

        return true;
    }
    /**
     * 配送区域验证
     *
     * @param array $address
     * @param array $merReservationInfo
     * @return boolean
     */
    public function checkRegion(array $address, array $merReservationInfo): bool
    {
        $isRegion = false;
        $addresString = $address['province_id'] . ',' . $address['city_id'] . ',' . $address['district_id'];
        foreach ($merReservationInfo['region'] as $region) {
            $regionString = implode(',', $region);
            if (strpos($addresString, $regionString) !== false) {
                $isRegion = true;
                break;
            }
        }

        return $isRegion;
    }
    /**
     * 配送围栏验证
     *
     * @param array $address
     * @param array $merReservationInfo
     * @return boolean
     */
    public function checkFence(array $address, array $merReservationInfo): bool
    {
        $isFence = false;
        $addressDetail = $address['province'] . $address['city'] . $address['district'] . $address['street'] . $address['detail'];
        $addressLatAndLong = lbs_address([], $addressDetail);
        foreach ($merReservationInfo['fence'] as $fence) {
            $method = 'checkIn' . ucfirst($fence['type']);
            if (method_exists($this, $method)) {
                if ($this->$method($fence, $addressLatAndLong)) {
                    $isFence = true;
                    break;
                }
            }
        }

        return $isFence;
    }
}
