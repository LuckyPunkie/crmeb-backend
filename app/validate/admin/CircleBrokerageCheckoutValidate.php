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
namespace app\validate\admin;

use EasyWeChat\Support\Arr;
use think\Validate;

class CircleBrokerageCheckoutValidate extends Validate
{
    protected $rule = [
        'agent_id|商圈代理ID' => 'require|number|gt:0',
        'withdrawal_amount|提现金额' => 'require|float',
        'withdrawal_type|提现方式' => 'require|number|in:0,1,2',
        'audit_status|审核状态' => 'require|in:1,-1',
        'audit_reason|审核原因' => 'requireIf:audit_status,-1|max:200',
        'transfer_voucher|转账凭证' => 'require|array|max:9',
        'transfer_remark|转账备注' => 'max:200',
    ];

    protected $scene = [
        'create' => ['agent_id', 'withdrawal_amount', 'withdrawal_type'],
        'apply' => ['audit_status', 'audit_reason'],
        'voucher' => ['transfer_voucher', 'transfer_remark']
    ];

    public function add(array $data): bool
    {
        if (!$this->scene('create')->check($data)) {
            return false;
        }

        return true;
    }

    public function audit(array $data): bool
    {
        if (!$this->scene('apply')->check($data)) {
            return false;
        }

        return true;
    }

    public function transfer(array $data): bool
    {
        if (!$this->scene('voucher')->check($data)) {
            return false;
        }

        return true;
    }
}