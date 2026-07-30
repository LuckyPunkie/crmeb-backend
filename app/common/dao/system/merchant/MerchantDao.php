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


namespace app\common\dao\system\merchant;


use app\common\dao\BaseDao;
use app\common\model\system\merchant\Merchant;
use crmeb\services\merchant\MerchantWriteGuardService;
use crmeb\services\VicWordService;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use think\Model;

class MerchantDao extends BaseDao
{

    /**
     * @return string
     * @author xaboy
     * @day 2020-04-16
     */
    protected function getModel(): string
    {
        return Merchant::class;
    }

    /**
     * 创建商户数据，并在写入前校验核心字段写入许可。
     *
     * @param array $data 商户创建数据。
     * @return mixed 返回父级 DAO 创建结果。
     * @throws \think\exception\ValidateException 数据包含核心字段且缺少写入许可时抛出。
     */
    public function create(array $data)
    {
        app()->make(MerchantWriteGuardService::class)->assertAllowedForData(null, $data, 'merchant_dao_create');
        return parent::create($data);
    }

    /**
     * 更新商户数据，并在写入前校验核心字段写入许可。
     *
     * @param int $id 商户 ID。
     * @param array $data 商户更新数据。
     * @return mixed 返回父级 DAO 更新结果。
     * @throws \think\exception\ValidateException 数据包含核心字段且缺少写入许可时抛出。
     */
    public function update(int $id, array $data)
    {
        app()->make(MerchantWriteGuardService::class)->assertAllowedForData($id, $data, 'merchant_dao_update');
        return parent::update($id, $data);
    }

