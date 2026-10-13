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

namespace app\common\repositories\store\product;

use app\common\dao\store\product\StoreDiscountDao;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 组合套餐
 */
class StoreDiscountRepository extends BaseRepository
{
    public function __construct(StoreDiscountDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取API列表
     *
     * 本函数通过复杂的数据库查询和后续处理，获取符合特定条件的API列表。
     * 这些条件包括产品状态和显示状态等，同时对结果进行排序。
     * 处理过程中，还会计算每个API所关联的促销产品的数量和最大价格，
     * 并只包含满足特定条件的产品在返回结果中。
     *
     * @param array $where 查询条件
     * @return array 包含API列表和总数的信息
     */
    public function getApilist($where,$limit)
    {
        // 根据$where条件查询，并包含关联的折扣产品和SKU信息，其中对产品和SKU做了状态过滤
        $query = $this->dao->getSearch($where)
                ->with([
                    'discountsProduct' => [
                        'product' => function($query){
                            $query->where('status',1)->where('is_show',1)->where('mer_status',1)->where('is_used',1)->with([
                                'attr',
                                'attrValue',
                            ]);
                        },
                        'productSku' => function($query)  {
                            $query->where('active_type', 10);
                        },
                    ]
                ])->limit($limit + 5)->order('sort DESC,create_time DESC');

        // 执行查询并获取数据
        $data = $query->select();

        // 初始化最终返回的API列表数组
        $list = [];
        // 如果有查询结果
        if ($data) {
            // 遍历查询结果
            foreach ($data->toArray() as $item) {
                // 获取关联的折扣产品信息
                $discountsProduct = $item['discountsProduct'];
                // 移除折扣产品信息，避免循环引用导致的问题
                unset($item['discountsProduct']);
                // 处理活动产品的SKU，得到活动产品的数据和价格
                $res = activeProductSku($discountsProduct, 'discounts');
                // 计算关联的活动产品数量
                $item['count'] = count($res['data']);
                // 计算产品ID字符串中包含的产品数量
                $count = count(explode(',',$item['product_ids']));
                // 根据API的类型和产品数量，决定是否将该API添加到最终列表中
                if ((!$item['type'] && $count == $item['count']) || ($item['type'] && $count > 0)) {
                    // 设置最大价格
                    $item['max_price'] = $res['price'];
                    // 设置关联的活动产品信息
                    $item['discountsProduct'] = $res['data'];
                    // 将API信息添加到最终列表中
                    if (count($list) < $limit)
                    $list[] = $item;
                }
            }
        }
        // 计算最终列表的总数
        $count = count($list);
        // 返回最终列表和总数
        return compact('count', 'list');
    }

    /**
     * 获取商家列表
     *
     * 根据给定的条件和分页信息，查询商家列表，并处理返回的数据。
     * 主要包括对商家信息的查询和对包含时间字段的商家信息进行格式化处理。
     *
     * @param array $where 查询条件数组，不包含is_del字段
     * @param int $page 当前页码
     * @param int $limit 每页数据数量
     * @return array 包含商家数量和商家列表的数组
     */
    public function getMerlist(array $where, int $page, int $limit)
    {
        // 设置查询条件中的is_del字段为0，表示未被删除的商家
        $where['is_del'] = 0;

        // 构建查询对象，包括搜索条件、关联查询和排序规则
        $query = $this->dao->getSearch($where)
            ->with(['discountsProduct'])
            ->order('sort DESC,create_time DESC');

        // 计算满足条件的商家总数
        $count = $query->count();

        // 进行分页查询，并对查询结果进行处理，主要是对时间字段进行格式化
        $list = $query->page($page, $limit)
            ->select()
            ->each(function ($item) {
                // 如果商家有设置时间范围，则对时间字段进行格式化
                if ($item['is_time']) {
                    $start_time = date('Y-m-d H:i:s', $item['start_time']);
                    $stop_time = date('Y-m-d H:i:s', $item['stop_time']);
                    // 移除原始的时间戳字段
                    unset($item['start_time'], $item['stop_time']);
                    // 添加格式化后的时间字段
                    $item['start_time'] = $start_time;
                    $item['stop_time'] = $stop_time;
                }
            });

        // 返回包含商家总数和商家列表的数组
        return compact('count', 'list');
    }

    /**
     * 获取管理员列表
     *
     * 根据指定的条件、分页和限制数量，查询管理员列表。特别处理了时间字段的显示格式。
     *
     * @param array $where 查询条件，允许通过数组传递额外的条件。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页显示的数量，用于分页查询。
     * @return array 返回包含管理员数量和管理员列表的数组。
     */
    public function getAdminlist(array $where, int $page, int $limit)
    {
        // 默认不查询已删除的管理员
        $where['is_del'] = 0;

        // 构建查询，包括关联查询和排序条件
        $query = $this->dao->getSearch($where)
            ->with(['discountsProduct','merchant'])
            ->order('sort DESC,create_time DESC');

        // 计算符合条件的管理员总数
        $count = $query->count();

        // 进行分页查询，并处理查询结果中的时间字段格式
        $list = $query->page($page, $limit)
            ->select()
            ->each(function ($item) {
                // 如果管理员设置了时间范围，则转换时间字段的格式
                if ($item['is_time']) {
                    $start_time = date('Y-m-d H:i:s', $item['start_time']);
                    $stop_time = date('Y-m-d H:i:s', $item['stop_time']);
                    unset($item['start_time'], $item['stop_time']);
                    $item['start_time'] = $start_time;
                    $item['stop_time'] = $stop_time;
                }
            });

        // 返回管理员总数和列表信息
        return compact('count', 'list');
    }

    /**
     * 保存折扣信息，并处理关联的产品数据。
     *
     * @param array $data 包含折扣相关数据和产品信息的数组。
     * @return mixed 如果更新折扣，则返回更新后的折扣ID；如果创建新折扣，则返回新创建的折扣ID。
     *
     * 此函数负责处理折扣的保存逻辑，包括更新现有折扣和创建新折扣。
     * 它还处理与折扣相关的产品数据验证和处理。
     */
    public function save($data)
    {
        // 初始化折扣数据数组，包含标题、图片、类型等信息。
        $discountsData = [];
        $discountsData['title'] = $data['title'];
        $discountsData['image'] = $data['image'];
        $discountsData['type'] = $data['type'];
        $discountsData['is_limit'] = $data['is_limit'];
        $discountsData['limit_num'] = $data['is_limit'] ? $data['limit_num'] : 0;
        $discountsData['is_time'] = $data['is_time'];
        $discountsData['start_time'] = $data['is_time'] ? strtotime($data['time'][0]) : 0;
        $discountsData['stop_time'] = $data['is_time'] ? strtotime($data['time'][1]) : 0;
        $discountsData['sort'] = $data['sort'];
        $discountsData['free_shipping'] = $data['free_shipping'];
        $discountsData['status'] = $data['status'];
        $discountsData['is_show'] = $data['is_show'];
        $discountsData['mer_id'] = $data['mer_id'];

        // 初始化产品ID数组。
        $product_ids = [];
        // 创建产品仓库实例，用于后续的产品数据查询和操作。
        $productRepository = app()->make(ProductRepository::class);

        // 遍历产品数据，进行验证和处理。
        foreach ($data['products'] as $product) {
            // 根据产品ID查询产品信息，确保产品存在且状态正常。
            $productData = $productRepository->getSearch([])
                ->where('mer_id', $data['mer_id'])
                ->where('product_id', $product['product_id'])
                ->where('status', 1)
                ->find();
            if (!$productData) throw new ValidateException('商品「 '.$product['store_name'].' 」不存在或未审核');
            if ($productData['is_gift_bag']) throw new ValidateException('商品「 '.$product['store_name'].' 」分销礼包不能参与');
            // 检查是否重复选择同一产品。
            if (in_array($product['product_id'], $product_ids))
                throw new ValidateException('商品「 '.$product['store_name'].' 」重复选择');

            // 如果产品类型为单一产品，则重置产品ID数组并结束循环。
            if ($product['type']) {
                $product_ids = [];
                $product_ids[] = $product['product_id'];
                break;
            } else {
                // 否则，将产品ID添加到数组中。
                $product_ids[] = $product['product_id'];
            }

            // 检查产品规格是否为空，确保产品规格设置完整。
            if(empty($product['items'])){
                throw new ValidateException('请设置商品「 '.$product['store_name'].' 」规格');
            }
        }

        // 将产品ID数组转换为字符串，用作折扣产品的ID列表。
        $discountsData['product_ids'] = implode(',', $product_ids);

        // 使用事务处理方式，执行折扣数据的保存操作。
        return Db::transaction(function () use($data, $discountsData){
            // 如果折扣ID存在，则更新折扣信息；否则，创建新折扣。
            if (isset($data['discount_id'])) {
                $discountsId = $data['discount_id'];
                $this->dao->update($discountsId, $discountsData);
                // 清除与折扣关联的产品缓存。
                app()->make(StoreDiscountProductRepository::class)->clear($discountsId,'discount_id');
                // 清除与折扣关联的SKU缓存。
                app()->make(ProductSkuRepository::class)->clear($discountsId, ProductSkuRepository::ACTIVE_TYPE_DISCOUNTS);
            } else {
                $res = $this->dao->create($discountsData);
                $discountsId = $res['discount_id'];
            }
            // 保存折扣与产品的关联关系。
            return $this->saveProduct($discountsId, $data['products'], $data['mer_id']);
        });
    }


    /**
     * 添加套餐商品
     * @param int $discountsId
     * @param array $data
     * @param int $merId
     * @author Qinii
     * @day 12/31/21
     */
    public function saveProduct(int $discountsId, array $data, int $merId)
    {
        $storeDiscountsProductsServices = app()->make(StoreDiscountProductRepository::class);
        $productSkuRepository = app()->make(ProductSkuRepository::class);
        foreach ($data as $item) {
            $productData = [];
            $productData['discount_id'] = $discountsId;
            $productData['product_id'] = $item['product_id'];
            $productData['store_name'] = $item['store_name'];
            $productData['image'] = $item['image'];
            $productData['type'] = $item['type'];
            $productData['temp_id'] = $item['temp_id'];
            $productData['mer_id'] = $merId;
            $discountProduct = $storeDiscountsProductsServices->create($productData);
            $productSkuRepository->save($discountsId, $item['product_id'], $item['items'],$discountProduct->discount_product_id);
        }
        return ;
    }

    /**
     * 详情
     * @param int $id
     * @param int $merId
     * @return array|\think\Model|null
     * @author Qinii
     * @day 12/31/21
     */
    public function detail(int $id, int $merId)
    {
        $where[$this->dao->getPk()] = $id;

        if ($merId) {
            $where['mer_id'] = $merId;
        }
        $res = $this->dao->getSearch($where)
            ->with([
                'discountsProduct' => function($query){
                    $query->with([
                        'product.attrValue',
                        'productSku' => function($query) {
                            $query->where('active_type', 10);
                        }
                    ]);
                }
            ])
            ->find();
        if (!$res) throw new ValidateException('数据不存在');
        $res->append(['time']);
        $ret = activeProductSku($res['discountsProduct']);
        $res['discountsProduct'] = $ret['data'];
        return $res;
    }

    /**
     * 检查购物车中的商品是否符合优惠套餐条件
     *
     * @param int $discountId 优惠套餐ID
     * @param array $products 购物车中的商品数组
     * @param object $userInfo 用户信息
     * @throws ValidateException 如果检查过程中发现任何问题，抛出异常
     * @return array 符合条件的商品数据数组
     */
    public function check($discountId, $products, $userInfo)
    {
        // 通过优惠套餐ID获取优惠套餐数据
        $discountData = $this->dao->get($discountId);
        // 如果优惠套餐数据不存在，则抛出异常
        if (!$discountData) throw new ValidateException('套餐活动已下架');
        // 检查优惠套餐状态，是否已下架或已删除
        if ($discountData['status'] !== 1 || $discountData['is_show'] !== 1 || $discountData['is_del'])
            throw new ValidateException('套餐活动已下架');
        // 检查优惠套餐是否已售罄
        if ($discountData['is_limit'] && $discountData['limit_num'] < 1) {
            throw new ValidateException('套餐已售罄');
        }
        // 检查优惠套餐是否在有效时间范围内
        if ($discountData['is_time']) {
            if ($discountData['start_time'] > time()) throw new ValidateException('套餐活动未开启');
            if ($discountData['stop_time'] < time()) throw new ValidateException('套餐活动已结束');
        }
        // 创建优惠套餐产品仓库实例
        $make = app()->make(StoreDiscountProductRepository::class);
        // 创建商品SKU仓库实例
        $productSkuRepository = app()->make(ProductSkuRepository::class);
        // 初始化产品ID数组和购物车数据数组
        $productId = [];
        $cartData = [];

        // 遍历购物车中的商品
        foreach ($products as $item) {
            // 检查商品ID是否已存在，防止重复
            if (in_array($item['product_id'], $productId))
                throw new ValidateException('套餐商品不能重复');
            // 检查商品ID是否为空
            if (!$item['product_id'])
                throw new ValidateException('商品ID不能为空');
            // 检查商品SKU是否为空
            if (!$item['product_attr_unique'])
                throw new ValidateException('ID: '. $item['product_id'] .',商品SKU不能为空');
            // 检查购买数量是否正确
            if ($item['cart_num'] != 1)
                throw new ValidateException('套餐商品每单只能购买1件');
            if ($item['cart_num'] <= 0)
                throw new ValidateException('购买数量有误');

            // 根据优惠套餐ID和商品ID检查商品是否在优惠套餐中
            $ret = $make->getWhere(['discount_id' => $discountId, 'product_id' => $item['product_id']]);
            if (!$ret) throw new ValidateException('商品ID:'.$item['product_id'].',不在套餐内');
            // 根据SKU信息检查商品是否在优惠套餐中
            $sku = $productSkuRepository->getWhere(
                [
                    'unique' => $item['product_attr_unique'],
                    'active_product_id' => $ret['discount_product_id'],
                ],
                '*',
                ['attrValue']
            );
            // 检查SKU是否存在于优惠套餐中
            if (!$sku)
                throw new ValidateException('商品ID:'.$item['product_id'].'的SKU不在套餐内');
            // 检查商品库存是否充足
            if (!$sku['attrValue']['stock'])
                throw new ValidateException('商品ID:'.$item['product_id'].'的库存不足');
            // 将商品ID添加到产品ID数组中
            $productId[] = $item['product_id'];

            // 设置购物车数据数组中的商品信息
            $item['uid'] = $userInfo->uid;
            $item['mer_id'] = $discountData['mer_id'];
            $item['product_type'] = 10;
            $item['source'] = 10;
            $item['source_id'] = $discountId;

            // 将商品数据添加到购物车数据数组中
            $cartData[] = $item;
        }
        // 如果优惠套餐类型为1，检查优惠套餐是否包含主商品
        if ($discountData['type'] == 1){
            if (!in_array($discountData['product_ids'], $productId))
                throw new ValidateException('此套餐必须包含主商品');
        }
        // 返回符合条件的购物车商品数据数组
        return $cartData;
    }

}
