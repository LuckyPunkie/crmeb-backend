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
use think\model\concern\SoftDelete;

class PddOrder extends BaseModel
{
    use SoftDelete;

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = null;

    protected $type = [
        'uid' => 'integer',
        'order_sn' => 'string',
        'item_id' => 'string',
        'item_price' => 'float',
        'item_num' => 'integer',
        'total_price' => 'float',
        'income_price' => 'float',
        'commission_price' => 'float',
        'order_status' => 'integer',
        'order_type' => 'integer',
        'is_share' => 'integer',
        'pdd_status' => 'integer',
        'order_time' => 'timestamp',
        'pay_time' => 'timestamp',
        'finish_time' => 'timestamp',
        'category_id' => 'integer',
        'is_fanli' => 'integer',
        'fanli_time' => 'timestamp',
    ];

    public static $statusText = [
        0 => '未付款',
        1 => '已付款',
        2 => '已完成',
        3 => '已取消',
    ];

    public static $typeText = [
        0 => '自购',
        1 => '分享',
    ];

    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'taoke_pdd_order';
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'uid', 'uid');
    }

    public function commissionLogs()
    {
        return $this->hasMany(CommissionLog::class, 'order_id', 'id');
    }

    public function getStatusTextAttr($status)
    {
        return self::$statusText[$status] ?? '未知';
    }

    public function getTypeTextAttr($type)
    {
        return self::$typeText[$type] ?? '未知';
    }

    public function searchUidAttr($query, $value)
    {
        $query->where('uid', $value);
    }

    public function searchOrderStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('order_status', $value);
        }
    }

    public function searchOrderSnAttr($query, $value)
    {
        if ($value) {
            $query->where('order_sn', 'like', '%' . $value . '%');
        }
    }

    public function searchItemTitleAttr($query, $value)
    {
        if ($value) {
            $query->where('item_title', 'like', '%' . $value . '%');
        }
    }
}
