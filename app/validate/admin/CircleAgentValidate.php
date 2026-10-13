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

class CircleAgentValidate extends Validate
{
    protected $rule = [
        'type|类型' => 'require|number|in:0,1',
        'uid|用户ID' => 'number|gt:0',
        'name|姓名' => 'require|max:64',
        'phone|电话' => 'require|mobile|gt:0',
        'qualification|申请资质' => 'array|max:10',
        'remark|说明' => 'max:255',
        'extend|扩展表单' => 'array',
        'status|状态' => 'require|in:1,-1',
        'audit_reason|审核原因' => 'requireIf:status,-1|max:255',
        'payment_method|结算方式' => 'require|number|in:0,1,2',
        'payment_name|结算账户姓名' => 'require|max:16',
        'payment_account|结算账号' => 'require|max:30',
        'payment_bank|开户行' => 'requireIf:payment_method,0|max:30',
        'payment_qr_img|收款二维码图片' => 'requireIn:payment_method,1,2|max:200',
        'account|账号' => 'requireIf:type,1|max:30',
        'password|密码' => 'requireIf:type,1|max:30'
    ];

    protected $scene = [
        'create' => ['type', 'uid', 'name', 'phone', 'qualification', 'remark', 'extend', 'account', 'password'],
        'edit' => ['type', 'name', 'phone', 'qualification', 'remark', 'extend', 'uid'],
        'audit' => ['status', 'audit_reason'],
        'settlementMethod' => ['payment_method', 'payment_name', 'payment_account', 'payment_bank', 'payment_qr_img']
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
        if(in_array($data['payment_method'], [$rule[1], $rule[2]]) && $value === '') {
            return false;
        }

        return true;
    }

    public function add(array $data): bool
    {
        if (!$this->scene('create')->check($data)) {
            return false;
        }

        return true;
    }

    public function edit(array $data): bool
    {
        if (!$this->scene('edit')->check($data)) {
            return false;
        }

        return true;
    }

    public function audit(array $data): bool
    {
        if (!$this->scene('audit')->check($data)) {
            return false;
        }

        return true;
    }

    public function setSettlementMethod(array $data)
    {
        if (!$this->scene('settlementMethod')->check($data)) {
            return false;
        }

        return true;
    }
}
