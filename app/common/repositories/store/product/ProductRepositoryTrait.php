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

use think\facade\Queue;
use think\facade\Cache;
use crmeb\services\SwooleTaskService;
use crmeb\jobs\DownloadProductCopyImage;
use app\common\model\system\merchant\Merchant;
use app\common\repositories\store\parameter\ParameterValueRepository;

trait ProductRepositoryTrait
{
    /**
     * 商户状态
     *
     * @param Merchant $merchantInfo
     * @return integer
     */
    protected function merStatus(Merchant $merchantInfo): int
    {
        return ($merchantInfo->is_del || !$merchantInfo->mer_state || !$merchantInfo->status) ? 0 : 1;
    }
    /**
     * 保存attr_result
     *
     * @param array $data
     * @param integer $productId
     * @return boolean
     */
    protected function saveProductResult(array $data, int $productId) : bool
    {
        return app()->make(ProductResultRepository::class)->save($productId, $data);
    }
    /**
     * 保存参数数据
     *
     * @param array $parameter
     * @param integer $productId
     * @param integer $merId
     * @return void
     */
    protected function saveParameterValue(array $parameter, int $productId, int $merId) : void
    {
        if ($parameter) {
            app()->make(ParameterValueRepository::class)->create($parameter, $productId, $merId);
        };
    }
    /**
     * 保存商品属性
     *
     * @param array $attr
     * @param integer $productId
     * @param integer $productType
     * @return void
     */
    protected function saveProductAttr(array $attr, int $productId, int $productType) : void
    {
        $attr = $this->setAttr($attr, $productId, $productType);
        if (isset($attr)) {
            $productAttrRepository = app()->make(ProductAttrRepository::class);
            $productAttrRepository->clearAttr($productId);
            $productAttrRepository->insert($attr);
        }
    }
    /**
     * 保存商品属性值
     *
     * @param integer $type
     * @param integer $productId
     * @param array $attrValue
     * @return void
     */
    protected function saveProductAttrValue(
        int $type,
        int $productId,
        array $attrValue
    ): void {
        $productAttrValueRepository = app()->make(ProductAttrValueRepository::class);
        if ($type == self::DEFINE_TYPE_CARD) {
            app()->make(CdkeyLibraryRepository::class)->cancel($productId);
        } else if ($type == self::DEFINE_TYPE_CLOUD) {
            app()->make(ProductCdkeyRepository::class)->clearAttr($productId);
        } else if ($type == self::DEFINE_TYPE_RESERVATION) {
            $productAttrValueRepository->deleteReservation($productId);
        }
        // 添加商品属性值
        $productAttrValueRepository->clearAttr($productId);
        foreach ($attrValue as $item) {
            $productAttrValueRepository->add($item, $type);
        }
    }
    /**
     * 保存商品spu
     *
     * @param array $productData
     * @param array $data
     * @param integer $productId
     * @param integer $productType
     * @param integer $seckillActiveId
     * @return void
     */
    protected function saveProductSpu(array $productData, array $data, int $productId, int $productType, int $seckillActiveId)
    {
        $productData['mer_labels'] = $data['mer_labels'];
        app()->make(SpuRepository::class)->baseUpdate($productData, $productId, $seckillActiveId, $productType);
    }
    /**
     * 保存商品分类
     *
     * @param array $merCateId
     * @param integer $productId
     * @param integer $productType
     * @return void
     */
    protected function saveProductCate(array $merCateId, int $productId, int $productType) : void
    {
        // 格式商品商户分类
        $cate = $this->setMerCate($merCateId, $productId, $productType);
        if (isset($cate)) {
            $productCateRepository = app()->make(ProductCateRepository::class);
            $productCateRepository->clearAttr($productId);
            $productCateRepository->insert($cate);
        }
    }
    /**
     * 保存商品内容
     *
     * @param string $content
     * @param integer $productId
     * @param integer $productType
     * @return void
     */
    protected function saveProductContent(string $content, int $productId, int $productType) : void
    {
        app()->make(ProductContentRepository::class)->clearAttr($productId, $productType);
        $this->dao->createContent($productId, ['content' => $content]);
    }
    /**
     * 自动上架
     *
     * @param integer $productId
     * @param string|null $autoOnTime
     * @param string|null $autoOffTime
     * @return void
     */
    protected function autoShow(int $productId, ?string $autoOnTime, ?string $autoOffTime) : void
    {
        if ($autoOnTime || $autoOffTime) {
            $this->autoOnTime($productId, $autoOnTime, $autoOffTime);
        }
    }
    /**
     * 保存预约商品设置
     *
     * @param array $data
     * @param integer $productId
     * @return void
     */
    protected function saveReservationProduct(array $data, int $productId) : void
    {
        if ($data['type'] == self::DEFINE_TYPE_RESERVATION) {
            app()->make(ProductReservationRepository::class)->clear($productId);
            $reservationData = [];
            $reservationData['product_id'] = $productId;
            $reservationData['reservation_time_type'] = $data['reservation_time_type'] ?? 1;
            $reservationData['reservation_start_time'] = $data['reservation_start_time'] ?? '';
            $reservationData['reservation_end_time'] = $data['reservation_end_time'] ?? '';
            $reservationData['reservation_time_interval'] = $data['reservation_time_interval'] ?? 10;
            $reservationData['time_period'] = $data['time_period'] ? json_encode($data['time_period']) : '';
            $reservationData['reservation_type'] = $data['reservation_type'] ?? 1;
            $reservationData['show_num_type'] = $data['show_num_type'] ?? 0;
            $reservationData['sale_time_type'] = $data['sale_time_type'] ?? 1;
            $reservationData['sale_time_start_day'] = $data['sale_time_start_day'] ?? '';
            $reservationData['sale_time_end_day'] = $data['sale_time_end_day'] ?? '';
            $reservationData['sale_time_week'] = $data['sale_time_week'] ? json_encode($data['sale_time_week']) : '';
            $reservationData['show_reservation_days'] = $data['show_reservation_days'] ?? 1;
            $reservationData['is_advance'] = $data['is_advance'] ?? 0;
            $reservationData['advance_time'] = $data['advance_time'] ?? 1;
            $reservationData['is_cancel_reservation'] = $data['is_cancel_reservation'] ?? 0;
            $reservationData['cancel_reservation_time'] = $data['cancel_reservation_time'] ?? 0;
            $reservationData['reservation_form_type'] = $data['reservation_form_type'] ?? 1;

            $this->dao->createReservation($productId, $reservationData);
        }
    }
    /**
     * 发送后台通知
     *
     * @param array $data
     * @param integer $productId
     * @param integer $productType
     * @return void
     */
    protected function adminApplyNotify(array $data, int $productId, int $productType) : void
    {
        // 后台通知
        if (isset($data['status']) && $data['status'] !== 1) {
            $message = '您有1个新的' . ($productType ? '秒杀商品' : ($data['is_gift_bag'] ? '礼包商品' : '商品')) . '待审核';
            $type = $productType ? 'new_seckill' : ($data['is_gift_bag'] ? 'new_bag' : 'new_product');
            SwooleTaskService::admin('notice', [
                'type' => $type,
                'data' => ['title' => '商品审核', 'message' => $message, 'id' => $productId]
            ]);
        }
    }
    /**
     * 商品图片采集
     *
     * @param integer $productId
     * @param array $data
     * @return void
     */
    protected function productCollectImage(int $productId, array $data)
    {
        if (isset($data['is_copy']) && $data['is_copy']) {
            Queue::push(DownloadProductCopyImage::class, ['id' => $productId, 'data' => $data]);
        }
    }
    /**
     * 合并预约商品数据
     *
     * @param array $product
     * @return array
     */
    protected function mergeReservation(array $product) : array
    {
        if($product['reservation']) {
            $product = array_merge($product, $product['reservation']);
            unset($product['reservation']);
        }

        return $product;
    }
    /**
     * 格式化商品属性
     *
     * @param [type] $attrResult
     * @return array
     */
    protected function translateAttrResult(array $info) : array
    {
        $info['attr'] = [];
        $info['params'] = [];
        if(empty($info['attr_result'])) {
            unset($info['attr_result']);
            return $info;
        }

        $attrResult = json_decode($info['attr_result']['result'],true);
        unset($info['attr_result']);

        $info['attr'] = $attrResult['attr'];
        $info['params'] = $attrResult['params'];

        foreach ($info['attrValue'] as &$value) {
            $_sku = implode(',',array_values((array)$value['detail'] ?? []));
            $value['attr_arr'] = [$_sku];
        }
        foreach ($info['params'] as &$item) {
            $item['value'] = array_column($item['values'], 'value');
        }

        return $info;
    }
    /**
     * 清除商品缓存
     *
     * @param array $product
     * @return void
     */
    protected function clearProductCache(array $product)
    {
        ksort($product);
        $cache_unique = 'get_product_show_' . $product['product_id'] . '_' . md5(json_encode($product));
        Cache::delete($cache_unique);
    }
}
