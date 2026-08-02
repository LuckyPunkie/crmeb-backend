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


namespace app\common\dao\store\order;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\store\order\StoreCart;
use app\common\model\user\UserAddress;
use app\common\model\store\shipping\ShippingTemplate;
use app\common\repositories\store\order\StoreCartRepository;
use think\model\Relation;

class StoreCartDao extends BaseDao
{

    protected function getModel(): string
    {
        return StoreCart::class;
    }

    /**
     * 检查购物车项是否有效并存在交叉
     *
     * 本函数用于验证给定的购物车ID列表中，是否存在属于特定用户（$uid）且满足特定条件（如未删除、未失败、未支付）的购物车项。
     * 如果提供了$merId，则还会检查这些购物车项是否属于指定的商家。
     *
     * @param array $ids 购物车ID列表
     * @param int $uid 用户ID
     * @param int|null $merId 商家ID（可选），用于过滤属于特定商家的购物车项
     * @return array 有效并存在交叉的购物车ID列表
     */
    public function validIntersection(array $ids, $uid, int $merId = null, string $touristUniqueKey = ''): array
    {
        // 从数据库中获取满足条件的购物车项的cart_id列
        return StoreCart::getDB()->whereIn('cart_id', $ids)
            // 如果提供了商家ID，则进一步过滤出属于该商家的购物车项
            ->when($merId, function ($query, $merId) {
                /** @var \think\db\Query $query */
                $query->where('mer_id', $merId);
            })
            ->when($touristUniqueKey, function ($query, $touristUniqueKey) {
                /** @var \think\db\Query $query */
                $query->where('tourist_unique_key', $touristUniqueKey);
            })
            // 过滤出未删除、未失败、未支付的购物车项
            ->where('is_del', 0)->where('is_fail', 0)->where('is_pay', 0)
            // 过滤出属于指定用户的购物车项
            ->where('uid', $uid)->column('cart_id');
    }

    /**
     * 获取用户的所有购物车商品
     *
     * 本函数用于根据用户ID查询该用户购物车中所有未删除、未标记为新、未支付的商品。
     * 查询时会包括商品的基本信息、属性信息，以及（注释掉的）商家信息。
     * 查询结果按照创建时间降序排列，并限制返回结果的数量。
     *
     * @param int $uid 用户ID
     * @return \think\Paginator 返回查询结果的分页器对象
     */
    public function getAll(int $uid)
    {
        // 构建查询条件，查询用户购物车中符合条件的商品
        $query = ($this->getModel())::where(['uid' => $uid, 'is_del' => 0, 'is_new' => 0, 'is_pay' => 0])
            // 排除扫码下单本店购物车，避免污染平台统一购物车
            ->where('cart_scene', 'mall')
            ->with([
                // 加载商品相关信息，包括商品基础信息和库存价格等
                'product' => function ($query) {
                    $query->field('product_id,image,store_name,is_show,status,is_del,unit_name,price,mer_status,is_used,product_type,once_max_count,once_min_count,pay_limit,mer_svip_status,svip_price_type,pay_limit,once_max_count,once_min_count');
                },
                // 加载商品属性信息，包括SKU和特殊价格等
                'productAttr' => function ($query) {
                    $query->field('product_id,stock,price,unique,sku,image,svip_price,bar_code');
                },
                // 注释掉的商家信息加载部分，可能用于未来扩展
//                'merchant' => function ($query) {
//                    $query->field('mer_id,mer_name,mer_state,mer_avatar,is_trader,type_id')->with(['type_name']);
//                }
            ])
            // 限制返回的购物车项目数量
            ->limit(StoreCartRepository::CART_LIMIT_COUNT)
            // 按照创建时间降序排列
            ->order('create_time DESC')
            // 执行查询并返回结果
            ->select();

        return $query;
    }


