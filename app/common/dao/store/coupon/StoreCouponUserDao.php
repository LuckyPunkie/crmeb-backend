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


namespace app\common\dao\store\coupon;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\store\coupon\StoreCouponUser;
use think\facade\Db;

/**
 * Class StoreCouponUserDao
 * @package app\common\dao\store\coupon
 * @author xaboy
 * @day 2020-05-14
 */
class StoreCouponUserDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return StoreCouponUser::class;
    }

    /**
     * 根据条件搜索优惠券用户信息
     *
     * 该方法通过接收一个包含搜索条件的数组，动态构建查询语句，以搜索符合指定条件的优惠券用户信息。
     * 支持的搜索条件包括用户名、优惠券类型、优惠券名称或ID、状态、用户ID、商家ID、优惠券ID、发送者ID、是否为商家以及状态标签。
     *
     * @param array $where 搜索条件数组，包含各种可能的搜索参数。
     */
    public function search(array $where)
    {
        // 使用查询构建器实例化StoreCouponUser模型
        return StoreCouponUser::when(isset($where['username']) && $where['username'] !== '', function ($query) use ($where) {
            // 如果指定了用户名，则添加对用户昵称的模糊搜索条件
            $query->hasWhere('user', [['nickname', 'LIKE', "%{$where['username']}%"]]);
        })->when(isset($where['coupon_type']) && $where['coupon_type'] !== '', function ($query) use ($where) {
            // 如果指定了优惠券类型，则添加对优惠券类型的条件查询
            $query->hasWhere('coupon', ['type' => $where['coupon_type']]);
        })->alias('StoreCouponUser')->when(isset($where['coupon']) && $where['coupon'] !== '', function ($query) use ($where) {
            // 如果指定了优惠券名称或ID，则添加对优惠券标题的模糊搜索条件
            $query->whereLike('StoreCouponUser.coupon_title', "%{$where['coupon']}%");
        })->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            // 如果指定了状态，则添加对状态的条件查询
            $query->where('StoreCouponUser.status', $where['status']);
        })->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
            // 如果指定了用户ID，则添加对用户ID的条件查询
            $query->where('StoreCouponUser.uid', $where['uid']);
        })->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            // 如果指定了商家ID，则添加对商家ID的条件查询
            $query->where('StoreCouponUser.mer_id', $where['mer_id']);
        })->when(isset($where['coupon_id']) && $where['coupon_id'] !== '', function ($query) use ($where) {
            // 如果指定了优惠券ID，则添加对优惠券ID的条件查询
            $query->where('StoreCouponUser.coupon_id', $where['coupon_id']);
        })->when(isset($where['coupon']) && $where['coupon'] !== '', function ($query) use ($where) {
            // 如果再次指定了优惠券名称或ID，则添加对优惠券标题或ID的模糊搜索条件
            $query->whereLike('StoreCouponUser.coupon_title|StoreCouponUser.coupon_id', "%{$where['coupon']}%");
        })->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
            // 如果指定了类型，则添加对类型的条件查询
            $query->where('StoreCouponUser.type', $where['type']);
        })->when(isset($where['send_id']) && $where['send_id'] !== '', function ($query) use ($where) {
            // 如果指定了发送者ID，并且类型为发送，则添加相应的条件查询
            $query->where('StoreCouponUser.send_id', $where['send_id'])->where('StoreCouponUser.type', 'send');
        })->when(isset($where['is_mer']) && $where['is_mer'] !== '', function ($query) use ($where) {
            // 如果指定了是否为商家，则添加对商家ID的非空查询
            $query->where('StoreCouponUser.mer_id', '<>',0);
        })->when(isset($where['statusTag']) && $where['statusTag'] !== '', function ($query) use ($where) {
            // 根据状态标签的不同，添加不同的条件查询，包括未使用的优惠券或在指定时间内使用的优惠券
            if ($where['statusTag'] == 1) {
                $query->where('StoreCouponUser.status', 0);
            } else {
                $query->whereIn('StoreCouponUser.status', [1, 2])->where('StoreCouponUser.create_time', '>', date('Y-m-d H:i:s', strtotime('-60 day')));
            }
        })->order('coupon_user_id DESC');
    }

    public function validIntersection($merId, $uid, array $ids): array
    {
        $time = date('Y-m-d H:i:s');
        return StoreCouponUser::getDB()->whereIn('coupon_user_id', $ids)->where('start_time', '<', $time)->where('end_time', '>', $time)
            ->where('is_fail', 0)->where('status', 0)->where('mer_id', $merId)->where('uid', $uid)->column('coupon_user_id');
    }

    public function validQuery($type)
    {
        $time = date('Y-m-d H:i:s');
        return StoreCouponUser::getDB()
            ->when($type, function ($query) use($time){
                $query->where('start_time', '<', $time);
            })
            ->where('end_time', '>', $time)->where('is_fail', 0)->where('status', 0);
    }

    public function failCoupon($uid = 0)
    {
        $time = date('Y-m-d H:i:s');
        $res = StoreCouponUser::getDB()
            ->where('end_time', '<', $time)
            ->where('is_fail', 0)
            ->where('status', 0)
            ->when($uid, function($query) use($uid){
                $query->where('uid', $uid);
            })
            ->update(['status' => 2]);
        return $res;
    }

    public function userTotal($uid, $type = 1)
    {
        return $this->validQuery($type)->where('uid', $uid)->count();
    }

    public function usedNum($couponId)
    {
        return StoreCouponUser::getDB()->where('coupon_id', $couponId)->where('status', 1)->count();
    }

    public function sendNum($couponId, $sendId = null, $status = null)
    {
        return StoreCouponUser::getDB()->where('coupon_id', $couponId)->when($sendId, function ($query, $sendId) {
            $query->where('type', 'send')->where('send_id', $sendId);
        })->when(isset($status), function ($query) use ($status) {
            $query->where('status', $status);
        })->count();
    }

    public function validUserPlatformCoupon($uid)
    {
        $time = date('Y-m-d H:i:s');
        return StoreCouponUser::getDB()->where('uid', $uid)->where('mer_id', 0)->where('start_time', '<', $time)->where('end_time', '>', $time)
            ->where('is_fail', 0)->where('status', 0)
            ->with(['product' => function ($query) {
                $query->field('coupon_id,product_id');
            }, 'coupon' => function ($query) {
                $query->field('coupon_id,type,send_type');
            }])->order('coupon_price DESC, coupon_user_id ASC')->select();
    }
    /**
     * 获取用户可用优惠券
     *
     * @param integer $uid
     * @param integer $merId
     * @return void
     */
    public function validUserCoupon(int $uid, int $merId = 0, array $ids = [])
    {
        $time = date('Y-m-d H:i:s');
        $query = StoreCouponUser::getDB()
            ->where('uid', $uid)
            ->where('start_time', '<', $time)
            ->where('end_time', '>', $time)
            ->where('is_fail', 0)
            ->where('status', 0)
            ->with(['product','coupon'])
            ->order('coupon_price DESC, coupon_user_id ASC');

        if ($merId) {
            $query->whereIn('mer_id', [0, $merId]);
        }

        if ($ids) {
            $query->whereIn('coupon_user_id', $ids);
        }

        return $query->select();
    }

    public function clear($id,$field)
    {
        $this->getModel()::getDB()->where($field, $id)->delete();
    }

}
