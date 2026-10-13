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

namespace app\validate\merchant;

use think\Exception;
use think\File;
use think\Validate;
use app\common\repositories\store\product\NewProductRepository;

class StoreProductValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        "image|主图" => 'require|max:128',
        "store_name|商品名称" => 'require|max:128',
        "cate_id|平台分类" => 'require',
        "mer_cate_id|商户分类" => 'array',
        "unit_name|单位名称" => 'max:4',
        "spec_type" => "in:0,1",
        "is_show|是否上架" => "require|in:0,1,2",
        "auto_on_time|定时上架时间" => "requireIf:is_show,2|date",
        "auto_off_time|定时下架时间" => "date",
        "extension_type|分销类型" => "in:0,1",
        'type|商品类型' => 'require|in:0,1,2,3,4',
        'delivery_way|发货方式' => 'requireIf:type,0|requireIf:type,1|requireIf:type,2|requireIf:type,3',
        'once_min_count|最小限购' => 'min:0',
        'pay_limit|是否限购' => 'require|in:0,1,2|payLimit',
        'reservation_time_type|预约时段划分' => 'requireIf:type,4|in:1,2|integer',
        'reservation_start_time|预约开始时段' =>'requireIf:reservation_time_type,1|date',
        'reservation_end_time|预约结束时间' =>'requireIf:reservation_time_type,1|date',
        'reservation_time_interval|时间跨度' =>'requireIf:reservation_time_type,1|integer|egt:10|elt:1440',
        'time_period|时段信息' =>'requireIf:type,4|array|validateTimePeriods',
        'reservation_type|服务模式' =>'requireIf:type,4|in:1,2,3|integer',
        'sale_time_type|可售日期类型' =>'requireIf:type,4|in:1,2|integer',
        'sale_time_start_day|可售日期自定义开始时间' =>'requireIf:sale_time_type,2|date',
        'sale_time_end_day|可售日期自定义结束时间' =>'requireIf:sale_time_type,2|date',
        'sale_time_week|可售日期星期数据' =>'requireIf:sale_time_type,2|array',
        'show_reservation_days|预约展示天数' =>'requireIf:type,4|integer|egt:1|elt:999999',
        'show_num_type|是否显示剩余可约数量' =>'requireIf:type,4|in:0,1',
        'is_advance|是否提前预约' =>'requireIf:type,4|in:0,1',
        'advance_time|提前预约时间' =>'requireIf:is_advance,1|integer|egt:0|elt:999999',
        'is_cancel_reservation|是否可取消预约' =>'requireIf:type,4|in:0,1',
        'cancel_reservation_time|取消预约时间' =>'requireIf:is_cancel_reservation,1|integer|egt:0|elt:999999',
        "attr|商品规格" => "requireIf:spec_type,1|Array|checkUnique|checkValueLength",
        "attrValue|商品属性" => "require|array|productAttrValue",
        'keyword|商品关键词' => 'max:128',
    ];

    protected $scene = [
        'add' => ['image','auto_on_time','auto_off_time','store_name','cate_id','mer_cate_id','unit_name','spec_type','is_show','extension_type','attr','attrValue','type','delivery_way','once_min_count','pay_limit','keyword'],
        'reservation' => [
            'reservation_time_type',
            'reservation_start_time',
            'reservation_end_time',
            'reservation_time_interval',
            'time_period',
            'reservation_type',
            'sale_time_type',
            'sale_time_start_day',
            'sale_time_end_day',
            'sale_time_week',
            'show_reservation_days',
            'show_num_type',
            'is_advance',
            'advance_time',
            'is_cancel_reservation',
            'cancel_reservation_time'
        ]
    ];

    protected function checkValueLength($array) {
        $maxLength = 30;
        foreach ($array as $item) {
            if (isset($item['value']) && (mb_strlen($item['value']) > $maxLength)) {
                return '规格长度不能超过30个字符'; // 发现超过 20 的值，返回 false
            }
            if (isset($item['detail']) && is_array($item['detail'])) {
                foreach ($item['detail'] as $detailItem) {
                    if (isset($detailItem['value']) && mb_strlen($detailItem['value']) > $maxLength) {
                        return '规格值长度不能超过30个字符';
                    }
                }
            }
        }
        return true; // 所有值都符合长度限制
    }

    protected function payLimit($value,$rule,$data)
    {
        if ($value && ($data['once_max_count'] < $data['once_min_count']))
           return '限购数量不能小于最少购买件数';
        return true;
    }

    protected function productAttrValue($value,$rule,$data)
    {
        $arr = [];
        try{
            foreach ($value as $v){
                $sku = '';
                if(isset($v['detail']) && is_array($v['detail'])){
                    sort($v['detail'],SORT_STRING);
                    $sku = implode(',',$v['detail']);
                    if(in_array($sku,$arr)) return '商品SKU重复';
                    $arr[] = $sku;
                }
                if(isset($data['extension_type']) && $data['extension_type'] && systemConfig('extension_status')){
                    if(!isset($v['extension_one']) || !isset($v['extension_two'])) return '佣金金额必须填写';
                    if(($v['extension_one'] < 0) || ($v['extension_two'] < 0))
                        return '佣金金额不可存在负数';
                    if($v['price'] < bcadd($v['extension_one'],$v['extension_two'],2))
                        return '自定义佣金总金额不能大于商品售价';
                }
                if ($data['product_type'] == 20 && !$v['ot_price']) {
                    return '积分商品兑换积分必须大于0';
                }
                if($data['type'] == NewProductRepository::DEFINE_TYPE_RESERVATION) {
                    if(!isset($v['reservation'])) {
                        return '预约商品规格错误';
                    }
                    foreach ($v['reservation'] as $item) {
                        if(!isset($item['start_time'])
                            || !isset($item['end_time'])
                            || !isset($item['stock'])
                        ) {
                            return '预约商品规格错误';
                        }

                        if($item['stock'] < 0) {
                            return '预约库存不可小于0';
                        }
                    }
                }
            }
        } catch (\Exception $exception) {
            return '商品属性格式错误';
        }
        return true;
    }

    protected function checkUnique($value,$rule,$data)
    {
        if($data['type'] == NewProductRepository::DEFINE_TYPE_RESERVATION && count($value) > 1) {
            return '预约商品规格最多只能添加一个';
        }

        $arr = [];
        foreach ($value as $item){
            if(in_array($item['value'],$arr))return '规格重复';
            $arr[] = $item['value'];
            if ($data['product_type'] == 20) {
                $count = array_unique($item['detail']);
            } else {
                $count = array_unique(array_column($item['detail'],'value'));
            }
            if (count($item['detail']) != count($count))
                return '属性重复';
        }
        return true;
    }

    public function sceneCreate(array $data)
    {
        if (!$this->scene('add')->check($data)) {
            return false;
        }
        if (!$data['spec_type'] && count($data['attrValue']) > 1) {
            $this->error = '单规格商品属性错误';
            return false;
        }

        if($data['type'] == NewProductRepository::DEFINE_TYPE_RESERVATION) {
            if(!$this->scene('reservation')->check($data)) {
                return false;
            }
        }

        return true;
    }

    public function sceneUpdate(array $data)
    {
        if (!$this->scene('add')->check($data)) {
            return false;
        }
        if (!$data['spec_type'] && count($data['attrValue']) > 1) {
            $this->error = '单规格商品属性错误';
            return false;
        }

        if($data['type'] == NewProductRepository::DEFINE_TYPE_RESERVATION) {
            if(!$this->scene('reservation')->check($data)) {
                return false;
            }
        }

        return true;
    }

    public function sceneBatchProductStock(array $data)
    {
        if(!isset($data['stockValue'])) {
            $this->error = '预约商品规格错误';
            return false;
        }
        foreach ($data['stockValue'] as $item) {
            if(!isset($item['value_id'])) {
                $this->error = '预约商品规格传参错误';
                return false;
            }
            foreach ($item['reservation'] as $reservation) {
                if(!isset($reservation['stock']) || !isset($reservation['attr_reservation_id'])) {
                    $this->error = '预约商品规格错误';
                    return false;
                }

                if($reservation['stock'] < 0) {
                    $this->error = '预约库存不可小于0';
                    return false;
                }

            }
        }

        return true;
    }

    protected function validateTimePeriods($periods)
    {
        if (!is_array($periods) || empty($periods)) {
            return '时段不能为空';
        }

        $uniquePeriods = [];
        $lastEndTime = null;
        foreach ($periods as $period) {
            if (!isset($period['start']) || !isset($period['end']) || !isset($period['is_show'])) {
                return '时段传参错误';
            }

            $startTime = strtotime($period['start']);
            $endTime = strtotime($period['end']);
            if (!$startTime || !$endTime) {
                return '时间格式错误';
            }
            if ($startTime >= $endTime) {
                return '时段结束时间必须大于开始时间';
            }
            // 检查时间段是否递增且无交集
            if ($lastEndTime !== null && $startTime < $lastEndTime) {
                return '时段必须递增且无交集';
            }

            $lastEndTime = $endTime;
            // 检查重复时段
            $timeKey = $period['start'] . '-' . $period['end'];
            if (in_array($timeKey, $uniquePeriods)) {
                return '存在重复时段';
            }
            $uniquePeriods[] = $timeKey;
        }

        return true;
    }
}
