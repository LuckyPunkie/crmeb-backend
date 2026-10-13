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

use think\Validate;

class DeliveryConfigValidate extends Validate
{
    protected $rule = [
        'mer_delivery_type|配送方式' => 'require|array',
        'mer_delivery_order_status|配送员抢单开关' => 'require|integer|in:0,1',
        'dada_app_key|达达AppKey' => 'requireIn:mer_delivery_type,1',
        'dada_app_sercret|达达AppSecret' => 'requireIn:mer_delivery_type,1',
        'dada_source_id|达达商户ID' => 'requireIn:mer_delivery_type,1',
        'uupt_appkey|UU跑腿AppKey' => 'requireIn:mer_delivery_type,2',
        'uupt_app_id|UU跑腿AppId' => 'requireIn:mer_delivery_type,2',
        'uupt_open_id|UU跑腿OpenId' => 'requireIn:mer_delivery_type,2',
        'min_delivery_amount|起送价' => 'require|float|egt:0|elt:999999.99',
        'base_shipping_fee|基础运费' => 'require|float|egt:0|elt:999999.99',
        'free_shipping_amount|包邮金额' => 'require|float|egt:0|elt:999999.99',
        'is_premium_stack_enabled|是否开启溢价叠加' => 'require|integer|in:0,1',
        'distance_premium_config|距离溢价设置' => 'requireIf:is_premium_stack_enabled,1|array',
        'distance_premium_config.level_first.lt_distance|第一级公里数' => 'requireIf:is_premium_stack_enabled,1|float|gt:0|elt:999999.99',
        'distance_premium_config.level_last.gt_distance|最后一级公里数' => 'requireIf:is_premium_stack_enabled,1|float|gt:0|elt:999999.99',
        'distance_premium_config.level_last.add_distance|最后一级公里数,增加公里数' => 'requireIf:is_premium_stack_enabled,1|float|gt:0|elt:999999.99',
        'distance_premium_config.level_last.add_amount|最后一级公里数,增加运费' => 'requireIf:is_premium_stack_enabled,1|float|gt:0|elt:999999.99',
        'weight_premium_config|重量溢价设置' => 'requireIf:is_premium_stack_enabled,1|array',
        'weight_premium_config.level_first.lt_weight|第一级重量' => 'requireIf:is_premium_stack_enabled,1|float|gt:0|elt:999999.99',
        'weight_premium_config.level_last.gt_weight|最后一级重量' => 'requireIf:is_premium_stack_enabled,1|float|gt:0|elt:999999.99',
        'weight_premium_config.level_last.add_weight|最后一级重量,增加重量' => 'requireIf:is_premium_stack_enabled,1|float|gt:0|elt:999999.99',
        'weight_premium_config.level_last.add_amount|最后一级重量,增加运费' => 'requireIf:is_premium_stack_enabled,1|float|gt:0|elt:999999.99',
        'delivery_time_type|配送时间类型' => 'require|integer|in:1,2',
        'selectable_days|用户端可选天数' => 'requireIf:delivery_time_type,1|integer|between:0,30',
        'delivery_prompt|尽快送达文案' => 'requireIf:delivery_time_type,2',
        'commission_rate|配送员提成' => 'require|float|between:0,100',
        'start_distance|距离阶梯,开始距离' => 'require|float|gt:0|elt:999999.99',
        'end_distance|距离阶梯,结束距离' => 'require|float|gt:0|elt:999999.99',
        'add_distance|距离阶梯,增加距离' => 'require|float|gt:0|elt:999999.99',
        'start_weight|重量阶梯,开始重量' => 'require|float|gt:0|elt:999999.99',
        'end_weight|重量阶梯,结束重量' => 'require|float|gt:0|elt:999999.99',
        'add_weight|重量阶梯,增加重量' => 'require|float|gt:0|elt:999999.99',
        'add_amount|重量阶梯,增加运费' => 'require|float|gt:0|elt:999999.99',
    ];

    protected $scene = [
        'basic'  =>  [
            'mer_delivery_type',
            'mer_delivery_order_status',
            'dada_app_key',
            'dada_app_sercret',
            'dada_source_id',
            'uupt_appkey',
            'uupt_app_id',
            'uupt_open_id'
        ],
        'config' => [
            'min_delivery_amount',
            'base_shipping_fee',
            'free_shipping_amount',
            'is_premium_stack_enabled',
            'distance_premium_config',
            'distance_premium_config.level_first.lt_distance',
            'distance_premium_config.level_last.gt_distance',
            'distance_premium_config.level_last.add_distance',
            'distance_premium_config.level_last.add_amount',
            'weight_premium_config',
            'weight_premium_config.level_first.lt_weight',
            'weight_premium_config.level_last.gt_weight',
            'weight_premium_config.level_last.add_weight',
            'weight_premium_config.level_last.add_amount',
            'delivery_time_type',
            'selectable_days',
            'delivery_prompt',
            'commission_rate'
        ],
        'stair_distance' => [
            'start_distance',
            'end_distance',
            'add_distance',
            'add_amount'
        ],
        'stair_weight' => [
            'start_weight',
            'end_weight',
            'add_weight',
            'add_amount'
        ]
    ];

