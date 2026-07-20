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
use app\common\model\user\User;
use app\common\model\system\admin\Admin;

class CircleAgent extends BaseModel
{
    protected $updateTime = 'update_time';

    public static function tablePk(): ?string
    {
        return 'circle_agent_id';
    }

    public static function tableName(): string
    {
        return 'circle_agent';
    }

    public function setQualificationAttr($value)
    {
        return is_array($value) ? json_encode($value) : $value;
    }

    public function getQualificationAttr($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    public function setExtendAttr($value)
    {
        return is_array($value) ? json_encode($value) : $value;
    }

    public function getExtendAttr($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    public function user()
    {
        return $this->hasOne(User::class, 'uid', 'uid')->field('uid, nickname, phone, avatar');
    }

    public function circle()
    {
        return $this->hasMany(Circle::class, 'circle_agent_id', 'circle_agent_id')
            ->field('circle_id, name, circle_agent_id, commission_type, commission_rate, level, status, type')
            ->append(['merchant_count'], true);
    }

    public function auditAdmin()
    {
        return $this->hasOne(Admin::class, 'admin_id', 'audit_admin_id')->field('admin_id, real_name, account');
    }

    public function admin()
    {
        return $this->hasOne(Admin::class, 'circle_agent_id', 'circle_agent_id')
            ->field('admin_id, real_name, account, circle_agent_id')
            ->where('is_agent', '>', 0);
    }
    /**
     * 商户数量
     *
     * @return void
     */
    public function getMerchantCountAttr()
    {
        $num = 0;

        $circle = $this->circle->toArray();
        if($circle) {
            $num = array_sum(array_column($circle, 'merchant_count'));
        }

        return $num;
    }
    /**
     * 订单数量
     *
     * @return void
     */
    public function getOrderCountAttr()
    {
        $id = $this->circle_agent_id;
        $count = CircleFinancialRecord::where('agent_id', $id)->where('status', '<>', -1)->group('order_id')->count();

        return $count;
    }
    /**
     * 总提成
     *
     * @return void
     */
    public function getTotalAmountAttr()
    {
        $id = $this->circle_agent_id;
        $amount = CircleFinancialRecord::where('agent_id', $id)->where('status', '<>', -1)->sum('amount');

        return $amount;
    }
    /**
     * 冻结提成
     *
     * @return void
     */
    public function getFrozenAmountAttr()
    {
        $id = $this->circle_agent_id;
        $amount = CircleFinancialRecord::where('agent_id', $id)->where('status', 0)->sum('amount');

        return $amount;
    }

}
