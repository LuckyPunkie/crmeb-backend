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

class StoreSeckillTime extends BaseModel
{
    protected $append = ['start_time_text','end_time_text'];
    const ISTIME = [
        0   => '00:00',
        1   => '01:00',
        2   => '02:00',
        3   => '03:00',
        4   => '04:00',
        5   => '05:00',
        6   => '06:00',
        7   => '07:00',
        8   => '08:00',
        9   => '09:00',
        10  => '10:00',
        11  => '11:00',
        12  => '12:00',
        13  => '13:00',
        14  => '14:00',
        15  => '15:00',
        16  => '16:00',
        17  => '17:00',
        18  => '18:00',
        19  => '19:00',
        20  => '20:00',
        21  => '21:00',
        22  => '22:00',
        23  => '23:00',
        24  => '24:00',
    ];

    /**
     *
     * @return string
     * @author Qinii
     * @day 2020-07-30
     */
    public static function tablePk(): string
    {
        return 'seckill_time_id';
    }

    /**
     *
     * @return string
     * @author Qinii
     * @day 2020-07-30
     */
    public static function tableName(): string
    {
        return 'store_seckill_time';
    }

    /**
     * 开始时间获取器
     * @param $value
     * @param $data
     * @return void
     * FerryZhao 2024/4/12
     */
    public static function getStartTimeTextAttr($value,$data)
    {
        if(isset($data['start_time']) && $data['start_time'] == 0){
            return $data['start_time'].'0:00';
        }
        if(isset($data['start_time'])){
            return $data['start_time'].':00';
        }
    }


    /**
     * 结束时间获取器
     * @param $value
     * @param $data
     * @return void
     * FerryZhao 2024/4/12
     */
    public static function getEndTimeTextAttr($value,$data)
    {
        if(isset($data['end_time']) && $data['end_time'] == 0){
            return $data['end_time'].'0:00';
        }
        if(isset($data['end_time'])){
            return $data['end_time'].':00';
        }
    }
}
