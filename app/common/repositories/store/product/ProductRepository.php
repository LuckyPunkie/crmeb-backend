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

use crmeb\jobs\SetSeckillStockCacheJob;
use app\common\model\store\StoreSeckillTime;
use app\common\model\user\User;
use app\common\repositories\community\CommunityRepository;
use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\store\GuaranteeRepository;
use app\common\repositories\store\GuaranteeTemplateRepository;
use app\common\repositories\store\GuaranteeValueRepository;
use app\common\repositories\store\order\StoreCartRepository;
use app\common\repositories\store\order\StoreOrderProductRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\parameter\ParameterValueRepository;
use app\common\repositories\store\StoreActivityRepository;
use app\common\repositories\store\StoreSeckillActiveRepository;
use app\common\repositories\store\StoreSeckillTimeRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\system\operate\OperateLogRepository;
use app\common\repositories\user\UserRelationRepository;
use app\common\repositories\user\UserVisitRepository;
use app\validate\merchant\StoreProductValidate;
use crmeb\jobs\ChangeSpuStatusJob;
use crmeb\jobs\DownloadProductCopyImage;
use crmeb\jobs\SendSmsJob;
use crmeb\services\QrcodeService;
use crmeb\services\RedisCacheService;
use crmeb\services\SwooleTaskService;
use Endroid\QrCode\Exception\ValidationException;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;
use app\common\repositories\BaseRepository;
use app\common\dao\store\product\ProductDao as dao;
use app\common\repositories\store\StoreCategoryRepository;
use app\common\repositories\store\shipping\ShippingTemplateRepository;
use app\common\repositories\store\StoreBrandRepository;
use think\facade\Queue;
use think\facade\Route;
use think\contract\Arrayable;

/**
 * 主商品
 */
class ProductRepository extends BaseRepository
{
    use ProductRepositoryTrait;

    protected $dao;
    const CREATE_PARAMS = [
        "is_copy", "image", "slider_image", "store_name", "store_info", "keyword", "bar_code", "guarantee_template_id", "cate_id", "unit_name", "sort", "is_show", "is_good", 'is_gift_bag', 'integral_rate', "video_link", "temp_id", "content", "spec_type", "extension_type", "attr", 'mer_labels', 'delivery_way', 'delivery_free', 'param_temp_id','custom_temp_id', 'extend', 'mer_form_id', 'auto_on_time', 'auto_off_time',
        ["mer_cate_id", []],
        ['refund_switch', 1],
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
        ['product_type', 0],
        ['good_ids', []],
        'reservation_time_type',
        'reservation_start_time',
        'reservation_end_time',
        'reservation_time_interval',
        'time_period',
        'reservation_type',
        'show_num_type',
        'sale_time_type',
        'sale_time_start_day',
        'sale_time_end_day',
        'sale_time_week',
        'show_reservation_days',
        'is_advance',
        'advance_time',
        'is_cancel_reservation',
        'cancel_reservation_time',
        'reservation_form_type',
        'labels',
        'activity_label_ids',
        ['hit_amount', 0],
        ['welfare_commission', 0],
    ];
    protected $admin_filed = 'Product.product_id,Product.mer_id,brand_id,spec_type,unit_name,mer_status,rate,reply_count,store_info,cate_id,Product.image,slider_image,Product.store_name,Product.keyword,Product.sort,U.rank,Product.is_show,Product.sales,Product.price,extension_type,refusal,Product.cost,Product.ot_price,Product.stock,is_gift_bag,Product.care_count,Product.status,is_used,Product.create_time,Product.product_type,old_product_id,star,ficti,integral_total,integral_price_total,sys_labels,Product.param_temp_id,Product.custom_temp_id,Product.mer_svip_status,Product.svip_price,Product.svip_price_type,Product.mer_form_id,Product.good_ids,mer_form_id,Product.activity_label_ids,Product.delivery_free,Product.give_coupon_ids,Product.delivery_way,Product.type';
    protected $filed = 'Product.product_id,Product.mer_id,brand_id,unit_name,spec_type,mer_status,rate,reply_count,store_info,cate_id,Product.image,slider_image,Product.store_name,Product.keyword,Product.sort,Product.is_show,Product.sales,Product.price,extension_type,refusal,cost,Product.ot_price,stock,is_gift_bag,Product.care_count,Product.status,is_used,Product.create_time,Product.product_type,old_product_id,integral_total,integral_price_total,mer_labels,Product.is_good,Product.is_del,Product.type,Product.param_temp_id,Product.custom_temp_id,Product.mer_svip_status,Product.svip_price,Product.svip_price_type,Product.mer_form_id,Product.good_ids,spu_id,video_link,mer_form_id,Product.activity_label_ids,Product.delivery_free,Product.give_coupon_ids,Product.delivery_way';

    const  NOTIC_MSG = [
        1 => [
            '0' => 'product_success',
            '1' => 'product_seckill_success',
            '2' => 'product_presell_success',
            '3' => 'product_assist_success',
            '4' => 'product_group_success',
            'msg' => '审核通过'
        ],
        -1 => [
            '0' => 'product_fail',
            '1' => 'product_seckill_fail',
            '2' => 'product_presell_fail',
            '3' => 'product_assist_fail',
            '4' => 'product_group_fail',
            'msg' => '审核失败'
        ],
        -2 => [
            '0' => 'product_fail',
            '1' => 'product_seckill_fail',
            '2' => 'product_presell_fail',
            '3' => 'product_assist_fail',
            '4' => 'product_group_fail',
            'msg' => '被下架'
        ],
    ];
    const AUTO_ON_TIME = 'mer_auto_on_time';
    const AUTO_OFF_TIME = 'mer_auto_off_time';
    const AUTO_TIME_STATUS = 'mer_auto_on_off_time';
    const AUTO_PREFIX_SET = 'auto_set_';

    const SECKILL_STOCK_CACHE_KEY = 'seckill_stock_cache_key';

    //物流发货
    const DEFINE_TYPE_ENTITY = 0;
    //虚拟发货
    const DEFINE_TYPE_VIRTUAL = 1;
    //云盘商品
    const DEFINE_TYPE_CLOUD = 2;
    //卡密商品
    const DEFINE_TYPE_CARD = 3;
    //预约商品
    const DEFINE_TYPE_RESERVATION = 4;

    // 普通商品
    const PRODUCT_TYPE_NORMAL = 0;
    // 秒杀商品
    const PRODUCT_TYPE_SKILL = 1;
    // 预售商品
    const PRODUCT_TYPE_PRESELL = 2;
    // 助力商品
    const PRODUCT_TYPE_ASSIST = 3;
    // 拼团商品
    const PRODUCT_TYPE_GROUP_BUY = 4;
    // 套餐商品
    const PRODUCT_TYPE_DISCOUNT = 10;
    // 积分商品
    const PRODUCT_TYPE_INTEGRAL = 20;

    /**
     * ProductRepository constructor.
     * @param dao $dao
     */
    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    /**
     *  检查分类是否存在
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param int $id
     * @param int $merId
     * @return mixed
     */
    public function CatExists(int $id)
    {
        return (app()->make(StoreCategoryRepository::class))->merExists(0, $id);
    }

    /**
     * 商户分类是否存在
     * @Author:Qinii
     * @Date: 2020/5/20
     * @param $ids
     * @param int $merId
     * @return bool
     */
    public function merCatExists($ids, int $merId)
    {
        if (!is_array($ids ?? '')) return true;
        foreach ($ids as $id) {
            if (!(app()->make(StoreCategoryRepository::class))->merExists($merId, $id))
                return false;
        }
        return true;
    }

    /**
     * 运费模板是否存在
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param int $merId
     * @param int $id
     * @return mixed
     */
    public function merShippingExists(int $merId, int $id)
    {
        $make = app()->make(ShippingTemplateRepository::class);
        return $make->merExists($merId, $id);
    }

    /**
     * 品牌是否存在
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param int $id
     * @return mixed
     */
    public function merBrandExists(int $id)
    {
        $make = app()->make(StoreBrandRepository::class);
        return $make->meExists($id);
    }

    /**
     * 商户是否存在
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param int $merId
     * @param int $id
     * @return bool
     */
    public function merExists(?int $merId, int $id)
    {
        return $this->dao->merFieldExists($merId, $this->getPk(), $id);
    }

    /**
     * 软删除的商品是否存在
     * @param int $merId
     * @param int $id
     * @return bool
     */
    public function merDeleteExists(int $merId, int $id)
    {
        return $this->dao->getDeleteExists($merId, $id);
    }

    /**
     *  移动端使用的商品是否存在
     * @Author:Qinii
     * @Date: 2020/5/30
     * @param int $merId
     * @param int $id
     * @return bool
     */
    public function apiExists(?int $merId, int $id)
    {
        return $this->dao->apiFieldExists($merId, $this->getPk(), $id);
    }

    /**
     * 检查指定商家是否已绑定特定模板
     * @param int $merId
     * @param int $tempId
     * @return bool
     * @author Qinii
     */
    public function merTempExists(int $merId, int $tempId)
    {
        return $this->dao->merFieldExists($merId, 'temp_id', $tempId);
    }

    /**
     * 添加商品
     * @param array $data 商品
     * @param int $productType 商品类型
     * @param $conType
     * @param $seckillActiveId 秒杀活动场次ID
     * @return mixed
     * FerryZhao 2024/4/12
     */
    public function create(array $data, int $productType = 0, $conType = 0, $seckillActiveId = 0)
    {
        if (!$data['spec_type']) {
            $data['attr'] = [];
            if (count($data['attrValue']) > 1) throw new ValidateException('单规格商品属性错误');
        }
        $content = ['content' => $conType ? json_encode($data['content']) : $data['content'],'type' => $conType];
        $admin_info = [];
        if (isset($data['admin_info'])) {
            $admin_info = $data['admin_info'] ?? [];
            unset($data['admin_info']);
        }
        $product = $this->setProduct($data);//组合数据
        event('product.create.before', compact('data', 'productType', 'conType'));

        $parameterValueRepository = app()->make(ParameterValueRepository::class);
        $result =  Db::transaction(function () use ($data, $productType, $conType, $content, $product, $admin_info,
            $seckillActiveId,$parameterValueRepository) {
            $result = $this->dao->create($product);//添加商品

            if ($productType == 0 && !empty($data['params'] ?? [])) {
                $data['params'] = $parameterValueRepository->create($data['params'], $result->product_id, $data['mer_id']);
            }
            app()->make(ProductResultRepository::class)->save($result->product_id,$data, 0);
            //添加定时上架
            if (!$productType)
                $this->autoOnTime($result->product_id,$product['auto_on_time'] ?? null, $product['auto_off_time'] ?? null);
            //格式商品SKU
            $settleParams = $this->setAttrValue($data, $result->product_id, $productType, 0);
            //格式商品商户分类
            $settleParams['cate'] = $this->setMerCate($data['mer_cate_id'], $result->product_id, $data['mer_id']);
            //格式商品规格
            $settleParams['attr'] = $this->setAttr($data['attr'], $result->product_id,$productType);
            //普通商品添加商品参数
            $this->save($result, $settleParams, $content, $product, $productType);
            if (in_array($productType, [0, 1])) {
                //秒杀商品
                $product['price'] = $settleParams['data']['price'];
                $product['ot_price'] = $settleParams['data']['ot_price'];
                $product['mer_labels'] = $data['mer_labels'];
                app()->make(SpuRepository::class)->create($product, $result->product_id, $seckillActiveId, $productType);//添加spu数据
            }
            return $result;
        });
        event('product.create', compact('product'));//商品添加事件
        //采集消息队列
        if (isset($data['is_copy']) && $data['is_copy']){
            Queue::push(DownloadProductCopyImage::class, ['id' => $result->product_id, 'data' => $data]);
        }

        // 添加操作日志
        if (!empty($admin_info)) {
            event('create_operate_log', [
                'category' => OperateLogRepository::MERCHANT_CREATE_PRODUCT,
                'data' => ['product' => $result, 'admin_info' => $admin_info,],
                'mer_id' => $product['mer_id'] ?? 0
            ]);
        }
        return $result->product_id;
    }

    /**
     * 编辑商品
     * @param int $id
     * @param array $data
     * @param int $merId
     * @param int $productType
     * @param $conType
     * @param $seckillActiveId
     * @return mixed
     * FerryZhao 2024/4/13
     */
    public function edit(int $id, array $data, int $merId, int $productType, $conType = 0, $seckillActiveId = 0)
    {
        if (!$data['spec_type']) {
            $data['attr'] = [];
            if (count($data['attrValue']) > 1) throw new ValidateException('单规格商品属性错误');
        }
        event('product.update.before', compact('id', 'data', 'merId', 'productType', 'conType'));//商品修改事件
        $spuData = $product = $this->setProduct($data);//整理商品数据

        $settleParams = $this->setAttrValue($data, $id, $productType, 1);//格式商品SKU

        $settleParams['cate'] = $this->setMerCate($data['mer_cate_id'], $id, $merId);//格式商品商户分类
        $settleParams['attr'] = $this->setAttr($data['attr'], $id,$productType);//格式商品规格

        $content = [
            'content' => $conType ? json_encode($data['content']) : $data['content'],
            'type' => $conType
        ];
        $spuData['price'] = $settleParams['data']['price'];
        $spuData['mer_id'] = $merId;
        $spuData['mer_labels'] = $data['mer_labels'];
        if (isset($data['admin_info'])) {
            $product['admin_info'] = $data['admin_info'];
            unset($data['admin_info']);
        }

        return Db::transaction(function () use ($seckillActiveId, $id, $data, $productType, $settleParams, $content, $product, $spuData, $merId) {
            $res = $this->dao->get($id);
            app()->make(ProductResultRepository::class)->save($id,$data);
            $productData = $this->save($res, $settleParams, $content, $product, $productType);//保存商品信息
            if (!$productType) $this->autoOnTime($id,$product['auto_on_time'] ?? null ,$product['auto_off_time'] ?? null);
            if ($productType == 0 && $data['params'] ?? []) {
                $make = app()->make(ParameterValueRepository::class);
                $data['params'] = $make->create($data['params'] ?? [],$id,  $merId);
            }
            app()->make(ProductResultRepository::class)->save($id,$data, 0);

            app()->make(SpuRepository::class)->baseUpdate($spuData, $id, $seckillActiveId, $productType);//修改spu
            event('product.update', compact('id'));//商品事件
            app()->make(SpuRepository::class)->changeStatus($id, $productType);//修改商品状态
            return $productData;
        });
    }

    /**
     *  免审核属性编辑
     * @param int $id
     * @param array $data
     * @param int $merId
     * @param $admin_info
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author Qinii
     */
    public function freeTrial(int $id, array $data, int $merId, $admin_info = [])
    {
        if (!$data['spec_type']) {
            $data['attr'] = [];
            if (count($data['attrValue']) > 1) throw new ValidateException('单规格商品属性错误');
        }
        $res = $this->dao->get($id);
        if (!$res) throw new ValidateException('商品不存在');
        $data['svip_price_type'] = $res['svip_price_type'];
        $settleParams = $this->setAttrValue($data, $id, 0, 1);
        $settleParams['cate'] = $this->setMerCate($data['mer_cate_id'], $id, $merId);
        $settleParams['attr'] = $this->setAttr($data['attr'], $id);
        $data['price'] = $settleParams['data']['price'];
        unset($data['attrValue'], $data['attr'], $data['mer_cate_id']);
        $ret = app()->make(SpuRepository::class)->getSearch(['product_id' => $id, 'product_type' => 0,])->find();
        Db::transaction(function () use ($res, $data, $settleParams, $ret, $admin_info) {
            $this->save($res, $settleParams, null, ['admin_info' => $admin_info], 0);
            app()->make(SpuRepository::class)->update($ret->spu_id, ['price' => $data['price']]);
            Queue(SendSmsJob::class, ['tempId' => 'PRODUCT_INCREASE', 'id' => $res->product_id]);
        });
    }


    /**
     *  彻底删除商品
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param $id
     */
    public function destory($id)
    {
        (app()->make(ProductAttrRepository::class))->clearAttr($id);
        (app()->make(ProductAttrValueRepository::class))->clearAttr($id);
        (app()->make(ProductContentRepository::class))->clearAttr($id, null);
        (app()->make(ProductCateRepository::class))->clearAttr($id);
        $res = $this->dao->destory($id);
    }

    /**
     * 删除秒杀活动商品
     * @param $activeId
     * @return void
     * FerryZhao 2024/4/17
     */
    public function deleteActiveProduct($activeId, $merId = null)
    {
        $where = [
            'active_id' => $activeId,
        ];
        if ($merId) {
            $where['mer_id'] = $merId;
        }
        app()->make(StoreSeckillActiveRepository::class)->updateActiveChart($activeId);
        return $this->dao->getSearch([])->where($where)->update(['is_del' => 1]);
    }

    /**
     *  商品信息保存操作
     * @Author:Qinii
     * @Date: 2020/5/20
     * @param $id
     * @param $spec_type
     * @param $settleParams
     * @param $content
     * @return array|\think\Model|null
     */
    public function save($res, $settleParams, $content, $data = [], $productType = 0)
    {
        // 商品规格

        if (isset($settleParams['attr'])) {
            $productAttrRepository = app()->make(ProductAttrRepository::class);
            $productAttrRepository->clearAttr($res->product_id);
            $productAttrRepository->insert($settleParams['attr']);
        }

        if (isset($settleParams['cate'])){
            // 商品商户分类
            $productCateRepository = app()->make(ProductCateRepository::class);
            $productCateRepository->clearAttr($res->product_id);
            $productCateRepository->insert($settleParams['cate']);
        }


        if (isset($settleParams['attrValue'])) {
            // 商品规格
            $productAttrValueRepository = app()->make(ProductAttrValueRepository::class);
            $productAttrValueRepository->clearAttr($res->product_id);

            if ($res->type == self::DEFINE_TYPE_CARD) {
                app()->make(CdkeyLibraryRepository::class)->cancel($res->product_id);
            }else if ($res->type == self::DEFINE_TYPE_CLOUD) {
                app()->make(ProductCdkeyRepository::class)->clearAttr($res->product_id);
            }

            $arr = array_chunk($settleParams['attrValue'], 30);
            foreach ($arr as $item) {
                $productAttrValueRepository->add($item, $res->type);
            }
        }

        // 商品详情
        if ($content) {
            app()->make(ProductContentRepository::class)->clearAttr($res->product_id, $content['type']);
            $this->dao->createContent($res->product_id, $content);
        }
        if (isset($data['admin_info'])) {
            $admin_info = $data['admin_info'];
            unset($data['admin_info']);
        }
        // 基础信息修改
        $update_infos = $settleParams['update_infos'] ?? [];
        if (isset($settleParams['data'])) $data = array_merge($data, $settleParams['data']);
        if (!empty($data) ){
            $this->dao->update($res->product_id, $data);
        }
        // 后台通知
        if (isset($data['status']) && $data['status'] !== 1) {
            $message = '您有1个新的' . ($productType ? '秒杀商品' : ($data['is_gift_bag'] ? '礼包商品' : '商品')) . '待审核';
            $type = $productType ? 'new_seckill' : ($data['is_gift_bag'] ? 'new_bag' : 'new_product');
            SwooleTaskService::admin('notice', [
                'type' => $type,
                'data' => ['title' => '商品审核', 'message' => $message, 'id' => $res->product_id]
            ]);
        }

        // 操作日志
        if (!empty($admin_info) && !empty($update_infos)) {
            event('create_operate_log', [
                'category' => OperateLogRepository::MERCHANT_EDIT_PRODUCT,
                'data' => ['product' => $res, 'admin_info' => $admin_info, 'update_infos' => $update_infos,],
            ]);
        }

        return $res;
    }

