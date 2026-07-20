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


namespace app\common\repositories\store\pionts;

use app\common\model\user\User;
use app\common\repositories\community\CommunityRepository;
use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\store\GuaranteeRepository;
use app\common\repositories\store\GuaranteeTemplateRepository;
use app\common\repositories\store\GuaranteeValueRepository;
use app\common\repositories\store\order\StoreCartRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\parameter\ParameterValueRepository;
use app\common\repositories\store\product\ProductAssistSkuRepository;
use app\common\repositories\store\product\ProductAttrRepository;
use app\common\repositories\store\product\ProductAttrValueRepository;
use app\common\repositories\store\product\ProductCateRepository;
use app\common\repositories\store\product\ProductContentRepository;
use app\common\repositories\store\product\ProductGroupSkuRepository;
use app\common\repositories\store\product\ProductPresellSkuRepository;
use app\common\repositories\store\product\ProductReplyRepository;
use app\common\repositories\store\product\SpuRepository;
use app\common\repositories\store\StoreActivityRepository;
use app\common\repositories\store\StoreSeckillActiveRepository;
use app\common\repositories\store\StoreSeckillTimeRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\user\UserRelationRepository;
use app\common\repositories\user\UserVisitRepository;
use app\validate\merchant\StoreProductValidate;
use crmeb\jobs\ChangeSpuStatusJob;
use crmeb\jobs\SendSmsJob;
use crmeb\services\QrcodeService;
use crmeb\services\RedisCacheService;
use crmeb\services\SwooleTaskService;
use FormBuilder\Factory\Elm;
use think\contract\Arrayable;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;
use app\common\repositories\BaseRepository;
use app\common\dao\store\product\ProductDao as dao;
use app\common\repositories\store\shipping\ShippingTemplateRepository;
use think\facade\Queue;

/**
 * 主商品
 */
class PointsProductRepository extends BaseRepository
{

    protected $dao;
    const CREATE_PARAMS = [
        "image", "slider_image", "store_name", "store_info", "keyword", "bar_code", "guarantee_template_id", "cate_id", "unit_name", "sort", "is_show", 'integral_rate', "video_link", "content", "spec_type", "attr", 'delivery_way', 'delivery_free', 'param_temp_id',
        ["brand_id", 0],
        ['once_max_count', 0],
        ['once_min_count', 0],
        ['pay_limit', 0],
        ["attrValue", []],
        ['give_coupon_ids', []],
        ['type', 0],
        ['svip_price', 0],
        ['svip_price_type', 0],
        ['params', []],
    ];
    protected $admin_filed = 'Product.product_id,Product.mer_id,brand_id,spec_type,unit_name,mer_status,rate,reply_count,store_info,cate_id,Product.image,slider_image,Product.store_name,Product.keyword,Product.sort,U.rank,Product.is_show,Product.sales,Product.price,extension_type,refusal,cost,U.ot_price,stock,is_gift_bag,Product.care_count,Product.status,is_used,Product.create_time,Product.product_type,old_product_id,star,ficti,integral_total,integral_price_total,sys_labels,param_temp_id';
    protected $filed = 'Product.product_id,Product.mer_id,brand_id,unit_name,spec_type,mer_status,rate,reply_count,store_info,cate_id,Product.image,slider_image,Product.store_name,Product.keyword,Product.sort,Product.is_show,Product.sales,Product.price,extension_type,refusal,cost,U.ot_price,stock,is_gift_bag,Product.care_count,Product.status,is_used,Product.create_time,Product.product_type,old_product_id,integral_total,integral_price_total,mer_labels,Product.is_good,Product.is_del,type,param_temp_id,mer_svip_status,svip_price,svip_price_type';

    //积分商品
    const PRODUCT_TYPE_POINTS = 20;

    /**
     * ProductRepository constructor.
     * @param dao $dao
     */
    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 检查指定商户是否存在
     *
     * 本函数通过调用DAO层的方法来查询指定商户ID在数据库中是否存在。
     * 主要用于验证传入的商户ID是否有效，避免对不存在的商户进行操作，从而引发错误。
     *
     * @param int|null $merId 商户ID，允许为空。如果为空，则函数的具体行为取决于DAO层的实现。
     * @param int $id 主键ID，用于标识特定的记录。这里的具体用途取决于业务逻辑。
     * @return bool 返回true表示商户存在，返回false表示商户不存在。
     */
    public function merExists(?int $merId, int $id)
    {
        // 调用DAO层的方法来检查指定的商户ID是否存在
        return $this->dao->merFieldExists($merId, $this->getPk(), $id);
    }