    /**
     * 验证某个字段包含某个值的时候必须
     *
     * @param string $value 配送方式
     * @param string $rule 验证规则
     * @param array $data 验证数据
     * @return void
     */
    protected function requireIn(string $value, string $rule, array $data = [])
    {
        $rule = explode(',', $rule);
        if(in_array($rule[1], $data['mer_delivery_type']) && $value === '') {
            return false;
        }

        return true;
    }

    public function update(array $basicSettings, array $deliveryParams)
    {
        if(empty($basicSettings['mer_delivery_type'])) {
            $this->error = '配送方式不能为空';
            return false;
        }

        if(!$this->scene('basic')->check($basicSettings)) {
            return false;
        }
        if(!$this->scene('config')->check($deliveryParams)) {
            return false;
        }
        if($deliveryParams['is_premium_stack_enabled']) {
            // 距离溢价设置验证
            $kaishiDistance = $deliveryParams['distance_premium_config']['level_first']['lt_distance'];
            if(!empty($deliveryParams['distance_premium_config']['level_stairs'])) {
                foreach ($deliveryParams['distance_premium_config']['level_stairs'] as $key => $item) {
                    $num = $key + 1;
                    if (!$this->scene('stair_distance')->check($item)) {
                        return false;
                    }
                    if($item['start_distance'] >= $item['end_distance']) {
                        $this->error = '第'.$num.'级阶梯公里数范围有误';
                        return false;
                    }
                    if($key == 0 && $kaishiDistance > $item['start_distance']) {
                        $this->error = '范围公里数，必须大于等于基础公里数';
                        return false;
                    }
                    if($kaishiDistance > $item['start_distance']) {
                        $this->error = '第'.$num.'级范围公里数必须大于等于第'.($num - 1).'级范围公里数';
                        return false;
                    }
                    if($item['add_distance'] === '' || $item['add_distance'] <= 0) {
                        $this->error = '第'.$num.'级范围公里数,增加公里数必须大于0';
                        return false;
                    }
                    if($item['add_amount'] === '' || $item['add_amount'] <= 0) {
                        $this->error = '第'.$num.'级范围公里数,增加运费必须大于0';
                        return false;
                    }

                    $kaishiDistance = $item['end_distance'];
                }
            }
            if($kaishiDistance > $deliveryParams['distance_premium_config']['level_last']['gt_distance']) {
                $this->error = '最后一级公里数,必须大于等于上一级公里数';
                return false;
            }
            // 重量溢价设置验证
            $kaishiWeight = $deliveryParams['weight_premium_config']['level_first']['lt_weight'];
            if(!empty($deliveryParams['weight_premium_config']['level_stairs'])) {
                foreach ($deliveryParams['weight_premium_config']['level_stairs'] as $key => $item) {
                    $num = $key + 1;
                    if (!$this->scene('stair_weight')->check($item)) {
                        return false;
                    }
                    if($item['start_weight'] >= $item['end_weight']) {
                        $this->error = '第'.$num.'级阶梯重量范围有误';
                        return false;
                    }
                    if($key == 0 && $kaishiWeight > $item['start_weight']) {
                        $this->error = '范围重量，必须大于等于基础重量';
                        return false;
                    }
                    if($kaishiWeight > $item['start_weight']) {
                        $this->error = '第'.$num.'级范围重量必须大于等于第'.($num - 1).'级范围重量';
                        return false;
                    }
                    if($item['add_weight'] === '' || $item['add_weight'] <= 0) {
                        $this->error = '第'.$num.'级范围重量,增加重量必须大于0';
                        return false;
                    }
                    if($item['add_amount'] === '' || $item['add_amount'] <= 0) {
                        $this->error = '第'.$num.'级范围重量,增加运费必须大于0';
                        return false;
                    }

                    $kaishiWeight = $item['end_weight'];
                }
            }
            if($kaishiWeight > $deliveryParams['weight_premium_config']['level_last']['gt_weight']) {
                $this->error = '最后一级重量,必须大于等于上一级重量';
                return false;
            }
        }

        return true;
    }
}