    /**
     * @param array $where
     * @return BaseQuery
     * @author xaboy
     * @day 2020-04-16
     */
    public function search(array $where, $is_del = 0)
    {
        $query = Merchant::getDB();
        // 分组权限
        if (app('request')->hasMacro('regionAuthority') && $region = app('request')->regionAuthority()) {
            $query->whereIn('mer_id', $region);
        }
        $query->when($is_del !== null, function ($query) use ($is_del) {
                $query->where('is_del', $is_del);
            })
            ->when(isset($where['is_trader']) && $where['is_trader'] !== '', function ($query) use ($where) {
                $query->where('is_trader', $where['is_trader']);
            })
            ->when(isset($where['is_margin']) && $where['is_margin'] !== '', function ($query) use ($where) {
                $query->where('is_margin', $where['is_margin']);
            })
            //补缴
            ->when(isset($where['margin']) && $where['margin'] !== '', function ($query) use ($where) {
                if ($where['margin']) {
                    $query->where('is_margin',$where['margin']);
                } else {
                    $query->where(function($query){
                        $query->where('is_margin',1)->whereOr(function($query){
                            $query->where('is_margin',10)->whereRaw('ot_margin > margin');
                        });
                    });
                }
            })
            ->when(isset($where['is_best']) && $where['is_best'] !== '', function ($query) use ($where) {
                $query->where('is_best', $where['is_best']);
            })
            ->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['date']);
            })
            ->when(isset($where['mer_state']) && $where['mer_state'] !== '', function ($query) use ($where) {
                $query->where('mer_state', $where['mer_state']);
            })
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->whereIn('mer_id', $where['mer_id']);
            })
            ->when(isset($where['category_id']) && $where['category_id'] !== '', function ($query) use ($where) {
                $query->whereIn('category_id', is_array($where['category_id']) ? $where['category_id'] : explode(',', $where['category_id']));
            })
            ->when(isset($where['type_id']) && $where['type_id'] !== '', function ($query) use ($where) {
                $query->whereIn('type_id', is_array($where['type_id']) ? $where['type_id'] : explode(',', $where['type_id']));
            })
            ->when(isset($where['delivery_way']) && $where['delivery_way'] !== '', function ($query) use ($where) {
                $query->whereLike('delivery_way', "%{$where['delivery_way']}%");
            })
            ->when(isset($where['offline_switch']) && $where['offline_switch'] !== '', function ($query) use ($where) {
                $query->where('offline_switch', $where['offline_switch']);
            })
            ->when(isset($where['region_id']) && $where['region_id'] !== '', function ($query) use ($where) {
                $query->whereIn('region_id', $where['region_id']);
            })
            ->when(isset($where['business_id']) && $where['business_id'] !== '', function ($query) use ($where) {
                $query->whereIn('business_id', $where['business_id']);
            })
            ->when(isset($where['is_blindbox']) && $where['is_blindbox'] !== '', function ($query) use ($where) {
                $query->where('is_blindbox', $where['is_blindbox']);
            })
            ->when(isset($where['create_source']) && $where['create_source'] !== '', function ($query) use ($where) {
                $query->where('create_source', (int)$where['create_source']);
            });


        if (isset($where['keyword']) && $where['keyword']) {
            if (is_numeric($where['keyword'])) {
                $query->whereLike('mer_name|mer_keyword|mer_phone|real_name', "%{$where['keyword']}%");
            } else {
                $word = app()->make(VicWordService::class)->getWord($where['keyword']);
                $query->where(function ($query) use ($word, $where) {
                    foreach ($word as $item) {
                        if(mb_strlen($item) > 1) {
                            $query->whereOr('mer_name', 'LIKE', "%$item%");
                        }
                    }
                    $query->whereOr('mer_name|mer_keyword|real_name', 'LIKE', "%{$where['keyword']}%");
                });
            }
        }
        if (isset($where['status']) && $where['status'] !== '')
            $query->where('status', $where['status']);
        $order = $where['order'] ?? '';
        $query->when($order, function ($query) use ($where, $order) {
            if ($order == 'rate') {
                $query->order('is_best DESC, product_score DESC,service_score DESC,postage_score DESC, sort DESC,create_time DESC');
            } else if ($order == 'location' && isset($where['location']['long'], $where['location']['lat'])) {
                $lng = (float)$where['location']['long'];
                $lat = (float)$where['location']['lat'];
                $query->whereNotNull('lat')->whereNotNull('long')
                    ->order(Db::raw("(2 * 6378.137 * ASIN(
	SQRT(
	POW( SIN( PI( ) * ( $lng- `long` ) / 360 ), 2 ) + COS( PI( ) * $lat / 180 ) * COS( `lat` * PI( ) / 180 ) * POW( SIN( PI( ) * ( $lat- `lat` ) / 360 ), 2 ) 
	) 
	) 
	) ASC "));
            } else {
                $query->order('sort DESC,create_time DESC');
            }
        }, function ($query) use ($order) {
            $query->order('is_best DESC, sort DESC,sales DESC,create_time DESC');
        });
        return $query;
    }

    /**
     * @param int $id
     * @return array|Model|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-04-17
     */
    public function get($id)
    {
        return Merchant::getInstance()->where('is_del', 0)->find($id);
    }

    /**
     * @param $id
     * @author Qinii
     */
    public function apiGetOne($id)
    {
        return Merchant::getInstance()->where(['is_del' => 0, 'status' => 1, 'mer_state' => 1])->find($id);
    }

    /**
     * @param int $merId
     * @author Qinii
     */
    public function incCareCount(int $merId)
    {
        ($this->getModel()::getDB())->where($this->getPk(), $merId)->inc('care_count', 1)->update();
    }

    /**
     * @param int $merId
     * @param int $inc
     * @author xaboy
     * @day 2020/9/25
     */
    public function incSales($merId, $inc)
    {
        ($this->getModel()::getDB())->where($this->getPk(), $merId)->inc('sales', $inc)->update();
    }

    /**
     * @param int $merId
     * @author Qinii
     */
    public function decCareCount(array $merId)
    {
        ($this->getModel()::getDB())->whereIn($this->getPk(), $merId)->where('care_count', '>', 0)->dec('care_count', 1)->update();
    }

    public function dateMerchantNum($date)
    {
        return Merchant::getDB()->where('is_del', 0)->when($date, function ($query, $date) {
            getModelTime($query, $date);
        })->count();
    }

    /**
     *  获取复制商品次数
     * @param int $merId
     * @return mixed
     * @author Qinii
     * @day 2020-08-06
     */
    public function getCopyNum(int $merId)
    {
        return Merchant::getDB()->where('mer_id', $merId)->value('copy_product_num');
    }

    /**
     *  变更复制次数
     * @param int $merId
     * @param int $num 正负数
     * @return mixed
     * @throws \think\exception\ValidateException 缺少 copy_product_num 写入许可时抛出。
     * @author Qinii
     * @day 2020-08-06
     */
    public function changeCopyNum(int $merId, int $num)
    {
        app()->make(MerchantWriteGuardService::class)->assertAllowedForFields($merId, ['copy_product_num'], 'merchant_copy_num_change');
        return $this->getModel()::where('mer_id', $merId)->inc('copy_product_num', $num)->update();
    }

    /**
     * @param $field
     * @param $value
     * @param int|null $except
     * @return bool
     * @author xaboy
     * @day 2020-03-30
     */
    public function fieldExists($field, $value, ?int $except = null): bool
    {
        $query = ($this->getModel())::getDB()->where($field, $value);
        if (!is_null($except)) $query->where($this->getPk(), '<>', $except);
        return $query->where('is_del', 0)->count() > 0;
    }

    public function names(array $ids)
    {
        return Merchant::getDB()->whereIn('mer_id',$ids)->column('mer_name');
    }

    /**
     *  增加商户余额
     * @param int $merId
     * @param float $num
     * @return void
     * @throws \think\exception\ValidateException 缺少 mer_money 写入许可时抛出。
     * @author Qinii
     * @day 3/19/21
     */
    public function addMoney(int $merId, float $num)
    {
        app()->make(MerchantWriteGuardService::class)->assertAllowedForFields($merId, ['mer_money'], 'merchant_money_add');
        $field = 'mer_money';
        $merchant = $this->getModel()::getDB()->where('mer_id', $merId)->find();
        if ($merchant) {
            $mer_money = bcadd($merchant[$field], $num, 2);
            $merchant[$field] = $mer_money;
            $merchant->save();
        }
    }

    /**
     *  减少商户余额
     * @param int $merId
     * @param float $num
     * @return void
     * @throws \think\exception\ValidateException 缺少 mer_money 写入许可时抛出。
     * @author Qinii
     * @day 3/19/21
     */
    public function subMoney(int $merId, float $num)
    {
        app()->make(MerchantWriteGuardService::class)->assertAllowedForFields($merId, ['mer_money'], 'merchant_money_sub');
        $field = 'mer_money';
        $merchant = $this->getModel()::getDB()->where('mer_id', $merId)->find();
        if ($merchant) {
            $mer_money = bcsub($merchant[$field], $num, 2);
            $merchant[$field] = $mer_money;
            $merchant->save();
        }
    }

    /**
     * 清理指定商户类型关联。
     *
     * @param int $typeId 商户类型 ID。
     * @return mixed 返回更新结果。
     * @throws \think\exception\ValidateException 缺少 type_id 写入许可时抛出。
     */
    public function clearTypeId(int $typeId)
    {
        app()->make(MerchantWriteGuardService::class)->assertAllowedForFields(null, ['type_id'], 'merchant_type_clear');
        return Merchant::getDB()->where('type_id', $typeId)->update(['type_id' => 0]);
    }

    /**
     * 增加商户指定数值字段。
     *
     * @param int $merId 商户 ID。
     * @param int $num 增加数量，负数会转为正数。
     * @param string $field 字段名。
     * @return void
     * @throws \think\exception\ValidateException 字段属于核心字段且缺少写入许可时抛出。
     */
    public function addFieldNum(int $merId, int $num, string $field)
    {
        app()->make(MerchantWriteGuardService::class)->assertAllowedForFields($merId, [$field], 'merchant_field_add');
        if ($num < 0) $num = -$num;
        $merchant = $this->getModel()::getDB()->where('mer_id', $merId)->find();
        $number = $merchant[$field] + $num;
        $merchant[$field] = $number;
        $merchant->save();
    }

    /**
     * 减少商户指定数值字段。
     *
     * @param int $merId 商户 ID。
     * @param int $num 减少数量，负数会转为正数。
     * @param string $field 字段名。
     * @return void
     * @throws \think\exception\ValidateException 字段属于核心字段且缺少写入许可时抛出。
     */
    public function sumFieldNum(int $merId, int $num, string $field)
    {
        app()->make(MerchantWriteGuardService::class)->assertAllowedForFields($merId, [$field], 'merchant_field_sub');
        if ($num < 0) $num = -$num;
        $merchant = $this->getModel()::getDB()->where('mer_id', $merId)->find();
        $number = $merchant[$field] - $num;
        $merchant[$field] = $number;
        $merchant->save();
    }

    public function merIdByImage($merIds)
    {
        return $this->getModel()::getDB()->whereIn('mer_id', $merIds)->column('mer_id,mer_avatar');
    }

    /**
     * 按商户类型批量更新保证金配置。
     *
     * @param int $typeId 商户类型 ID。
     * @param mixed $margin 保证金金额。
     * @param mixed $is_margin 保证金状态。
     * @return mixed 返回更新结果。
     * @throws \think\exception\ValidateException 缺少 is_margin 或 margin 写入许可时抛出。
     */
    public function updateMargin($typeId, $margin, $is_margin)
    {
        app()->make(MerchantWriteGuardService::class)->assertAllowedForFields(null, ['is_margin', 'margin'], 'merchant_type_margin_update');
        return $this->getModel()::where('type_id',$typeId)->where('is_margin','in',[0,1])->update([
            'is_margin' => $is_margin,
            'margin' => $margin
        ]);
    }

}