    /**
     * 创建产品
     *
     * 本函数用于根据提供的数据创建一个新的产品。它处理了产品规格的设定，
     * 并在数据库中创建产品及其相关属性。如果数据不符合规范，将抛出异常。
     *
     * @param array $data 产品相关数据，包括规格、内容、属性值等。
     * @param int $productType 产品类型，默认为0，表示普通产品。
     * @return int 返回新创建的产品的ID。
     * @throws ValidateException 如果数据验证失败，将抛出此异常。
     */
    public function create(array $data, int $productType = 0)
    {
        // 检查产品规格类型，若为单规格，则处理属性数据
        if (!$data['spec_type']) {
            $data['attr'] = []; // 初始化属性数组
            // 如果属性值数量大于1，抛出异常，因为单规格产品只能有一个属性值
            if (count($data['attrValue']) > 1)
                throw new ValidateException('单规格商品属性错误');
        }

        // 初始化产品内容信息
        $content = ['content' => $data['content'], 'type' => 0];

        // 根据$data数据设置产品信息，准备创建产品
        $product = $this->setProduct($data);

        // 使用事务处理来确保数据一致性和完整性
        return Db::transaction(function () use ($data, $productType, $content, $product) {
            // 初始化活动ID为0，表示此产品未关联任何活动
            $activity_id = 0;

            // 创建产品
            $result = $this->dao->create($product);

            // 处理产品属性值，并保存
            $settleParams = $this->setAttrValue($data, $result->product_id, $productType, 0);
            $settleParams['attr'] = $this->setAttr($data['attr'], $result->product_id);
            $this->save($result->product_id, $settleParams, $content, $product, $productType);

            // 更新产品价格信息
            $product['price'] = $settleParams['data']['price'];
            $product['ot_price'] = $settleParams['data']['ot_price'];

            // 创建SPU（Standard Product Unit），即标准化产品单元
            app()->make(SpuRepository::class)->create($product, $result->product_id, $activity_id, $productType);

            // 返回新创建的产品ID
            return $result->product_id;
        });
    }

    /**
     * 编辑商品信息。
     *
     * 该方法用于更新已有商品的详细信息，包括商品属性、价格等。同时，它也处理了商品规格的逻辑，
     * 并在数据库中更新相应的记录。如果商品是单规格，则对属性值进行特殊处理。
     *
     * @param int $id 商品ID，用于定位要更新的商品。
     * @param array $data 商品的详细信息，包括属性、价格、内容等。
     * @param int $merId 商家ID，用于确定商品所属的商家。
     * @param int $productType 商品类型，用于区分不同种类的商品。
     * @param int $conType 内容类型，可选参数，用于指定商品的内容类型，默认为0。
     * @throws ValidateException 如果商品属性错误，抛出验证异常。
     */
    public function edit(int $id, array $data, int $merId, int $productType, $conType = 0)
    {
        // 检查商品规格类型，如果是单规格，则处理商品属性。
        if (!$data['spec_type']) {
            $data['attr'] = [];
            // 如果单规格商品存在多个属性值，抛出异常。
            if (count($data['attrValue']) > 1)
                throw new ValidateException('单规格商品属性错误');
        }

        // 初始化商品数据，并处理商品属性值。
        $spuData = $product = $this->setProduct($data);
        // 处理商品属性值，并计算商品价格。
        $settleParams = $this->setAttrValue($data, $id, $productType, 1);
        // 设置商品属性。
        $settleParams['attr'] = $this->setAttr($data['attr'], $id);

        // 构建商品内容信息。
        $content = ['content' => $data['content'], 'type' => $conType];

        // 更新SPU数据，包括价格和原价。
        $spuData['price'] = $settleParams['data']['price'];
        $spuData['ot_price'] = $settleParams['data']['ot_price'];

        // 获取SPU仓库实例。
        $SpuRepository = app()->make(SpuRepository::class);

        // 使用数据库事务来确保更新操作的一致性。
        Db::transaction(function () use ($id, $data, $productType, $settleParams, $content, $product, $spuData, $merId, $SpuRepository) {
            // 更新商品信息。
            $this->save($id, $settleParams, $content, $product, $productType);
            // 更新SPU数据。
            $SpuRepository->baseUpdate($spuData, $id, 0, $productType);
            // 修改商品状态。
            $SpuRepository->changeStatus($id, $productType);
        });
    }

    /**
     * 处理商品试用期的免费试用功能。
     * 此函数用于在试用期内为商品设置免费试用的属性和价格，并在数据库中进行相应的更新操作。
     *
     * @param int $id 商品ID，用于查询和更新数据库中的商品信息。
     * @param array $data 商品的相关数据，包括规格、属性、价格等信息。
     * @param int $merId 商家ID，用于确定商品所属的商家。
     *
     * @throws ValidateException 如果商品属性错误，抛出验证异常。
     */
    public function freeTrial(int $id, array $data, int $merId)
    {
        // 检查商品的规格类型，如果是单规格，则清理属性数组，并检查属性值的数量是否正确。
        if (!$data['spec_type']) {
            $data['attr'] = [];
            if (count($data['attrValue']) > 1) throw new ValidateException('单规格商品属性错误');
        }

        // 通过ID查询商品的详细信息，用于后续设置商品的VIP价格类型。
        $res = $this->dao->get($id);
        $data['svip_price_type'] = $res['svip_price_type'];

        // 设置商品的属性值，并计算最终的价格。
        $settleParams = $this->setAttrValue($data, $id, 0, 1);
        $settleParams['cate'] = $this->setMerCate($data['mer_cate_id'], $id, $merId);
        $settleParams['attr'] = $this->setAttr($data['attr'], $id);
        $data['price'] = $settleParams['data']['price'];

        // 清理不需要的数据，为数据库更新做准备。
        unset($data['attrValue'], $data['attr'], $data['mer_cate_id']);

        // 查询商品的SPU信息，用于后续更新SPU的价格。
        $ret = app()->make(SpuRepository::class)->getSearch(['product_id' => $id, 'product_type' => 0,])->find();

        // 使用事务处理来确保数据库操作的一致性。
        Db::transaction(function () use ($id, $data, $settleParams, $ret) {
            // 更新商品信息和属性。
            $this->save($id, $settleParams, null, [], 0);
            // 更新SPU的价格。
            app()->make(SpuRepository::class)->update($ret->spu_id, ['price' => $data['price']]);
            // 异步发送短信通知，通知内容为商品价格的增加。
            Queue(SendSmsJob::class, ['tempId' => 'PRODUCT_INCREASE', 'id' => $id]);
        });
    }