    /**
     * 根据传入的用户ID和购物车ID数组，以及用户地址，获取购物车商品信息。
     * 此函数详细说明了如何从数据库中获取与用户购物车相关的商品数据，
     * 包括商品的基本信息、属性信息以及配送方式和费用等。
     *
     * @param array $ids 购物车项ID数组
     * @param int $uid 用户ID
     * @param UserAddress|null $address 用户地址对象，可能为null
     * @return array 返回符合查询条件的购物车商品数据集合
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function cartIbByData(array $ids, int $uid, ?UserAddress $address)
    {

        // 通过用户ID和购物车ID数组从数据库获取购物车商品信息
        return StoreCart::getDb()->where('uid', $uid)->with([
            // 获取商品基本信息，包括产品ID、分类ID、图片、商店名称等
            'product' => function ($query) use ($address) {
                $query->field('product_id,mer_id,cate_id,image,store_name,is_show,status,is_del,unit_name,price,mer_status,temp_id,give_coupon_ids,is_gift_bag,is_used,product_type,old_product_id,integral_rate,delivery_way,delivery_free,type,extend,pay_limit,once_max_count,once_min_count,mer_svip_status,svip_price_type,refund_switch,mer_form_id,active_id,activity_label_ids');
                $query->with([
                    'storeCategory' => function ($query) {
                        $query->field('store_category_id,cate_name,path');
                    },
                    'reservation',
                    'merchant',
                    'issetCoupon'
                ]);

                // 如果用户地址存在，进一步获取与地址相关的配送模板和免运费模板信息
                if ($address) {
                    $cityIds = array_filter([$address->province_id, $address->city_id, $address->district_id, $address->street_id]);
                    $query->with([
                        'temp' => [
                            'region' => function ($query) use ($cityIds) {
                                // 根据用户地址的省市ID，查询适用的配送区域
                                $query->where(function ($query) use ($cityIds) {
                                    foreach ($cityIds as $v) {
                                        $query->whereOr('city_id', 'like', "%/{$v}/%");
                                    }
                                    $query->whereOr('city_id', '0');
                                })->order('shipping_template_region_id DESC')->withLimit(1);
                            },
                            'undelives' => function ($query) use ($cityIds) {
                                // 查询不能配送的区域
                                foreach ($cityIds as $v) {
                                    $query->whereOr('city_id', 'like', "%/{$v}/%");
                                }
                            },
                            'free' => function ($query) use ($cityIds) {
                                // 查询免运费模板
                                foreach ($cityIds as $v) {
                                    $query->whereOr('city_id', 'like', "%/{$v}/%");
                                }
                                $query->order('shipping_template_free_id DESC')->withLimit(1);
                            }
                        ],
                        'storeCategory' => function ($query) {
                            $query->field('store_category_id,cate_name,path');
                        },
                        'reservation',
                        'merchant',
                        'issetCoupon'
                    ]);
                }
            },
            // 获取商品属性信息，包括属性值ID、图片、库存、价格等
            'productAttr' => function ( $query) {
                $query->field('value_id,image,extension_one,extension_two,product_id,stock,price,unique,sku,volume,weight,ot_price,cost,svip_price,library_id,bar_code,bar_code_number,settlement_price')->with(['product'])->append(['bc_extension_one', 'bc_extension_two'], true);
            },
            'reservation',
            'reservationAttr'
            // 注释掉的商家信息部分，原注释说明了这部分代码的作用是获取商家的基本信息、优惠券、配置信息等
            // 'merchant' => function (Relation $query) use ($uid) {
            //     ...
            // }
        ])->whereIn('cart_id', $ids)->order('product_type DESC,cart_id DESC')->select();
    }

    public function getCartItemsByIds(array $ids, int $uid, $cityIds = [])
    {
        if (!$ids && !$uid) return ;
        // 查询购物车并加载关联
        $cartItems = StoreCart::getDb()
            //->where('uid', $uid)
            ->whereIn('cart_id', $ids)
            ->with(['product', 'productAttr' => function($query){
                $query->field('value_id,image,extension_one,extension_two,product_id,stock,price,unique,sku,volume,weight,ot_price,cost,svip_price,library_id,bar_code,bar_code_number');
            }])
            ->order('product_type DESC,cart_id DESC')
            ->select();
        // 批量加载配送模板
        $this->batchLoadShippingTemplates($cartItems, $cityIds);

        return $cartItems;
    }

    private function batchLoadShippingTemplates($cartItems, array $cityIds)
    {
        if (empty($cityIds)) {
            return;
        }

        // 获取所有模板 ID
        $templateIds = $cartItems->column('product.temp_id'); // 提取模板 ID
        $templateIds = array_unique($templateIds);           // 去重

        // 一次性查询所有模板数据，包括 region、free 和 undelives
        $shippingTemplates = ShippingTemplate::whereIn('id', $templateIds)
            ->with([
                'region' => function ($query) use ($cityIds) {
                    foreach ($cityIds as $cityId) {
                        $query->whereOr('city_id', 'like', "%/{$cityId}/%");
                    }
                    $query->whereOr('city_id', '0'); // 适配所有区域
                    $query->order('shipping_template_region_id DESC')->withLimit(1);
                },
                'free' => function ($query) use ($cityIds) {
                    foreach ($cityIds as $cityId) {
                        $query->whereOr('city_id', 'like', "%/{$cityId}/%");
                    }
                    $query->order('shipping_template_free_id DESC')->withLimit(1);
                },
                'undelives' => function ($query) use ($cityIds) {
                    foreach ($cityIds as $cityId) {
                        $query->whereOr('city_id', 'like', "%/{$cityId}/%");
                    }
                }
            ])
            ->select()
            ->column(null, 'id'); // 按模板 ID 索引结果

        // 将模板数据分配给购物车项
        foreach ($cartItems as $item) {
            $item->product->shipping_template = $shippingTemplates[$item->product->temp_id] ?? null;
        }
    }

    /**
     * 批量删除购物车中指定ID的商品
     *
     * 此方法通过传入的用户ID和购物车ID数组，批量从数据库中删除对应的购物车记录。
     * 主要用于处理用户批量清除购物车商品的场景，或者在特定情况下批量清理数据库中的购物车数据。
     *
     * @param array $cartIds 购物车项ID数组，代表需要被删除的购物车记录的ID。
     * @param int $uid 用户ID，用于指定哪些购物车记录属于该用户，从而进行精准删除。
     * @return int 返回删除操作的影响行数，即被删除的购物车记录数量。
     */
    public function batchDelete(array $cartIds, int $uid)
    {
        // 通过模型获取数据库实例，并构造删除条件，最终执行删除操作。
        return ($this->getModel()::getDB())->where('uid', $uid)->whereIn('cart_id', $cartIds)->delete();
    }

