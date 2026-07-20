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
use app\common\repositories\store\order\MerchantOrderCreateRepository;

class OrderValidate extends Validate
{
    protected $rule = [
        'id|订单id' => 'require|number',
        'uid|用户id' => 'require|number',
        'cart_ids|购物车ids' => 'require|array',
        'address_id|地址id' => 'number',
        'delivery_way|配送方式' => 'require|number|in:0,1,4',
        'use_coupon|使用优惠券' => 'array',
        'is_free_shipping|是否免运费' => 'require|number',
        'use_integral|使用积分' => 'require|number',
        'tourist_unique_key' | '游客唯一标识' => 'require',
        'key' => 'require',
        'mark' => 'max:255',
        'pay_type|支付方式' => 'require',
        'old_pay_price|支付价格' => 'require|float|egt:0|elt:999999.99',
        'phone|手机号' => 'mobile|requireIf:pay_type,balance', // 余额支付时必须
        'sms_code|验证码' => 'length:4|number|requireIf:pay_type,balance', // 余额支付时必须
        'auth_code|条码号' => 'requireIf:pay_type,weixinBarCode|requireIf:pay_type,alipayBarCode', // 条码支付时必须
        'part_start|预约开始时段' => 'require',
        'part_end|预约结束时段' => 'require',
        'order_type|服务方式' =>'require|number',
        'real_name|预约人姓名' =>'require',
        'user_phone|预约人手机号' =>'require|mobile',
        'user_address|预约人地址' =>'requireIf:order_type,0',
        'order_extend|表单信息' =>'array',
        'reservation_date|预约日期' =>'require|dateFormat:Y-m-d',
    ];

    protected $message = [
        'uid.require' => '用户id不能为空',
        'uid.number' => '用户id格式错误',
        'cart_ids.require' => '购物车ids不能为空',
        'cart_ids.array' => '购物车ids格式错误',
        'address_id.require' => '地址id不能为空',
        'address_id.number' => '地址id格式错误',
        'delivery_way.require' => '配送方式不能为空',
        'delivery_way.number' => '配送方式格式错误',
        'use_coupon.require' => '使用优惠券不能为空',
        'use_coupon.array' => '使用优惠券格式错误',
        'is_free_shipping.require' => '是否免运费不能为空',
        'is_free_shipping.number' => '是否免运费格式错误',
        'use_integral.require' => '使用积分不能为空',
        'use_integral.number' => '使用积分格式错误',
        'key.require' => '订单操作超时,请刷新页面'
    ];

    protected $scene = [
        'orderCheck'  =>  ['uid', 'cart_ids', 'address_id', 'delivery_way', 'use_coupon', 'is_free_shipping', 'use_integral'],
        'config' => ['uid'],
        'orderCreate' => [
            'uid',
            'cart_ids',
            'address_id',
            'delivery_way',
            'use_coupon',
            'key',
            'pay_type',
            'mark',
            'old_pay_price'
        ],
        'orderPay' => ['id', 'uid', 'pay_type', 'phone', 'sms_code', 'auth_code'],
        'payStatus' => ['id', 'uid', 'pay_type'],
        'reschedule' => ['order_type', 'real_name', 'user_phone', 'user_address', 'order_extend','reservation_date','part_start', 'part_end'],
        'reservation_time' => ['reservation_date', 'part_start', 'part_end']
    ];

    public function sceneCheck(array $data): bool
    {
        if (!$this->scene('orderCheck')->check($data)) {
            return false;
        }

        if ($data['uid'] != 0 && !empty($data['tourist_unique_key'])) {
            $this->error = '用户id和游客唯一标识不能同时存在';
            return false;
        }

        if ($data['uid'] == 0 && empty($data['tourist_unique_key'])) {
            $this->error = '游客唯一标识不能为空';
            return false;
        }

        return true;
    }

    public function sceneCreate(array $data, $merchant): bool
    {
        if (!$this->scene('orderCreate')->check($data)) {
            return false;
        }
        if ($data['uid'] != 0 && !empty($data['tourist_unique_key'])) {
            $this->error = '用户id和游客唯一标识不能同时存在';
            return false;
        }
        if ($data['uid'] == 0 && empty($data['tourist_unique_key'])) {
            $this->error = '游客唯一标识不能为空';
            return false;
        }
        if ($data['delivery_way'] == 0 && empty($data['address_id'])) {
            $this->error = '地址id不能为空';
            return false;
        }
        if ($data['pay_type'] == 'offline') {
            if (!systemConfig('offline_switch')) {
                $this->error = '未开启线下支付功能';
                return false;
            }
            if (!(($merchant['offline_switch']) ?? '')) {
                $this->error = '该店铺未开启线下支付';
                return false;
            }
        }

        return true;
    }

    public function scenePayConfig(array $data): bool
    {
        if (!$this->scene('config')->check($data)) {
            return false;
        }

        return true;
    }

    public function scenePay(array $data, $groupOrder): bool
    {
        if (!$this->scene('orderPay')->check($data)) {
            return false;
        }
        if (!in_array($data['pay_type'], MerchantOrderCreateRepository::PAY_TYPE)) {
            $this->error = '请选择正确的支付方式';
            return false;
        }
        if (!$groupOrder) {
            $this->error = '订单不存在或已支付';
            return false;
        }
        if ($data['pay_type'] == 'offline') {
            if (count($groupOrder['orderList']) > 1) {
                $this->error = '线下支付仅支持同店铺商品';
                return false;
            }
            if (!systemConfig('offline_switch')) {
                $this->error = '未开启线下支付功能';
                return false;
            }
            if (!(($groupOrder['orderList'][0]->merchant['offline_switch']) ?? '')) {
                $this->error = '该店铺未开启线下支付';
                return false;
            }
        }
        if ($data['pay_type'] == 'balance' && $data['uid'] == 0) {
            $this->error = '游客不能使用余额支付，请检查支付方式';
            return false;
        }

        return true;
    }

    public function sceneStatus(array $data): bool
    {
        if (!$this->scene('payStatus')->check($data)) {
            return false;
        }
        return true;
    }

    public function sceneReservationReschedule(array $data): bool
    {
        if (!$this->scene('reschedule')->check($data)) {
            return false;
        }
        return true;
    }

    public function sceneOnlyReservationTime(array $data): bool
    {
        if (!$this->scene('reservation_time')->check($data)) {
            return false;
        }
        return true;
    }
}
