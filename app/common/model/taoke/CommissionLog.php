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

namespace app\common\model\taoke;

use app\common\model\BaseModel;
use app\common\model\user\User;

class CommissionLog extends BaseModel
{
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $type = [
        'order_id' => 'integer',
        'uid' => 'integer',
        'parent_uid' => 'integer',
        'level' => 'integer',
        'is_share' => 'integer',
        'commission_total' => 'float',
        'commission_rate' => 'float',
        'commission_money' => 'float',
        'status' => 'integer',
        'settle_time' => 'timestamp',
    ];

    public static $statusText = [
        0 => '预估',
        1 => '已结算',
        2 => '已失效',
    ];

    public static $levelText = [
        0 => '自购',
        1 => '一级',
        2 => '二级',
    ];

    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'taoke_commission_log';
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'uid', 'uid');
    }

    public function parentUser()
    {
        return $this->belongsTo(User::class, 'parent_uid', 'uid');
    }

    public function taobaoOrder()
    {
        return $this->belongsTo(TaobaoOrder::class, 'order_id', 'id')->where('order_type', 'tb');
    }

    public function jdOrder()
    {
        return $this->belongsTo(JdOrder::class, 'order_id', 'id')->where('order_type', 'jd');
    }

    public function pddOrder()
    {
        return $this->belongsTo(PddOrder::class, 'order_id', 'id')->where('order_type', 'pdd');
    }

    public function getStatusTextAttr($status)
    {
        return self::$statusText[$status] ?? '未知';
    }

    public function getLevelTextAttr($level)
    {
        return self::$levelText[$level] ?? '未知';
    }

    public function searchUidAttr($query, $value)
    {
        $query->where('uid', $value);
    }

    public function searchStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }

    public function searchOrderTypeAttr($query, $value)
    {
        if ($value) {
            $query->where('order_type', $value);
        }
    }
}
