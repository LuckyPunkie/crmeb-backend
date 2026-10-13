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

namespace app\common\model\store;

use app\common\model\BaseModel;
use app\common\model\store\order\StoreOrderProduct;
use app\common\model\store\product\Product;
use app\common\repositories\store\StoreActivityRepository;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;

class StoreSeckillActive extends BaseModel
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $deleteTime = 'delete_time';

    use SoftDelete;

    protected $append = ['status_text', 'seckill_time_text_arr','stop_time'];

    /**
     *  设置主键
     * @return string
     * @author Qinii
     * @day 2020-07-30
     */
    public static function tablePk(): string
    {
        return 'seckill_active_id';
    }

    /**
     *  设置表名
     * @return string
     * @author Qinii
     * @day 2020-07-30
     */
    public static function tableName(): string
    {
        return 'store_seckill_active';
    }


    /**
     * 开始日期修改器
     * @param $value
     * @param $data
     * @return false|int|void
     * FerryZhao 2024/4/11
     */
    protected static function setStartDayAttr($value, $data)
    {
        if ($value) {
            return date('Y-m-d', strtotime($value));
        }
    }

    /**
     * 标识修改器
     * @param $value
     * @param $data
     * @return false|string|void
     * FerryZhao 2024/4/24
     */
    protected static function setSignAttr($value, $data)
    {
        return 1;
    }

    /**
     * 结束日期修改器
     * @param $value
     * @param $data
     * @return false|int|void
     * FerryZhao 2024/4/11
     */
    protected static function setEndDayAttr($value, $data)
    {
        if(isset($data['seckill_time_ids']) && $data['seckill_time_ids']){
            $data['seckill_time_ids'] = explode(',', $data['seckill_time_ids']);
            $seckillEndTime = app()->make(StoreSeckillTime::class)->where(['status'=>1])->whereIn('seckill_time_id',$data['seckill_time_ids'])->order('end_time','desc')->value('end_time');
            $endTime = $seckillEndTime * 60 * 60;
            $endTime = $seckillEndTime == 24 ? $endTime - 1 : $endTime;
            if ($value) {
                return date('Y-m-d H:i:s',strtotime(date('Y-m-d',strtotime($value))) + $endTime);
            }
            return $endTime;
        }
    }

    /**
     * 活动状态
     * @param $value
     * @param $data
     * @return int|string|void
     * FerryZhao 2024/4/19
     */
    public static function setActiveStatusAttr($value,$data)
    {
        $startTime = strtotime($data['start_day']);
        $endTime =  strtotime($data['end_day']. '23:59:59');
        if($startTime > time()){
            return 0;
        }else if($endTime < time()){
            return '-1';
        }else if($startTime <= time() && $endTime >= time()){
            return 1;
        }
    }

    /**
     * @return array
     */
    public function getSeckillTimesAttr()
    {
        if ($this->seckill_time_ids) {
            return StoreSeckillTime::whereIn('seckill_time_id',$this->seckill_time_ids)
                ->where('status',1)
                ->column('title,start_time,end_time,seckill_time_id');
        }
        return [];
    }

    /**
     * 开始日期获取器
     * @param $value
     * @param $data
     * @return false|int|void
     * FerryZhao 2024/4/11
     */
    protected static function getStartDayAttr($value, $data)
    {
        if ($value) {
            return date('Y-m-d', strtotime($value));
        }
    }

    /**
     * 结束日期获取器
     * @param $value
     * @param $data
     * @return false|int|void
     * FerryZhao 2024/4/11
     */
    protected static function getEndDayAttr($value, $data)
    {
        if ($value) {
            return date('Y-m-d', strtotime($value));
        }
    }

    /**
     * 活动场次获取器
     * @param $value
     * @param $data
     * @return void
     * FerryZhao 2024/4/11
     */
    protected static function getSeckillTimeIdsAttr($value, $data)
    {
        if (!empty($value)) {
            return explode(',', $value);
        }
    }

    /**
     * 平台一级商品分类获取器
     * @param $value
     * @param $data
     * @return void
     * FerryZhao 2024/4/11
     */
    protected static function getProductCategoryIdsAttr($value, $data)
    {
        if (!empty($value)) {
            return explode(',', $value);
        }
    }


    /**
     * 状态说明获取器
     * @param $value
     * @param $data
     * @return void
     * FerryZhao 2024/4/11
     */
    protected static function getStatusTextAttr($value, $data)
    {
        $statusTextArray = [
            '0'=>'未开始',
            '1'=>'进行中',
            '-1'=>'已结束'
        ];
        return $statusTextArray[$data['active_status']];

    }

    /**
     * 时间场次获取器
     * @param $value
     * @param $data
     * @return void
     * FerryZhao 2024/4/13
     */
    protected function getSeckillTimeTextArrAttr($value, $data)
    {
        $timeList = [];
        if (isset($data['seckill_time_ids'])) {
            $list = app()->make(StoreSeckillTime::class)->whereIn('seckill_time_id', explode(',', $data['seckill_time_ids']))->field('start_time,end_time')->select();
            foreach ($list as $item) {
                $timeList[] = $item['start_time_text'] . ' - ' . $item['end_time_text'];
            }
        }

        return $timeList;
    }


    /**
     * 氛围图获取器
     * @param $value
     * @param $data
     * @return void
     * FerryZhao 2024/4/18
     */
    public function getAtmospherePicAttr($value, $data)
    {
        $storeActivity = app()->make(StoreActivityRepository::class);
        $pic =  app()->make(StoreActivity::class)->where(
            [
                'scope_type' => $storeActivity::TYPE_MUST_SECKILL_ACTIVE,
                'activity_type' => $storeActivity::ACTIVITY_TYPE_ATMOSPHERE,
                'link_id' => $data['seckill_active_id']
            ]
        )->value('pic');
        return $pic ?: '';
    }

    /**
     * 氛围图获取器
     * @param $value
     * @param $data
     * @return void
     * FerryZhao 2024/4/18
     */
    public function getBorderPicAttr($value, $data)
    {
        $storeActivity = app()->make(StoreActivityRepository::class);
        $pic =  app()->make(StoreActivity::class)->where(
            [
                'scope_type' => $storeActivity::TYPE_MUST_SECKILL_ACTIVE,
                'activity_type' => $storeActivity::ACTIVITY_TYPE_BORDER,
                'link_id' => $data['seckill_active_id']
            ]
        )->value('pic');
        return $pic ?: '';
    }


    /**
     * 关联订单商品
     * @return void
     * FerryZhao 2024/4/26
     */
    public function seckillStoreOrderProduct()
    {
        return $this->hasMany(StoreOrderProduct::class, 'activity_id', 'seckill_active_id')->where(['product_type'=>1]);
    }

    /**
     * 停止时间获取器
     * @param $value
     * @param $data
     * @return void
     * FerryZhao 2024/4/28
     */
    public function getStopTimeAttr($value,$data)
    {
        $time = app()->make(StoreSeckillTime::class)->whereIn('seckill_time_id', explode(',', $data['seckill_time_ids']))->field('start_time,end_time')->max('end_time');
        $date = date('Y-m-d',strtotime($data['end_day'])).' '.$time.':00:00';
        return strtotime($date);
    }
}
