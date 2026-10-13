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


namespace app\common\repositories\store\coupon;


use app\common\repositories\store\order\StoreCartRepository;
use app\common\repositories\user\UserRepository;
use app\common\dao\store\coupon\StoreCouponUserDao;
use app\common\repositories\BaseRepository;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * Class StoreCouponUserRepository
 * @package app\common\repositories\store\coupon
 * @author xaboy
 * @day 2020-05-14
 * @mixin  StoreCouponUserDao
 */
class StoreCouponUserRepository extends BaseRepository
{

    //获取方式(receive:自己领取 send:后台发送  give:满赠  new:新人 buy:买赠送)
    const SEND_TYPE_BUY = 'buy';
    const SEND_TYPE_RECEIVE = 'receive';
    const SEND_TYPE_SEND = 'send';
    const SEND_TYPE_GIVE = 'give';
    const SEND_TYPE_NEW = 'new';
    /**
     * @var StoreCouponUserDao
     */
    protected $dao;

    /**
     * StoreCouponUserRepository constructor.
     * @param StoreCouponUserDao $dao
     */
    public function __construct(StoreCouponUserDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取用户列表
     *
     * 根据给定的条件和分页信息，查询用户列表，并包含用户的优惠券和商户信息。
     * 当页码为1时，执行特定的数据库操作（如标记优惠券为失效），以实现特定的业务逻辑。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页记录数
     * @return array 包含用户数量和用户列表的数组
     */
    public function userList(array $where, $page, $limit)
    {
        // 当页码为1时，执行特定数据库操作
        if ($page == 1) {
            $this->dao->failCoupon();
        }

        // 根据条件查询用户
        $query = $this->dao->search($where);

        // 统计查询到的用户总数
        $count = $query->count();

        // 查询用户列表，同时加载每个用户的优惠券和商户信息
        // 通过with方法加载关联数据，减少数据库查询次数，提高性能
        $list = $query->with([
            'coupon' => function ($query) {
                // 加载用户的优惠券信息，只包含指定字段
                $query->field('coupon_id,type,send_type');
            },
            'merchant' => function ($query) {
                // 加载用户的商户信息，只包含指定字段
                $query->field('mer_id,mer_name,mer_avatar');
            },
        ])->page($page, $limit)->select();

        // 返回用户总数和用户列表
        return compact('count', 'list');
    }

    /**
     * 根据条件获取优惠券列表
     *
     * 本函数用于根据给定的条件和分页信息，从数据库中检索优惠券列表。它包括与优惠券相关的用户、优惠券本身和商家信息。
     * 如果请求的是第一页，则会触发一个失败的优惠券操作，这可能是为了处理特定的业务逻辑。
     *
     * @param array $where 搜索条件数组
     * @param int $page 当前页码
     * @param int $limit 每页记录数
     * @return array 包含记录总数和记录列表的数组
     */
    public function getList(array $where, $page, $limit)
    {
        // 如果是第一页，则执行failCoupon方法
        if ($page == 1) {
            $this->dao->failCoupon();
        }

        // 构建查询，包括关联的用户、优惠券和商家信息，只选取需要的字段
        $query = $this->dao->search($where)->with([
            'user' => function ($query) {
                $query->field('avatar,uid,nickname,user_type');
            },
            'coupon' => function ($query) {
                 $query->field('coupon_id,type');
            },
            'merchant' => function ($query) {
                $query->field('mer_id,mer_name,mer_state,mer_avatar,is_trader,type_id');
            }
        ]);

        // 计算满足条件的总记录数
        $count = $query->count();

        // 获取当前页的记录列表
        $list = $query->page($page, $limit)->select();

        // 返回记录总数和记录列表的数组
        return compact('count', 'list');
    }

    const PLATFORM_TYPES = [10,11,12];
    /**
     * 获取用户优惠券
     * 标记优惠券失效情况
     *
     * @param integer $uid
     * @param integer $merId
     * @param array $cartIds
     * @return void
     */
    public function validCoupon($userCoupons, $useCoupon, $cartInfo, $cartTotalPrice, $merId)
    {
        if(empty($cartInfo)) {
            return $userCoupons;
        }
        $checkedTypes = [];
        $failCoupons = [];
        // 筛选优惠券,并标记失效优惠券
        foreach ($userCoupons as &$userCoupon) {
            $userCoupon['disabled'] = false;
            if(empty($useCoupon) && $cartTotalPrice == 0) {
                $userCoupon['disabled'] = true;
            }
            $coupon = $userCoupon['coupon'];
            // 总金额为0时，取勾选的券type
            if($cartTotalPrice == 0 && isset($userCoupon['checked']) && $userCoupon['checked'] == true) {
                $checkedTypes[] = in_array($coupon['type'], self::PLATFORM_TYPES) ? 3 : $coupon['type'];
            }
            // 状态
            if($coupon['status'] !== 1) {
                $userCoupon['disabled'] = true;
            }
            // 是否删除
            if($coupon['is_del'] !== 0) {
                $userCoupon['disabled'] = true;
            }
            // 是否到门槛
            if($coupon['use_min_price'] > 0 && $cartTotalPrice < $coupon['use_min_price']) {
                $userCoupon['disabled'] = true;
            }
            // 平台券
            if($coupon['mer_id'] == 0 ){
                // 品类券：获取购物车列表中的所有商品的品类，判断该券支持的品类是否在所有商品品类列表中，不在则标记失效
                if($coupon['type'] == 11) {
                    if(!$this->isUsableCategoryCoupon($cartInfo, $userCoupon)) {
                        $userCoupon['disabled'] = true;
                    }
                }
                // 跨店券：获取该券支持的商户ids，判断该商户id是否在当前商户ids里，不在则标记失效
                if($coupon['type'] == 12) {
                    $meryIds = array_column($userCoupon['product'], 'product_id');
                    if(!in_array($merId, $meryIds)) {
                        $userCoupon['disabled'] = true;
                    }
                }
            }
            // 商户券
            if($coupon['mer_id'] !== 0){
                // 商品券
                if($coupon['type'] == 1) {
                    $productIds = array_column($userCoupon['product'], 'product_id');
                    $cartProductIds = array_column(array_column($cartInfo, 'product'), 'product_id');
                    // 对比两个数组，找出两个数组的交集，如果交集为空，则说明购物车里没有该券支持的商品，则标记失效
                    if(empty(array_intersect($productIds, $cartProductIds))) {
                        $userCoupon['disabled'] = true;
                    }
                }
            }
        }
        // 总金额为0时，将未勾选的其他类别券标记失效
        foreach($userCoupons as $key => &$value) {
            $coupon = $value['coupon'];
            if(isset($value['checked']) && $value['checked'] == true) {
                continue;
            }

            $type = in_array($coupon['type'], self::PLATFORM_TYPES) ? 3 : $coupon['type'];
            if($cartTotalPrice == 0 && !in_array($type, $checkedTypes)) {
                $value['disabled'] = true;
            }
            // 取出失效的优惠券，放到另外一个数组里
            if($value['disabled']) {
                $failCoupons[] = $value;
                unset($userCoupons[$key]);
            }
        }

        // 合并数组，将失效优惠券的放到后边返回
        return array_merge($userCoupons, $failCoupons);
    }
    /**
     * 是否为可用的品类优惠券
     *
     * @return boolean
     */
    public function isUsableCategoryCoupon($cartInfo, $userCoupon)
    {
        // 优惠券支持的品类id列表
        $cartgoryIds = array_column($userCoupon['product'], 'product_id');
        // 购物车里商品的品类id列表
        $productCartgoryIds = array_column(array_column($cartInfo, 'product'), 'cate_id');
        // 取出购物车商品品类ID的所有父级id列表
        $cateIds = [];
        $cates = array_column(array_column(array_column($cartInfo, 'product'), 'storeCategory'), 'path');
        foreach($cates as $cate) {
            $tmp = explode('/', trim($cate, '/'));
            $cateIds = array_merge($cateIds, $tmp);
        }
        // 合并品类id列表，去除重复的id
        $cateIds = array_unique(array_merge($cateIds, $productCartgoryIds));
        // 对比两个数组，找出两个数组的交集，如果交集为空，则说明购物车里没有该券支持的商品，则要标记失效，返回false
        if(empty(array_intersect($cartgoryIds, $cateIds))) {
            return false;
        }

        return true;
    }
}
