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

class CartValidate extends Validate
{
    protected $rule = [
        'uid|用户id' => 'require|number',
        'product_id|商品id' => 'require|number',
        'cart_num|数量' => 'require|number|gt:0',
        'product_attr_unique|规格编码' => 'max:32',
        'cart_ids' => 'require|array',
        'type|类型' => 'require|number|between:0,2',
        'old_price|应付金额' => 'require|float|egt:0|elt:999999.99',
        'new_price|一口价金额' => 'float|egt:0|elt:999999.99|requireIf:type,0',
        'reduce_price|减价金额' => 'float|egt:0|elt:999999.99|requireIf:type,1|requireIf:change_fee_type,1',
        'discount_rate|折扣率' => 'number|egt:0|elt:100|requireIf:type,2|requireIf:change_fee_type,2',
        'change_fee_type|批量改价类型' => 'require|number|between:0,2',
        'old_pay_price|应付金额' => 'require|float|egt:0|elt:999999.99',
        'new_pay_price|一口价金额' => 'float|egt:0|elt:999999.99|requireIf:change_fee_type,0'
    ];

    public $message = [
        'discount_rate.number' => '折扣率必须是整数'
    ];

    protected $scene = [
        'create'  =>  ['uid', 'product_id', 'cart_num', 'product_attr_unique'],
        'change' =>  ['uid', 'cart_num', 'product_attr_unique'],
        'list' =>  ['uid'],
        'updatePrice' =>  ['type', 'old_price', 'new_price', 'reduce_price', 'discount_rate'],
        'batchUpdatePrice' => ['uid', 'change_fee_type', 'old_pay_price', 'new_pay_price', 'reduce_price', 'discount_rate', 'cart_ids']
    ];

    public function createCheck(array $data): bool
    {
        if (!$this->scene('create')->check($data)) {
            return false;
        }

        if($data['uid'] == 0 && empty($data['tourist_unique_key'])) {
            $this->error = '请传入游客唯一标识';
            return false;
        }

        return true;
    }

    public function changeCheck(array $data): bool
    {
        if (!$this->scene('change')->check($data)) {
            return false;
        }

        return true;
    }

    public function listCheck(array $data): bool
    {
        if (!$this->scene('list')->check($data)) {
            return false;
        }

        if($data['uid'] == 0 && empty($data['tourist_unique_key'])) {
            $this->error = '请传入游客唯一标识';
            return false;
        }

        return true;
    }

    public function updatePriceCheck(array $data): bool
    {
        if (!$this->scene('updatePrice')->check($data)) {
            return false;
        }

        return true;
    }

    public function batchUpdatePriceCheck(array $data): bool
    {
        if (!$this->scene('batchUpdatePrice')->check($data)) {
            return false;
        }

        return true;
    }
}