    /**
     *  平台后台编辑商品保存操作
     * @Author:Qinii
     * @Date: 2020/5/18
     * @param int $id
     * @param array $data
     * @return int
     */
    public function adminUpdate(int $id, array $data)
    {
        return Db::transaction(function () use ($id, $data) {
            app()->make(ProductContentRepository::class)->clearAttr($id, 0);
            if (isset($data['content'])) {
                $this->dao->createContent($id, ['content' => $data['content']]);
                unset($data['content']);
            }
            $res = $this->dao->getWhere(['product_id' => $id], '*', ['seckillActive']);
            $activityId = $res['seckillActive']['seckill_active_id'] ?? 0;
            app()->make(SpuRepository::class)->changRank($activityId, $id, $res['product_type'], $data);
            unset($data['star']);
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
     * 格式商品主体信息
     * @param array $data
     * @return array
     */
    public function setProduct(array $data)
    {
        $give_coupon_ids = '';
        if (isset($data['give_coupon_ids']) && !empty($data['give_coupon_ids'])) {
            $gcoupon_ids = array_unique($data['give_coupon_ids']);
            $give_coupon_ids = implode(',', $gcoupon_ids);
        }

        if (isset($data['integral_rate'])) {
            $integral_rate = $data['integral_rate'];
            if ($data['integral_rate'] < 0) $integral_rate = -1;
            if ($data['integral_rate'] > 100) $integral_rate = 100;

        }
        $result = [
            'store_name' => $data['store_name'],
            'image' => $data['image'],
            'slider_image' => is_array($data['slider_image']) ? implode(',', $data['slider_image']) : '',
            'store_info' => $data['store_info'] ?? '',
            'keyword' => $data['keyword'] ?? '',
            'brand_id' => $data['brand_id'] ?? 0,
            'cate_id' => $data['cate_id'] ?? 0,
            'unit_name' => $data['unit_name'] ?? '件',
            'rank' => isset($data['rank']) && $data['rank'] ? $data['rank'] : 0,
            'sort' => $data['sort'] ?? 0,
            'is_show' => $data['is_show'] ?? 0,
            'is_used' => (isset($data['status']) && $data['status'] == 1) ? 1 : 0,
            'is_good' => $data['is_good'] ?? 0,
            'video_link' => $data['video_link'] ?? '',
            'temp_id' => $data['delivery_free'] ? 0 : ($data['temp_id'] ?? 0),
            'extension_type' => $data['extension_type'] ?? 0,
            'spec_type' => $data['spec_type'] ?? 0,
            'status' => $data['status'] ?? 0,
            'give_coupon_ids' => $give_coupon_ids,
            'mer_status' => $data['mer_status'],
            'guarantee_template_id' => $data['guarantee_template_id'] ?? 0,
            'is_gift_bag' => $data['is_gift_bag'] ?? 0,
            'integral_rate' => $integral_rate ?? 0,
            'delivery_way' => is_array($data['delivery_way']) && !empty($data['delivery_way']) ? implode(',', $data['delivery_way']) : $data['delivery_way'],
            'delivery_free' => $data['delivery_free'] ?? 0,
            'once_min_count' => $data['once_min_count'] ?? 0,
            'once_max_count' => $data['once_max_count'] ?? 0,
            'pay_limit' => $data['pay_limit'] ?? 0,
            'svip_price_type' => $data['svip_price_type'] ?? 0,
            'refund_switch' => $data['refund_switch'] ?? 0,
            'mer_form_id' => $data['mer_form_id'] ?? 0,
            'rate' => $data['rate'] ?? 5.0,
            'active_id' => $data['active_id'] ?? 0,
            'bar_code_number' => $data['bar_code_number'] ?? '',
            'auto_off_time' => isset($data['auto_off_time']) ? strtotime($data['auto_off_time']) : 0,
            'labels' => $data['mer_labels'] ? json_encode($data['mer_labels']) : '',
            'activity_label_ids' => isset($data['activity_label_ids']) ? $data['activity_label_ids'] : '',
            'hit_amount' => isset($data['hit_amount']) ? (float)$data['hit_amount'] : 0,
            'welfare_commission' => isset($data['welfare_commission']) ? (float)$data['welfare_commission'] : 0,
        ];
        if (isset($data['extend']))
            $result['extend'] = $data['extend'] ? json_encode($data['extend'], JSON_UNESCAPED_UNICODE) : '';
        if (isset($data['mer_id']))
            $result['mer_id'] = $data['mer_id'];
        if (isset($data['old_product_id']))
            $result['old_product_id'] = $data['old_product_id'];
        if (isset($data['product_type']))
            $result['product_type'] = $data['product_type'];
        if (isset($data['type']) && $data['type'])
            $result['type'] = $data['type'];
        if (isset($data['param_temp_id']))
            $result['param_temp_id'] = $data['param_temp_id'];
        if (isset($data['custom_temp_id']))
            $result['custom_temp_id'] = json_encode($data['custom_temp_id']);
        if (isset($data['good_ids']))
            $result['good_ids'] = $data['good_ids'];
        if ($data['is_show'] == 2)
            $result['auto_on_time'] = strtotime($data['auto_on_time']);

        return $result;
    }

    /**
     *  格式商品商户分类
     * @Author:Qinii
     * @Date: 2020/9/15
     * @param array $data
     * @param int $productId
     * @param int $merId
     * @return array
     */
    public function setMerCate(array $data, int $productId, int $merId)
    {
        $result = [];
        foreach ($data as $value) {
            $result[] = [
                'product_id' => $productId,
                'mer_cate_id' => $value,
                'mer_id' => $merId,
            ];
        }
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
    public function setAttr(array $data, int $productId,$productType = 0)
    {
        $result = [];
        try {
            foreach ($data as $value) {
                $detail = empty(array_column($value['detail'],'value')) ? $value['detail'] : array_column($value['detail'],'value');
                $result[] = [
                    'type' => 0,
                    'product_id' => $productId,
                    "attr_name" => $value['value'] ?? $value['attr_name'],
                    'attr_values' => implode('-!-', $detail),
                ];
            }
        } catch (\Exception $exception) {
            throw new ValidateException('商品规格格式错误' . $exception->getMessage());
        }
        return $result;
    }

    /**
     *  格式商品SKU
     * 商品添加编辑规格整理
     * 所有商品添加编辑时预览规格整理
     * @Author:Qinii
     * @Date: 2020/9/15
     * @param array $data
     * @param int $productId
     * @return mixed
     */
    public function setAttrValue(array $data, int $productId, int $productType, int $isUpdate = 0)
    {
        $extension_status = systemConfig('extension_status');
        $result = ['attrValue' => [], 'data' => []];
        $update_infos = [
            OperateLogRepository::MERCHANT_INC_PRODUCT_PRICE => '',
            OperateLogRepository::MERCHANT_DEC_PRODUCT_PRICE => '',
            OperateLogRepository::MERCHANT_INC_PRODUCT_STOCK => '',
            OperateLogRepository::MERCHANT_DEC_PRODUCT_STOCK => '',
        ];
        if ($isUpdate) {
            $productAttrValue = app()->make(ProductAttrValueRepository::class)->search(['product_id' => $productId])->select()->toArray();
            $oldSku = $this->detailAttrValue($productAttrValue, null);
            $unique_price = array_column($productAttrValue,'price','unique');
            $unique_stock = array_column($productAttrValue,'stock','unique');
        }

        $cdkeyLibraryRepository = app()->make(CdkeyLibraryRepository::class);
        /**
         *  循环处理商品的每条规格组合
         *  1. 获取商品规格组合的库存和价格
         *  2. 计算商品最低价格
         *  3. 处理卡密商品属性
         */
        $svip = isset($data['svip_price_type']) && $data['svip_price_type'] == 2 ? true : false;
        $base_num = [];

        foreach ($data['attrValue'] as $value) {
            if (!$productType && !$base_num && (!isset($value['is_default_select']) || $value['is_default_select']) ) {
                $base_num = [
                    'price' => $value['price'],
                    'ot_price' => $value['ot_price'],
                    'cost' => $value['cost'],
                    'svip_price' => $svip ? $value['svip_price'] : 0,
                ];
            }
            try {
                if (isset($value['checked']) && !$value['checked']) {continue;}
                $sku = '';
                if (isset($value['detail']) && !empty($value['detail']) && is_array($value['detail'])) {
                    $sku = implode(',', $value['detail']);
                }
                $unique = $this->setUnique($productId, $sku, $productType);
                // 卡密/网盘商品 cdkey 关联
                if ($productType == 0  && isset($data['type'])) {
                    if ($data['type'] == self::DEFINE_TYPE_CARD) { // 卡密商品
                        if (isset($value['library_id']) && $value['library_id']) {
                            $library  = $cdkeyLibraryRepository->getSearch(['id' => $value['library_id'],'mer_id' => $data['mer_id']])->find();
                            if (is_null($library)) throw new ValidateException('卡密库ID错误'.$value['library_id']);
                            if ($library['product_id'] && (!$isUpdate || $library['product_id'] != $productId)) {
                                throw new ValidateException('此卡密库ID已被使用【'.$value['library_id'].'】');
                            }
                        }
                    } else if ($data['type'] == self::DEFINE_TYPE_CLOUD){ //网盘商品
                        if (!isset($value['cdkey']) || empty($value['cdkey'])) throw new ValidateException('网盘商品必须填写信息');
                        [$cdkey, $cdkeey_stock] = $this->setCdkey($value['cdkey'], $productId);
                        !$cdkeey_stock ?: $value['stock'] = $cdkeey_stock;
                    }
                }
                $attrValueItem = [];
                if(($data['type'] ?? 0) == self::DEFINE_TYPE_RESERVATION) {
                    $attrValueItem['reservation'] = $value['reservation'] ?? [];
                    $value['stock'] = array_sum(array_column($value['reservation'],'stock'));
                }
                //新售价
                $new_price = $value['price'] ? (($value['price'] < 0) ? 0 : $value['price']) : 0;
                //新库存
                $new_stock = $value['stock'] ? (($value['stock'] < 0) ? 0 : $value['stock']) : 0;

                $attrValueItem["type"] = 0;
                $attrValueItem["sku"] = $sku;
                $attrValueItem["unique"] = $unique;
                $attrValueItem["price"] = $new_price;
                $attrValueItem["stock"] = $new_stock;
                $attrValueItem["product_id"] = $productId;
                $attrValueItem["image"] = isset($value["image"]) ? $value["image"] : $value["pic"] ?? '';
                $attrValueItem["bar_code"] = $value["bar_code"] ?? '';
                $attrValueItem['library_id'] = $value['library_id'] ?? 0;
                $attrValueItem['bar_code_number'] = $value['bar_code_number'] ?? '';
                $attrValueItem['detail'] = json_encode($value['detail'] ?? '');
                $attrValueItem['svip_price'] = $svip ? ($value['svip_price'] ?? 0) : 0;
                $attrValueItem['sales'] = $isUpdate ? ($oldSku[$sku]['sales'] ?? 0) : 0;
                $attrValueItem["extension_one"] = $extension_status ? ($value['extension_one'] ?? 0) : 0;
                $attrValueItem["extension_two"] = $extension_status ? ($value['extension_two'] ?? 0) : 0;
                $attrValueItem["cost"] = $value['cost'] ? (($value['cost'] < 0) ? 0 : $value['cost']) : 0;
                $attrValueItem["ot_price"] = $value['ot_price'] ? (($value['ot_price'] < 0) ? 0 : $value['ot_price']) : 0;
                $attrValueItem["volume"] = isset($value['volume']) ? ($value['volume'] ? (($value['volume'] < 0) ? 0 : $value['volume']) : 0) : 0;
                $attrValueItem["weight"] = isset($value['weight']) ? ($value['weight'] ? (($value['weight'] < 0) ? 0 : $value['weight']) : 0) : 0;
                $attrValueItem['is_default_select'] = $value['is_default_select'] ?? 0;
                $attrValueItem['is_show'] = $value['is_show'] ?? 1;
                $attrValueItem["settlement_price"] = $value["settlement_price"] ?? 0;
                $attrValueItem["probability_weight"] = isset($value['probability_weight']) ? max(0, (int)$value['probability_weight']) : 10;
                if (isset($cdkey)) {$attrValueItem['cdkey'] = $cdkey;}
                $result['attrValue'][] = $attrValueItem;
                // 组合操作日志数据
                if ($isUpdate && isset($value['unique'])) {
                    if (isset($unique_price[$value['unique']])) {
                        if ($new_price > $unique_price[$value['unique']]) {
                            $update_infos[OperateLogRepository::MERCHANT_INC_PRODUCT_PRICE] .= $attrValueItem['sku'] . '价格增加了' . bcsub($new_price, $unique_price[$value['unique']], 2) . ',';
                        }
                        if ($new_price < $unique_price[$value['unique']]) {
                            $update_infos[OperateLogRepository::MERCHANT_DEC_PRODUCT_PRICE] .= $attrValueItem['sku'] . '价格减少了' . bcsub($unique_price[$value['unique']], $new_price, 2) . ',';
                        }
                    }
                    if (isset($unique_stock[$value['unique']])) {
                        if ($new_stock > $unique_stock[$value['unique']]) {
                            $update_infos[OperateLogRepository::MERCHANT_INC_PRODUCT_STOCK] .= $attrValueItem['sku'] . '库存增加了' . ($new_stock - $unique_stock[$value['unique']]) . ',';
                        }
                        if ($new_stock < $unique_stock[$value['unique']]) {
                            $update_infos[OperateLogRepository::MERCHANT_DEC_PRODUCT_STOCK] .= $attrValueItem['sku'] . '库存减少了' . ($unique_stock[$value['unique']] - $new_stock) . ',';
                        }
                    }
                }
            } catch (\Exception $exception) {
                throw new ValidateException('规格错误:'.$exception->getMessage());
            }
        }
        if (!$base_num) {
            $base_num = $this->validateAttributeValue($data['attrValue'], $productType, $svip);
        }
        $result['update_infos'] = $update_infos;
        $base_num['stock'] = array_sum(array_column($result['attrValue'],'stock'));
        $result['data'] = $base_num;
        return $result;
    }

    /**
     *  获取最低价格，总库存，等数据
     * @param $value
     * @param $productType
     * @param $svip
     * @return array
     * @author Qinii
     */
    public function validateAttributeValue($value, $productType,$svip)
    {
       switch($productType) {
           case 2:
               $ot_price = 'price';
               $price_filed = 'presell_price';
               break;
           case 3:
               $ot_price = 'price';
               $price_filed = 'ot_price';
               break;
           default:
               $ot_price = 'ot_price';
               $price_filed = 'price';
               break;
       }
       // 价格排序
       usort($value,function($a, $b) use($price_filed){
           return bccomp((string)($a[$price_filed] ?? 0), (string)($b[$price_filed] ?? 0), 2);
       });
       $minValue = $value[0];
       return [
           'price' => $minValue[$price_filed],
           'ot_price' => $minValue[$ot_price],
           'cost' => $minValue['cost'],
           'svip_price' => $svip ? $minValue['svip_price'] : 0,
       ];
    }

    /**
     * 添加/编辑商品卡密
     * @param $data
     * @param $productId
     * @return array
     * @author Qinii
     * @day 2024/6/5
     */
    public function setCdkey($data, $productId)
    {
        if (!isset($data['is_type'])) throw new ValidateException('请选择添加卡密');
        $stock = 0;
        // 一次性卡密
        if ($data['is_type']) {
            $stock = count($data['list']);
            foreach ($data['list'] as $datum) {
                if (!isset($datum['key']) || !$datum['key']) throw new ValidateException('请添加卡密内容');
                $cdkey[] = [
                    'is_type' => $data['is_type'],
                    'key' => $datum['key'],
                    'pwd' => $datum['pwd'],
                    'product_id' => $productId
                ];
            }
        //网盘
        } else {
            $cdkey[] = [
                'is_type' => $data['is_type'],
                'key' => $data['key'],
                'pwd' => '',
                'product_id' => $productId
            ];
        }
        return [$cdkey, $stock];
    }


    /**
     *  获取sku唯一值
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param int $id
     * @param string $sku
     * @param int $type
     * @return string
     */
    public function setUnique(int $id, $sku, int $type)
    {
        return $unique = substr(md5($sku . $id), 12, 11) . $type;
        //        $has = (app()->make(ProductAttrValueRepository::class))->merUniqueExists(null, $unique);
        //        return $has ? false : $unique;
    }

    public function getEdit($id, $is_copy)
    {
        $with = [
            'attrValue' => function ($query) { $query->with(['productCdkey'])->order('value_id ASC'); },
            'content' => function ($query) { $query->where('type', 0); },
            'attr_result',
            'merCateId'
        ];
        $product = $this->dao->geTrashedtProduct($id)->with($with)->find();
        if ($product['attr_result'] && $product['spec_type'] == 1) {
            $product = $product->toArray();
            $result = json_decode($product['attr_result']['result'],true);
            unset($product['attr_result']);
            $product['attr'] = $result['attr'];
            $oldAttrValue = $product['attrValue'];
            unset($product['attrValue']);
            $old = array_combine(array_column($oldAttrValue,'sku'),$oldAttrValue);
            foreach ($result['attrValue'] as &$value) {
                $_sku = '';
                if(isset($value['attr_arr'])) {
                    $_sku = implode(',',$value['attr_arr']);
                } else {
                    $_sku = implode(',',array_values($value['detail'] ?? []));
                    $value['attr_arr'] = [$_sku];
                }
                $value['sku'] = $_sku;
                $value['unique'] = $old[$_sku]['unique'] ?? '';
                $value['stock'] = $old[$_sku]['stock'] ?? 0;
                $value['value_id'] = $old[$_sku]['value_id'] ?? 0;
                $value['productCdkey'] = $old[$_sku]['productCdkey'] ?? [];
            }
            $product['params'] = $result['params'];
            $product['attrValue'] = $result['attrValue'];;
        } else {
            foreach ($product->attr as $k => $v) {
                $product['attr'][$k] = [
                    'value' => $v['attr_name'],
                    'detail' => array_map(function($i){return ['pic' => '','value' => $i];},$v['attr_values'])
                ];
            }
            $product['attrValue'] = $product->attrValue;
            $product = $product->toArray();
        }
        $product['delivery_way'] = empty($product['delivery_way']) ? [2] : explode(',', $product['delivery_way']);
        $mer_cat = [];
        if (isset($product['merCateId'])) {
            $mer_cat = array_column($product['merCateId'], 'mer_cate_id') ;
        }
        $product['mer_cate_id'] = $mer_cat;
        $product['content'] = $product['content']['content'] ?? '';
        if (!$product['param_temp_id']) {
            $product['param_temp_id'] = '';
        }
        // 拼接商品推荐商品
        if (!empty($product['good_ids'])) {
            $product['goodList'] = $this->dao->getGoodList($product['good_ids'], $product['mer_id'], false);
        }
        if (!empty($product['give_coupon_ids'])) {
            $where = [['coupon_id', 'in', $product['give_coupon_ids']]];
            $product['coupon'] = app()->make(StoreCouponRepository::class)->selectWhere($where, 'coupon_id,title')->toArray();
        }
        $spu_where = ['activity_id' => $product['active_id'], 'product_type' => $product['product_type'], 'product_id' => $id];
        $spu = app()->make(SpuRepository::class)->getSearch($spu_where)->find();
        if ($spu) $spu->append(['mer_labels_data', 'sys_labels_data','us_status']);
        $product['star'] = $spu['star'] ?? '';
        $product['mer_labels'] = $spu['mer_labels'] ?? '';
        $product['sys_labels'] = $spu['sys_labels'] ?? '';
        $product['mer_labels_data'] = $spu['mer_labels_data'] ?? [];
        $product['sys_labels_data'] = $spu['sys_labels_data'] ?? [];

        foreach ($product['attrValue'] as $k => $v) {
            $product['attrValue'][$k]['cdkey'] = $v['productCdkey'];
        }

        return $product;
    }

    /**
     * 后台管理需要的商品详情
     * @param int $id
     * @param int|null $activeId
     * @return array|\think\Model|null
     * @author Qinii
     * @day 2020-11-24
     */
    public function getAdminOneProduct(int $id, ?int $activeId, $conType = 0, $is_copy = 0)
    {
        $appcdkey = $is_copy ? ['productCdkey', 'cdkeyLibrary'] : ['productCdkey', 'reservation'];
        $with = [
            'attr', 'attrValue.productCdkey', 'oldAttrValue', 'merCateId.category', 'storeCategory', 'brand','reservation',
            'temp', 'seckillActive','guarantee.templateValue.value', 'getFormName','attr_result',
            'attrValue' => function ($query) use($appcdkey) { $query->with($appcdkey)->order('value_id ASC'); },
            'content' => function ($query) use ($conType) { $query->where('type', $conType); },
            'merchant' => function ($query) {
                $query->with(['typeName', 'categoryName'])->field('mer_id,category_id,type_id,mer_avatar,mer_name,is_trader');
            },
        ];

        $data = $this->dao->geTrashedtProduct($id)->with($with)->find();
        $data['delivery_way'] = empty($data['delivery_way']) ? [2] : explode(',', $data['delivery_way']);
        $where = [['coupon_id', 'in', $data['give_coupon_ids']]];
        $data['coupon'] = app()->make(StoreCouponRepository::class)->selectWhere($where, 'coupon_id,title')->toArray();
        $spu_make = app()->make(SpuRepository::class);
        $append = [];
        if ($data['product_type'] == 0) { $append = ['us_status', 'parameter_params'];$activeId = 0; }
        if ($data['product_type'] == 1) {
            $activeId = $data->seckillActive->seckill_active_id ?? 0;
            $storeOrderProduct = app()->make(StoreOrderProductRepository::class);
            $productRepository = app()->make(ProductRepository::class);
            $make = $storeOrderProduct;
            $append = ['us_status', 'seckill_status'];
            $data['sales'] = $storeOrderProduct->getSearch([])->where(['product_type' => 1, 'is_refund' => 0, 'product_id' => $data['product_id']])->sum('product_num');
            $data['stock'] = $productRepository->getSearch([])->where(['product_id'=>$data['old_product_id']])->value('stock');
        }
        if ($data['product_type'] == 2) $make = app()->make(ProductPresellSkuRepository::class);
        if ($data['product_type'] == 3) $make = app()->make(ProductAssistSkuRepository::class);
        if ($data['product_type'] == 4) $make = app()->make(ProductGroupSkuRepository::class);

        $spu_where = ['activity_id' => $activeId, 'product_type' => $data['product_type'], 'product_id' => $id];
        $spu = $spu_make->getSearch($spu_where)->find();
        if ($spu) $spu->append(['mer_labels_data', 'sys_labels_data','us_status']);
        $data['star'] = $spu['star'] ?? '';
        $data['mer_labels'] = $spu['mer_labels'] ?? '';
        $data['sys_labels'] = $spu['sys_labels'] ?? '';
        $data['mer_labels_data'] = $spu['mer_labels_data'] ?? [];
        $data['sys_labels_data'] = $spu['sys_labels_data'] ?? [];
        // 处理表单ID转换 兼容前端显示
        $data['mer_form_id'] = $data['mer_form_id'] ?: null;
        $append = array_merge($append, ['activity_labels']);
        $data->append($append);
        $mer_cat = [];
        // if (isset($data['merCateId'])) {
        //     $mer_cat = array_column($data['merCateId']->toArray(), 'mer_cate_id') ;
        // }
        $data['mer_cate_id'] = $mer_cat;
        foreach ($data['attr'] as $k => $v) {
            $data['attr'][$k] = ['value' => $v['attr_name'], 'detail' => $v['attr_values']];
        }
        $attrValue = (in_array($data['product_type'], [3, 4])) ? $data['oldAttrValue'] : $data['attrValue'];
        unset($data['oldAttrValue'], $data['attrValue']);
        $arr = [];
        if (in_array($data['product_type'], [1, 3])) $value_make = app()->make(ProductAttrValueRepository::class);
        $attrValue = json_decode(json_encode($attrValue,true),true);

        // 批量查询优化：预先加载所有需要的数据
        $uniques = array_column($attrValue, 'unique');
        $skus = array_column($attrValue, 'sku');

        // 批量查询SKU对应的ProductAttrValue(product_type=1,3)
        $valueMap = [];
        if (in_array($data['product_type'], [1, 3]) && !empty($skus)) {
            $valueList = $value_make->getSearch(['product_id' => $data['old_product_id']])->where('sku', 'in', $skus)->select();
            foreach ($valueList as $value) {
                $valueMap[$value['sku']] = $value;
            }
        }

        // 批量查询SKU销量(product_type=1)
        $salesMap = [];
        if ($data['product_type'] == 1 && !empty($uniques)) {
            $salesList = $make->getSearch([])->where('product_sku', 'in', $uniques)
                ->where('product_type', 1)
                ->field('product_sku, SUM(product_num) as total_sales')
                ->group('product_sku')->select();
            foreach ($salesList as $sales) {
                $salesMap[$sales['product_sku']] = $sales['total_sales'];
            }
        }

        // 批量查询活动SKU(product_type=2,3,4)
        $activeSkuMap = [];
        if (in_array($data['product_type'], [2, 3, 4]) && !empty($uniques)) {
            $whereField = $data['product_type'] == 2 ? 'product_presell_id'
                : ($data['product_type'] == 3 ? 'product_assist_id' : 'product_group_id');
            $activeSkuList = $make->getSearch([$whereField => $activeId])->where('unique', 'in', $uniques)->select();
            foreach ($activeSkuList as $sku) {
                $activeSkuMap[$sku['unique']] = $sku;
            }
        }

        foreach ($attrValue as $key => $item) {
            if ($data['product_type'] == 1) {
                $value = $valueMap[$item['sku']] ?? null;
                if ($value){
                    $old_stock = $value['stock'];
                    $item['old_stock'] = $old_stock ?? $item['stock'];
                }
                $item['sales'] = $salesMap[$item['unique']] ?? 0;
            }
            if ($data['product_type'] == 2) {
                $item['presellSku'] = $activeSkuMap[$item['unique']] ?? null;
                if (is_null($item['presellSku'])) continue;
            }
            if ($data['product_type'] == 3) {
                $item['assistSku'] = $activeSkuMap[$item['unique']] ?? null;
                if (is_null($item['assistSku']))
                    continue;
            }
            if ($data['product_type'] == 4) {
                $item['_sku'] = $activeSkuMap[$item['unique']] ?? null;
                if (is_null($item['_sku']))
                    continue;
            }
            $sku = explode(',', $item['sku']);
            $item['old_stock'] = $old_stock ?? $item['stock'];
            if (isset($item['productCdkey']) && count($item['productCdkey'])) {
                $hasCdkey = $item['productCdkey'];
                if ($hasCdkey['is_type']) {
                    $cdkey['list'][] = $hasCdkey;
                } else {
                    $cdkey['key'] = $hasCdkey['key'];
                }
                $cdkey['is_type'] = $hasCdkey['is_type'];
                $item['cdkey'] = $cdkey;
            }
            unset($item['productCdkey']);
            foreach ($sku as $k => $v) { $item['value' . $k] = $v; }
            if ($is_copy) {$item['library_id'] = 0;$item['stock'] = 0;}
            $arr[] = $item;
        }
        $data['attrValue'] = $arr;
        $content = $data['content']['content'] ?? '';
        if ($conType) $content = json_decode($content);
        unset($data['content']);
        $data['content'] = $content;
        // 查找该商品积分抵扣比例
        if (!empty($data['merchant'])) {
            $data['merchant']['mer_integral_status'] = merchantConfig($data['merchant']['mer_id'], 'mer_integral_status');
            $data['merchant']['mer_integral_rate'] = merchantConfig($data['merchant']['mer_id'], 'mer_integral_rate');
        }
        // 拼接商品分类
        if (!empty($data['storeCategory'])) {
            $cate_name = app()->make(StoreCategoryRepository::class)->getAllFatherName($data['storeCategory']['store_category_id']);
            $data['storeCategory']['cate_name'] = $cate_name;
        }

        // 拼接商品推荐商品
        if (!empty($data['good_ids'])) {
            $data['goodList'] = $this->dao->getGoodList($data['good_ids'], $data['mer_id'], false);
        }
        $data['merchant']['mer_config'] = $this->merConfig($data['mer_id']);
        $data = $data->toArray();
        $data = $this->mergeReservation($data);
        $data = $this->translateAttrResult($data);
        return $data;
    }

    /**
     * @param $merId
     * @return mixed
     * @author Qinii
     */
    public function merConfig($merId)
    {
        $data = systemConfig(['extension_status', 'svip_switch_status', 'integral_status', 'extension_one_rate', 'extension_two_rate']);
        $merData = merchantConfig($merId, ['mer_integral_status', 'mer_integral_rate', 'mer_svip_status', 'svip_store_rate']);
        // 计算商家 svip 店铺比例
        $svip_store_rate = $merData['svip_store_rate'] > 0 ? bcdiv($merData['svip_store_rate'], 100, 2) : 0;
        // 判断商家 svip 状态
        $data['mer_svip_status'] = ($data['svip_switch_status'] && $merData['mer_svip_status'] != 0) ? 1 : 0;
        // 设置商家 svip 店铺比例
        $data['svip_store_rate'] = $svip_store_rate;
        // 判断积分状态
        $data['integral_status'] = $data['integral_status'] && $merData['mer_integral_status'] ? 1 : 0;
        // 设置积分比例
        $data['integral_rate'] = $merData['mer_integral_rate'] ?: 0;
        // 设置分销一比例
        $data['extension_one_rate'] = $data['extension_one_rate'] ? $data['extension_one_rate'] * 100 : 0;
        // 设置分销二比例
        $data['extension_two_rate'] = $data['extension_two_rate'] ? $data['extension_two_rate'] * 100 : 0;
        return $data;
    }

    /**
     *  后台不同状态查询组合的条件
     * @Author:Qinii
     * @Date: 2020/5/18
     * @param $type
     * @param int|null $merId
     * @return array
     */
    public function switchType($type, ?int $merId = 0, $productType = 0)
    {
//        1 出售中 2 仓库中 3 已售罄 4 警戒库存 5 回收站 6 待审核 7 审核未通过
        $stock = 0;
        if ($merId) $stock = merchantConfig($merId, 'mer_store_stock');
        switch ($type) {
            case 1:
                $where = ['is_show' => 1, 'status' => 1,];
                break;
            case 2:
                $where = ['is_show' => 0, 'status' => 1];
                break;
            case 3:
                $where = ['is_show' => 1, 'stock' => 0, 'status' => 1];
                break;
            case 4:
                $where = ['stock' => $stock ? $stock : 0, 'status' => 1];
                break;
            case 5:
                $where = ['soft' => true];
                break;
            case 6:
                $where = ['status' => 0];
                break;
            case 7:
                $where = ['status' => -1];
                break;
            case 20:
                $where = ['status' => 1];
                break;
            default:
                //                $where = ['is_show' => 1, 'status' => 1];
                break;
        }
        if ($productType == 0) {
            $where['product_type'] = $productType;
            if (!$merId) $where['is_gift_bag'] = 0;
        }
        if ($productType == 1) {
            $where['product_type'] = $productType;
            $where['not_active_id'] = 0;
        }
        if ($productType == 10) {
            $where['is_gift_bag'] = 1;
        }
        if (!$merId) $where['star'] = '';
        return $where;
    }

    /**
     * 获取每个类型的数量
     * @Author:Qinii
     * @Date: 2020/5/18
     * @param int|null $merId
     * @return array
     */
    public function getFilter(?int $merId, array $where = [], $name = '', $productType = 0)
    {
        $result = [];
        $result[] = [
            'type' => 1,
            'name' => '出售中' . $name,
            'count' => $this->getFiltercCount(1, $merId, $productType, $where)
        ];
        $result[] = [
            'type' => 2,
            'name' => '仓库中' . $name,
            'count' => $this->getFiltercCount(2, $merId, $productType, $where)
        ];
        if ($merId) {
            $result[] = [
                'type' => 3,
                'name' => '已售罄' . $name,
                'count' => $this->getFiltercCount(3, $merId, $productType, $where)
            ];
            $result[] = [
                'type' => 4,
                'name' => '警戒库存',
                'count' => $this->getFiltercCount(4, $merId, $productType, $where)
            ];
        }
        $result[] = [
            'type' => 6,
            'name' => '待审核' . $name,
            'count' => $this->getFiltercCount(6, $merId, $productType, $where)
        ];
        $result[] = [
            'type' => 7,
            'name' => '审核未通过' . $name,
            'count' => $this->getFiltercCount(7, $merId, $productType, $where)
        ];

        $result[] = [
            'type' => 5,
            'name' => '回收站' . $name,
            'count' => $this->getFiltercCount(5, $merId, $productType, $where)
        ];
        return $result;
    }

    protected function getFiltercCount($type, $merId, $productType, $searchWhere)
    {
        if($productType == 1) {
            $activeStatusWhere = app()->make(StoreSeckillActiveRepository::class)
                ->search($searchWhere)->column('seckill_active_id');
            if (!empty($activeStatusWhere)) {
                $searchWhere['seckill_active_id'] = $activeStatusWhere;
            } else if ((isset($searchWhere['active_status']) && $searchWhere['active_status'] != '') || (isset($searchWhere['active_name']) && $searchWhere['active_name'] != '')) {
                return 0;
            }
            unset($searchWhere['active_status']);
            unset($searchWhere['active_name']);
        }

        $countWhere = $this->switchType($type, $merId, $productType);
        unset($countWhere['star']);

        return $this->dao->search($merId, array_merge($searchWhere, $countWhere))
            ->removeOption('order')
            ->count();
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
    public function getList(?int $merId, array $where, int $page, int $limit, $isWith = true)
    {
        $query = $this->dao->search($merId, $where);
        if ($isWith) $query = $query->with(['merCateId.category', 'storeCategory', 'brand']);
        $count = $query->count();
        $list = $query->page($page, $limit)->setOption('field', [])->field($this->filed)->select()
            ->each(function ($item) {
                if (!$item->mer_labels) {
                    $item->mer_labels = [];
                } else {
                    $item->mer_labels = explode(',', rtrim(ltrim($item->mer_labels, ','), ','));
                }
            });
        if ($isWith) $list->append(['us_status','activity_labels']);
        return compact('count', 'list');
    }

    /**
     * 商户秒杀商品列表
     * @param int|null $merId
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * FerryZhao 2024/4/17
     */
    public function getSeckillList(?int $merId, array $where, int $page, int $limit, $isPage)
    {
        $query = $this->dao->search($merId, $where)->with(['merCateId.category', 'storeCategory', 'brand', 'attrValue ', 'seckillActive']);
        $count = $query->count();
        $productAttrValue = app()->make(ProductAttrValueRepository::class);
        if ($isPage) {
            $query = $query->page($page, $limit);
        }
        $list = $query->setOption('field', [])->field($this->filed)->order('sort DESC')->select()
            ->each(function ($item) use ($productAttrValue) {
                if (!$item->sys_labels) {
                    $item->sys_labels = [];
                } else {
                    $item->sys_labels = explode(',', rtrim(ltrim($item->sys_labels, ','), ','));
                }
                if (!$item->mer_labels) {
                    $item->mer_labels = [];
                } else {
                    $item->mer_labels = explode(',', rtrim(ltrim($item->mer_labels, ','), ','));
                }

                //处理old_stock
                if (!empty($item['attrValue'])) {
                    foreach ($item['attrValue'] as &$attrItem) {
                        $value = $productAttrValue->getSearch(['sku' => $attrItem['sku'], 'product_id' => $item['product_id']])->find();
                        if ($value) $attrItem['old_stock'] = $value['stock'];
                    }
                }
                $item['old_stock'] = $item['stock'];

                //分类处理
                $merCat = [];
                if (isset($item['merCateId'])) {
                    foreach ($item['merCateId'] as $i) {
                        $merCat[] = $i['mer_cate_id'];
                    }
                }
                $item['mer_cate_id'] = $merCat;

                //规格处理
                foreach ($item['attr'] as $k => $v) {
                    $item['attr'][$k] = [
                        'value' => $v['attr_name'],
                        'detail' => $v['attr_values']
                    ];
                }
                $item['content'] = $item['content']['content'] ?? '';//详情处理
                $item['old_product_id'] = $item['product_id'];//old_product_id
            })->append(['us_status']);
        $data = compact('count', 'list');
        if (!$isPage) {
            $data = compact('list');
        }
        return $data;
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
        $query = $this->dao->search($merId, $where)->with([
            'merCateId.category',
            'storeCategory',
            'brand',
            'merchant',
        ]);
        $count = $query->count();
        $list = $query->page($page, $limit)->setOption('field', [])->field($this->admin_filed)->select()
            ->each(function ($item) {
                $item->sys_labels = $item->sys_labels ? explode(',', rtrim(ltrim($item->sys_labels, ','), ',')) : [];
            })->append(['us_status','activity_labels']);
        return compact('count', 'list');
    }

    /**
     * 平台秒杀商品列表
     * @param int|null $merId
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * FerryZhao 2024/4/17
     */
    public function getAdminSeckillPageList(?int $merId, array $where, int $page, int $limit): array
    {
        $count = 0;
        $list = [];
        if ((isset($where['active_status']) && $where['active_status'] != '') || (isset($where['active_name']) && $where['active_name'] != '')) {
            $activeStatusWhere = app()->make(StoreSeckillActiveRepository::class)->search($where)->column('seckill_active_id');
            if (!$activeStatusWhere) {
                return compact('count', 'list');
            }
            $where['seckill_active_id'] = $activeStatusWhere;
        }
        //$activeStatusWhere = app()->make(StoreSeckillActiveRepository::class)->search($where)->column('seckill_active_id');
        //if (!empty($activeStatusWhere)) {
        //    $where['seckill_active_id'] = $activeStatusWhere;
        //} else if ((isset($where['active_status']) && $where['active_status'] != '') || (isset($where['active_name']) && $where['active_name'] != '')) {
        //    return compact('count', 'list');
        //}
        unset($where['active_status']);
        unset($where['active_name']);
        $query = $this->dao->search($merId, $where)->with([
            'merCateId.category',
            'storeCategory',
            'brand',
            'merchant',
            'seckillActive',
            'attrValue'
        ]);
        $storeOrderProductRepository = app()->make(StoreOrderProductRepository::class);
        $productAttrValueRepository = app()->make(ProductAttrValueRepository::class);
        $query->page($page, $limit);
        $count = $query->count();
        $list = $query->field('Product.*,U.star,U.rank,U.sys_labels,U.mer_labels')->select()
            ->each(function ($item) use ($storeOrderProductRepository,$productAttrValueRepository) {
                $item->sys_labels = $item->sys_labels ? explode(',', rtrim(ltrim($item->sys_labels, ','), ',')) : [];
                $item->mer_labels = $item->mer_labels ? explode(',', rtrim(ltrim($item->mer_labels, ','), ',')) : [];
//                $item['stock'] = $this->dao->getSearch([])->where(['product_id'=>$item['product_id']])->value('stock');
//                $item['old_stock'] =$productAttrValueRepository->getSearch([])->where(['product_id'=>$item['product_id']])->value('stock');
                //分类处理
                $merCat = [];
                if (isset($item['merCateId'])) {
                    foreach ($item['merCateId'] as $i) {
                        $merCat[] = $i['mer_cate_id'];
                    }
                }
                $item['mer_cate_id'] = $merCat;
                //规格处理
                foreach ($item['attrValue'] as $k => &$v) {
                    $v['old_stock'] = $productAttrValueRepository->getSearch([])->where(['sku'=>$v['sku'],'product_id'=>$item['old_product_id']])->value('stock');
                    $item['attr'][$k] = [
                        'value' => $v['attr_name'],
                        'detail' => $v['attr_values']
                    ];
                }

                $item['content'] = $item['content']['content'] ?? '';//详情处理

                if (!isset($item['old_product_id']) || !$item['old_product_id']) {
                    $item['old_product_id'] = $item['product_id'];//old_product_id
                }

                $item['sales'] = app()->make(StoreOrderRepository::class)->seckillOrderCounut($item['active_id'],$item['product_id']);
                $item['stock'] = $this->getSeckillAttrValue($item['attrValue'], $item['old_product_id'])['stock'];
            })->append(['us_status']);
        return compact('count', 'list');
    }

    /**
     * 平台秒杀商品列表
     * @param int|null $merId
     * @param array $where
     * @return array
     * FerryZhao 2024/4/17
     */
    public function getAdminSeckillList(?int $merId, array $where): array
    {
        $list = [];

        $activeStatusWhere = app()->make(StoreSeckillActiveRepository::class)->search($where)->column('seckill_active_id');
        if (!empty($activeStatusWhere)) {
            $where['seckill_active_id'] = $activeStatusWhere;
        } else if ((isset($where['active_status']) && $where['active_status'] != '') || (isset($where['active_name']) && $where['active_name'] != '')) {
            return compact('list');
        }
        unset($where['active_status']);
        unset($where['active_name']);
        $query = $this->dao->search($merId, $where)->with([
            'merCateId.category',
            'storeCategory',
            'brand',
            'merchant',
            'seckillActive',
            'attrValue' => function ($query) {
                $query->append(['old_stock'], true);
            }
        ]);
        $list = $query->field('Product.*,U.star,U.rank,U.sys_labels,U.mer_labels')->select()
            ->each(function ($item) {
                if (!$item->sys_labels) {
                    $item->sys_labels = [];
                } else {
                    $item->sys_labels = explode(',', rtrim(ltrim($item->sys_labels, ','), ','));
                }
                if (!$item->mer_labels) {
                    $item->mer_labels = [];
                } else {
                    $item->mer_labels = explode(',', rtrim(ltrim($item->mer_labels, ','), ','));
                }
                $item['old_stock'] = $item['stock'];

                //分类处理
                $merCat = [];
                if (isset($item['merCateId'])) {
                    foreach ($item['merCateId'] as $i) {
                        $merCat[] = $i['mer_cate_id'];
                    }
                }
                $item['mer_cate_id'] = $merCat;

                //规格处理
                foreach ($item['attr'] as $k => $v) {
                    $item['attr'][$k] = [
                        'value' => $v['attr_name'],
                        'detail' => $v['attr_values']
                    ];
                }

                $item['content'] = $item['content']['content'] ?? '';//详情处理

                if (!isset($item['old_product_id']) || !$item['old_product_id']) {
                    $item['old_product_id'] = $item['product_id'];//old_product_id
                }
            })->append(['us_status']);
        return compact('list');
    }


    /**
     * 移动端商品列表
     * @Author:Qinii
     * @Date: 2020/5/28
     * @param array $where
     * @param int $page
     * @param int $limit
     * @param $userInfo
     * @return array
     */
    public function getApiSearch($merId, array $where, int $page, int $limit, $userInfo)
    {
        $where = array_merge($where, $this->dao->productShow());
        //搜索记录
        if (isset($where['keyword']) && !empty($where['keyword']))
            app()->make(UserVisitRepository::class)->searchProduct(
                $userInfo ? $userInfo['uid'] : 0,
                $where['keyword'],
                (int)($where['mer_id'] ?? 0)
            );
        $query = $this->dao->search($merId, $where)->with(['merchant', 'issetCoupon']);
        $count = $query->count();
        $list = $query->page($page, $limit)->setOption('field', [])->field($this->admin_filed)->select()->each(function ($item) {
            foreach ($item['activity_labels'] as $key => $item) {
                if (!$item['is_show']) unset($item['activity_labels'][$key]);
            }
            $item['activity_labels'] = array_values($item['activity_labels']);
        });
        $append[] = 'max_extension';
        if (get_extension_info($userInfo)['isPromoter']) $list->append($append);
        return compact('count', 'list');
    }

    /**
     * 获取秒杀商品列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * FerryZhao 2024/4/23
     */
    public function getApiSeckill(array $where, int $page, int $limit)
    {
        $field = 'Product.product_id,Product.active_id,Product.sales,Product.mer_id,is_new,U.keyword,brand_id,U.image,U.product_type,U.store_name,U.sort,U.rank,star,rate,reply_count,U.price,cost,Product.ot_price,stock,extension_type,care_count,unit_name,U.create_time,Product.activity_label_ids,Product.delivery_free,Product.give_coupon_ids,Product.delivery_way,Product.product_type,Product.type';
        $count = 0;
        $list = [];

        $storeOrderRepository = app()->make(StoreOrderRepository::class);
        $storeSeckillActiveRepository = app()->make(StoreSeckillActiveRepository::class);
        $storeSeckillTimeRepository = app()->make(StoreSeckillTimeRepository::class);
        $now = time();
        $currentHour = date('H', $now);
        if ((isset($where['start_time']) && $where['start_time']) || (isset($where['end_time']) && $where['end_time'])) {
            $where['status'] = 1;
            $timeInfo = $storeSeckillTimeRepository->search($where)->find();//获取时间段
            if (!$timeInfo) return ['count' => 0, 'list' => []];
            if ($timeInfo['end_time'] <= $currentHour) {
                $seckillStatus = -1;
            } else if ($timeInfo['start_time'] <= $currentHour) {
                $seckillStatus = 1;
            } else {
                $seckillStatus = 0;
            }
        } else {
            $where['start_time'] = $currentHour;
            $where['end_time'] = $currentHour;
            $where['status'] = 1;
            $timeInfo = $storeSeckillTimeRepository->search($where)->find();//获取时间段
            if (!$timeInfo) return ['count' => 0, 'list' => []];
            $seckillStatus = 1;
        }

        $activeIds = $storeSeckillActiveRepository->getSearch([])
            ->where(['active_status' => 1, 'status' => 1])
            ->where('end_day', '>', date('Y-m-d H:i:s', $now))
            ->whereFindInSet("seckill_time_ids", $timeInfo['seckill_time_id'])
            ->column('seckill_active_id');//获取搜索符合条件的活动
        if (empty($activeIds)) {
            return compact('count', 'list');
        }

        $seckillWhere = ['active_id' => $activeIds, 'star' => ''];
        if(isset($where['mer_id']) && !empty($where['mer_id'])){
            $seckillWhere['mer_id'] = $where['mer_id'];
        }
        $query = $this->dao->seckillSearch($seckillWhere)->with(['seckillActive','merchant','reservation']);
        $count = $query->count();
        $list = $query->page($page, $limit)->setOption('field', [])->field($field)->select()
            ->each(function ($item) use ($storeOrderRepository, $timeInfo, $seckillStatus) {
                $item['stock'] = $this->getSeckillAttrValue($item['attrValue'], $item['old_product_id'])['stock'];
                $item['sales'] = $storeOrderRepository->seckillOrderCounut($item['active_id'], $item['product_id']);
                $item['stop'] = strtotime(date('Y-m-d', time()) . ' ' . ($timeInfo['end_time'] . ':00:00'));
                $item['skill_status'] = $this->getApiSeckillStatusByActive($item['seckillActive'], $seckillStatus);
            });

        $list = getThumbWaterImage($list, ['image'], 'mid');
        return compact('count', 'list');
    }

    /**
     * 根据活动日期修正秒杀状态
     * @param $seckillActive
     * @param int $timeStatus
     * @return int
     */
    protected function getApiSeckillStatusByActive($seckillActive, int $timeStatus): int
    {
        if (!$seckillActive || $seckillActive['active_status'] === -1) return -1;

        $now = time();
        $endDay = strtotime($seckillActive->getData('end_day'));
        if ($endDay <= $now) return -1;

        $startDay = strtotime($seckillActive['start_day']);
        if ($startDay > $now) return 0;

        return $timeStatus;
    }

    /**
     * 平台礼包列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     * @day 2020-06-01
     */
    public function getBagList(array $where, int $page, int $limit)
    {
        $query = $this->dao->search(null, $where)->with(['merCateId.category', 'storeCategory', 'brand', 'merchant' => function ($query) {
            $query->field('mer_id,mer_avatar,mer_name,product_score,service_score,postage_score,status,care_count,is_trader');
        }]);
        $count = $query->count($this->dao->getPk());
        $list = $query->page($page, $limit)->setOption('field', [])->field($this->filed)->select();

        return compact('count', 'list');
    }
    /**
     * 获取代客下单商品列表
     *
     * @param integer|null $merId
     * @param array $where
     * @param integer $page
     * @param integer $limit
     * @return void
     */
    public function getBehalfProductList(?int $merId, array $where, int $page, int $limit)
    {
        $where['Product.type'] = 0;
        $where['Product.is_used'] = 1;
        $where['Product.status'] = $where['status'];
        $where['Product.is_show'] = $where['is_show'];
        $where['Product.product_type'] = $where['product_type'];
        $where['is_gift_bag'] = 0;
        $search = $where['search'] ?? null;
        unset($where['search']);
        unset($where['status']);
        unset($where['is_show']);
        unset($where['product_type']);

        $query = $this->dao->search($merId, $where, $search);
        $count = $query->count();
        $list = $query->page($page, $limit)->setOption('field', [])->field($this->admin_filed)->select()->append(['us_status']);

        return compact('count', 'list');
    }

    public function getBehalfCustomerOrderDetail(int $merId, int $id)
    {
        $where = [
            'is_show' => 1,
            'status' => 1,
            'is_used' => 1,
            'mer_status' => 1,
            'mer_id' => $merId,
            'product_id' => $id
        ];

        return $this->apiProductDetail($where, 0, null);
    }

    /**
     * 根据搜索条件返回对应的品牌ID
     * @Author:Qinii
     * @Date: 2020/5/28
     * @param array $where
     * @return mixed
     */
    public function getBrandByCategory(array $where)
    {
        $mer_id = $where['mer_id'] ? $where['mer_id'] : null;
        unset($where['mer_id']);
        $query = $this->dao->search($mer_id, $where);
        return $query->group('brand_id')->column('brand_id');
    }

    /**
     * 根据搜索条件获取分类ID
     * @param array $where
     * @return mixed
     * @author Qinii
     */
    public function getCateIdByCategory(array $where)
    {
        $mer_id = $where['mer_id'] ? $where['mer_id'] : null;
        unset($where['mer_id']);
        $query = $this->dao->search($mer_id, $where);
        return $query->column('param_temp_id');
    }

    /**
     * api 获取商品详情
     * @Author:Qinii
     * @Date: 2020/5/30
     * @param $id
     * @param $userInfo
     */
    public function detail(int $id, $userInfo)
    {
        $where = [
            'is_show' => 1,
            'status' => 1,
            'is_used' => 1,
            'mer_status' => 1,
            'product_id' => $id
        ];
        return $this->apiProductDetail($where, 0, null, $userInfo);
    }


    /**
     * api秒杀商品详情
     * @param $id
     * @author Qinii
     * @day 2020-08-05
     */
    public function seckillDetail($id, $userInfo, $seckillTimeId = null)
    {
        $where = $this->dao->seckillShow();
        $where['product_id'] = $id;
        $data = $this->apiProductDetail($where, 1, null, $userInfo, $seckillTimeId);
        if ($data) $this->seckillStockCache($data);
        return $data;
    }

    /**
     * 移动端商品详情
     * @param $productId
     * @param $cache_unique
     * @return array|mixed
     * @author Qinii
     * @day 2023/8/23
     */
    public function getContent($productId)
    {
        $key = '_get_content' . $productId;
        $res = Cache::get($key);
        if ($res) return json_decode($res, true);
        $res = app()->make(ProductContentRepository::class)->getSearch(['product_id' => $productId])->order('product_id desc')->select()->toArray();
        $res = array_pop($res);
        $res = $res ?: [
            'product_id' => $productId,
            'content' => '',
            'type' => 0
        ];
        if ($res && $res['content'] && $res['type'] == 1) {
            $res['content'] = json_decode($res['content']);
        }
        Cache::set($key, json_encode($res), 1500);
        return $res;
    }


    /**
     * 移动端商品详情查询店铺信息
     * @param $merId
     * @param $good_ids
     * @param $uid
     * @return array|mixed
     * @author Qinii
     * @day 2023/8/23
     */
    public function getMerchant($merId, $good_ids, $uid)
    {
        $merchantRepository = app()->make(MerchantRepository::class);
        $care = false;
        if ($uid) $care = $merchantRepository->getCareByUser($merId, $uid);
        $merchant = $merchantRepository->search(['mer_id' => $merId])->with(['merchant_type'])
            ->field('mer_id,mer_name,real_name,mer_address,mer_keyword,mer_avatar,mer_banner,mini_banner,product_score,service_score,postage_score,service_phone,care_count,type_id,care_ficti,is_trader')->find()
            ->append(['isset_certificate', 'services_type'])->toArray();
        $merchant['top_banner'] = merchantConfig($merId, 'mer_pc_top');
        $merchant['care'] = $care;
        $merchant['care_count'] = (int)$merchant['care_ficti'] + (int)$merchant['care_count'];
        $merchant['recommend'] = $this->dao->getGoodList($good_ids, $merId);
        $merchant['type_name'] = $merchant['merchant_type']['type_name'];

        return $merchant;
    }

    /**
     * 获取商品店铺推荐
     * @param int $productId
     * @param int $uid
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getGoodList(int $productId, int $uid)
    {
        // 查询商品详情
        $product = $this->dao->get($productId);
        if (empty($product)) {
            throw new ValidateException('商品异常');
        }
        return $this->getMerchant($product['mer_id'], $product['good_ids'], $uid);
    }

    /**
     *  移动端商品详情
     * @param $where
     * @param int $productType
     * @param int|null $activityId
     * @param $userInfo
     * @param $seckillTimeId
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author Qinii
     */
    public function apiProductDetail($where, int $productType, ?int $activityId, $userInfo = null, $seckillTimeId = null)
    {
        [$field, $with, $append] = $this->buildApiProductDetailOptions($productType, $userInfo);
        $extension = $this->buildApiProductDetailExtension($productType, $userInfo);

        $product = $this->dao->getWhere($where, $field, $with);
        if (!$product) throw new ValidateException('商品已下架');

        $seckillStatus = 0;
        if ($productType == 1) {
            $seckillStatus = $this->fillApiProductDetailSeckill($product, $where, $seckillTimeId);
        }

        $product['isRelation'] = $this->getApiProductDetailRelation($productType, $activityId, $where, $userInfo);

        if (systemConfig('sys_reply_status')) {
            $product['replayData'] = app()->make(ProductReplyRepository::class)->getReplyRate($product['product_id']);
        }

        [$attr, $sku] = $this->buildApiProductDetailSku($product, $userInfo, $productType, $activityId);
        $product = $product->append($append);
        $product['attr'] = $attr;
        $product['sku'] = $sku;
        $product = json_decode(json_encode($product,true),true);
        $product['seckill_status'] = $seckillStatus;
        $product['promoter'] = $extension;
        $product = $this->mergeReservation($product);
        $list = getThumbWaterImage([$product], ['image','slider_image'], 'big');
        return $list[0];
    }

    /**
     * 移动端商品详情查询配置
     * @param int $productType
     * @param $userInfo
     * @return array
     */
    protected function buildApiProductDetailOptions(int $productType, $userInfo = null): array
    {
        $field = 'is_show,product_id,active_id,mer_id,image,slider_image,store_name,store_info,unit_name,price,cost,ot_price,stock,sales,video_link,product_type,extension_type,old_product_id,rate,guarantee_template_id,temp_id,once_max_count,pay_limit,once_min_count,integral_rate,delivery_way,delivery_free,type,cate_id,svip_price_type,svip_price,mer_svip_status,mer_form_id,activity_label_ids,give_coupon_ids';
        $with = [
            'attr',
            'merchant',
            'reservation',
            'attrValue' => function ($query) {
                $query->with(['reservation']);
            },
        ];
        $append = ['topReply','activity_labels'];
        $extension_info = get_extension_info($userInfo);

        switch ($productType) {
            case 1:
                $with['seckillActive'] = function ($query) {
                    $query->field('seckill_active_id,start_day,end_day,start_time,end_time,once_pay_count,all_pay_count,active_status,seckill_time_ids');
                };
                break;
            case 2:
                break;
            case 3:
                //notbreak;
            case 4:
                $with[] = 'oldAttrValue';
                break;
            default:
                if ($extension_info['extension_pop']) {
                    $append[] = 'max_extension';
                    $append[] = 'min_extension';
                }
                $append[] = 'max_integral';
                $append[] = 'show_svip_info';
                break;
        }

        return [$field, $with, $append];
    }

    /**
     * 移动端商品详情推广信息
     * @param int $productType
     * @param $userInfo
     * @return array
     */
    protected function buildApiProductDetailExtension(int $productType, $userInfo = null): array
    {
        $extension_info = get_extension_info($userInfo);
        $extension = [
            'is_show' => !$productType && $extension_info['extension_status'] ? 1 : 0,
            'promoter_type' => $extension_info['promoter_type'],
            'isPromoter' => $extension_info['isPromoter'],
            'extension_pop' => $extension_info['extension_pop'],
        ];
        if ($extension_info['promoter_type'] == 1 && !$extension_info['isPromoter']) $extension['is_show'] = 0;
        return $extension;
    }

    /**
     * 填充移动端商品详情秒杀数据
     * @param $product
     * @param array $where
     * @param $seckillTimeId
     * @return int
     */
    protected function fillApiProductDetailSeckill($product, array $where, $seckillTimeId = null): int
    {
        $result = $this->getSeckillAttrValue($product['attrValue'], $product['old_product_id']);
        $product['attrValue'] = $result['item'];
        $product['stock'] = $result['stock'];
        $product['sales'] = app()->make(StoreOrderRepository::class)->seckillOrderCounut($product['active_id'] , $product['product_id']);
        $product['once_pay_count'] = $product['seckillActive']['once_pay_count'] ?? 0;
        $product['all_pay_count'] = $product['seckillActive']['all_pay_count'] ?? 0;

        $seckillStatus = 0;
        $now = time();
        $currentHour = date('G', $now);
        $storeSeckillTimeRepository = app()->make(StoreSeckillTimeRepository::class);
        $endTime = date('Y-m-d', $now);
        unset($product['seckill_status']);

        $activeEndDay = isset($product['seckillActive']) ? strtotime($product['seckillActive']->getData('end_day')) : 0;
        if ($activeEndDay && $activeEndDay <= $now) {
            $seckillStatus = -1;
        } else if ($seckillTimeId) {
            $timeInfo = $storeSeckillTimeRepository->getSearch([])->where(['seckill_time_id' => $seckillTimeId])->find();
            if (!empty($timeInfo)) {
                [$seckillStatus, $endTime] = $this->parseApiProductDetailSeckillTime($timeInfo, $currentHour, $endTime, $seckillStatus);
            }
        } else {
            $timeList = $storeSeckillTimeRepository->getSearch([])->whereIn('seckill_time_id', $product['seckillActive']['seckill_time_ids'] ?? 0)->select()->toArray();
            foreach ($timeList as $item) {
                [$seckillStatus, $endTime, $matched] = $this->parseApiProductDetailSeckillTime($item, $currentHour, $endTime, $seckillStatus, true);
                if ($matched) break;
            }
        }

        $product['stop'] = strtotime($endTime);
        $product['quota'] = $this->seckillStock($where['product_id']);
        unset($product['seckillActive']);
        return $seckillStatus;
    }

    /**
     * 解析秒杀时间段状态
     * @param $timeInfo
     * @param int $currentHour
     * @param string $endTime
     * @param int $seckillStatus
     * @param bool $withMatched
     * @return array
     */
    protected function parseApiProductDetailSeckillTime($timeInfo, int $currentHour, string $endTime, int $seckillStatus, bool $withMatched = false): array
    {
        $matched = false;
        if ($timeInfo['start_time'] <= $currentHour && $currentHour < $timeInfo['end_time']) {
            $seckillStatus = 1;
            $endTime = $endTime . ' ' . $timeInfo['end_time'] . ':00:00';
            $matched = true;
        } else if ($timeInfo['end_time'] <= $currentHour) {
            $seckillStatus = -1;
        }
        return $withMatched ? [$seckillStatus, $endTime, $matched] : [$seckillStatus, $endTime];
    }

    /**
     * 移动端商品详情用户关联状态
     * @param int $productType
     * @param int|null $activityId
     * @param array $where
     * @param $userInfo
     * @return bool
     */
    protected function getApiProductDetailRelation(int $productType, ?int $activityId, array $where, $userInfo = null): bool
    {
        if (!$userInfo) return false;
        return app()->make(UserRelationRepository::class)->getUserRelation([
            'type_id' => $activityId ?? $where['product_id'],
            'type' => $productType
        ],
            $userInfo['uid']
        );
    }

    /**
     * 移动端商品详情规格数据
     * @param $product
     * @param $userInfo
     * @param int $productType
     * @param int|null $activityId
     * @return array
     */
    protected function buildApiProductDetailSku($product, $userInfo, int $productType, ?int $activityId): array
    {
        $attr = $this->detailAttr($product['attr']);
        $attrValue = (in_array($product['product_type'], [3, 4])) ? $product['oldAttrValue'] : $product['attrValue'];
        $sku = $this->detailAttrValue($attrValue, $userInfo, $productType, $activityId);
        $sku = getThumbWaterImage($sku, ['image'], 'small');
        unset($product['attr'], $product['attrValue'], $product['oldAttrValue']);
        if (count($attr) > 1) {
            $firstSku = [];
            foreach ($attr as $item) {
                $firstSku[] = $item['attr_values'][0];
            }
            $firstSkuKey = implode(',', $firstSku);
            if (isset($sku[$firstSkuKey])) {
                $sku = array_merge([$firstSkuKey => $sku[$firstSkuKey]], $sku);
            }
        }
        return [$attr, $sku];
    }

    /**
     * 商品详情部分缓存
     * @param $productId
     * @param $product
     * @param $activityId
     * @param $uid
     * @return mixed
     * @author Qinii
     * @day 2023/8/24
     */
    public function getProductShow($productId, $product, $activityId, $uid)
    {
        if (empty($product)) {
            $productData = $this->dao->get($productId);
            if (!$productData) throw new ValidateException('数据不存在');
            $product = $productData->append(['parameter_params'])->toArray();
        }
        ksort($product);
        $cache_unique = 'get_product_show_' . $productId . '_' . md5(json_encode($product));
        if (!env('APP_DEBUG', false)) {
            $res = Cache::get($cache_unique);
        }

        if (!isset($res) || !$res) {
            $productType = $product['product_type'];
            $res['content'] = $this->getContent($product['product_id']);
            $res['temp'] = app()->make(ShippingTemplateRepository::class)->getSearch([])->where('shipping_template_id', $product['temp_id'])->find();

            $res['params'] = $product['parameter_params'] ?? [];
            $guaranteeTemplateRepository = app()->make(GuaranteeTemplateRepository::class);
            $guaranteeTemplate = $guaranteeTemplateRepository->getSearch([])->where('guarantee_template_id', $product['guarantee_template_id'])->where('status', 1)->where('is_del', 0)->find();
            if ($guaranteeTemplate) {
                $guaranteeValueRepository = app()->make(GuaranteeValueRepository::class);
                $guaranteeRepository = app()->make(GuaranteeRepository::class);
                $guarantee_id = $guaranteeValueRepository->getSearch([])->where('guarantee_template_id', $guaranteeTemplate['guarantee_template_id'])->column('guarantee_id');
                $res['guarantee'] = $guaranteeRepository->getSearch([])->where('guarantee_id', 'in', $guarantee_id)->where('status', 1)->where('is_del', 0)->select()->toArray();
            }
            $spu = app()->make(SpuRepository::class)->getSpuData($activityId ?: $product['product_id'], $productType, 0);
            $res['spu_id'] = $spu['spu_id'];
            if (systemConfig('community_status')) {
                $res['community'] = app()->make(CommunityRepository::class)->getDataBySpu($spu['spu_id']);
            }
            //热卖排行
            if (systemConfig('hot_ranking_switch') && $res['spu_id']) {
                $hot = $this->getHotRanking($res['spu_id'], $product['cate_id']);
                $res['top_name'] = $hot['top_name'] ?? '';
                $res['top_num'] = $hot['top_num'] ?? 0;
                $res['top_pid'] = $hot['top_pid'] ?? 0;
            }
            //活动氛围图
            if (in_array($product['product_type'], [0, 2, 4])) {
                $storeActivityRepository = app()->make(StoreActivityRepository::class);
                $list = $storeActivityRepository->getPic([$spu], StoreActivityRepository::ACTIVITY_TYPE_ATMOSPHERE, 'atmosphere_pic');
                $res['atmosphere_pic'] = $list[0]['atmosphere_pic'] ?? "";
            }
            Cache::tag('get_product')->set($cache_unique, json_encode($res), 60);
        } else {
            $res = json_decode($res, true);
        }
        $res['merchant'] = $this->getMerchant($product['mer_id'], $product['good_ids'] ?? [], $uid);
        return $res;
    }

    /**
     * 热卖排行
     * @param int $spuId
     * @param int $cateId
     * @return array
     * @author Qinii
     */
    public function getHotRanking(int $spuId, int $cateId)
    {
        $cache_unique = md5('get_hot_ranking_' . json_encode([$spuId, $cateId]));
        $res = Cache::get($cache_unique);
        if ($res) return json_decode($res, true);
        $data = [];
        //热卖排行
        $lv = systemConfig('hot_ranking_lv') ?: 0;
        $categoryMake = app()->make(StoreCategoryRepository::class);
        $cate = $categoryMake->getWhere(['store_category_id' => $cateId]);
        if ($lv != 2 && $cate) $cateId = $lv == 1 ? $cate->pathIds[2] : $cate->pathIds[1];
        $RedisCacheService = app()->make(RedisCacheService::class);
        $prefix = env('QUEUE_NAME', 'merchant') . '_hot_ranking_';
        $key = ($prefix . 'top_item_' . $cateId . '_' . $spuId);
        $k1 = $RedisCacheService->keys($key);
        if ($k1) {
            $top = $RedisCacheService->handler()->get($key);
            $top = json_decode($top);
            $data['top_name'] = $top[0];
            $data['top_num'] = $top[1];
            $data['top_pid'] = $cateId;
        }
        Cache::set($cache_unique, json_encode($data), 1500);
        return $data;
    }

    /**
     * 商户下的推荐
     * @param $productId
     * @param $merId
     * @return array
     * @author Qinii
     * @day 12/7/21
     */
    public function getRecommend($productId, $merId)
    {
        $cache_unique = 'get_product_recommend_' . $productId . '_' . $merId;
        $res = Cache::get($cache_unique);
        if ($res) return json_decode($res, true);

        $field = 'mer_id,product_id,store_name,image,price,is_show,status,is_gift_bag,is_good,sales,create_time';
        $make = app()->make(ProductCateRepository::class);
        $product_id = [];
        if ($productId) {
            $catId = $make->getSearch(['product_id' => $productId])->column('mer_cate_id');
            $product_id = $make->getSearch([])->whereIn('mer_cate_id', $catId)->column('product_id');
        }
        $res = $this->dao->getSearch([])
            ->where($this->dao->productShow())
            ->when($productId, function ($query) use ($productId) {
                $query->where('product_id', '<>', $productId);
            })
            ->when($product_id, function ($query) use ($product_id) {
                $query->whereIn('product_id', $product_id);
            })
            ->where('mer_id', $merId)->setOption('field', [])->field($field)->limit(3)->select();
        $data = $res ? $res->toArray() : [];
        $count = count($res);
        if ($count < 3) {
            $productIds[] = $productId;
            $res = $this->dao->getSearch([])
                ->where($this->dao->productShow())
                ->whereNotIn('product_id', $productIds)
                ->where('is_good', 1)
                ->where('mer_id', $merId)
                ->field('mer_id,product_id,store_name,image,price,is_show,status,is_gift_bag,is_good,sales,sort,create_time')
                ->order('sort DESC,create_time DESC')
                ->limit((3 - $count))
                ->select()->toArray();
            $data = array_merge($data, $res);
        }
        Cache::tag('get_product')->set($cache_unique, json_encode($data), 1500);
        return $data;
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
            foreach ($values as $value) {
                $arr[] = ['attr' => $value, 'check' => false];
            }
            $attr[$key]['product_id'] = $item['product_id'];
            $attr[$key]['attr_name'] = $item['attr_name'];
            $attr[$key]['attr_value'] = $arr;
            $attr[$key]['attr_values'] = $values;
        }
        return $attr;
    }

    /**
     * 获取秒杀商品的库存数
     * @param array $data
     * @param int $oldProductId
     * @return array
     * @author Qinii
     * @day 2020-11-12
     */
    public function getSeckillAttrValue($skuList, $oldProductId)
    {
        /**
         *  秒杀商品限购数量
         *  原商品库存 > 限购数
         *      销量 = 订单总数 - 退款退货 - （未发货且仅退款）
         *      限购数 = 限购数 - 销量
         *  原商品库存 < 限购数
         *      限购数 = 原商品库存
         */
        $productAttrValueRepository = app()->make(ProductAttrValueRepository::class);
        $storeOrderProductRepository = app()->make(StoreOrderProductRepository::class);
        $orderCount = $storeOrderProductRepository->getSearch([])
            ->where('product_sku', 'in', array_column($skuList->toArray(), 'unique'))
            ->where('is_refund', 'in', '0,1,2')
            ->whereDay('create_time')
            ->column('refund_num', 'product_sku');
        $stock = 0;
        $item = [];

        $oldAttrValue = $productAttrValueRepository->search(['product_id' => $oldProductId])->column('stock', 'sku');
        foreach ($skuList as $value) {
            $_stock = 0;
            if (isset($oldAttrValue[$value['sku']]) && $oldAttrValue[$value['sku']]) {
                $_stock = ($value['stock'] < $oldAttrValue[$value['sku']]) ? $value['stock'] : $oldAttrValue[$value['sku']];
                if ($_stock > 0 && ($value['stock'] < $oldAttrValue[$value['sku']])) {
                    $_stock = $_stock - ($orderCount[$value['unique']] ?? 0);
                }
                $value['stock'] = max(0,$_stock);
            } else {
                $value['stock'] = 0;
            }
            $stock += $_stock;
            $item[] = $value;
        }
        return compact('stock','item');
    }

    /**
     * 单商品sku
     * @param $data
     * @param $userInfo
     * @return array
     * @author Qinii
     * @day 2020-08-05
     */
    public function detailAttrValue($data, $userInfo, $productType = 0, $artiveId = null, $svipInfo = [])
    {
        $sku = [];
        $make_presll = app()->make(ProductPresellSkuRepository::class);
        $make_assist = app()->make(ProductAssistSkuRepository::class);
        $make_group = app()->make(ProductGroupSkuRepository::class);
        foreach ($data as $value) {
            $_value = [
                'value_id' => $value['value_id'],
                'sku' => $value['sku'],
                'price' => $value['price'],
                'stock' => $value['is_show'] ? $value['stock'] : 0,
                'image' => $value['image'],
                'weight' => $value['weight'],
                'volume' => $value['volume'],
                'sales' => $value['sales'],
                'unique' => $value['unique'],
                'bar_code' => $value['bar_code'],
                'is_show' => $value['is_show'],
                'is_default_select' => $value['is_default_select'] ?? 0
            ];
            if ($productType == 0) {
                $_value['ot_price'] = $value['ot_price'];
                $_value['svip_price'] = $value['svip_price'];
            }
            if ($productType == 2) {
                $_sku = $make_presll->getSearch(['product_presell_id' => $artiveId, 'unique' => $value['unique']])->find();
                if (!$_sku) continue;
                $_value['price'] = $_sku['presell_price'];
                $_value['stock'] = $_sku['stock'];
                $_value['down_price'] = $_sku['down_price'];
            }
            //助力
            if ($productType == 3) {
                $_sku = $make_assist->getSearch(['product_assist_id' => $artiveId, 'unique' => $value['unique']])->find();
                if (!$_sku) continue;
                $_value['price'] = $_sku['assist_price'];
                $_value['stock'] = $_sku['stock'];
            }
            //拼团
            if ($productType == 4) {
                $_sku = $make_group->getSearch(['product_group_id' => $artiveId, 'unique' => $value['unique']])->find();
                if (!$_sku) continue;
                $_value['price'] = $_sku['active_price'];
                $_value['stock'] = $_sku['stock'];
            }
            //推广员
            if (get_extension_info($userInfo)['isPromoter'] && isset($value['bc_extension_one']) && isset($value['bc_extension_two'])) {
                $_value['extension_one'] = $value['bc_extension_one'];
                $_value['extension_two'] = $value['bc_extension_two'];
            }
            $sku[$value['sku']] = $_value;
        }
        return $sku;
    }


    /**
     * 秒杀商品库存检测
     * @param int $productId
     * @return bool|int
     * @author Qinii
     * @day 2020-08-05
     */
    public function seckillStock(int $productId)
    {
        $product = $this->dao->getWhere(['product_id' => $productId], '*', ['attrValue']);
        $count = app()->make(StoreOrderRepository::class)->seckillOrderCounut($product['active_id'], $product['product_id']);
        if ($product['stock'] > $count) {
            $make = app()->make(ProductAttrValueRepository::class);
            foreach ($product['attrValue'] as $item) {
                $attr = [
                    ['sku', '=', $item['sku']],
                    ['product_id', '=', $product['old_product_id']],
                    ['stock', '>', 0],
                ];
                if ($make->getWhereCount($attr)) return true;
            }
        }
        return false;
    }


    /**
     *  为你推荐
     * @Author:Qinii
     * @Date: 2020/5/30
     * @param $userInfo
     * @param int|null $merId
     * @param $page
     * @param $limit
     * @return array
     */
    public function recommend($userInfo, ?int $merId, $page, $limit)
    {
        $where = ['order' => 'sales'];
        if (!is_null($userInfo)) {
            $cate_ids = app()->make(UserVisitRepository::class)->getRecommend($userInfo['uid']);
            if ($cate_ids) $where = ['cate_ids' => $cate_ids];
        }
        $where = array_merge($where, $this->switchType(1, $merId, 0), $this->dao->productShow());
        $query = $this->dao->search($merId, $where);
        $count = $query->count();
        $list = $query->page($page, $limit)->setOption('field', [])->with(['issetCoupon', 'merchant'])->select();

        return compact('count', 'list');
    }

    /**
     * 检测是否有效
     * @Author:Qinii
     * @Date: 2020/6/1
     * @param $id
     * @return mixed
     */
    public function getOne($id)
    {
        $data = ($this->dao->getWhere([$this->dao->getPk() => $id]));
        if (!is_null($data) && $data->check()) return $data;
        return false;
    }

    /**
     * 上下架 / 显示
     * @param $id
     * @param $status
     * @author Qinii
     * @day 2022/11/12
     */
    public function switchShow($id, $status, $field, $merId = 0, $admin_info = [])
    {
        $where['product_id'] = $id;
        if ($merId) $where['mer_id'] = $merId;
        $product = $this->dao->getWhere($where);
        if (!$product) throw new ValidateException('数据不存在');
        if ($status == 1 && $product['product_type'] == 2)
            throw new ValidateException('商品正在参与预售活动');
        if ($status == 1 && $product['product_type'] == 3)
            throw new ValidateException('商品正在参与助力活动');
        $this->dao->update($id, [$field => $status]);

        app()->make(SpuRepository::class)->changeStatus($id, $product->product_type);

        //记录操作日志
        if (!empty($admin_info)) {
            $this->addChangeStatusLog($field, $status, $admin_info, $product);
        }
    }

    /**
     *  批量设置上下架 / 显示隐藏
     * @param $id
     * @param $status
     * @param $field
     * @param $merId
     * @param $admin_info
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author Qinii
     */
    public function batchSwitchShow($id, $status, $field, $merId = 0, $admin_info = [])
    {
        $where['product_id'] = $id;
        if ($merId) $where['mer_id'] = $merId;
        $products = $this->dao->getSearch([])->where('product_id', 'in', $id)->select();
        if ($products->isEmpty())
            throw new ValidateException('商品不存在或已删除，请检查');
        foreach ($products as $product) {
            $product_type = $product['product_type'];
            if ($merId && $product['mer_id'] !== $merId)
                throw new ValidateException('商品不属于您');
            if ($status == 1 && $product['product_type'] == 2)
                throw new ValidateException('ID：' . $product->product_id . ' 商品正在参与预售活动');
            if ($status == 1 && $product['product_type'] == 3)
                throw new ValidateException('ID：' . $product->product_id . ' 商品正在参与助力活动');
        }
        $this->dao->updates($id, [$field => $status]);
        $operate_data = [];
        if (!empty($admin_info)) {
            $operate_data = [
                'field' => $field,
                'status' => $status,
                'admin_info' => $admin_info,
            ];
        }
        Queue::push(ChangeSpuStatusJob::class, ['id' => $id, 'product_type' => $product_type, 'operate_data' => $operate_data]);
    }

    /**
     * 商品审核
     * @param $id
     * @param $data
     * @author Qinii
     * @day 2022/11/14
     */
    public function switchStatus($id, $data, $admin_info = [])
    {
        $product = $this->getSearch([])->find($id);
        if (!$product) {
            throw new ValidateException('商品不存在');
        }
        $this->dao->update($id, $data);
        $status = $data['status'];
        $type = self::NOTIC_MSG[$data['status']][$product['product_type']];
        $message = '您有1个' . ($product['product_type'] ? '秒杀商品' : '商品') . self::NOTIC_MSG[$data['status']]['msg'];
        SwooleTaskService::merchant('notice', [
            'type' => $type,
            'data' => [
                'title' => $status == -2 ? '下架提醒' : '审核结果',
                'message' => $message,
                'id' => $product['product_id']
            ]
        ], $product['mer_id']);
        app()->make(SpuRepository::class)->changeStatus($id, $product->product_type);

        //记录操作日志
        if (!empty($admin_info)) {
            $this->addChangeStatusLog('status', $status, $admin_info, $product);
        }

    }

    /**
     * 审核操作
     * @param array $id
     * @param array $data
     * @param $product_type
     * @author Qinii
     * @day 2022/9/6
     */
    public function batchSwitchStatus(array $id, array $data, $admin_info = [])
    {
        $productData = $this->dao->getSearch([])->whereIn('product_id', $id)->select()->toArray();
        if (!$productData) {
            throw new ValidateException('商品不存在或已删除，请检查');
        }
        foreach ($productData as $product) {
            $product_type = $product['product_type'];
            $type = self::NOTIC_MSG[$data['status']][$product['product_type']];
            $message = '您有1个' . ($product['product_type'] ? '秒杀商品' : '商品') . self::NOTIC_MSG[$data['status']]['msg'];
            SwooleTaskService::merchant('notice', [
                'type' => $type,
                'data' => [
                    'title' => $data['status'] == -2 ? '下架提醒' : '审核结果',
                    'message' => $message,
                    'id' => $product['product_id']
                ]
            ], $product['mer_id']);
        }
        $this->dao->updates($id, $data);
        $operate_data = [];
        if (!empty($admin_info)) {
            $operate_data = [
                'field' => 'status',
                'status' => $data['status'],
                'admin_info' => $admin_info,
            ];
        }
        Queue(ChangeSpuStatusJob::class, ['id' => $id, 'product_type' => $product_type, 'operate_data' => $operate_data]);
        event('product.status', compact('id', 'data'));
    }

    /**
     * 生成分享二维码
     * @param int $productId
     * @param int $productType
     * @param $user
     * @return bool|mixed|string
     * @author Qinii
     */
    public function wxQrCode(int $productId, int $productType, $user = null)
    {
        if ($user) {
            $key = 'pwx' . $productId . $productType . $user->uid . $user['is_promoter'] . date('Ymd');
            $params = '?id=' . $productId . '&spid=' . $user['uid'];
        } else {
            $key = 'pwx' . $productId . $productType . date('Ymd');
            $params = '?id=' . $productId;
        }

        $name = md5($key) . '.jpg';
        $make = app()->make(QrcodeService::class);
        $link = '';
        switch ($productType) {
            case 0: //普通商品
                $link = '/pages/goods_details/index';
                break;
            case 1: //秒杀商品
                $link = '/pages/activity/goods_seckill_details/index';
                break;
            case 2: //预售商品
                $link = '/pages/activity/presell_details/index';
                break;
            case 3: //助力商品
                $link = 'pages/activity/assist_detail/index';
                break;
            case 4: //拼团商品
                $link = '/pages/activity/combination_details/index';
                break;
            case 40: //拼团商品2
                $link = '/pages/activity/combination_status/index';
                break;
            default:
                return false;
        }
        $link = $link . $params;
        $key = 'p' . $productType . '_' . $productId . '_' . ($user['uid'] ?? 0);
        return $make->getWechatQrcodePath($name, $link, false, $key);
    }

    /**
     * 生成常规商品类型二维码
     *
     * 该方法用于根据商品ID和商品类型生成不同场景下的二维码，可以是普通商品、秒杀商品、预售商品、拼团商品等。
     * 可以选择为二维码关联特定的推广用户，以便于跟踪和统计二维码的扫描情况。
     *
     * @param int $productId 商品ID，用于标识具体商品。
     * @param int $productType 商品类型，不同类型的商品需要生成不同链接的二维码，具体类型包括普通商品、秒杀商品、预售商品、拼团商品等。
     * @param User|null $user 可选参数，推广用户对象。如果提供了该参数，二维码将关联到该推广用户，用于统计推广效果。
     * @return string 返回生成的二维码路径。
     */
    public function routineQrCode(int $productId, int $productType, $user = null)
    {
        if ($user) {
            $key = 'sprt' . $productId . $productType . $user->uid . $user['is_promoter'] . date('Ymd');
            $params = 'id=' . $productId . '&spid=' . $user['uid'];
        } else {
            $key = 'sprt' . $productId . $productType . date('Ymd');
            $params = 'id=' . $productId;
        }

        //小程序
        $name = md5($key) . '.jpg';
        $make = app()->make(QrcodeService::class);
        $params = $params;
        $link = '';
        switch ($productType) {
            case 0: //普通商品
                $link = 'pages/goods_details/index';
                break;
            case 1: //秒杀商品
                $link = 'pages/activity/goods_seckill_details/index';
                break;
            case 2: //预售商品
                $link = 'pages/activity/presell_details/index';
                break;
            case 4: //拼团商品
                $link = 'pages/activity/combination_details/index';
                break;
            case 40: //拼团商品2
                $link = 'pages/activity/combination_status/index';
                break;
        }

        return $make->getRoutineQrcodePath($name, $link, $params);
    }

    /**
     * 礼包是否超过数量限制
     * @param $merId
     * @return bool
     * @author Qinii
     * @day 2020-06-25
     */
    public function checkMerchantBagNumber($merId)
    {
        $where = ['is_gift_bag' => 1];
        $promoter_bag_number = systemConfig('max_bag_number');
        $count = $this->dao->search($merId, $where)->count();
        if (is_null($promoter_bag_number) || ($promoter_bag_number > $count)) return true;
        return false;
    }

    /**
     *  更新商品的库存和销量
     * @param $order
     * @param $cart
     * @param $productNum
     * @return void
     * @author Qinii
     */
    public function orderProductIncStock($order, $cart, $productNum = null)
    {
        $productNum = $productNum ?? $cart['product_num'];
        Db::transaction(function () use ($order, $cart, $productNum) {
            $productAttrValueRepository = app()->make(ProductAttrValueRepository::class);
            if ($cart['product_type'] == '1') {
                $oldId = $cart['cart_info']['product']['old_product_id'];
                $productAttrValueRepository->incSkuStock($oldId, $cart['cart_info']['productAttr']['sku'], $productNum);
                $this->dao->incStock($oldId, $productNum);
//                $this->dao->descSales($oldId, $productNum);
            } else if ($cart['product_type'] == '2') {
                $presellSku = app()->make(ProductPresellSkuRepository::class);
                $presellSku->incStock($cart['cart_info']['productPresellAttr']['product_presell_id'], $cart['cart_info']['productPresellAttr']['unique'], $productNum);
                $productAttrValueRepository->incStock($cart['product_id'], $cart['cart_info']['productAttr']['unique'], $productNum);
                $this->dao->incStock($cart['product_id'], $productNum);
            } else if ($cart['product_type'] == '3') {
                app()->make(ProductAssistSkuRepository::class)->incStock($cart['cart_info']['productAssistAttr']['product_assist_id'], $cart['cart_info']['productAssistAttr']['unique'], $productNum);
                $productAttrValueRepository->incStock($cart['cart_info']['product']['old_product_id'], $cart['cart_info']['productAttr']['unique'], $productNum);
                $this->dao->incStock($cart['cart_info']['product']['old_product_id'], $productNum);
            } else if ($cart['product_type'] == '4') {
                app()->make(ProductGroupSkuRepository::class)->incStock($cart['cart_info']['activeSku']['product_group_id'], $cart['cart_info']['activeSku']['unique'], $productNum);
                $this->dao->incStock($cart['cart_info']['product']['old_product_id'], $productNum);
                $productAttrValueRepository->incStock($cart['cart_info']['product']['old_product_id'], $cart['cart_info']['productAttr']['unique'], $productNum);
            } else {
                if (isset($cart['cart_info']['product']['old_product_id']) && $cart['cart_info']['product']['old_product_id'] > 0) {
                    $oldId = $cart['cart_info']['product']['old_product_id'];
                    $productAttrValueRepository->incSkuStock($oldId, $cart['cart_info']['productAttr']['sku'], $productNum);
                    $this->dao->incStock($oldId, $productNum);
                } else {
                    $productAttrValueRepository->incStock($cart['product_id'], $cart['cart_info']['productAttr']['unique'], $productNum);
                    $this->dao->incStock($cart['product_id'], $productNum);
                }
                if ($cart->integral > 0) {
                    $totalIntegral = bcmul($productNum, $cart->integral, 0);
                    $this->dao->descIntegral($cart->product_id, $totalIntegral, bcmul(bcdiv($totalIntegral, $order->integral, 2), $order->integral_price, 2));
                }
            }
        });
    }

    /**
     *  虚拟销量表单
     * @param int $id
     * @return \FormBuilder\Form
     * @throws \FormBuilder\Exception\FormBuilderException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author Qinii
     */
    public function fictiForm(int $id)
    {
        $form = Elm::createForm(Route::buildUrl('systemStoreProductAddFicti', ['id' => $id])->build());
        $res = $this->dao->getWhere(['product_id' => $id], 'ficti,sales');
        $form->setRule([
            Elm::input('number', '现有已售数量：', $res['ficti'])->disabled(true),
            Elm::radio('type', '修改类型：', 1)
                ->setOptions([
                    ['value' => 1, 'label' => '增加'],
                    ['value' => 2, 'label' => '减少'],
                ]),
            Elm::number('ficti', '修改已售数量：', 0),
        ]);
        return $form->setTitle('修改已售数量');
    }

    /**
     * 普通商品加入购物车检测
     * @param int $prodcutId
     * @param string $unique
     * @param int $cartNum
     * @author Qinii
     * @day 2020-10-20
     */
    public function cartCheck(array $data, $userInfo)
    {
        $cart = null;
        $where = $this->dao->productShow();
        $where['product_id'] = $data['product_id'];
        unset($where['is_gift_bag']);
        $product = $this->dao->search(null, $where)->find();
        if (!$product) throw new ValidateException('商品已下架');
        if ($product['once_min_count'] > 0 && $product['once_min_count'] > $data['cart_num'])
            throw new ValidateException('[低于起购数:' . $product['once_min_count'] . ']' . mb_substr($product['store_name'], 0, 10) . '...');
        if ($product['pay_limit'] == 1 ){
            if($product['once_max_count'] < $data['cart_num']) {
                throw new ValidateException('[超出单次限购数：' . $product['once_max_count'] . ']' . mb_substr($product['store_name'], 0, 10) . '...');
            }
            $cart_num = $this->productOnceCountCart($product['product_id'], $data['product_attr_unique'],$userInfo->uid);
            if($cart_num && ($cart_num + $data['cart_num']) > $product['once_max_count']) {
                throw new ValidateException('[加购总数量超出单次限购数：' . $product['once_max_count'] . ']' . mb_substr($product['store_name'], 0, 10) . '...');
            }
        }

        if ($product['pay_limit'] == 2) {
            //如果长期限购
            //已购买数量
            $storeOrderRepository = app()->make(StoreOrderRepository::class);
            $count = $storeOrderRepository->getMaxCountNumber($userInfo->uid, $product['product_id']);
            if (($data['cart_num'] + $count) > $product['once_max_count'])
                throw new ValidateException('[超出限购总数：' . $product['once_max_count'] . ']' . mb_substr($product['store_name'], 0, 10) . '...');
        }
        if ($product['type'] && !$data['is_new']) throw new ValidateException('虚拟商品不可加入购物车');
        if ($product['type'] == self::DEFINE_TYPE_CARD && $data['cart_num'] != 1)
            throw new ValidateException('卡密商品只能单个购买');

        if ($product['type'] == self::DEFINE_TYPE_RESERVATION) {
            //预约商品的话需要检测库存
            $val = app()->make(ProductAttrValueReservationRepository::class)->validateStock($data['product_id'],
                $data['reservation_id'], $data['reservation_date'],$data['cart_num']);
            if ($val == false) throw new ValidateException('该时间段库存不足');
            $sku['stock'] = $val;
        } else  {
            $value_make = app()->make(ProductAttrValueRepository::class);
            $sku = $value_make->getOptionByUnique($data['product_attr_unique']);
            if (!$sku) throw new ValidateException('SKU不存在');
        }
        //分销礼包
        if ($product['is_gift_bag']) {
            $config = systemConfig(['extension_status', 'promoter_type']);
            if (!$config['extension_status']) throw new ValidateException('分销功能未开启');
            if ($config['promoter_type']) throw new ValidateException('后台未开启礼包分销模式');
            if (!$data['is_new']) throw new ValidateException('礼包商品不可加入购物车');
            if ($data['cart_num'] !== 1) throw new ValidateException('礼包商品只能购买一个');
            $extensionInfo = get_extension_info($userInfo);//获取用户是否可以分销以及是否内购
            if ($extensionInfo['isPromoter']) throw new ValidateException('您已经是分销员了');
        }
        //立即购买 限购
        if ($data['is_new']) {
            $cart_num = $data['cart_num'];
        } else {
            //加入购物车
            //购物车现有
            $_num = $this->productOnceCountCart($where['product_id'], $data['product_attr_unique'], $userInfo->uid);
            $cart_num = $_num + $data['cart_num'];
        }
        if ($sku['stock'] < $cart_num) throw new ValidateException('库存不足');
        //添加购物车
        if (!$data['is_new']) {
            $cart = app()->make(StoreCartRepository::class)->getCartByProductSku($data['product_attr_unique'], $userInfo->uid);
        }

        return compact('product', 'sku', 'cart');
    }

    /**
     * 购物车单商品数量
     * @param $productId
     * @param $uid
     * @param $num
     * @author Qinii
     * @day 5/26/21
     */
    public function productOnceCountCart($productId, $product_attr_unique, $uid)
    {
        $make = app()->make(StoreCartRepository::class);
        $where = [
            'is_pay' => 0,
            'is_del' => 0,
            'is_new' => 0,
            'is_fail' => 0,
            'product_type' => 0,
            'product_id' => $productId,
            'uid' => $uid,
            'product_attr_unique' => $product_attr_unique,
        ];
        $cart_num = $make->getSearch($where)->sum('cart_num');
        return $cart_num;
    }

    /**
     * 秒杀商品加入购物车检测
     * @param array $data
     * @param int $userInfo
     * @return array
     * @author Qinii
     * @day 2020-10-21
     */
    public function cartSeckillCheck(array $data, $userInfo)
    {
        if ($data['is_new'] !== 1) throw new ValidateException('秒杀商品不能加入购物车');
//        if ($data['cart_num'] !== 1) throw new ValidateException('秒杀商品只能购买一个');
        $where = $this->dao->seckillShow();
        $where['product_id'] = $data['product_id'];
        $product = $this->dao->search(null, $where)->find();

        if (!$product) throw new ValidateException('商品已下架');
        $storeOrderRepository = app()->make(StoreOrderRepository::class);

        if (!$storeOrderRepository->getDayPayCount((int)$userInfo->uid, $data['product_id'], $data['cart_num']))
            throw new ValidateException('本次活动您购买数量已达到上限');

        if (!$storeOrderRepository->getPayCount((int)$userInfo->uid, $data['product_id'], $data['cart_num']))
            throw new ValidateException('本次活动您该商品购买数量已达到上限');

        if ($product->seckill_status !== 1) throw new ValidateException('该商品不在秒杀时间段内');
        $order_make = app()->make(StoreOrderRepository::class);

        $count = $order_make->seckillOrderCounut($product['active_id'], $data['product_id']);
        $value_make = app()->make(ProductAttrValueRepository::class);

        $sku = $value_make->getOptionByUnique($data['product_attr_unique'], $data['product_id']);
        if ($sku['stock'] <= $count) throw new ValidateException('秒杀商品已售罄');

        $cache_stock = $this->getSeckillStockCache($product, $data['product_attr_unique']);
        if (!$cache_stock['stock'] ||  $cache_stock['stock'] < $data['cart_num'])
            throw new ValidateException('商品是售空');

        $_sku = $value_make->getWhere(['sku' => $sku['sku'], 'product_id' => $product['old_product_id']]);
        if (!$_sku) throw new ValidateException('原商品SKU不存在');
        if ($_sku['stock'] <= 0) throw new ValidateException('原库存不足');
        $cart = null;
        $active_id = $product['active_id'];
        return compact('product', 'sku', 'cart', 'active_id');
    }

    /**
     * 复制一条商品
     * 目前除了预售商品是修改原商品，其他商品是复制
     * @param int $productId
     * @param array $data
     * @return mixed
     * @author Qinii
     * @day 2020-11-19
     */
    public function productCopy(int $productId, array $data, $productType = 0)
    {
        $product = $this->getAdminOneProduct($productId, null);
        if ($data) {
            foreach ($data as $k => $v) {
                $product[$k] = $v;
            }
        }
        return $this->create($product, $productType);
    }

    /**
     *  检查商品是否存在
     * @param int $id
     * @param $productType
     * @return int
     * @author Qinii
     */
    public function existsProduct(int $id, $productType)
    {
        switch ($productType) {
            case 2:
                $make = app()->make(ProductPresellRepository::class);
                break;
            case 3:
                $make = app()->make(ProductAssistSetRepository::class);
                break;
            case 4:
                $make = app()->make(ProductGroupRepository::class);
                break;
            case 40:
                $make = app()->make(ProductGroupBuyingRepository::class);
                break;
            default:
                $make = $this->dao;
                break;
        }
        $where = [
            $make->getPk() => $id,
            'is_del' => 0
        ];
        return $make->getWhereCount($where);
    }

    /**
     *  设置排序
     * @param int $id
     * @param int|null $merId
     * @param array $data
     * @return null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author Qinii
     */
    public function updateSort(int $id, ?int $merId, array $data)
    {
        $where[$this->dao->getPk()] = $id;
        if ($merId) $where['mer_id'] = $merId;
        $ret = $this->dao->getWhere($where);
        if (!$ret) throw new  ValidateException('数据不存在');
        app()->make(ProductRepository::class)->update($ret['product_id'], $data);
        $make = app()->make(SpuRepository::class);
        $activityId = $ret['product_type'] ? $ret->seckillActive->seckill_active_id : 0;
        return $make->updateSort($ret['product_id'], $activityId, $ret['product_type'], $data);
    }

    /**
     * 删除商户所有的
     * @param int $merId
     * @author Qinii
     * @day 5/15/21
     */
    public function clearMerchantProduct($merId)
    {
        //普通 秒杀
        $this->dao->clearProduct($merId);
        //助理
        app()->make(ProductAssistRepository::class)->clearProduct($merId);
        //拼团
        app()->make(ProductGroupRepository::class)->clearProduct($merId);
        //预售
        app()->make(ProductPresellRepository::class)->clearProduct($merId);
        //spu
        app()->make(SpuRepository::class)->clearProduct($merId);
    }

    /**
     * 保障服务
     * @param $where
     * @return mixed
     * @author Qinii
     * @day 5/20/21
     */
    public function GuaranteeTemplate($where)
    {
        $data = app()->make(GuaranteeTemplateRepository::class)->getSearch($where)->with(
            [
                'templateValue' => [
                    'value' => function ($query) {
                        $query->field('guarantee_id,guarantee_name,guarantee_info');
                    }
                ],
            ])->find();
        return $data ?? [];
    }

    /**
     * 添加到货通知
     * @param int $uid
     * @param string $unique
     * @param int $type
     * @author Qinii
     * @day 5/24/21
     */
    public function increaseTake(int $uid, string $unique, int $type, int $product_id)
    {
        $status = systemConfig('procudt_increase_status');
        if (!$status) throw new ValidateException('未开启到货通知');
        $make = app()->make(ProductTakeRepository::class);
        $where['product_id'] = $product_id;
        if ($unique) $where['unique'] = $unique;
        $sku = app()->make(ProductAttrValueRepository::class)->getWhere($where);
        if (!$sku) throw new ValidateException('商品不存在');
        $data = [
            'product_id' => $sku['product_id'],
            'unique' => $unique ?: 1,
            'uid' => $uid,
            'status' => 0,
            'type' => $type
        ];
        $make->findOrCreate($data);
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
        if (isset($data['params']))
            $product['params'] = $data['params'];
        if (isset($data['assist_count'])) {
            //助力
            $product['assist_count'] = $data['assist_count'];
            $product['assist_user_count'] = $data['assist_user_count'];
            $product['price'] = $data['attrValue'][0]['assist_price'];
            $productType = 3;
        }

        if (isset($data['buying_count_num'])) {
            //
            $product['buying_count_num'] = $data['buying_count_num'];
            $product['pay_count'] = $data['pay_count'];
            $productType = 4;
        }

        $product['slider_image'] = explode(',', $product['slider_image']);
        $product['merchant'] = $data['merchant'];
        $product['content'] = ['content' => $data['content']];
        $settleParams = $this->setAttrValue($data, 0, $productType, 0);
        $settleParams['attr'] = $this->setAttr($data['attr'], 0,$productType);

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

        if (isset($data['guarantee_template_id'])) {
            $guarantee_id = app()->make(GuaranteeValueRepository::class)->getSearch(['guarantee_template_id' => $data['guarantee_template_id']])->column('guarantee_id');
            $product['guaranteeTemplate'] = app()->make(GuaranteeRepository::class)->getSearch(['status' => 1, 'is_del' => 0])->where('guarantee_id', 'in', $guarantee_id)->select();
        }
        if (isset($data['temp_id'])) {
            $product['temp'] = app()->make(ShippingTemplateRepository::class)->getSearch(['shipping_template_id' => $data['temp_id']])->find();
        }

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
                $product = $this->apiProductDetail(['product_id' => $data['id']], 0, 0);
                $res = $this->getProductShow($data['id'], $product, null, 0);
                $ret = array_merge($product, $res);
                break;
            case 1:
                $product = $this->apiProductDetail(['product_id' => $data['id']], 1, 0);

                $ret = array_merge($product, $this->getProductShow($data['id'], $product, null, 0));
                $ret['stop'] = time() + 3600;
                break;
            case 2:
                $make = app()->make(ProductPresellRepository::class);
                $res = $make->getWhere([$make->getPk() => $data['id']])->toArray();
                $ret = $this->apiProductDetail(['product_id' => $res['product_id']], 2, $data['id']);
                $ret['ot_price'] = $ret['price'];
                $ret['start_time'] = $res['start_time'];
                $ret['p_end_time'] = $res['end_time'];
                $ret['presell_type'] = $res['presell_type'];
                $show = $this->getProductShow($res['product_id'], $ret, $res['product_presell_id'], 2);
                $ret = array_merge($ret, $show);
                break;
            case 3:
                $make = app()->make(ProductAssistRepository::class);
                $res = $make->getWhere([$make->getPk() => $data['id']])->toArray();
                $ret = $this->apiProductDetail(['product_id' => $res['product_id']], 3, $data['id']);
                $show = $this->getProductShow($res['product_id'], $ret, $res['product_assist_id'], 3);
                $ret = array_merge($ret, $show);
                foreach ($ret['sku'] as $value) {
                    $ret['price'] = $value['price'];
                    $ret['stock'] = $value['stock'];
                }
                break;
            case 4:
                $make = app()->make(ProductGroupRepository::class);
                $res = $make->get($data['id'])->toArray();
                $ret = $this->apiProductDetail(['product_id' => $res['product_id']], 4, $data['id']);
                $ret['ot_price'] = $ret['price'];
                $ret['price'] = $res['price'];
                $show = $this->getProductShow($res['product_id'], $ret, $res['product_group_id'], 4);
                $ret = array_merge($ret, $show);
                break;
            default:
                break;
        }
        return $ret;
    }

    /**
     *  设置标签
     * @param $id
     * @param $data
     * @param $merId
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author Qinii
     */
    public function setLabels($id, $data, $merId = 0)
    {
        $where['product_id'] = $id;
        $field = isset($data['sys_labels']) ? 'sys_labels' : 'mer_labels';
        if ($merId) $where['mer_id'] = $merId;
        app()->make(ProductLabelRepository::class)->checkHas($merId, $data[$field]);
        $ret = $this->dao->getWhere($where);

        $activeId = $ret->seckillActive->seckill_active_id ?? 0;

        $spu = ['activity_id' => $activeId, 'product_type' => $ret['product_type'], 'product_id' => $id];
        $ret = app()->make(SpuRepository::class)->getWhere($spu);
        if (!$ret) throw new ValidateException('数据不存在');
        $ret->$field = $data[$field];
        $ret->save();
    }

    /**
     *  获取商品规格
     * @param int $id
     * @param int $merId
     * @return mixed
     * @author Qinii
     */
    public function getAttrValue(int $id, int $merId)
    {
        $data = $this->dao->getWhere(['product_id' => $id, 'mer_id' => $merId]);
        if (!$data) throw new ValidateException('数据不存在');
        return app()->make(ProductAttrValueRepository::class)->getSearch(['product_id' => $id])->select()->append(['is_svip_price']);
    }

    /**
     *  商品参数验证
     * @param $data
     * @param $merId
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function checkParams($data, $merId, $id = null)
    {
        if (!$data['pay_limit']) $data['once_max_count'] = 0;
        if ($data['brand_id'] > 0 && !$this->merBrandExists($data['brand_id']))
            throw new ValidateException('品牌不存在');
        if (!$data['cate_id'] || !$this->CatExists($data['cate_id']))
            throw new ValidateException('平台分类不存在');
        if (isset($data['mer_cate_id']) && !$this->merCatExists($data['mer_cate_id'], $merId))
            throw new ValidateException('不存在的商户分类');
        if ($data['type'] !== self::DEFINE_TYPE_RESERVATION && $data['delivery_way'] == 2 && !$this->merShippingExists((int)$merId, (int)
            $data['temp_id']))
            throw new ValidateException('运费模板不存在');
        // 判断是否为礼包商品并且商家礼包数量是否超过限制
        if ($data['is_gift_bag'] && !$this->checkMerchantBagNumber($merId))
            throw new ValidateException('礼包数量超过数量限制');

        if (isset($data['type']) && $data['type'] == 1 && $data['extend']) {
            $key = ['email', 'text', 'number', 'date', 'time', 'idCard', 'mobile', 'image'];
            if (count($data['extend']) > 10) throw new ValidateException('附加表单不能超过10条');
            $title = [];
            foreach ($data['extend'] as $item) {
                if (empty($item['title']))
                    throw new ValidateException('表单名称不能为空：' . $item['key']);
                if (in_array($item['title'], $title))
                    throw new ValidateException('表单名称不能重复：' . $item['title']);
                $title[] = $item['title'];
                if (!in_array($item['key'], $key))
                    throw new ValidateException('表单类型错误：' . $item['key']);
                $extend[] = [
                    'title' => $item['title'],
                    'key' => $item['key'],
                    'require' => $item['require'],
                ];
            }
        }

        app()->make(ProductLabelRepository::class)->checkHas($merId, $data['mer_labels']);
        $count = app()->make(StoreCategoryRepository::class)->getWhereCount(['store_category_id' => $data['cate_id'], 'is_show' => 1]);
        if (!$count) throw new ValidateException('平台分类不存在或不可用');
        $validate = app()->make(StoreProductValidate::class);
        if (!$validate->sceneCreate($data)){
            throw new ValidateException($validate->getError());
        }
        // 公益商品：命中金额/分销金额校验（仅公益店铺）
        $merchant = app()->make(\app\common\repositories\system\merchant\MerchantRepository::class)->get($merId);
        $isWelfareShop = $merchant && (int)($merchant['is_welfare_shop'] ?? 0) === 1;
        if ($isWelfareShop) {
            $hitAmount = isset($data['hit_amount']) ? (float)$data['hit_amount'] : 0;
            $welfareCommission = isset($data['welfare_commission']) ? (float)$data['welfare_commission'] : 0;
            if ($hitAmount < 0 || $welfareCommission < 0) {
                throw new ValidateException('命中金额/分销金额不能为负数');
            }
            $minPrice = 0;
            if (!empty($data['attrValue']) && is_array($data['attrValue'])) {
                foreach ($data['attrValue'] as $sku) {
                    $p = (float)($sku['price'] ?? 0);
                    if ($minPrice <= 0 || ($p > 0 && $p < $minPrice)) {
                        $minPrice = $p;
                    }
                }
            }
            if ($minPrice > 0 && $hitAmount > $minPrice) {
                throw new ValidateException('命中金额不能大于商品售价');
            }
        } else {
            $data['hit_amount'] = 0;
            $data['welfare_commission'] = 0;
        }
        // 保存推荐商品
        if (!empty($data['good_ids'])) {
            if (30 < count($data['good_ids'])) throw new ValidateException('关联商品不得超过30个');
            $data['good_ids'] = implode(',', $data['good_ids']);
        } else {
            $data['good_ids'] = '';
        }
        return $data;
    }

    /**
     * 生成规格 sku
     * @param array $data
     * @param int $productId
     * @param $skuValue
     * @return array
     * @author Qinii
     */
    public function isFormatAttr(array $data, int $productId, $skuValue = [],$isCopy = 0)
    {
        if ($productId) {
            $make = app()->make(ProductAttrValueRepository::class);
            $append = ['productCdkey','cdkeyLibrary'];
            if (!$isCopy)$append = ['productCdkey','cdkeyLibrary'];
            $_sukValue = $make->search(['product_id' => $productId])->with($append)->select()->toArray();
            foreach ($_sukValue as $value) {
                $value['cdkey'] = $value['productCdkey'] ?? [];
                unset($value['productCdkey']);
                $skuValue[$value['sku']] = $value;
            }
        }
        $valueNew = [];
        $count = 0;
        [$attr, $head] = attr_format($data);
        foreach ($attr as $suk) {
            $detail = explode(',', $suk);
            foreach ($detail as $k => $v) {
                $valueNew[$count]['value' . ($k + 1)] = $v;
            }
            $valueNew[$count]['sku'] = $suk;
            $valueNew[$count]['cost'] = $skuValue[$suk]['cost'] ?? 0;
            $valueNew[$count]['image'] = $skuValue[$suk]['image'] ?? '';
            $valueNew[$count]['price'] = $skuValue[$suk]['price'] ?? 0;
            $valueNew[$count]['stock'] = $skuValue[$suk]['stock'] ?? 0;
            $valueNew[$count]['cdkey'] = $skuValue[$suk]['cdkey'] ?? [];
            $valueNew[$count]['weight'] = $skuValue[$suk]['weight'] ?? 0;
            $valueNew[$count]['volume'] = $skuValue[$suk]['volume'] ?? 0;
            $valueNew[$count]['unique'] = $skuValue[$suk]['unique'] ?? 0;
            $valueNew[$count]['detail'] = array_combine($head, $detail);
            $valueNew[$count]['bar_code'] = $skuValue[$suk]['bar_code'] ?? '';
            $valueNew[$count]['ot_price'] = $skuValue[$suk]['ot_price'] ?? 0;
            $valueNew[$count]['svip_price'] = $skuValue[$suk]['svip_price'] ?? 0;
            $valueNew[$count]['extension_one'] = $skuValue[$suk]['extension_one'] ?? 0;
            $valueNew[$count]['extension_two'] = $skuValue[$suk]['extension_two'] ?? 0;
            $valueNew[$count]['cdkeyLibrary'] = $skuValue[$suk]['cdkeyLibrary'] ?? null;
            $valueNew[$count]['library_id'] = !$isCopy ? $skuValue[$suk]['library_id'] ?? 0 : 0;
            $count++;
        }
        return ['attr' => $data, 'value' => $valueNew];
    }

    /**
     *  移动端搜索商品列表页的推荐
     * @param $where
     * @return mixed
     * @author Qinii
     */
    public function getHotSearchList($where)
    {
        $where = array_merge($where, $this->dao->productShow());
        $where['star'] = '';
        //搜索记录
        $query = $this->dao->search(null, $where);
        $list = $query->limit(10)->select()->toArray();
        // 判断商品数量是否足够，不足补齐
        if (count($list) < 10) {
            $list2 = $this->dao->query([])->orderRaw('RAND()')->limit(10 - count($list))->select()->toArray();
            $list = array_merge($list, $list2);
        }
        return $list;
    }

    /**
     * 状态操作日志
     * @param $field
     * @param $status
     * @param $admin_info
     * @param $product
     * @return void
     */
    public function addChangeStatusLog($field, $status, $admin_info, $product)
    {
        $fieldMappings = [
            'status' => [
                1 => OperateLogRepository::PLATFORM_AUDIT_PRODUCT_PASS,
                -1 => OperateLogRepository::PLATFORM_AUDIT_PRODUCT_REFUSE,
                -2 => OperateLogRepository::PLATFORM_AUDIT_PRODUCT_OFF_SALE,
            ],
            'is_show' => [
                1 => OperateLogRepository::MERCHANT_EDIT_PRODUCT_ON_SALE,
                0 => OperateLogRepository::MERCHANT_EDIT_PRODUCT_OFF_SALE,
            ],
            'is_used' => [
                1 => OperateLogRepository::PLATFORM_EDIT_PRODUCT_SHOW,
                0 => OperateLogRepository::PLATFORM_EDIT_PRODUCT_HIDE,
            ],
        ];
        $category = $fieldMappings[$field][$status] ?? '';
        if ($category !== '') {
            event('create_operate_log', [
                'category' => $category,
                'data' => [
                    'product' => $product,
                    'admin_info' => $admin_info,
                ],
            ]);
        }
    }


    /**
     * 获取产品
     * @param array $merWhere 商户条件
     * @param array $productWhere 商品条件
     * @param int $page 页数
     * @param int $limit 条数
     * @return array
     * FerryZhao 2024/4/12
     */
    public function getProductList(array $merWhere = [], array $productWhere = [], int $page = 1, int $limit = 10, $isSystem = false): array
    {
        //try {
            $list = [];
            $count = 0;
            $merchantWhere = [];
            $oldProductIds = [];
            $activeId = isset($productWhere['active_id']) && $productWhere['active_id'] ? $productWhere['active_id'] : null;
            unset($productWhere['active_id']);
            if ($activeId) {
                $oldProductIds = $this->dao->getSearch([])->where(['active_id' => $activeId])->column('old_product_id');
            }
            $filed = 'Product.type,Product.product_id,Product.temp_id,Product.refund_switch,Product.mer_id,brand_id,unit_name,spec_type,mer_status,rate,reply_count,store_info,cate_id,Product.image,slider_image,Product.store_name,Product.keyword,Product.sort,Product.rank,Product.is_show,Product.sales,Product.price,extension_type,refusal,cost,Product.ot_price,stock,is_gift_bag,Product.care_count,Product.status,is_used,Product.create_time,Product.product_type,old_product_id,integral_total,integral_price_total,mer_labels,Product.is_good,Product.is_del,Product.type,Product.param_temp_id,Product.mer_svip_status,Product.svip_price,Product.svip_price_type,Product.mer_form_id,Product.good_ids,Product.delivery_free,Product.delivery_way';

            $selfMerchantIds = app()->make(MerchantRepository::class)->search($merWhere)->column('mer_id');
            if ($isSystem && !empty($selfMerchantIds)) {
                $merchantWhere = $selfMerchantIds;
            } else if ($isSystem && empty($selfMerchantIds)) {
                return compact('count', 'list');
            } elseif (!$isSystem) {
                $merchantWhere[] = $merWhere['mer_id'];
            }

            $with = ['attr', 'attrValue', 'merCateId.category', 'storeCategory', 'content',
                'merchant' => function ($query) {
                    $query->with(['typeName', 'categoryName'])->field('mer_id,category_id,type_id,mer_avatar,mer_name,is_trader');
                },
            ];
            $productAttrValue = app()->make(ProductAttrValueRepository::class);

            $query = $this->dao->search($merchantWhere, $productWhere)->when($oldProductIds, function ($query) use ($oldProductIds) {
                $query->whereNotIn('Product.product_id', $oldProductIds);
            })->with($with);

            $count = $query->count();
            $list = $query->page($page, $limit)->setOption('field', [])->field($filed)->select()->each(function
            ($item) use ($productAttrValue) {
                //处理old_stock
                if (!empty($item['attrValue'])) {
                    foreach ($item['attrValue'] as &$attrItem) {
                        $value = $productAttrValue->getSearch(['sku' => $attrItem['sku'], 'product_id' => $item['product_id']])->find();
                        if ($value) $attrItem['old_stock'] = $value['stock'];
                        $attrItem['price'] = $attrItem['price'] ?? $attrItem['ot_price'];
                    }
                }
                $item['old_stock'] = $item['stock'];
                if (!$item->sys_labels) {
                    $item->sys_labels = [];
                } else {
                    $item->sys_labels = explode(',', rtrim(ltrim($item->sys_labels, ','), ','));
                }

                //分类处理
                $merCat = [];
                if (isset($item['merCateId'])) {
                    foreach ($item['merCateId'] as $i) {
                        $merCat[] = $i['mer_cate_id'];
                    }
                }
                $item['mer_cate_id'] = $merCat;

                //规格处理
                foreach ($item['attr'] as $k => $v) {
                    $item['attr'][$k] = [
                        'value' => $v['attr_name'],
                        'detail' => $v['attr_values']
                    ];
                }
                $item['content'] = $item['content']['content'] ?? '';//详情处理
                $item['old_product_id'] = $item['product_id'];
            });
            return compact('count', 'list');
        //} catch (\Exception $e) {
        //    throw new ValidateException($e->getMessage());
        //}
    }


    /**
     * 秒杀商品列表
     * @param int|null $merId
     * @param array $where
     * @param int $page
     * @param int $limit
     * @param int $isPage
     * @return array
     * FerryZhao 2024/4/13
     */
    public function getSeckillProductList(?int $merId, array $where, int $page, int $limit, $isPage = 0)
    {
        $query = $this->dao->search($merId, $where)->with([
            'merCateId.category',
            'storeCategory',
            'brand',
            'merchant',
            'attrValue',
        ]);

        $count = $query->count();
        if ($isPage) {
            $query->page($page, $limit);
        }
        $productAttrValue = app()->make(ProductAttrValueRepository::class);
        $list = $query->field('Product.*,U.star,U.rank,U.sys_labels')->select()
            ->each(function ($item) use ($productAttrValue) {
                if (!$item->sys_labels) {
                    $item->sys_labels = [];
                } else {
                    $item->sys_labels = explode(',', rtrim(ltrim($item->sys_labels, ','), ','));
                }
                if (!$item->mer_labels) {
                    $item->mer_labels = [];
                } else {
                    $item->mer_labels = explode(',', rtrim(ltrim($item->mer_labels, ','), ','));
                }

                //处理old_stock
                if (!empty($item['attrValue'])) {
                    foreach ($item['attrValue'] as &$attrItem) {
                        $value = $productAttrValue->getSearch(['sku' => $attrItem['sku'], 'product_id' => $item['product_id']])->find();
                        if ($value) $attrItem['old_stock'] = $value['stock'];
                    }
                }
                $item['old_stock'] = $item['stock'];

                //分类处理
                $merCat = [];
                if (isset($item['merCateId'])) {
                    foreach ($item['merCateId'] as $i) {
                        $merCat[] = $i['mer_cate_id'];
                    }
                }
                $item['mer_cate_id'] = $merCat;

                //规格处理
                foreach ($item['attr'] as $k => $v) {
                    $item['attr'][$k] = [
                        'value' => $v['attr_name'],
                        'detail' => $v['attr_values']
                    ];
                }

                $item['content'] = $item['content']['content'] ?? '';//详情处理
                $item['old_product_id'] = $item['product_id'];//old_product_id


            });
        $data = compact('count', 'list');
        if (!$isPage) {
            $data = compact('list');
        }
        return $data;
    }


    /**
     * 审核商品
     * @param $id 商品ID
     * @return \FormBuilder\Form
     * FerryZhao 2024/4/18
     */
    public function getSwitchStatusForm($id)
    {
        $data = app()->make(ProductRepository::class)->getSearch([])->where(['product_id' => $id])->find();
        if (!$data) throw new ValidateException('数据不存在');
        if ($data['status'] != 0) throw new ValidateException('当前商品已审核，请勿重复操作');
        $form = Elm::createForm(Route::buildUrl('systemStoreSeckillProductSwitchStatus', ['id' => $id])->build());
        return $form->setRule([
            Elm::radio('status', '审核状态：', 1)->setOptions([
                ['value' => 1, 'label' => '审核通过'],
                ['value' => -1, 'label' => '未通过']
            ])->appendControl(-1, [Elm::textarea('refusal', '原因：', '信息存在违规')->placeholder('请输入下架理由')])
        ])->setTitle('秒杀商品审核');

    }

    /**
     * 强制下架
     * @param $id
     * FerryZhao 2024/4/15
     */
    public function downProductForm($id)
    {
        $data = app()->make(ProductRepository::class)->get($id);
        if (!$data) throw new ValidateException('数据不存在');
        $form = Elm::createForm(Route::buildUrl('systemCommunityStatus', ['id' => $id])->build());
        return $form->setRule([
            Elm::hidden('status', -1),
            Elm::textarea('refusal', '下架理由：', '信息存在违规')->placeholder('请输入下架理由')->required('请输入下架理由')
        ])->setTitle('强制下架');
    }


    /**
     * 批量更新商品sku
     * @param $id
     * @param $attrValue
     * @param $field
     * FerryZhao 2024/4/22
     * @throws ValidationException
     */
    public function saveAllSku(int $id, array $attrValue, $field = [])
    {
        $attrValue_ = $attrValue;
        $productAttrValueRepository = app()->make(ProductAttrValueRepository::class);
        if (!empty($field)) {
            foreach ($attrValue as &$subArray) {
                if (!isset($subArray['value_id']) || !$subArray['value_id']) throw new ValidationException('主键参数错误');
                if ($subArray['stock'] > $subArray['old_stock']) throw new ValidationException('限量不能大于库存');
                // 使用 array_filter() 函数过滤键名
                $subArray = array_filter(
                    $subArray,
                    function ($key) use ($field) {
                        return in_array($key, $field);
                    },
                    ARRAY_FILTER_USE_KEY
                );
            }
        }
        $res = $this->dao->get($id);
        try {
            return Db::transaction(function () use ($id, $attrValue, $productAttrValueRepository,$res,$attrValue_) {
                foreach ($attrValue as $item) {
                    $valueId = $item['value_id'];
                    unset($item['value_id']);
                    $productAttrValueRepository->getSearch([])
                        ->where(['value_id' => $valueId, 'product_id' => $id])
                        ->update($item);
                }
                //置为sku的最低价
                $minPrice = min(array_column($attrValue, 'price'));
                $this->dao->update($id, ['price' => $minPrice]);
                Queue::push(SetSeckillStockCacheJob::class,['res'=> $res]);
                return $minPrice;
            });
        } catch (\Exception $e) {
            throw new ValidateException('编辑商品sku失败' . $e->getMessage());
        }
    }


    /**
     *  定时任务
     * @param int $id
     * @param $on_time
     * @param $off_time
     * @author Qinii
     * @day 2024/5/15
     */
    public function autoOnTime(int $id, $on_time, $off_time)
    {
        $on_time_key = self::AUTO_ON_TIME;
        $off_time_key = self::AUTO_OFF_TIME;
        $reids = app()->make(RedisCacheService::class);
        $set = false;
        if ($on_time) {
            $hget = $reids->hGet($on_time_key,$on_time);
            if (!$hget) {
                $reids->zAdd(self::AUTO_PREFIX_SET.$on_time_key,$on_time,$on_time);
                $hget = $id;
            } else {
                $hget = $hget. ','. $id;
            }
            $reids->hset($on_time_key,$on_time,$hget);
            $set = true;
        }
        if ($off_time) {
            $hget = $reids->hGet($off_time_key,$on_time);
            if (!$hget) {
                $reids->zAdd(self::AUTO_PREFIX_SET.$off_time_key,$off_time,$off_time);
                $hget = $id;
            } else {
                $hget = $hget. ','. $id;
            }
            $reids->hset($off_time_key,$off_time,$hget);
            $set = true;
        }
        if ($set) Cache::set(self::AUTO_TIME_STATUS,1);
    }

    /**
     *  批量添加定时上下架的缓存
     * @param array $data
     * @param string $key
     * @author Qinii
     * @day 2024/5/15
     */
    public function bathAutoOntime(array $data, string $key)
    {
        /**
         *  整理为 时间戳 => [ 商品 id ]
         */
        $reversedArray = array_reduce(array_keys($data), function ($result, $key) use ($data) {
            $value = $data[$key];
            if (!isset($result[$value])) {
                $result[$value] = array($key);
            } else {
                $result[$value][] = $key;
            }
            return $result;
        }, array());
        $redis = app()->make(RedisCacheService::class);
        $hset = [];
        $_key = self::AUTO_PREFIX_SET.$key;
        foreach ($reversedArray as $k => $v) {
            $redis->zAdd($_key, $k, $k);
            $hset[$k] = implode(',',$v);
        }
        if ($hset) $redis->hMSet($key,$hset);
    }

    /**
     *  定时上下级操作
     * @param $times
     * @param $key
     * @return string
     * @author Qinii
     * @day 2024/5/16
     */
    public function autoSwitchShow($times, $key)
    {
        $redis = app()->make(RedisCacheService::class);
        $ids = $redis->hMget($key,$times);
        $productIds = implode(',',array_values($ids));
        switch ($key) {
            case self::AUTO_ON_TIME :
                $data = ['is_show' => 1, 'auto_on_time' => '',];
                break;
            case self::AUTO_OFF_TIME :
                $data = ['is_show' => 0, 'auto_on_time' => '', 'auto_off_time' => '',];
                break;
            default:
                return '类型错误';
        }
        $id = array_chunk(array_values($ids), 30);
        foreach ($id as $i) {
            $this->dao->getSearch([])->whereIn('product_id', $i)->update($data);
            Queue::push(ChangeSpuStatusJob::class, ['id' => $i, 'product_type' => 0]);
        }
        $zrem = $times;
        array_unshift($zrem,ProductRepository::AUTO_PREFIX_SET.$key);
        call_user_func_array(array($redis,'zRem'),$zrem);
        $redis->hDel($key, ...$times);
        return $productIds;
    }

    /**
     * 大图推荐获取下滑商品列表
     * @param $where
     * @param $merId
     * @param $page
     * @param $limit
     * @return void
     * @author Qinii
     */
    public function cateHotList($where,$merId, $page, $limit, $user, $not)
    {
        $type_id = [];
        if ($user) {
            //获取用户关注的商户 id
            $userRelationRepository = app()->make(UserRelationRepository::class);
            $type_id = $userRelationRepository->getSearch(['type' => 1, 'uid' => $user->uid])->column('type_id');
        }
        $where = array_merge($where, $this->dao->productShow());
        $query = $this->dao->search($merId, $where)->where('Product.product_id','<>', $not)->with(['merchant']);
        $count = $query->count();
        $filter = 'cate_id';
        $cate_pid = 0;
        if (isset($where['cate_id']) && $count < $limit) {
            $cateData = app()->make(StoreCategoryRepository::class)->getSearch(['id' => $where['cate_id']])->find();
            if ($cateData) {
                $path = explode('/',trim($cateData['path'],'/'));
                // $path是以根据path‘/’分隔,获取顶级分类ID
                $cate_pid = $path[0];
                $filter = 'cate_pid';
                $where[$filter] = $cate_pid;
            }
            unset($where['cate_id']);
            $query = $this->dao->search($merId, $where)->with(['merchant']);
            $count = $query->count();
        }
        $list = $query->field($this->filed)->page($page, $limit)->order('cate_hot DESC,create_time DESC')->select()->toArray();
        if ($type_id) {
            foreach ($list as &$item) {
                $item['isRelation'] = in_array($item['spu_id'],$type_id);
            }
        }
        $cate_id = $list[0]['cate_id'] ?? 0;
        return compact('filter','cate_id','cate_pid','count','list');
    }


    /**
     * 秒杀商品增加库存缓存
     * @param $res
     * @param $attrValue
     * @return true
     * @author Qinii
     */
    public function seckillStockCache($res, $reset = false)
    {
        /**
         * 秒杀商品生成缓存库存
         * 缓存key格式: self::SECKILL_STOCK_CACHE_KEY . 活动ID . 日期 . 商品ID . 秒杀时间段ID . 属性unique
         */
        $storeSeckillActiveRepository = app()->make(StoreSeckillActiveRepository::class);
        $active = $storeSeckillActiveRepository->get($res['active_id']);
        $today = strtotime(date('Y-m-d'));
        if ($today < strtotime($active['start_day']) || $today > strtotime($active['end_day'])) return ;

        $product_today = [self::SECKILL_STOCK_CACHE_KEY, $res['active_id'],  date('Y-m-d',$today) , $res['product_id']];
        $redis = app()->make(RedisCacheService::class);
        $product_today_key = implode('_',$product_today);

        $key = $redis->get($product_today_key);
        if (!$reset && $key) return ;

        //需要组合的key
        $cache_attr = [];
        $cache_array = [];
        $cache_key_times = [];
        $cache_attr[] = $product_today;
        foreach ($active['seckill_time_ids'] as $id) {
            $cache_key = array_map(function($item) use($id) {
                $item[] = $id;
                return $item;
            },$cache_attr);
            $cache_key_times = array_merge($cache_key_times,$cache_key);
        }

        $cache_key_unique = [];
        foreach ($res['sku'] as $value) {
            $cache_keys = array_map(function($item) use($value) {
                $item[] = $value['unique'];
                $item = implode('_',$item);
                return $item;
            },$cache_key_times);
            $cache_array = array_combine($cache_keys, array_fill(0, count($cache_keys), $value['stock']));
            $cache_key_unique = array_merge($cache_key_unique,$cache_array);
        }
        $redis->set($product_today_key, 1, 60 * 60 * 24);
        //批量存入redis
        foreach ($cache_key_unique as $k => $v) {
            $this->push($k,1,$v);
        }
        return true;
    }

    /**
     *  根据秒杀商品
     * @param $product
     * @param $sku
     * @return array
     * @author Qinii
     */
    public function getSeckillStockCache($product, $unique)
    {
        //缓存key格式: self::SECKILL_STOCK_CACHE_KEY . 活动ID . 日期 . 商品ID . 秒杀时间段ID . 属性unique
        if (!$product || !$unique) throw new ValidateException('参数错误');
        if ($product->product_type != 1) throw new ValidateException('商品类型错误'.$product->product_type);
        if (!$product->active_id) throw new ValidateException('秒杀活动不存在');
        $key = self::SECKILL_STOCK_CACHE_KEY.'_'.$product->active_id . '_' . date('Y-m-d',time()) . '_' . $product->product_id;
        $storeSeckillActiveRepository = app()->make(StoreSeckillActiveRepository::class);
        $active = $storeSeckillActiveRepository->get($product->active_id);
        $currentHour = date('G', time());
        $seckill_time_id = StoreSeckillTime::whereIn('seckill_time_id',$active->seckill_time_ids)
            ->where('start_time','<=',$currentHour)->where('end_time','>',$currentHour)
            ->where('status',1)
            ->value('seckill_time_id');
        $key .= '_'.$seckill_time_id. '_'. $unique;
        $stock = $this->getLen($key,1);
        return compact('key','stock');
    }

    /**
     * 库存添加入redis的队列中
     * @param string $unique 标识
     * @param string $type 类型
     * @param int $number 库存个数
     * @param bool $isPush 是否放入之前删除当前队列
     * @return bool
     */
    public function push(string $unique, string $type, int $number, bool $isPush = false)
    {
        $name = $unique . '_' . $type;
        $res = true;
        $redis = app()->make(RedisCacheService::class);
        if (!$isPush) {
            $redis->del($name);
        }
        for ($i = 1; $i <= $number; $i++) {
            $res = $res && $redis->lPush($name, $i);
        }
        return $res;
    }

    /**
     * 弹出redis队列中的条数
     * @param string $unique
     * @param string $type
     * @param int $number
     * @return mixed
     */
    public function pop(string $unique, string $type, int $number = 1)
    {
        $redis = app()->make(RedisCacheService::class);
        $name = $unique . '_' . $type;
        if ($number > $redis->lLen($name)) {
            return false;
        }
        $res = true;
        for ($i = 1; $i <= $number; $i++) {
            $res = $res && $redis->lPop($name);
        }
        return $res;
    }

    /**
     * 获取条数
     * @param string $unique
     * @param string $type
     * @return mixed
     * @author 等风来
     */
    public function getLen(string $unique, string $type)
    {
        $redis = app()->make(RedisCacheService::class);
        $name = $unique . '_' . $type;
        return $redis->lLen($name);
    }

    /**
     * 是否有指定的条数
     * @param string $unique
     * @param string $type
     * @param int $number
     * @return bool
     */
    public function islLen(string $unique, string $type, int $number = 1)
    {
        $name = $unique . '_' . $type;
        $redis = app()->make(RedisCacheService::class);
        if ($number > $redis->lLen($name)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 根据ID和用户信息获取商品规格详情
     *
     * @param int $id 商品ID
     * @param array $user 用户信息
     * @return array 包含商品属性和SKU信息的数组
     */
    public function getSpec($id, $user)
    {
        //apiProductDetail
        $spuRepository = app()->make(SpuRepository::class);
        $spuData = $spuRepository->dao->get($id);
        $productAttrValueRepository = app()->make(ProductAttrValueRepository::class);
        $productAttrRepository = app()->make(ProductAttrRepository::class);
        $attr = $productAttrRepository->search(['product_id' => $spuData['product_id']])->select();
        switch ($spuData['product_type']) {
            case 1:
                $product = $this->dao->get($spuData['product_id']);
                $result = $this->getSeckillAttrValue($product['attrValue'], $product['old_product_id']);
                $attrValue = $result['item'];
                break;
            case 4:
                $product = $this->dao->get($spuData['product_id']);
                $attrValue = $productAttrValueRepository->search(['product_id' => $product['old_product_id']])->select();
                break;
            default:
                $attrValue = $productAttrValueRepository->search(['product_id' => $spuData['product_id']])->select();
                break;
        }
        $attr = $this->detailAttr($attr);
        $sku = $this->detailAttrValue($attrValue, $user, $spuData['product_type'], $spuData['activity_id']);
        return compact('attr','sku');
    }

    public function unbindCdkeyLibrary($valueId, $libraryId)
    {
        $productAttrValueRepository = app()->make(ProductAttrValueRepository::class);
        $valueData = $productAttrValueRepository->getWhere(['value_id' => $valueId,'library_id' => $libraryId]);
        if (!$valueData) throw new ValidateException('数据不存在');
        $stock = $valueData->stock;
        Db::transaction(function () use ($valueData, $stock) {
            $valueData->library_id = 0;
            $valueData->stock = 0;
            $valueData->save();
            $res = $this->dao->get($valueData->product_id);
            $res->stock = $res->stock - $stock;
            $res->save();
        });
    }
    /**
     * 代客下商品加入购物车检测
     * @param int $prodcutId
     * @param string $unique
     * @param int $cartNum
     * @author Qinii
     * @day 2020-10-20
     */
    public function merchantCartCheck(array $data, $userInfo, $touristUniqueKey = '')
    {
        $cart = null;
        $where = $this->dao->productShow();
        $where['product_id'] = $data['product_id'];
        unset($where['is_gift_bag']);
        $product = $this->dao->search(null, $where)->find();

        if (!$product) throw new ValidateException('商品已下架');
        if ($product['once_min_count'] > 0 && $product['once_min_count'] > $data['cart_num'])
            throw new ValidateException('[低于起购数:' . $product['once_min_count'] . ']' . mb_substr($product['store_name'], 0, 10) . '...');
        if ($product['pay_limit'] == 1 && $product['once_max_count'] < $data['cart_num'])
            throw new ValidateException('[超出单次限购数：' . $product['once_max_count'] . ']' . mb_substr($product['store_name'], 0, 10) . '...');
        if ($product['pay_limit'] == 2) {
            //如果长期限购
            //已购买数量
            $storeOrderRepository = app()->make(StoreOrderRepository::class);
            if($userInfo) {
                $count = $storeOrderRepository->getMaxCountNumber($userInfo->uid, $product['product_id']);
                if (($data['cart_num'] + $count) > $product['once_max_count'])
                    throw new ValidateException('[超出限购总数：' . $product['once_max_count'] . ']' . mb_substr($product['store_name'], 0, 10) . '...');
            }
        }
        if ($product['type'] && !$data['is_new']) throw new ValidateException('虚拟商品不可加入购物车');
        if ($product['type'] == self::DEFINE_TYPE_CARD && $data['cart_num'] != 1)
            throw new ValidateException('卡密商品只能单个购买');
        $value_make = app()->make(ProductAttrValueRepository::class);
        $sku = $value_make->getOptionByUnique($data['product_attr_unique'], $data['product_id']);
        if (!$sku) throw new ValidateException('SKU不存在');
        //分销礼包
        if ($product['is_gift_bag']) {
            $config = systemConfig(['extension_status', 'promoter_type']);
            if (!$config['extension_status']) throw new ValidateException('分销功能未开启');
            if ($config['promoter_type']) throw new ValidateException('后台未开启礼包分销模式');
            if (!$data['is_new']) throw new ValidateException('礼包商品不可加入购物车');
            if ($data['cart_num'] !== 1) throw new ValidateException('礼包商品只能购买一个');
            if($userInfo) {
                $extensionInfo = get_extension_info($userInfo);//获取用户是否可以分销以及是否内购
                if ($extensionInfo['isPromoter']) throw new ValidateException('您已经是分销员了');
            }
        }
        //立即购买 限购
        if ($data['is_new']) {
            $cart_num = $data['cart_num'];
        } else {
            //加入购物车
            //购物车现有
            $_num = $this->productOnceCountCart($where['product_id'], $data['product_attr_unique'], $data['uid']);
            $cart_num = $_num + $data['cart_num'];
        }
        if ($sku['stock'] < $cart_num) throw new ValidateException('库存不足');
        //添加购物车
        if (!$data['is_new']) {
            $cart = app()->make(StoreCartRepository::class)->getCartByProductSku($data['product_attr_unique'], $data['uid'], $touristUniqueKey);
        }

        return compact('product', 'sku', 'cart');
    }

    public function isOverLimit($cart, $data)
    {
        // 非游客
        if($data['uid']) {
            $cart_num = $this->productOnceCountCart($cart['product_id'], $cart['product_attr_unique'], $data['uid']);
            if (($cart_num - $cart['cart_num'] + $data['cart_num']) > $cart->product->once_count) {
                return false;
            }
        }
        // 游客
        if(!$data['uid']) {
            if ($data['cart_num'] > $cart->product->once_count) {
                return false;
            }
        }

        return true;
    }

    public function recommendProduct(array $params)
    {
        $product = $this->dao->get($params['product_id']);
        if (!$product) {
            throw new ValidateException('商品数据不存在');
        }

        return $this->dao->recommendProduct($product['good_ids'], $product['mer_id'], $params['recommend_num'], $product['product_id']);
    }

    /**
     * @param $productIds
     * @param $labelIds
     * @param $merId
     * @param $type 1 覆盖 2 追加
     * @return void
     * @author Qinii
     */
    public function batchLabelCreate($productIds, $labelIds, $merId, $type = 1)
    {
        if ($type == 2 && !is_array($labelIds)) $labelIds = explode(',', $labelIds);
        $idsArr = array_chunk($productIds, 1000);
        foreach ($idsArr as $ids) {
            $res = $this->dao->search($merId, ['product_ids' => $ids])->select();
            foreach ($res as $product) {
                if ($type == 2 && $product['activity_label_ids']) {
                    $labelIds = array_unique(array_merge($product['activity_label_ids'], $labelIds));
                }
                $product->activity_label_ids = $labelIds;
                $product->save();
            }
        }
    }

    public function editMerCate($id, $ids, $merId)
    {
        $data = $this->setMerCate($ids, $id, $merId);
        $productCateRepository = app()->make(ProductCateRepository::class);
        $productCateRepository->clearAttr($id);
        $productCateRepository->insert($data);
    }

    /**
     * 移动端客服修改商品价格库存等信息
     * 移动端只能修改价格和库存，所以不需要其他操作直接更新
     * @param $id
     * @param $attrValue
     * @return void
     * @author Qinii
     */
    public function changeAttrValue($id, $attrValue)
    {
        $productAttrValueRepository = app()->make(ProductAttrValueRepository::class);
        $_stock = 0;
        foreach ($attrValue as $item) {
            $productAttrValueRepository->update($item['value_id'], [
                'cost' => $item['cost'],
                'price' => $item['price'],
                'ot_price' => $item['ot_price'],
                'stock' => $item['stock'],
                'probability_weight' => $item['probability_weight'] ?? 10,
                'bar_code' => $item['bar_code'] ?? '',
                'weight' => $item['weight'] ?? 0,
                'volume' => $item['volume'] ?? 0,
            ]);
            $_stock += $item['stock'];
        }
        $res = $this->validateAttributeValue($attrValue,0,0);

        $this->dao->update($id, [
            'price' => $res['price'],
            'ot_price' => $res['ot_price'],
            'cost' => $res['cost'],
            'svip_price' => $res['svip_price'],
            'stock' => $_stock
        ]);
    }

    /**
     * 批量复制商品到指定店铺
     *
     * @param array $productIds
     * @param string $storeId
     * @return void
     */
    public function batchCopyProductsToStore(array $productIds, string $storeId)
    {
        // 验证店铺是否存在
        $store = app()->make(MerchantRepository::class)->get($storeId);
        if (!$store) {
            throw new ValidateException('店铺不存在');
        }
        // 验证店铺是否正常
        if($store['status'] !== 1){
            throw new ValidateException('店铺已禁用，无法复制商品');
        }
        if ($store['mer_state'] !== 1) {
            throw new ValidateException('店铺已关闭，无法复制商品');
        }
        // 复制商品
        foreach($productIds as $productId){
            $product = $this->getAdminOneProduct($productId, null);
            $product['mer_id'] = $storeId;
            if($product['type'] == self::DEFINE_TYPE_RESERVATION) {
                $res = app()->make(NewProductRepository::class)->createProduct($product, $store, $store->admin);
            } else {
                $res = $this->create($product);
            }
            if(!$res){
                throw new ValidateException('复制商品失败');
            }
        }

        return true;
    }
}