    /**
     * 销毁指定ID的产品相关数据。
     * 此方法用于彻底删除一个产品及其相关的所有属性、属性值、内容等数据。
     * 它调用了多个仓库类的方法来清除不同类型的关联数据，确保数据的完整性删除。
     *
     * @param int $id 产品的唯一标识ID。
     */
    public function destory($id)
    {
        // 清除产品属性
        (app()->make(ProductAttrRepository::class))->clearAttr($id);
        // 清除产品属性值
        (app()->make(ProductAttrValueRepository::class))->clearAttr($id);
        // 清除产品内容
        (app()->make(ProductContentRepository::class))->clearAttr($id, null);
        // 清除产品分类
        (app()->make(ProductCateRepository::class))->clearAttr($id);
        // 删除SPU中的产品信息
        (app()->make(SpuRepository::class))->delProduct($id);
        // 最终销毁产品本身
        $this->dao->destory($id);
    }

    /**
     * 保存产品信息，包括属性、属性值、内容和数据更新。
     *
     * 此方法用于处理产品保存逻辑，它首先清除旧的属性和属性值，然后根据提供的参数
     * 插入新的属性和属性值。如果提供了内容信息，也会清除旧内容并创建新内容。
     * 最后，根据提供的数据更新产品信息。
     *
     * @param int $id 产品的ID，用于标识要更新的产品。
     * @param array $settleParams 包含属性和属性值等结算参数的数组。
     * @param array $content 包含产品内容信息的数组。
     * @param array $data 包含要更新的产品数据的数组，默认为空。
     * @param int $productType 产品类型，默认为0，可选参数用于区分不同类型的产品。
     * @return bool 返回更新操作的结果，true表示成功，false表示失败。
     */
    public function save($id, $settleParams, $content, $data = [], $productType = 0)
    {
        // 创建产品属性和属性值的仓库实例
        $ProductAttrRepository = app()->make(ProductAttrRepository::class);
        $ProductAttrValueRepository = app()->make(ProductAttrValueRepository::class);

        // 清除指定产品ID的旧属性
        $ProductAttrRepository->clearAttr($id);
        // 清除指定产品ID的旧属性值
        $ProductAttrValueRepository->clearAttr($id);

        // 如果设置了属性参数，则插入新的属性
        if (isset($settleParams['attr'])) {
            $ProductAttrRepository->insert($settleParams['attr']);
        }

        // 如果设置了属性值参数，则分批插入新的属性值
        if (isset($settleParams['attrValue'])) {
            $arr = array_chunk($settleParams['attrValue'], 30);
            foreach ($arr as $item) {
                $ProductAttrValueRepository->insertAll($item);
            }
        }

        // 如果提供了内容信息，则清除旧内容并创建新内容
        if ($content) {
            app()->make(ProductContentRepository::class)->clearAttr($id, $content['type']);
            $this->dao->createContent($id, $content);
        }

        // 如果设置了数据参数，则更新产品的价格、成本、库存等信息
        if (isset($settleParams['data'])) {
            $data['price'] = $settleParams['data']['price'];
            $data['ot_price'] = $settleParams['data']['ot_price'];
            $data['cost'] = $settleParams['data']['cost'];
            $data['stock'] = $settleParams['data']['stock'];
        }

        // 更新产品信息，并返回更新结果
        $res = $this->dao->update($id, $data);
        return $res;
    }

    /**
     * 管理员更新产品信息
     *
     * 该方法用于管理员对已存在的产品进行更新操作，包括产品内容的创建和属性的更新。
     * 使用数据库事务确保操作的原子性。
     *
     * @param int $id 产品ID，用于定位要更新的产品。
     * @param array $data 包含产品更新信息的数组，其中'content'字段用于更新产品内容，其余字段用于更新产品属性。
     * @return bool 更新操作的结果，true表示成功，false表示失败。
     */
    public function adminUpdate(int $id, array $data)
    {
        Db::transaction(function () use ($id, $data) {
            // 清除产品ID为$id的属性值
            app()->make(ProductContentRepository::class)->clearAttr($id, 0);

            // 创建产品内容，内容来自$data数组中的'content'字段
            $this->dao->createContent($id, ['content' => $data['content']]);

            // 从$data数组中移除'content'字段，因为它已经被用于创建内容
            unset($data['content']);

            // 获取当前产品的详细信息，包括关联的秒杀活动信息
            $res = $this->dao->getWhere(['product_id' => $id], '*', ['seckillActive']);

            // 获取秒杀活动ID，如果不存在则使用0
            $activity_id = $res['seckillActive']['seckill_active_id'] ?? 0;

            // 根据秒杀活动ID和产品ID，以及新的产品属性数据，更新产品的排名
            app()->make(SpuRepository::class)->changRank($activity_id, $id, $res['product_type'], $data);

            // 从$data数组中移除'star'字段，因为它可能不应该用于更新产品属性
            unset($data['star']);

            // 更新产品信息，使用$id和$data进行更新
            return $this->dao->update($id, $data);
        });
    }

