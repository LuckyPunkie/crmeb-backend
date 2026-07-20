<?php

namespace app\common\model\store\staff;

use app\common\model\BaseModel;
use app\common\model\system\merchant\Merchant;
use app\common\model\user\User;
use think\model\concern\SoftDelete;
use app\common\model\store\order\StoreOrder;

/**
 *  员工模型
 */
class Staffs extends BaseModel
{
    use SoftDelete;

    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = null;

    public static function tablePk(): ?string
    {
        return 'staffs_id';
    }

    public static function tableName(): string
    {
        return 'staffs';
    }

    public function user()
    {
        return $this->hasOne(User::class, 'uid', 'uid');
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'mer_id', 'mer_id');
    }

    public function searchUidAttr($query, $value)
    {
        $query->where('uid', $value);
    }

    public function searchStatusAttr($query, $value)
    {
        $query->where('status', $value);
    }

    public function order()
    {
        return $this->hasMany(StoreOrder::class, 'staffs_id', 'staffs_id')
            ->field('order_id,pay_price,order_sn,status,staffs_id, create_time')
            ->with(['orderProduct'])
            ->where('status', '<>', -1)
            ->where('paid', 1)
            ->where('is_del', 0)
            ->order('create_time DESC, order_id DESC');
    }
}