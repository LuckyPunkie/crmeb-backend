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

namespace app\common\repositories\store;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\product\SpuRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 秒杀活动商品
 */
class StoreSeckillProductRepository extends BaseRepository
{

    /**
     * 批量添加秒杀商品
     * @param   $activeId
     * @param  $productList
     * @param  $status
     * @return void
     * FerryZhao 2024/4/12
     */
    public function createSeckillProduct($activeId,$productList,$status = 0)
    {
        if(!$activeId){
            throw new ValidateException('活动ID参数错误');
        }
        $spuRepository = app()->make(SpuRepository::class);
        if(!empty($productList) && is_array($productList)){
            //拆分处理
            $productListArr = array_chunk($productList,2);
            foreach ($productListArr as $item){
                foreach ($item as $value){
                    $value['product_type'] = 1;
                    $value['status'] = $status;//平台免审or店铺开启免审
                    $value['is_gift_bag'] = 0;
                    $value['cate_hot'] = 0;
                    $value['active_id'] = $activeId;
                    $product_id = app()->make(ProductRepository::class)->create($value,1,0,$activeId);
                    $spuRepository->changeStatus($product_id,1);
                }
            }
        }
    }

    /**
     * 批量修改秒杀商品
     * @param $activeId
     * @param $productList
     * @return void
     * FerryZhao 2024/4/13
     */
    public function updateSeckillProduct($activeId,$productList,$merId = null)
    {
        if(!$activeId){
            throw new ValidateException('活动ID参数错误');
        }
        $seckillActive = app()->make(StoreSeckillActiveRepository::class);
        $activeInfo = $seckillActive->get($activeId);
        if(!$activeInfo)
            throw new ValidateException('活动不存在');
        if(!$activeInfo['status'] && $merId)
            throw new ValidateException('活动已关闭，无法参与');
        Db::transaction(function () use ($activeId, $productList,$merId) {
            app()->make(ProductRepository::class)->deleteActiveProduct($activeId,$merId);
            if(!empty($productList)){
                $this->createSeckillProduct($activeId,$productList);
            }
        });
    }
}
