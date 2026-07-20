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

use crmeb\services\pay\Pay;
use app\common\model\BaseModel;
use app\common\model\system\merchant\Merchant;

class StoreOrderProfitsharing extends BaseModel
{

    const TYPE_NAME = ['order' => '订单支付', 'presell' => '尾款支付'];

    const STATUS_NAME = [2 => '分账中', 1 => '已分账', 0 => '待分账',  -1 => '已退款', -2 => '退款失败'];

    public static function tablePk(): string
    {
        return 'profitsharing_id';
    }

    public static function tableName(): string
    {
        return 'store_order_profitsharing';
    }

    public function getTypeNameAttr()
    {
        return self::TYPE_NAME[$this->getAttr('type')];
    }

    public function getStatusNameAttr()
    {
        return self::STATUS_NAME[$this->status];
    }

    public function order()
    {
        return $this->hasOne(StoreOrder::class, 'order_id', 'order_id');
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'mer_id', 'mer_id');
    }

    public function getProfitsharingParmas($is_combine)
    {
        return [
            'transaction_id' => $this->transaction_id,
            'sub_mchid' => $is_combine == 1 ? $this->merchant->sub_mchid : $this->merchant->applyment_id,
            'out_order_no' => $this->profitsharing_sn,
            'receivers' => [
                [
                    'amount' =>  bcsub($this->profitsharing_price, $this->profitsharing_mer_price, 2),
                    'body' => '订单分账',
                    'receiver_account' => (string)systemConfig('wechat_service_merid'),
                ]
            ]
        ];
    }

    public function getProfitsharingFinishParmas($is_combine)
    {
        return [
            'sub_mchid' => $is_combine == 1 ? $this->merchant->sub_mchid : $this->merchant->applyment_id,
            'transaction_id' => $this->order->transaction_id,
            'out_order_no' => $this->profitsharing_sn,
            'description' => '订单分账',
        ];
    }
}
