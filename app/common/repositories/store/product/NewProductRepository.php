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

use think\facade\Db;
use think\exception\ValidateException;
use app\common\dao\store\product\ProductDao;
use app\common\model\system\merchant\Merchant;
use app\common\model\system\merchant\MerchantAdmin;
use app\common\repositories\store\StoreCategoryRepository;
use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\system\operate\OperateLogRepository;

class NewProductRepository extends ProductRepository
{
    use ProductRepositoryTrait;

    public function __construct(ProductDao $dao)
    {
        parent::__construct($dao);
    }

    private function supplyData(array $data, Merchant $merchantInfo, MerchantAdmin $adminInfo): array
    {
        $data['mer_id'] = $merchantInfo->mer_id;
        $data['mer_status'] = $this->merStatus($merchantInfo);
        $data['status'] = $merchantInfo->is_audit ? 0 : 1;
        $data['admin_id'] = $adminInfo->merchant_admin_id;

        return $data;
    }

    public function createProduct(array $data, Merchant $merchantInfo, MerchantAdmin $adminInfo, int $seckillActiveId = 0)
    {
        $data = $this->supplyData($data, $merchantInfo, $adminInfo);
        $productData = $this->setProduct($data);

        event('product.create.before', compact('data'));
        try {
            $result =  Db::transaction(function () use ($data, $productData, $seckillActiveId) {
                // 添加商品
                $product = $this->dao->create($productData);
                if (!$product) {
                    throw new ValidateException('创建商品失败');
                }
                $productId = $product->product_id;
                $productType = $product->product_type;

                $settleParams = $this->setAttrValue($data, $productId, $productType);
                // 添加arrt_result
                $this->saveProductResult($data, $productId);
                // 添加预约商品设置
                $this->saveReservationProduct($data, $productId);
                // 添加商品属性
                $this->saveProductAttr($data['attr'], $productId, $productType);
                // 添加商品内容
                $this->saveProductContent($data['content'], $productId, $productType);
                // 添加商品类型
                $this->saveProductCate($data['mer_cate_id'], $productId, $productType);
                // 添加商品参数
                $this->saveParameterValue($data['params'], $productId, $data['mer_id']);
                // 添加商品属性值
                $this->saveProductAttrValue($data['type'],  $productId, $settleParams['attrValue']);
                // 添加商品spu
                $this->saveProductSpu(array_merge($productData, $settleParams['data']), $data, $productId,$productType, $seckillActiveId);
                // 修改商品信息
                $this->dao->update($productId, $settleParams['data']);

                return $product;
            });
            // 定时上架
            $this->autoShow($result->product_id, $productData['auto_on_time'] ?? '', $productData['auto_off_time'] ?? '');
            // 管理员消息
            $this->adminApplyNotify($data, $result->product_id, $result->product_type);
            // 采集消息队列
            $this->productCollectImage($result->product_id, $data);
            // 操作日志
            event('create_operate_log', [
                'category' => OperateLogRepository::MERCHANT_CREATE_PRODUCT,
                'data' => [
                    'product' => $result,
                    'admin_info' => $adminInfo
                ],
                'mer_id' => $data['mer_id']
            ]);
        } catch (\Exception $exception) {
            throw new ValidateException('创建商品失败：' . $exception->getMessage());
        }
        event('product.create', compact('result'));

        return $result->product_id;
    }

