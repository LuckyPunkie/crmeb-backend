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

namespace app\common\model\circle;
        
use app\common\model\BaseModel;
use app\common\model\system\admin\Admin;

class CircleBrokerageCheckout extends BaseModel
{
    protected $updateTime = 'update_time';

    public static function tablePk(): ?string
    {
        return 'checkout_id';
    }
    
    public static function tableName(): string
    {
        return 'circle_brokerage_checkout';
    }

    public function getTransferVoucherAttr($value)
    {
        return json_decode($value, true);
    }

    public function agent()
    {
        return $this->hasOne(CircleAgent::class, 'circle_agent_id', 'agent_id')->field('circle_agent_id,uid');
    }

    public function admin()
    {
        return $this->hasMany(Admin::class, 'admin_id', 'audit_admin_id')->field('admin_id, real_name, account');
    }
}
