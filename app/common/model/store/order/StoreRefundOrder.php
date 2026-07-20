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


namespace app\common\model\store\order;


use app\common\model\BaseModel;
use app\common\model\store\service\StoreService;
use app\common\model\system\merchant\Merchant;
use app\common\model\system\merchant\MerchantAdmin;
use app\common\model\user\User;

class StoreRefundOrder extends BaseModel
{

    public static function tablePk(): ?string
    {
        return 'refund_order_id';
    }

    public static function tableName(): string
    {
        return 'store_refund_order';
    }

    public function getPicsAttr($val)
    {
        return $val ? explode(',', $val) : [];
    }

    public function setPicsAttr($val)
    {
        return $val ? implode(',', $val) : '';
    }

    public function refundProduct()
    {
        return $this->hasMany(StoreRefundProduct::class, 'refund_order_id', 'refund_order_id');
    }

    public function admin()
    {
        return $this->hasOne(MerchantAdmin::class, 'merchant_admin_id', 'admin_id');
    }
    public function services()
    {
        return $this->hasOne(StoreService::class, 'service_id', 'admin_id');
    }


    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'mer_id', 'mer_id');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'uid', 'uid');
    }

    public function order()
    {
        return $this->hasOne(StoreOrder::class, 'order_id', 'order_id');
    }

    public function searchDataAttr($query, $value)
    {
        return getModelTime($query, $value);
    }

    public function getPhoneAttr($value)
    {
        if (env('SHOW_PHONE',false) && app('request')->hasMacro('adminInfo') && $value && is_numeric($value)){
            if (app('request')->userType() !== 2 || app('request')->adminInfo()['level'] != 0) {
                return substr_replace($value, '****', 3, 4);
            }
        }
        return $value;
    }

    public function getAutoRefundTimeAttr()
    {
        $merAgree = systemConfig('mer_refund_order_agree') ?: 7;
        return strtotime('+' . $merAgree . ' day', strtotime($this->status_time));
    }

    public function getCombineRefundParams($type = 2)
    {
        $sub_mchid = $type == 2 ? $this->merchant->applyment_id : $this->merchant->sub_mchid;
        return [
            'sub_mchid' => $sub_mchid,
            'order_sn' => $this->order->order_sn,
            'refund_order_sn' => $this->refund_order_sn,
            'refund_price' => $this->refund_price,
            'pay_price' => $this->order->pay_price,
            'refund_message' => $this->refund_message,
            'open_id' => $this->user->wechat->routine_openid ?? null,
            'transaction_id' => $this->order->transaction_id,
        ];
    }

    public function getCreateUserAttr()
    {
        switch ($this->user_type){
            case 1:
                return $this->user->nickname ?? '无';
                break;
            case 3:
                return $this->admin->real_name ?? '无';
                break;
            case 4:
                return $this->services->nickname ?? '无';
                break;
        }
        return '无';
    }

    public function platform()
    {
        return $this->hasOne(StoreOrderStatus::class, 'order_sn', 'refund_order_sn')
            ->where('type', 'refund')
            ->whereIn('change_type', 'refund_platform_refuse,refund_platform_agree');
    }
}