    public function editProduct(int $id, array $data, Merchant $merchantInfo, MerchantAdmin $adminInfo, int $seckillActiveId = 0)
    {
        $productInfo = $this->dao->get($id);
        if (!$productInfo) {
            throw new ValidateException('数据不存在');
        }
        $data = $this->supplyData($data, $merchantInfo, $adminInfo);
        $productData = $this->setProduct($data);

        event('product.update.before', compact('id', 'data'));
        try {
            $settleParams = $this->setAttrValue($data, $id, $productData['product_type'], true);
            $result =  Db::transaction(function () use ($productInfo, $data, $productData, $settleParams, $seckillActiveId) {
                // 修改商品
                $productData = array_merge($productData, $settleParams['data']);
                $res = $productInfo->save($productData);
                if (!$res) {
                    throw new ValidateException('修改失败');
                }
                $productId = $productInfo->product_id;
                $productType = $productInfo->product_type;

                // 修改arrt_result
                $this->saveProductResult($data, $productId);
                // 修改预约商品设置
                $this->saveReservationProduct($data, $productId);
                // 修改商品属性
                $this->saveProductAttr($data['attr'], $productId, $productType);
                // 修改商品内容
                $this->saveProductContent($data['content'], $productId, $productType);
                // 修改商品类型
                $this->saveProductCate($data['mer_cate_id'], $productId, $productType);
                // 修改商品参数
                $this->saveParameterValue($data['params'], $productId, $data['mer_id']);
                // 修改商品属性值
                $this->saveProductAttrValue($data['type'],  $productId, $settleParams['attrValue']);
                // 修改商品spu
                $this->saveProductSpu($productData, $data, $productId, $productType, $seckillActiveId);

                return $productInfo;
            });
            // 定时上架
            $this->autoShow($result->product_id, $productData['auto_on_time'] ?? '', $productData['auto_off_time'] ?? '');
            // 操作日志
            event('create_operate_log', [
                'category' => OperateLogRepository::MERCHANT_EDIT_PRODUCT,
                'data' => [
                    'product' => $productInfo,
                    'admin_info' => $adminInfo,
                    'update_infos' => $settleParams['update_infos']
                ],
                'mer_id' => $data['mer_id']
            ]);
        } catch (\Exception $exception) {
            throw new ValidateException('修改商品失败：' . $exception->getMessage());
        }
        event('product.update', compact('id', 'result'));
        // 清除缓存
        $this->clearProductCache($result->toArray());

        return $result->product_id;
    }

    public function productDetail(int $id): array
    {
        $with = [
            'attr',
            'temp',
            'brand',
            'content',
            'merCateId',
            'attr_result',
            'reservation',
            'oldAttrValue',
            'seckillActive',
            'storeCategory',
            'merCateId.category',
            'guarantee.templateValue.value',
            'attrValue' => function ($query) {
                $query->with(['reservation', 'productCdkey', 'cdkeyLibrary']);
            },
            'merchant' => function ($query) {
                $query->with(['typeName', 'categoryName']);
            }
        ];
        $info = $this->dao->geTrashedtProduct($id)->with($with)->find();
        if (!$info) {
            throw new ValidateException('数据不存在');
        }
        $append = ['us_status'];
        $append == self::PRODUCT_TYPE_SKILL ? $append[] = 'seckill_status' : $append[] = 'parameter_params';
        $info = $info->append($append)->toArray();
        $info = $this->mergeReservation($info);
        $info = app()->make(SpuRepository::class)->productSpu($info, ['mer_labels_data', 'sys_labels_data']);

        $info = $this->translateAttrResult($info);
        $info['content'] = $info['content']['content'];
        $info['merchant']['mer_config'] = $this->merConfig($info['mer_id']);
        $info['goodList'] = $this->dao->getGoodList($info['good_ids'], $info['mer_id'], false);
        $info['mer_cate_id'] = $info['merCateId'] ? array_column($info['merCateId'], 'mer_cate_id') : [];
        $info['merchant']['mer_integral_rate'] = merchantConfig($info['merchant']['mer_id'], 'mer_integral_rate');
        $info['coupon'] = app()->make(StoreCouponRepository::class)->getProductGiveCoupons($info['give_coupon_ids']);
        $info['merchant']['mer_integral_status'] = merchantConfig($info['merchant']['mer_id'], 'mer_integral_status');
        $info['storeCategory']['cate_name'] = app()->make(StoreCategoryRepository::class)->getAllFatherName($info['storeCategory']['store_category_id']);

        unset($info['merCateId']);

        return $info;
    }

    public function editInfo(int $id): array
    {
        $with = [
            'attr',
            'content',
            'merCateId',
            'attr_result',
            'reservation',
            'attrValue' => function ($query) {
                $query->with(['reservation', 'productCdkey', 'cdkeyLibrary']);
            }
        ];
        $info = $this->dao->geTrashedtProduct($id)->with($with)->find();
        if (!$info) {
            throw new ValidateException('数据不存在');
        }

        $info = $info->toArray();
        $info = app()->make(SpuRepository::class)->productSpu($info, ['mer_labels_data', 'sys_labels_data']);
        $info = $this->mergeReservation($info);
        $info = $this->translateAttrResult($info);
        $info['content'] = $info['content']['content'];
        $info['goodList'] = $this->dao->getGoodList($info['good_ids'], $info['mer_id'], false);
        $info['mer_cate_id'] = $info['merCateId'] ? array_column($info['merCateId'], 'mer_cate_id') : [];
        $info['coupon'] = app()->make(StoreCouponRepository::class)->getProductGiveCoupons($info['give_coupon_ids']);

        unset($info['merCateId']);

        return $info;
    }
}