    /**
     * 获取用户购物车中商品的数量
     *
     * 本函数用于查询指定用户ID的购物车中，未删除、未标记为新、未支付的商品总数。
     * 这对于显示用户的购物车总数或者进行相关统计非常有用。
     *
     * @param int $uid 用户ID，用于指定查询哪个用户的购物车数据。
     * @return array 返回一个包含商品总数的数组，如果商品数量为0，则数组中的计数为0。
     */
    public function getCartCount(int $uid)
    {
        // 通过模型获取数据库实例，并根据条件查询购物车商品数量，条件包括用户ID、未删除、未标记为新、未支付。
        $data = ($this->getModel()::getDB())->where(['uid' => $uid, 'is_del' => 0, 'is_new' => 0, 'is_pay' => 0, 'cart_scene' => 'mall'])->field('SUM(cart_num) as count')->select();

        // 确保返回的数据中count字段不为空，如果为空则默认为0。
        $data[0]['count'] = $data[0]['count'] ? $data[0]['count'] : 0;

        // 返回查询结果。
        return $data;
    }

    /**
     * 获取用户购物车中商品的数量
     *
     * 本函数用于查询指定用户ID的购物车中，未删除、未标记为新、未支付的商品总数。
     * 这对于显示用户的购物车总数或者进行相关统计非常有用。
     *
     * @param int $uid 用户ID，用于指定查询哪个用户的购物车数据。
     * @return array 返回一个包含商品总数的数组，如果商品数量为0，则数组中的计数为0。
     */
    public function getMerchantCartCount(int $uid, $cartIds)
    {
        // 通过模型获取数据库实例，并根据条件查询购物车商品数量，条件包括用户ID、未删除、未标记为新、未支付。
        $cartTotalNum = ($this->getModel()::getDB())->hasWhere('product', function($query) {
            $query->where('is_show', 1)->where('status', 1)->whereColumn('stock', '>=', 'StoreCart.cart_num');
        })->whereIn('StoreCart.cart_id', $cartIds)->where(['StoreCart.uid' => $uid, 'StoreCart.is_del' => 0, 'StoreCart.is_new' => 0, 'StoreCart.is_pay' => 0])->sum('StoreCart.cart_num');

        $data[0]['count'] = $cartTotalNum ?? 0;
        // 返回查询结果。
        return $data;
    }

    /**
     * 获取指定来源的支付信息
     *
     * 本函数用于查询指定来源（$source）的支付详情，包括支付数量和支付金额。
     * 如果提供了$ids参数，则只会查询这些ID对应的支付信息。
     *
     * @param string $source 来源标识，用于指定查询哪个来源的支付信息。
     * @param array|null $ids 可选参数，指定查询的来源ID列表。如果不提供，则查询所有来源。
     * @return array 返回一个包含支付信息的数组，每个元素包含支付数量（pay_num）、支付金额（pay_price）和来源ID（source_id）。
     */
    public function getSourcePayInfo($source, ?array $ids = null)
    {
        // 使用数据库查询工具，指定别名为A，查询满足条件的支付信息。
        return StoreCart::getDB()->alias('A')
            // 筛选来源为指定值且已支付的购物车项。
            ->where('A.source', $source)->where('A.is_pay', 1)
            // 如果提供了ID列表，则进一步筛选来源ID在列表中的项。
            ->when($ids, function ($query, $ids) {
                $query->whereIn('A.source_id', $ids);
            })
            // 左连接订单产品表，以获取购物车项对应的订单产品信息。
            ->leftJoin('StoreOrderProduct B', 'A.cart_id = B.cart_id')
            // 选择计算总支付数量和总支付金额的字段，以及来源ID。
            ->field('sum(B.product_num) as pay_num,sum(B.product_price) as pay_price,A.source_id')
            // 按来源ID分组，以聚合支付数量和金额。
            ->group('A.source_id')
            // 执行查询并返回结果集。
            ->select();
    }
}
