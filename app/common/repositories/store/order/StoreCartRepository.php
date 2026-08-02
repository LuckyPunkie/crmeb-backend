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


namespace app\common\repositories\store\order;


use app\common\dao\store\order\StoreCartDao;
use app\common\dao\store\order\StoreCartPriceDao;
use app\common\model\store\product\Product;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\coupon\StoreCouponProductRepository;
use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\user\MemberinterestsRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * Class StoreCartRepository
 * @package app\common\repositories\store\order
 * @author xaboy
 * @day 2020/5/30
 * @mixin StoreCartDao
 */
class StoreCartRepository extends BaseRepository
{
    //购物车最大条数
    const CART_LIMIT_COUNT = 99;

    /**
     * StoreCartRepository constructor.
     * @param StoreCartDao $dao
     */
    public function __construct(StoreCartDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * @param $uid
     * @return array
     * @author Qinii
     */
    /**
     * 获取用户的购物车列表
     *
     * 本函数旨在为指定用户获取其购物车中的所有商品列表。它首先通过用户的UID查询购物车数据，
     * 然后对查询结果进行额外的处理和补充，以确保返回的购物车列表是完整且最新的。
     *
     * @param object $user 用户对象，包含用户的UID等信息。
     * @return mixed 返回经过检查的购物车列表。具体的返回类型取决于checkCartList函数的实现。
     */
    public function getList($user)
    {
        // 通过DAO层获取用户购物车的所有商品，同时附加一些额外的信息如支付次数、激活SKU等。
        $res = $this->dao->getAll($user->uid)->append(['checkCartProduct', 'UserPayCount', 'ActiveSku','spu']);

        // 对获取到的购物车列表进行检查和处理，确保数据的完整性和准确性，并返回处理后的列表。
        return $this->checkCartList($res, $user, 1);
    }

    /**
     * 检查购物车列表
     * 该方法用于验证购物车中的商品信息，包括商品状态、库存、优惠券等的合法性。
     * 同时，它还会处理与商家配置、优惠券可用性等相关的信息。
     *
     * @param Collection $res 购物车中的商品项集合
     * @param User $user 当前用户对象
     * @param int $hasCoupon 是否有优惠券，默认为0表示没有
     * @return array 返回包含合法商品列表和不合法商品列表的数组
     * @throws ValidateException 如果购物车中存在已支付的商品，则抛出异常
     */
    public function checkCartList($res, $user, $hasCoupon = 0)
    {
        $configFiled = ['mer_integral_status', 'mer_integral_rate', 'mer_store_stock', 'mer_take_status', 'mer_take_name', 'mer_take_phone', 'mer_take_address', 'mer_take_location', 'mer_take_day','svip_coupon_merge','mer_take_time'];
        $uid = $user->uid;
        $arr = $fail = [];
      
        $productRepository = app()->make(ProductRepository::class);

        $storeCouponProductRepository = app()->make(StoreCouponProductRepository::class);
           
        //付费会员状态验证
        $svip_status = (systemConfig('svip_switch_status') && $user && $user->is_svip > 0) ? true : false;
        // 购物车所有商户的信息
       
        $merchantRepository = app()->make(MerchantRepository::class);
        $merIds = array_unique(array_column($res->toArray(),'mer_id'));
        $merchantInfoData = $merchantRepository->getSearch([])->whereIn('mer_id',$merIds)
            ->with([
                'config' => function($query) use($configFiled) {
                    $query->whereIn('config_key', $configFiled);
                },
                'coupon' => function ($query) use ($uid) {
                    $query->where('uid', $uid);
                },
                'merchantCategory'
            ])
            ->field('mer_id,category_id,mer_name,mer_state,mer_avatar,is_trader,type_id,delivery_way,commission_rate,commission_switch')->select()
            ->append(['openReceipt'])->toArray();
        $keyArray = array_column($merchantInfoData,'mer_id');
        $merchantInfoData = array_combine($keyArray,$merchantInfoData);
        $productNumber = 0;
        
        foreach ($res as $item) {
            if ($item['is_pay']) throw new ValidateException('存在以支付购物车信息');
            if (!$item['checkCartProduct']) {
                $item['product'] = $productRepository->getFailProduct($item['product_id']);
                $fail[] = $item;
            } else {
                $productNumber ++;
                if ($item['product']['product_type'] != 0) $svip_status = false;
                $coupon_make = app()->make(StoreCouponRepository::class);
                if (!isset($arr[$item['mer_id']])) {
                    //商户信息
                    $merchantData = $merchantInfoData[$item['mer_id']] ?? ['mer_id' => 0];
//                    $merchantData = $item['merchant'] ? $item['merchant']->toarray(): ['mer_id' => 0];
                    if ($hasCoupon)
                        $merchantData['hasCoupon'] = $coupon_make->validMerCouponExists($item['mer_id'], $hasCoupon);
                    $arr[$item['mer_id']] = $merchantData;
                }
                if ($hasCoupon && !$arr[$item['mer_id']]['hasCoupon']) {
                    $couponIds = $storeCouponProductRepository->productByCouponId([$item['product']['product_id']]);
                    $arr[$item['mer_id']]['hasCoupon'] = count($couponIds) ? $coupon_make->validProductCouponExists([$item['product']['product_id']], $hasCoupon) : 0;
                }
                if ($svip_status && !$item['product']['product_type'] && $item['product']['show_svip_price']) {
                    $item['productAttr']['show_svip_price'] = true;
                    $item['productAttr']['org_price'] = $item['productAttr']['price'];
                    $item['productAttr']['price'] = $item['productAttr']['svip_price'];
                } else {
                    $item['productAttr']['show_svip_price'] = false;
                }
                $arr[$item['mer_id']]['list'][] = $item;
            }
        }
        $list = array_values($arr);
        // 返回购物车列表、失败列表和商品数量
        return compact('list', 'fail', 'productNumber');
    }

    /**
     * 获取单条购物车信息
     * @Author:Qinii
     * @Date: 2020/5/30
     * @param int $id
     * @return mixed
     */
    public function getOne(int $id,int $uid)
    {
        $where = [$this->dao->getPk() => $id,'is_del'=>0,'is_fail'=>0,'is_new'=>0,'is_pay'=>0,'uid' => $uid];
        return ($this->dao->getWhere($where));
    }

    /**
     *  查看相同商品的sku是存在
     * @param $sku
     * @param $uid
     * @author Qinii
     */
    public function getCartByProductSku($sku,$uid, $touristUniqueKey = '', $excludeId = 0, string $cartScene = 'mall')
    {
        $where = ['is_del'=>0,'is_fail'=>0,'is_new'=>0,'is_pay'=>0,'uid' => $uid,'product_type' => 0,'product_attr_unique' => $sku];
        if ($cartScene !== '') {
            $where['cart_scene'] = $cartScene;
        }
        if($touristUniqueKey != '') {
            $where['tourist_unique_key'] = $touristUniqueKey;
        }
        if($excludeId) {
            $where[] = ['cart_id', '<>', $excludeId];
        }

        return ($this->dao->getWhere($where));
    }


    /**
     * 根据产品ID获取产品的数量
     * 此方法用于查询数据库中特定产品ID的产品数量，该产品未被删除、标记为新、且未支付
     *
     * @param int $productId 产品ID，用于查询特定产品
     * @return int 返回符合条件的产品数量
     */
    public function getProductById($productId)
    {
        // 定义查询条件，筛选未删除、未标记为新、未支付的产品
        $where = [
            'is_del' =>0,
            'is_new'=>0,
            'is_pay'=>0,
            'product_id'=>$productId
        ];
        // 调用DAO层方法，根据条件查询并返回产品数量
        return $this->dao->getWhereCount($where);
    }


    /**
     * 根据用户ID、产品类型和购买数量检查支付数量是否符合规则。
     * 此函数用于在用户支付前验证他们是否有资格购买指定数量的产品。
     * 它考虑了不同产品类型的特定购买限制，如单次购买最小数量、单次购买最大数量、每日购买限制等。
     *
     * @param array $ids 产品ID列表
     * @param int $uid 用户ID
     * @param int $productType 产品类型（0: 普通商品, 1: 促销商品）
     * @param int $cart_num 购买数量
     * @return bool 如果购买数量符合所有规则，则返回true；否则，抛出验证异常。
     * @throws ValidateException 如果购买数量不符合任何规则，则抛出此异常。
     */
    public function checkPayCountByUser($ids,$uid,$productType,$cart_num)
    {
        // 创建订单仓库和产品仓库实例
        $storeOrderRepository = app()->make(StoreOrderRepository::class);
        $productRepository = app()->make(ProductRepository::class);

        // 根据产品类型应用不同的购买限制检查
        switch ($productType) {
            // 普通商品的购买限制检查
            //普通商品
            case 0:
                $products = $productRepository->getSearch([])->where('product_id',$ids)->select();
                foreach ($products as $product) {
                    // 检查是否低于最小起购数量
                    if ($product['once_min_count'] > 0 &&  $product['once_min_count'] > $cart_num)
                        throw new ValidateException('[低于起购数:'.$product['once_min_count'].']'.mb_substr($product['store_name'],0,10).'...');
                    // 检查是否超过单次购买最大数量
                    if ($product['pay_limit'] == 1 && $product['once_max_count'] < $cart_num)
                        throw new ValidateException('[超出单次限购数：'.$product['once_max_count'].']'.mb_substr($product['store_name'],0,10).'...');
                    // 检查是否超过长期购买限制
                    if ($product['pay_limit'] == 2){
                        //如果长期限购
                        //已购买数量
                        $count = $storeOrderRepository->getMaxCountNumber($uid,$product['product_id']);
                        if (($cart_num + $count) > $product['once_max_count'])
                            throw new ValidateException('[超出限购总数：'. $product['once_max_count'].']'.mb_substr($product['store_name'],0,10).'...');
                    }
                }
                break;
            // 促销商品的购买限制检查
            case 1:
                $products = $productRepository->getSearch([])->where('product_id',$ids)->select();
                foreach ($products as $product) {
                    // 检查当天购买数量是否达到上限
                    if (!$storeOrderRepository->getDayPayCount($uid, $product['product_id'],$cart_num))
                        throw new ValidateException('本次活动您购买数量已达到上限');
                    // 检查活动期间购买数量是否达到上限
                    if (!$storeOrderRepository->getPayCount($uid, $product['product_id'],$cart_num))
                        throw new ValidateException('本次活动您该商品购买数量已达到上限');
                }
                break;
        }
        return true;
    }

    /**
     * 创建购物车项
     *
     * 该方法用于在购物车中添加新的项目。如果添加的项目不是新的（即$is_new标志为false），它会首先检查当前用户购物车中已有的非新、未支付、未失败的项目数量。
     * 如果数量超过了预设的限制，则会删除最旧的项目以保证总数在限制范围内。然后，无论是否进行了删除操作，都会继续添加新的购物车项目。
     *
     * @param array $data 添加到购物车的数据，包括商品信息和用户信息等。
     * @return bool|mixed 返回添加操作的结果，可能是新项目的ID或其他数据，也可能是false表示操作失败。
     */
    public function create(array $data)
    {
        // 使用事务处理来确保数据的一致性
        return Db::transaction(function() use($data) {
            // 如果数据标记为非新项目
            if (!$data['is_new']) {
                // 查询当前用户购物车中符合条件的项目数量
                // 查询现有多少条数据
                $query = $this->dao->getSearch(['uid' => $data['uid'],'is_new' => 0,'is_pay' => 0,'is_fail' => 0 ])->order('create_time DESC');
                $count = $query->count();
                // 定义购物车项目数量的限制
                $limit = self::CART_LIMIT_COUNT;
                // 如果当前项目数量超过限制
                //超过总限制的条数全部删除
                if ($count >= $limit) {
                    // 获取需要删除的项目ID
                    $cartId = $query->limit($limit,$count)->column('cart_id');
                    // 删除超出数量限制的购物车项目
                    $this->dao->updates($cartId,['is_del' => 1]);
                }
            }
            // 继续添加新的购物车项目
            return $this->dao->create($data);
        });
    }

    public function getCartIds(int $uid, int $merId = 0, string $tourist_unique_key = '')
    {
        $where['uid'] = $uid;
        if ($merId) {
            $where['mer_id'] = $merId;
        }
        if ($tourist_unique_key) {
            $where['tourist_unique_key'] = $tourist_unique_key;
        }
        $where['is_del'] = 0;
        $where['is_new'] = 0;
        $where['is_pay'] = 0;

        return $this->dao->getSearch($where)->column('cart_id');
    }

    /**
     * 本函数旨在为指定用户获取其购物车中的所有商品列表。
     * 它首先通过用户的UID和商户ID查询购物车数据
     * 然后对查询结果进行额外的处理和补充，以确保返回的购物车列表是完整且最新的。
     *
     * @param object $user 用户对象，包含用户的UID等信息。
     * @return mixed 返回经过检查的购物车列表。具体的返回类型取决于checkCartList函数的实现。
     */
    public function getMerchantList($user, int $merId, array $cartIds, $address = null)
    {
        // 游客uid为0
        $uid = $user ? $user->uid : 0;
        // 通过DAO层获取用户购物车的所有商品，同时附加一些额外的信息如支付次数、激活SKU等。
        $res = $this->dao->cartIbByData($cartIds, $uid, $address)->where('mer_id', $merId)->append(['checkCartProduct', 'UserPayCount', 'ActiveSku','spu']);

        // 对获取到的购物车列表进行检查和处理，确保数据的完整性和准确性，并返回处理后的列表。
        return $this->checkMechantCartList($res, $user);
    }


    /**
     * 检查购物车列表
     * 该方法用于验证购物车中的商品信息，包括商品状态、库存、优惠券等的合法性。
     * 同时，它还会处理与商家配置、优惠券可用性等相关的信息。
     *
     * @param Collection $res 购物车中的商品项集合
     * @param User $user 当前用户对象
     * @return array 返回包含合法商品列表和不合法商品列表的数组
     * @throws ValidateException 如果购物车中存在已支付的商品，则抛出异常
     */
    public function checkMechantCartList($res, $user)
    {
        $arr = $fail = [];

        $productRepository = app()->make(ProductRepository::class);
        $storeCartPriceDao = app()->make(StoreCartPriceDao::class);
        foreach ($res as $item) {
            if ($item['is_pay']) {
                throw new ValidateException('存在以支付购物车信息');
            }
            // 检查商品状态是否失效
            if (!$item['checkCartProduct']) {
                $item['product'] = $productRepository->getFailProduct($item['product_id']);
                $fail[] = $item;
                continue;
            }
            // 获取修改后价格信息
            $storeCartPriceInfo = $storeCartPriceDao->getCartPriceInfo($item['cart_id']);
            if ($storeCartPriceInfo) {
                $item['productAttr']['new_price'] = $storeCartPriceInfo['is_batch'] ? '' : $storeCartPriceInfo['new_price'];
                $item['productAttr']['new_batch_price'] = $storeCartPriceInfo['is_batch'] ? $storeCartPriceInfo['new_price'] : 0;
                $item['updatedTotalAmount'] = $storeCartPriceInfo['is_batch'] ? $storeCartPriceInfo['new_price'] : 0;
                $item['is_batch'] = $storeCartPriceInfo['is_batch'];
            }

            $arr[] = $item;
        }

        $list = array_values($arr);
        return compact('list', 'fail');
    }

    public function updatePrice(int $cartId, array $params)
    {
        $make = app()->make(StoreCartPriceDao::class);
        if($info = $make->getCartPriceInfo($cartId)) {
            if($info['is_batch'] == 1) {
                throw new ValidateException('已经存在整单改价信息，不能再次单商品改价, cart_id: ' . $cartId);
            }

            return $make->edit($info['cart_price_id'], $cartId, $params);
        }
        return $make->add($cartId, $params);
    }

    public function batchUpdatePrice(array $params)
    {
        $cartList = $this->dao->cartIbByData($params['cart_ids'], $params['uid'], null)->where('mer_id', $params['merId'])->append(['checkCartProduct', 'UserPayCount', 'ActiveSku','spu']);

        try {
            Db::transaction(function () use ($cartList, $params) {
                $make = app()->make(StoreCartPriceDao::class);

                [$newPayPrice, $changeFeeRate, $updatePriceFlag] = $this->getNewPayPrice($params);
                $remainingPrice = $newPayPrice;
                $productPrice = 0;

                foreach ($cartList as $key => $item) {
                    // 整单改价后，给每个商品分摊改价后金额，最后一个商品使用剩余金额
                    $cartNum = $item['cart_num'];
                    $oldPrice = $productPrice = (float)bcmul($item['productAttr']['price'], $cartNum, 2);
                    if ($updatePriceFlag && $changeFeeRate != 1) {
                        if ((count($cartList) == $key + 1)) {
                            $newPrice = $remainingPrice;
                        } else {
                            $newPrice = (float)bcmul($productPrice, $changeFeeRate, 2);
                            $remainingPrice = max((float)bcsub($remainingPrice, $newPrice, 2), 0);
                        }
                    }

                    if($params['change_fee_type'] == 1) {
                        $reducePrice = bcsub($oldPrice, $newPrice, 2);
                    }

                    $cartId = $item['cart_id'];
                    $data['cart_id'] = $item['cart_id'];
                    $data['old_price'] = $oldPrice;
                    $data['type'] = $params['change_fee_type'];
                    $data['reduce_price'] = isset($reducePrice) ? $reducePrice : 0;
                    $data['discount_rate'] = $params['discount_rate'] ?? 0;
                    $data['new_price'] = $newPrice;
                    $data['is_batch'] = 1;
                    $data['update_time'] = date('Y-m-d H:i:s', time());

                    if($info = $make->getCartPriceInfo($cartId)) {
                        if($info['is_batch'] == 0) {
                            throw new ValidateException('已经存在单商品改价信息，不能再次整单改价, cart_id: ' . $cartId);
                        }

                        $res = $make->edit($info['cart_price_id'], $cartId, $data, true);
                        if(!$res) {
                            throw new ValidateException('更新购物车改价信息失败, cart_id: ' . $cartId);
                        }
                        continue;
                    }
                    $res = $make->add($cartId, $data, true);
                    if(!$res) {
                        throw new ValidateException('添加购物车改价信息失败, cart_id: ' . $cartId);
                    }
                }
            });
        } catch (ValidateException $e) {
            throw new ValidateException($e->getMessage());
        }

        return true;
    }
    /**
     * 计算整单改价后的价格
     *
     * @param array $data
     * @return float
     */
    private function getNewPayPrice(array $data) : array
    {
        $updatePriceFlag = 0;
        // 判断是否改价了，如果没有改价，则返回默认值
        if(!isset($data['change_fee_type']) || $data['change_fee_type'] === '') {
            return [0, 1, $updatePriceFlag];
        }

        $newPayPrice = $data['new_pay_price'] ?? 0;
        if($data['change_fee_type'] == 1){ // 立减
            $newPayPrice = bcsub($data['old_pay_price'], $data['reduce_price'], 2);
        }
        if($data['change_fee_type'] == 2){ // 折扣
            $newPayPrice = bcmul($data['old_pay_price'], $data['discount_rate']/100, 4);
        }
        $changeFeeRate = bcdiv($newPayPrice, $data['old_pay_price'], 4);
        $updatePriceFlag = 1;

        return [(float)$newPayPrice, (float)$changeFeeRate, $updatePriceFlag];
    }
}