    /**
     *  格式化秒杀商品活动时间
     * @Author:Qinii
     * @Date: 2020/9/15
     * @param array $data
     * @return array
     */
    public function setSeckillProduct(array $data)
    {
        $dat = [
            'start_day' => $data['start_day'],
            'end_day' => $data['end_day'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'status' => 1,
            'once_pay_count' => $data['once_pay_count'],
            'all_pay_count' => $data['all_pay_count'],
        ];
        if (isset($data['mer_id'])) $dat['mer_id'] = $data['mer_id'];
        return $dat;
    }

    /**
     *  格式商品主体信息
     * @Author:Qinii
     * @Date: 2020/9/15
     * @param array $data
     * @return array
     */
    public function setProduct(array $data)
    {
        $result = [
            'store_name' => $data['store_name'],
            'image' => $data['image'],
            'slider_image' => is_array($data['slider_image']) ? implode(',', $data['slider_image']) : '',
            'store_info' => $data['store_info'] ?? '',
            'keyword' => $data['keyword'] ?? '',
            'brand_id' => $data['brand_id'] ?? 0,
            'cate_id' => $data['cate_id'] ?? 0,
            'unit_name' => $data['unit_name'] ?? '件',
            'sort' => $data['sort'] ?? 0,
            'is_show' => $data['is_show'] ?? 0,
            'is_used' => $data['is_used'] ?? ((isset($data['status']) && $data['status'] == 1) ? 1 : 0),
            'is_good' => $data['is_good'] ?? 0,
            'is_hot' => $data['is_hot'] ?? 0,
            'video_link' => $data['video_link'] ?? '',
            'temp_id' => $data['delivery_free'] ? 0 : ($data['temp_id'] ?? 0),
            'extension_type' => $data['extension_type'] ?? 0,
            'spec_type' => $data['spec_type'] ?? 0,
            'status' => $data['status'] ?? 0,
            'guarantee_template_id' => $data['guarantee_template_id'] ?? 0,
            'is_gift_bag' => 0,
            'integral_rate' => $integral_rate ?? 0,
            'delivery_way' => implode(',', $data['delivery_way']),
            'delivery_free' => $data['delivery_free'] ?? 0,
            'once_min_count' => $data['once_min_count'] ?? 0,
            'once_max_count' => $data['once_max_count'] ?? 0,
            'pay_limit' => $data['pay_limit'] ?? 0,
            'svip_price_type' => $data['svip_price_type'] ?? 0,
        ];
        if (isset($data['product_type']))
            $result['product_type'] = $data['product_type'];
        return $result;
    }


    /**
     *  格式商品规格
     * @Author:Qinii
     * @Date: 2020/9/15
     * @param array $data
     * @param int $productId
     * @return array
     */
    public function setAttr(array $data, int $productId)
    {
        $result = [];
        try {
            foreach ($data as $value) {
                $result[] = [
                    'type' => 0,
                    'product_id' => $productId,
                    "attr_name" => $value['value'] ?? $value['attr_name'],
                    'attr_values' => implode('-!-', $value['detail']),
                ];
            }
        } catch (\Exception $exception) {
            throw new ValidateException('商品规格格式错误');
        }

        return $result;
    }

    /**
     *  格式商品SKU
     * @Author:Qinii
     * @Date: 2020/9/15
     * @param array $data
     * @param int $productId
     * @return mixed
     */
    public function setAttrValue(array $data, int $productId, int $productType, int $isUpdate = 0)
    {
        $extension_status = systemConfig('extension_status');
        if ($isUpdate) {
            $product = app()->make(ProductAttrValueRepository::class)->search(['product_id' => $productId])->select()->toArray();
            $oldSku = $this->detailAttrValue($product, null);
        }
        $price = $stock = $ot_price = $cost = 0;
        try {
            foreach ($data['attrValue'] as $value) {
                $_svip_price = 0;
                $sku = '';
                if (isset($value['detail']) && !empty($value['detail']) && is_array($value['detail'])) {
                    $sku = implode(',', $value['detail']);
                }

                $cost = !$cost ? $value['cost'] : (($cost > $value['cost']) ? $cost : $value['cost']);
                $price = !$price ? $value['price'] : (($price > $value['price']) ? $value['price'] : $price);
                $ot_price = !$ot_price ? $value['ot_price'] : (($ot_price < $value['ot_price']) ? $ot_price : $value['ot_price']);

                $unique = $this->setUnique($productId, $sku, $productType);
                $result['attrValue'][] = [
                    'detail' => json_encode($value['detail'] ?? ''),
                    "bar_code" => $value["bar_code"] ?? '',
                    "image" => $value["image"] ?? '',
                    "cost" => $value['cost'] ? (($value['cost'] < 0) ? 0 : $value['cost']) : 0,
                    "price" => $value['price'] ? (($value['price'] < 0) ? 0 : $value['price']) : 0,
                    "volume" => isset($value['volume']) ? ($value['volume'] ? (($value['volume'] < 0) ? 0 : $value['volume']) : 0) : 0,
                    "weight" => isset($value['weight']) ? ($value['weight'] ? (($value['weight'] < 0) ? 0 : $value['weight']) : 0) : 0,
                    "stock" => $value['stock'] ? (($value['stock'] < 0) ? 0 : $value['stock']) : 0,
                    "ot_price" => $value['ot_price'] ? (($value['ot_price'] < 0) ? 0 : $value['ot_price']) : 0,
                    "extension_one" => $extension_status ? ($value['extension_one'] ?? 0) : 0,
                    "extension_two" => $extension_status ? ($value['extension_two'] ?? 0) : 0,
                    "product_id" => $productId,
                    "type" => self::PRODUCT_TYPE_POINTS,
                    "sku" => $sku,
                    "unique" => $unique,
                    'sales' => $isUpdate ? ($oldSku[$sku]['sales'] ?? 0) : 0,
                    'svip_price' => $_svip_price,
                ];
                $stock = $stock + intval($value['stock']);
            }
            $result['data'] = [
                'price' => $price,
                'stock' => $stock,
                'ot_price' => $ot_price,
                'cost' => $cost,
                'svip_price' => 0,
            ];
        } catch (\Exception $exception) {
            throw new ValidateException('规格错误 ：' . $exception->getMessage());
        }
        return $result;
    }

    /**
     * 单商品sku
     * @param $data
     * @param $userInfo
     * @return array
     * @author Qinii
     * @day 2020-08-05
     */
    public function detailAttrValue($data)
    {
        $sku = [];
        foreach ($data as $value) {
            $_value = [
                'sku' => $value['sku'],
                'price' => $value['price'],
                'stock' => $value['stock'],
                'image' => $value['image'],
                'weight' => $value['weight'],
                'volume' => $value['volume'],
                'sales' => $value['sales'],
                'unique' => $value['unique'],
                'bar_code' => $value['bar_code'],
                'ot_price' => (int)$value['ot_price'],
            ];
            $sku[$value['sku']] = $_value;
        }
        return $sku;
    }

    /**
     * 设置唯一标识
     * 该方法通过结合商品SKU、ID和类型生成一个唯一标识，用于确保某些数据的唯一性。
     * 例如，可以用于确保商品属性值的唯一性，避免重复。
     *
     * @param int $id 商品ID，用于生成唯一标识的一部分。
     * @param string $sku 商品SKU，商品的唯一库存单位，也是生成唯一标识的一部分。
     * @param int $type 商品类型，用于生成唯一标识的一部分，确保相同SKU和ID的商品在不同类型下有不同的唯一标识。
     * @return string 返回生成的唯一标识。
     */
    public function setUnique(int $id, $sku, int $type)
    {
        // 生成唯一标识：通过MD5加密SKU和ID的组合，然后取其中一部分加上类型作为唯一标识。
        return $unique = substr(md5($sku . $id), 12, 10) . $type;
        // 注释掉的代码用于检查唯一标识是否已存在，如果已存在则不返回该唯一标识。
        //        $has = (app()->make(ProductAttrValueRepository::class))->merUniqueExists(null, $unique);
        //        return $has ? false : $unique;
    }

    /**
     * 后台管理需要的商品详情
     * @param int $id
     * @param int|null $activeId
     * @return array|\think\Model|null
     * @author Qinii
     * @day 2020-11-24
     */
    public function detail(int $id)
    {
        $with = [
            'attr',
            'attrValue',
            'storeCategory',
            'content',
        ];
        $data = $this->dao->geTrashedtProduct($id)->with($with)->find();
        if (!$data) {
            throw new ValidateException('数据不存在');
        }
        $spu = app()->make(SpuRepository::class)->getSearch([
            'activity_id' => 0,
            'product_type' => $data['product_type'],
            'product_id' => $id
        ])->find();

        $data['star'] = $spu['star'] ?? '';
        foreach ($data['attr'] as $k => $v) {
            $data['attr'][$k] = [
                'value' => $v['attr_name'],
                'detail' => $v['attr_values']
            ];
        }
        foreach ($data['attrValue'] as $key => $item) {
            $sku = explode(',', $item['sku']);
            $item['old_stock'] = $old_stock ?? $item['stock'];
            foreach ($sku as $k => $v) {
                $item['value' . $k] = $v;
            }
            $data['attrValue'][$key] = $item;
        }
        $content = $data['content']['content'] ?? '';
        unset($data['content']);
        $data['content'] = $content;
        return $data;
    }


    /**
     * 商户商品列表
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param int $merId
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getList(?int $merId, array $where, int $page, int $limit)
    {
        $query = $this->dao->search($merId, $where)->with(['merCateId.category', 'storeCategory', 'brand']);
        $count = $query->count();
        $data = $query->page($page, $limit)->setOption('field', [])->field($this->filed)->select();
        $list = $data->append(['us_status']);
        return compact('count', 'list');
    }


    /**
     * 平台商品列表
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param int $merId
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getAdminList(?int $merId, array $where, int $page, int $limit)
    {
        $where['product_type'] = self::PRODUCT_TYPE_POINTS;
        $query = $this->dao->search($merId, $where)->with([
            'merCateId.category',
            'storeCategory',
            'brand',
            'merchant',
        ]);
        $count = $query->count();
        $list = $query->page($page, $limit)->order('Product.sort DESC,Product.create_time DESC')->select();
        return compact('count', 'list');
    }

    /**
     * 根据条件获取积分商品搜索结果
     *
     * 本函数用于查询特定条件下的积分商品列表，支持分页查询。查询条件包括产品类型、SPU状态和商家状态。
     * 通过SpuRepository的search方法进行查询，返回查询结果的总数和具体列表。
     *
     * @param array $where 搜索条件数组
     * @param int $page 当前页码
     * @param int $limit 每页显示数量
     * @return array 包含总数和列表的数组
     */
    public function getApiSearch(array $where, int $page, int $limit)
    {
        // 设置搜索条件中的产品类型为积分商品
        $where['product_type'] = self::PRODUCT_TYPE_POINTS;
        // 设置搜索条件中SPU的状态为启用
        $where['spu_status'] = 1;
        // 设置搜索条件中商家的状态为启用
        $where['mer_status'] = 1;

        // 创建SpuRepository实例，用于后续的商品查询
        $SpuRepository = app()->make(SpuRepository::class);
        // 根据条件进行查询
        $query = $SpuRepository->search($where);
        // 统计查询结果总数
        $count = $query->count();
        // 进行分页查询，并获取当前页的商品列表
        $list = $query->page($page, $limit)->field('P.*')->select();

        // 返回查询结果的总数和列表
        return compact('count', 'list');
    }


    /**
     * 上下架 / 显示
     * @param $id
     * @param $status
     * @author Qinii
     * @day 2022/11/12
     */
    public function switchShow($id, $status, $field)
    {
        $this->dao->update($id, [$field => $status,'is_show' => 1,'mer_status' => 1, 'status' => 1]);
        app()->make(SpuRepository::class)->changeStatus($id, self::PRODUCT_TYPE_POINTS);
    }

    /**
     * 批量切换商品展示状态
     *
     * 该方法用于根据提供的商品ID、状态和字段，批量修改商品的展示状态。
     * 主要用于后台管理操作，可以针对特定商家的商品进行操作。
     *
     * @param int $id 商品ID，用于指定需要操作的商品。
     * @param int $status 商品的新展示状态，通常为0（隐藏）或1（显示）。
     * @param string $field 需要更新的字段名，通常为'show_status'表示展示状态。
     * @param int $merId 商家ID，可选参数，用于指定商家。如果为0，则表示操作平台上的商品。
     * @throws ValidateException 如果找不到指定的商品，则抛出验证异常。
     */
    public function batchSwitchShow($id, $status, $field, $merId = 0)
    {
        // 初始化查询条件，指定商品ID
        $where['product_id'] = $id;
        // 如果提供了商家ID，则添加到查询条件中
        if ($merId) $where['mer_id'] = $merId;

        // 查询指定条件下的商品信息
        $products = $this->dao->getSearch([])->where('product_id', 'in', $id)->select();
        // 如果查询结果为空，则抛出异常，表示商品不存在
        if (!$products)
            throw new ValidateException('数据不存在');

        // 更新指定商品ID的商品字段值为新的展示状态
        $this->dao->updates($id, [$field => $status]);

        // 将商品状态更改的任务推入队列，用于后续的异步处理
        Queue::push(ChangeSpuStatusJob::class, ['id' => $id, 'product_type' => self::PRODUCT_TYPE_POINTS]);
    }

    /**
     * 复制一条商品
     * @param int $productId
     * @param array $data
     * @return mixed
     * @author Qinii
     * @day 2020-11-19
     */
    public function productCopy(int $productId, array $data, $productType = 0)
    {
        $product = $this->getAdminOneProduct($productId, null);
        $product = $product->toArray();
        if ($data) {
            foreach ($data as $k => $v) {
                $product[$k] = $v;
            }
        }
        return $this->create($product, $productType);
    }


    /**
     * 更新商品排序信息
     *
     * 该方法用于根据给定的ID和可选的商户ID更新商品的排序数据。
     * 它首先根据ID和可能的商户ID检索现有商品数据，如果数据不存在，则抛出一个验证异常。
     * 接着，它使用检索到的商品ID和提供的新数据更新商品记录。
     * 最后，它根据商品类型和活动ID调用SpuRepository类的updateSort方法来进一步更新排序信息。
     *
     * @param int $id 商品的唯一标识ID
     * @param int|null $merId 商户ID，用于指定特定商户的商品（如果有的话）
     * @param array $data 包含要更新的商品排序数据的数组
     * @throws ValidateException 如果根据提供的ID找不到商品数据，则抛出此异常
     * @return mixed 返回SpuRepository类中updateSort方法的执行结果
     */
    public function updateSort(int $id, ?int $merId, array $data)
    {
        // 根据主键ID准备查询条件
        $where[$this->dao->getPk()] = $id;
        // 如果提供了商户ID，则添加到查询条件中
        if ($merId) $where['mer_id'] = $merId;

        // 根据条件查询商品数据
        $ret = $this->dao->getWhere($where);
        // 如果查询结果为空，则抛出异常提示数据不存在
        if (!$ret) throw new  ValidateException('数据不存在');

        // 使用查询到的商品ID和更新数据来更新商品记录
        $this->dao->update($ret['product_id'], $data);

        // 实例化SpuRepository类
        $make = app()->make(SpuRepository::class);

        // 根据商品类型获取相应的活动ID，如果没有活动，则使用0
        $activityId = $ret['product_type'] ? $ret->seckillActive->seckill_active_id : 0;

        // 调用SpuRepository的updateSort方法来更新商品的排序信息
        return $make->updateSort($ret['product_id'], $activityId, $ret['product_type'], $data);
    }


    /**
     * 添加 编辑 预览商品
     * @param array $data
     * @param int $productType
     * @return array
     * @author Qinii
     * @day 6/15/21
     */
    public function preview(array $data)
    {
        if (!isset($data['attrValue']) || !$data['attrValue']) {
            throw new ValidateException('缺少商品规格');
        }
        $productType = 0;
        $product = $this->setProduct($data);
        if (isset($data['start_day'])) { //秒杀
            $product['stop'] = time() + 3600;
            $productType = 1;
        }
        if (isset($data['presell_type'])) { //预售
            $product['start_time'] = $data['start_time'];
            $product['end_time'] = $data['end_time'];
            $product['presell_type'] = $data['presell_type'];
            $product['delivery_type'] = $data['delivery_type'];
            $product['delivery_day'] = $data['delivery_day'];
            $product['p_end_time'] = $data['end_time'];
            $product['final_start_time'] = $data['final_start_time'];
            $product['final_end_time'] = $data['final_end_time'];
            $productType = 2;
        }
        $product['slider_image'] = explode(',', $product['slider_image']);
        $product['merchant'] = $data['merchant'];
        $product['content'] = ['content' => $data['content']];
        $settleParams = $this->setAttrValue($data, 0, $productType, 0);
        $settleParams['attr'] = $this->setAttr($data['attr'], 0);

        $product['price'] = $settleParams['data']['price'];
        $product['stock'] = $settleParams['data']['stock'];
        $product['cost'] = $settleParams['data']['cost'];
        $product['ot_price'] = $settleParams['data']['ot_price'];
        $product['product_type'] = $productType;
        foreach ($settleParams['attrValue'] as $k => $value) {
            $_value = [
                'sku' => $value['sku'],
                'price' => $value['price'],
                'stock' => $value['stock'],
                'image' => $value['image'],
                'weight' => $value['weight'],
                'volume' => $value['volume'],
                'sales' => $value['sales'],
                'unique' => $value['unique'],
                'bar_code' => $value['bar_code'],
            ];
            $sku[$value['sku']] = $_value;
        }
        $preview_key = 'preview' . $data['mer_id'] . $productType . '_' . time();
        unset($settleParams['data'], $settleParams['attrValue']);
        $settleParams['sku'] = $sku;
        $settleParams['attr'] = $this->detailAttr($settleParams['attr'], 1);

        $ret = array_merge($product, $settleParams);

        Cache::set($preview_key, $ret);

        return compact('preview_key', 'ret');
    }

    /**
     * 列表查看预览
     * @param array $data
     * @return array|\think\Model|null
     * @author Qinii
     * @day 7/9/21
     */
    public function getPreview(array $data)
    {
        switch ($data['product_type']) {
            case 0:
                return $this->apiProductDetail(['product_id' => $data['id']], 0, 0);
                break;
            case 1:
                $ret = $this->apiProductDetail(['product_id' => $data['id']], 1, 0);
                $ret['stop'] = time() + 3600;
                break;
            case 2:
                $make = app()->make(ProductPresellRepository::class);
                $res = $make->getWhere([$make->getPk() => $data['id']])->toArray();
                $ret = $this->apiProductDetail(['product_id' => $res['product_id']], 2, $data['id'])->toArray();
                $ret['ot_price'] = $ret['price'];
                $ret['start_time'] = $res['start_time'];
                $ret['p_end_time'] = $res['end_time'];
                $ret = array_merge($ret, $res);
                break;
            case 3:
                $make = app()->make(ProductAssistRepository::class);
                $res = $make->getWhere([$make->getPk() => $data['id']])->toArray();
                $ret = $this->apiProductDetail(['product_id' => $res['product_id']], 3, $data['id'])->toArray();

                $ret = array_merge($ret, $res);
                foreach ($ret['sku'] as $value) {
                    $ret['price'] = $value['price'];
                    $ret['stock'] = $value['stock'];
                }
                break;
            case 4:
                $make = app()->make(ProductGroupRepository::class);
                $res = $make->get($data['id'])->toArray();
                $ret = $this->apiProductDetail(['product_id' => $res['product_id']], 4, $data['id'])->toArray();
                $ret['ot_price'] = $ret['price'];
                $ret = array_merge($ret, $res);
                break;
            default:
                break;
        }
        return $ret;
    }

    /**
     * 根据产品ID展示产品详情
     *
     * 此方法通过产品ID检索特定产品的详细信息，包括产品属性、内容、价格等。
     * 它还处理了属性值的细节，以及根据属性组合生成SKU。
     *
     * @param int $id 产品ID
     * @return array 产品详细信息，包括属性、SKU等。如果产品不存在，则返回空数组。
     */
    public function show($id)
    {
        // 定义需要查询的产品字段
        $field = 'is_show,product_id,mer_id,image,slider_image,store_name,store_info,unit_name,price,cost,ot_price,stock,sales,video_link,product_type,extension_type,old_product_id,rate,guarantee_template_id,temp_id,once_max_count,pay_limit,once_min_count,integral_rate,delivery_way,delivery_free,type,cate_id,svip_price_type,svip_price,mer_svip_status';
        // 定义查询关联数据的策略，包括属性、内容和属性值
        $with = [
            'attr',
            'content' => function ($query) {
                $query->order('type ASC');
            },
            'attrValue',
        ];
        // 根据产品ID查询产品信息
        $res = $this->dao->getWhere(['product_id' => $id], $field, $with);
        // 如果产品不存在，则返回空数组
        if (!$res) return [];
        // 处理产品属性细节
        $attr = $this->detailAttr($res['attr']);
        // 获取产品属性值细节
        $attrValue = $res['attrValue'];
        // 根据属性值生成SKU细节
        $sku = $this->detailAttrValue($attrValue, null, 20, 0);
        // 设置是否有关联的标记，默认为false
        $res['isRelation'] = $isRelation ?? false;
        // 移除属性和属性值数据，以优化返回的结果集
        unset($res['attr'], $res['attrValue']);
        // 如果存在多个属性，处理SKU的显示顺序，将第一个属性值组合放在最前面
        if (count($attr) > 1) {
            $firstSku = [];
            foreach ($attr as $item) {
                $firstSku[] = $item['attr_values'][0];
            }
            $firstSkuKey = implode(',', $firstSku);
            // 如果存在第一个属性值组合的SKU，将其放在SKU数组的前面
            if (isset($sku[$firstSkuKey])) {
                $sku = array_merge([$firstSkuKey => $sku[$firstSkuKey]], $sku);
            }
        }
        // 更新产品信息，包括属性和SKU
        $res['attr'] = $attr;
        $res['sku'] = $sku;

        // 返回处理后的详细信息
        return $res;
    }

    /**
     * 单商品属性
     * @param $data
     * @return array
     * @author Qinii
     * @day 2020-08-05
     */
    public function detailAttr($data, $preview = 0, $user = null)
    {
        $attr = [];
        foreach ($data as $key => $item) {
            if ($item instanceof Arrayable) {
                $attr[$key] = $item->toArray();
            }
            $arr = [];
            if ($preview) {
                $item['attr_values'] = explode('-!-', $item['attr_values']);
                $attr[$key]['attr_values'] = $item['attr_values'];
            }
            $values = $item['attr_values'];
            foreach ($values as $i => $value) {
                $arr[] = [
                    'attr' => $value,
                    'check' => false
                ];
            }
            $attr[$key]['product_id'] = $item['product_id'];
            $attr[$key]['attr_name'] = $item['attr_name'];
            $attr[$key]['attr_value'] = $arr;
            $attr[$key]['attr_values'] = $values;
        }
        return $attr;
    }

    /**
     * 检查购物车中添加的商品是否有效
     *
     * 该方法用于在用户将商品添加到购物车之前，验证该商品是否有效。它检查商品是否已下架、是否为新的积分商品、SKU是否存在以及库存是否足够。
     *
     * @param array $data 商品相关数据，包括商品ID、商品属性唯一码和购物车数量。
     * @param mixed $userInfo 用户信息，该参数未在函数内部直接使用，可能是为了后续扩展而保留。
     * @return array 返回包含商品、SKU和购物车信息的数组。
     * @throws ValidateException 如果商品无效，抛出验证异常。
     */
    public function cartCheck(array $data, $userInfo)
    {
        // 初始化购物车变量
        $cart = null;

        // 获取商品显示条件
        $where = $this->dao->productShow();

        // 配置查询条件，特定于积分商品
        $where['product_id'] = $data['product_id'];
        $where['product_type'] = self::PRODUCT_TYPE_POINTS;
        unset($where['is_gift_bag']);

        // 根据条件查询商品信息
        $product = $this->dao->search(null, $where)->find();

        // 如果商品不存在，抛出异常
        if (!$product) throw new ValidateException('商品已下架');

        // 如果商品不是新的积分商品，抛出异常
        if (!$data['is_new']) throw new ValidateException('积分商品不可加入购物车');

        // 实例化产品属性值仓库
        $value_make = app()->make(ProductAttrValueRepository::class);

        // 根据商品属性唯一码获取SKU信息
        $sku = $value_make->getOptionByUnique($data['product_attr_unique']);

        // 如果SKU不存在，抛出异常
        if (!$sku) throw new ValidateException('SKU不存在');

        // 如果库存不足，抛出异常
        if ($sku['stock'] < $data['cart_num']) throw new ValidateException('库存不足');

        // 返回验证通过的商品、SKU和购物车信息
        return compact('product', 'sku', 'cart');
    }
}
