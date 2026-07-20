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
namespace app\common\dao\store\product;

use app\common\dao\BaseDao;
use app\common\model\store\product\Product;
use app\common\model\store\product\ProductCate;
use app\common\model\store\product\Spu;
use app\common\model\store\StoreCategory;
use app\common\model\store\parameter\ParameterProduct;
use app\common\repositories\store\StoreCategoryRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use crmeb\services\VicWordService;

class SpuDao extends  BaseDao
{
    public function getModel(): string
    {
        return Spu::class;
    }


    public function search($where)
    {
        // 1. 提前收集所有符合条件的 product_id
        $productIds = $this->collectProductIds($where);
        $query = Spu::alias('S')
            ->join('store_product P', 'S.product_id = P.product_id', 'LEFT')
            ->whereIn('S.product_id', $productIds);

        if ($productIds === false || empty($productIds)) {
            // 返回空查询，避免后续执行
            return $query;
        }

        // 2. 主查询：只查主表
        $query
            ->when(isset($where['keyword']) && $where['keyword'] !== '',function($query)use($where){
                if (is_numeric($where['keyword'])) {
                    $query->whereLike("S.store_name|S.keyword|S.product_id", "%{$where['keyword']}%");
                } else {
                    $word = app()->make(VicWordService::class)->getWord($where['keyword']);
                    $query->where(function ($query) use ($word) {
                        foreach ($word as $item) {
                            $query->whereOr('S.store_name|S.keyword', 'LIKE', "%$item%");
                        }
                    });
                }
            })
            ->when(isset($where['spu_id']) && $where['spu_id'] !== '', function ($query) use ($where) {
                $query->where('S.spu_id',$where['spu_id']);
            })
            ->when(isset($where['spu_ids']) && $where['spu_ids'] !== '', function ($query) use ($where) {
                $query->whereIn('S.spu_id',$where['spu_ids']);
            })
            ->when(isset($where['is_show']) && $where['is_show'] !== '', function ($query) use ($where) {
                $query->whereIn('P.is_show',$where['is_show']);
            })
            ->when(isset($where['common']) && $where['common'] !== '', function ($query) use ($where) {
                $query->whereIn('S.product_type', [0, 1]);
            })
            ->when(isset($where['price_on']) && $where['price_on'] !== '',function($query)use($where){
                $query->where('S.price','>=',$where['price_on']);
            })
            ->when(isset($where['price_off']) && $where['price_off'] !== '',function($query)use($where){
                $query->where('S.price','<=',$where['price_off']);
            })
            ->when(isset($where['product_type']) && $where['product_type'] !== '',function($query)use($where){
                $query->where('S.product_type',$where['product_type']);
            })
            ->when(isset($where['activity_id']) && $where['activity_id'] !== '',function($query)use($where){
                $query->where('S.activity_id',$where['activity_id']);
            })
            ->when(isset($where['product_id']) && $where['product_id'] !== '',function($query)use($where){
                $query->where('S.product_id',$where['product_id']);
            })
            ->when(isset($where['not_type']) && $where['not_type'] !== '',function($query)use($where){
                $query->whereNotIn('S.product_type',$where['not_type']);
            })
            ->when(isset($where['action']) && $where['action'] !== '',function($query)use($where){
                $query->where('S.product_type','>',0)->where('S.mer_id','<>',0);
            })
            ->when(isset($where['spu_status']) && $where['spu_status'] !== '',function($query)use($where){
                $query->where('S.status',$where['spu_status']);
            })
            ->when(isset($where['sys_labels']) && $where['sys_labels'] !== '',function($query)use($where){
                $query->whereLike('S.sys_labels',"%,{$where['sys_labels']},%");
            })
            ->when(isset($where['mer_labels']) && $where['mer_labels'] !== '',function($query)use($where){
                $query->whereLike('S.mer_labels',"%,{$where['mer_labels']},%");
            })

            ->when(isset($where['store_label_id']) && $where['store_label_id'] !== '',function($query)use($where){
                $lables = explode(',', $where['store_label_id']);
                foreach ($lables as $item) {
                    $query->whereFindInSet('S.sys_labels', $item);
                }
            })
            ->when(isset($where['mer_store_label_id']) && $where['mer_store_label_id'] !== '',function($query)use($where){
                $lables = explode(',', $where['mer_store_label_id']);
                foreach ($lables as $item) {
                    $query->whereFindInSet('S.mer_labels', $item);
                }
            })
            ->when(isset($where['scope']) && $where['scope'] !== '', function ($query) use ($where) {
                $scope = explode(',', $where['scope']);
                if ($scope[1] <= 0) {
                    $query->where('S.ot_price','<',$where['scope']);
                } else {
                    $query->where('S.ot_price','between',$scope);
                }
            })
        ;
        $query->order($this->buildOrderSql($where));

        return $query;
    }


    private function collectProductIds($where)
    {
        $query = Product::alias('P')->where('P.is_del', 0)
            ->join('StoreProductReservation R', 'P.product_id = R.product_id', 'LEFT');

        if (isset($where['product_type']) && $where['product_type'] != 20){
            $query->join('Merchant M', 'P.mer_id = M.mer_id', 'LEFT')
                ->where('M.status', 1)
                ->where('M.mer_state', 1);
        }

        $query
            ->when(isset($where['is_del']) && $where['is_del'] !== '',function($query)use($where){
                $query->where('P.is_del',$where['is_del']);
            })
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '',function($query)use($where){
                $query->where('P.mer_id',$where['mer_id']);
            })
            ->when(isset($where['mer_ids']) && $where['mer_ids'] !== '',function($query)use($where){
                $query->whereIn('P.mer_id',$where['mer_ids']);
            })
            ->when(isset($where['product_ids']) && $where['product_ids'] !== '',function($query)use($where){
                $query->whereIn('P.product_id',$where['product_ids']);
            })
            ->when(isset($where['is_trader']) && $where['is_trader'] !== '',function($query)use($where){
                $merId = app()->make(MerchantRepository::class)->search([
                    'is_trader' => $where['is_trader'],
                    'status' => 1,
                    'mer_state' => 1,
                    'is_del' => 1,
                ])->column('mer_id');
                $query->whereIn('P.mer_id',$merId);
            })
            ->when(isset($where['mer_type_id']) && $where['mer_type_id'] !== '',function($query)use($where){
                $merId = app()->make(MerchantRepository::class)->search([
                    'type_id' => $where['mer_type_id'],
                    'status' => 1,
                    'mer_state' => 1,
                    'is_del' => 1,
                ])->column('mer_id');
                $query->whereIn('P.mer_id',$merId);
            })

            ->when(isset($where['cate_pid']) && $where['cate_pid'], function ($query) use ($where) {
                $storeCategoryRepository = app()->make(StoreCategoryRepository::class);
                if (is_array($where['cate_pid'])) {
                    $cateIds = $storeCategoryRepository->selectChildrenId($where['cate_pid']);
                } else {
                    $cateIds = $storeCategoryRepository->findChildrenId((int)$where['cate_pid']);
                    $where['cate_pid'] = [$where['cate_pid']];
                }
                $cate = array_merge($cateIds, $where['cate_pid']);
                $query->whereIn('P.cate_id', $cate);
            })
            ->when(isset($where['pid']) && $where['pid'] !== '', function ($query) use ($where) {
                $query->join('StoreCategory CT','P.cate_id = CT.store_category_id')->where('CT.pid',$where['pid']);
            })
            ->when(isset($where['cate_id']) && $where['cate_id'] !== '', function ($query) use ($where) {
                $query->whereIn('P.cate_id', $where['cate_id']);
            })

            ->when(isset($where['is_stock']) && !empty($where['is_stock']), function ($query) use ($where) {
                $query->where('P.stock','>',0);
            })
            ->when(isset($where['is_coupon']) && !empty($where['is_coupon']), function ($query) use ($where) {
                $query->whereIn('P.product_type','0,2');
            })
            ->when(isset($where['brand_id']) && $where['brand_id'] !== '', function ($query) use ($where) {
                $query->whereIn('P.brand_id', array_map('intval', explode(',', $where['brand_id'])));
            })
            ->when(isset($where['is_gift_bag']) && $where['is_gift_bag'] !== '',function($query)use($where){
                $query->where('P.is_gift_bag',$where['is_gift_bag']);
            })

            ->when(isset($where['mer_cate_id']) && $where['mer_cate_id'] !== '',function($query)use($where){
                $merCateIds = explode(',', $where['mer_cate_id']);
                $storeCategory = StoreCategory::getDB();
                foreach ($merCateIds as $item) {
                    $storeCategory->whereOr('path','like','%/'.$item.'/%');
                }
                $ids = $storeCategory->column('store_category_id');
                $ids = array_unique(array_merge($merCateIds,$ids));
                $productId = ProductCate::where('mer_cate_id', 'in', $ids)->column('product_id');
                $productId = array_unique($productId);
                $query->where('P.product_id','in',$productId);
            })
            ->when(isset($where['mer_status']) && $where['mer_status'] !== '',function($query)use($where){
                $query->where('mer_status',$where['mer_status']);
            })

            ->when(isset($where['delivery_way']) && $where['delivery_way'] !== '', function ($query) use ($where) {
                $query->whereLike('P.delivery_way',"%{$where['delivery_way']}%");
            })
            ->when(isset($where['store_type_id']) && $where['store_type_id'] !== '', function ($query) use ($where) {
                $types = explode(',', $where['store_type_id']);
                foreach ($types as &$item) {
                    $item--;
                }
                $query->whereIn('P.type', $types);
            })
            ->when(isset($where['hot_type']) && $where['hot_type'] !== '', function ($query) use ($where) {
                if ($where['hot_type'] == 'new') $query->where('P.is_new', 1);
                else if ($where['hot_type'] == 'hot') $query->where('P.is_hot', 1);
                else if ($where['hot_type'] == 'best') $query->where('P.is_best', 1);
                else if ($where['hot_type'] == 'good') $query->where('P.is_benefit', 1);
            })
            ->when(isset($where['svip']) && $where['svip'] !== '',function($query)use($where){
                $query->where('P.svip_price_type','>',0)->where('P.mer_svip_status',1);
            })
            ->when(isset($where['product_type']) && $where['product_type'] !== '',function($query)use($where){
                $query->where('P.product_type',$where['product_type']);
            })
            ->when(isset($where['delivery_type']) && $where['delivery_type'] !== '',function($query)use($where){
                $productWay = [];
                $reservationWay = [];
                foreach(explode(',', $where['delivery_type']) as $item){
                    if($item <= 3) {
                        $productWay[] = $item;
                    }else{
                        $reservationWay[] = $item;
                    }
                }
                $reservationWhere = [];
                if(in_array('4', $reservationWay)){
                    $reservationWhere = ['R.reservation_type' => 2]; // 上门
                }
                if(in_array('5', $reservationWay)){
                    $reservationWhere = ['R.reservation_type' => 1]; // 到店
                }
                if(in_array('4', $reservationWay) && in_array('5', $reservationWay)){ //上门+到店
                    $reservationWhere = ['R.reservation_type' => 3];
                }
                if($productWay && !$reservationWhere){
                    $query->where('P.type','<>',4);
                    foreach($productWay as $item){
                        if($item <= 3) {
                            $query->whereFindInSet('P.delivery_way', $item);
                        }
                    }
                }
                if(!$productWay && $reservationWhere){
                    $query->where(function($query)use($reservationWhere){
                        $query->where('P.type',4);
                        $query->where($reservationWhere);
                    });
                }
                if($productWay && $reservationWhere){
                    $query->where(function($query)use($productWay, $reservationWhere){
                        $query->where(function($query)use($productWay){
                            $query->where('P.type','<>',4);
                            foreach($productWay as $item){
                                if($item <= 3) {
                                    $query->whereFindInSet('P.delivery_way', $item);
                                }
                            }
                        });
                        $query->whereOr(function($query)use($reservationWhere){
                            $query->where('P.type',4);
                            $query->where($reservationWhere);
                        });
                    });
                }
            })
        ;
        return $query->column('P.product_id') ?: false;
    }

    private function buildOrderSql($where)
    {
        $orderMappings = [
            'none'          => '',
            'is_new'        => 'P.is_new DESC,',
            'rate'          => 'P.rate DESC,',
            'sort'          => 'P.sort DESC,',
            'sales'         => 'P.sales DESC,',
            'range_asc'     => 'P.sort DESC,',
            'price_asc'     => 'S.price ASC,',
            'price_desc'    => 'S.price DESC,',
            'sales_asc'     => 'P.sales ASC,',
            'sales_desc'    => 'P.sales DESC,',
            'ot_price'      => 'S.ot_price DESC,',
            'ot_price_asc'  => 'S.ot_price ASC,',
            'ot_price_desc' => 'S.ot_price DESC,',
            'distance_asc'  => 'S.star ASC,S.rank ASC,',
            'distance_desc' => 'S.star DESC,S.rank DESC,',
        ];
        if(!isset($where['order'])){
            $where['order'] = 'none';
        }
        $order = ($orderMappings[$where['order']] ?? '') . 'S.create_time DESC';
        return $order;
    }


    /**
     * spu搜索
     * @param $where
     * @return mixed
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/17
     */
    public function search1($where)
    {
        $query = Spu::getDB()->alias('S')->join('StoreProductReservation R','S.product_id = R.product_id', 'left')->join('StoreProduct P','S.product_id = P.product_id', 'left');
        $query->when(isset($where['filter_params']) && $where['filter_params'] !== '',function($query) use($where){
            $query->join('ParameterProduct T','S.product_id = T.product_id')->whereIn('T.parameter_value_id', $where['filter_params']);
        });
        $query->when(isset($where['is_del']) && $where['is_del'] !== '',function($query)use($where){
                $query->where('P.is_del',$where['is_del']);
            })
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '',function($query)use($where){
                $query->where('P.mer_id',$where['mer_id']);
            })
            ->when(isset($where['mer_ids']) && $where['mer_ids'] !== '',function($query)use($where){
                $query->whereIn('P.mer_id',$where['mer_ids']);
            })
            ->when(isset($where['product_ids']) && $where['product_ids'] !== '',function($query)use($where){
                $query->whereIn('P.product_id',$where['product_ids']);
            })
            ->when(isset($where['keyword']) && $where['keyword'] !== '',function($query)use($where){
                if (is_numeric($where['keyword'])) {
                    $query->whereLike("S.store_name|S.keyword|S.product_id", "%{$where['keyword']}%");
                } else {
                    $word = app()->make(VicWordService::class)->getWord($where['keyword']);
                    $query->where(function ($query) use ($word) {
                        foreach ($word as $item) {
                            $query->whereOr('S.store_name|S.keyword', 'LIKE', "%$item%");
                        }
                    });
                }
            })
            ->when(isset($where['is_trader']) && $where['is_trader'] !== '',function($query)use($where){
                $merId = app()->make(MerchantRepository::class)->search([
                    'is_trader' => $where['is_trader'],
                    'status' => 1,
                    'mer_state' => 1,
                    'is_del' => 1,
                ])->column('mer_id');

                $query->whereIn('P.mer_id',$merId);
            })
            ->when(isset($where['mer_type_id']) && $where['mer_type_id'] !== '',function($query)use($where){
                $merId = app()->make(MerchantRepository::class)->search([
                    'type_id' => $where['mer_type_id'],
                    'status' => 1,
                    'mer_state' => 1,
                    'is_del' => 1,
                ])->column('mer_id');

                $query->whereIn('P.mer_id',$merId);
            })
            ->when(isset($where['cate_pid']) && $where['cate_pid'], function ($query) use ($where) {
                $storeCategoryRepository = app()->make(StoreCategoryRepository::class);
                if (is_array($where['cate_pid'])) {
                    $cateIds = $storeCategoryRepository->selectChildrenId($where['cate_pid']);
                } else {
                    $cateIds = $storeCategoryRepository->findChildrenId((int)$where['cate_pid']);
                    $where['cate_pid'] = [$where['cate_pid']];
                }
                $cate = array_merge($cateIds, $where['cate_pid']);
                $query->whereIn('P.cate_id', $cate);
            })
            ->when(isset($where['cate_id']) && $where['cate_id'] !== '', function ($query) use ($where) {
                $query->whereIn('P.cate_id', $where['cate_id']);
            })
            ->when(isset($where['spu_id']) && $where['spu_id'] !== '', function ($query) use ($where) {
                $query->where('S.spu_id',$where['spu_id']);
            })
            ->when(isset($where['spu_ids']) && $where['spu_ids'] !== '', function ($query) use ($where) {
                $query->whereIn('S.spu_id',$where['spu_ids']);
            })
            ->when(isset($where['is_stock']) && !empty($where['is_stock']), function ($query) use ($where) {
                $query->where('P.stock','>',0);
            })
            ->when(isset($where['is_coupon']) && !empty($where['is_coupon']), function ($query) use ($where) {
                $query->whereIn('P.product_type','0,2');
            })
            ->when(isset($where['common']) && $where['common'] !== '', function ($query) use ($where) {
                $query->whereIn('S.product_type', [0, 1]);
            })
            ->when(isset($where['price_on']) && $where['price_on'] !== '',function($query)use($where){
                $query->where('S.price','>=',$where['price_on']);
            })
            ->when(isset($where['price_off']) && $where['price_off'] !== '',function($query)use($where){
                $query->where('S.price','<=',$where['price_off']);
            })
            ->when(isset($where['brand_id']) && $where['brand_id'] !== '', function ($query) use ($where) {
                $query->whereIn('P.brand_id', array_map('intval', explode(',', $where['brand_id'])));
            })
            ->when(isset($where['is_gift_bag']) && $where['is_gift_bag'] !== '',function($query)use($where){
                $query->where('P.is_gift_bag',$where['is_gift_bag']);
            })
            ->when(isset($where['product_type']) && $where['product_type'] !== '',function($query)use($where){
                $query->where('S.product_type',$where['product_type']);
            })
            ->when(isset($where['activity_id']) && $where['activity_id'] !== '',function($query)use($where){
                $query->where('S.activity_id',$where['activity_id']);
            })
            ->when(isset($where['product_id']) && $where['product_id'] !== '',function($query)use($where){
                $query->where('S.product_id',$where['product_id']);
            })
            ->when(isset($where['not_type']) && $where['not_type'] !== '',function($query)use($where){
                $query->whereNotIn('S.product_type',$where['not_type']);
            })
            ->when(isset($where['action']) && $where['action'] !== '',function($query)use($where){
                $query->where('S.product_type','>',0)->where('S.mer_id','<>',0);
            })
            ->when(isset($where['mer_cate_id']) && $where['mer_cate_id'] !== '',function($query)use($where){
                $merCateIds = explode(',', $where['mer_cate_id']);
                $storeCategory = StoreCategory::getDB();
                foreach ($merCateIds as $item) {
                    $storeCategory->whereOr('path','like','%/'.$item.'/%');
                }
                $ids = $storeCategory->column('store_category_id');
                $ids = array_unique(array_merge($merCateIds,$ids));
                $productId = ProductCate::where('mer_cate_id', 'in', $ids)->column('product_id');
                $productId = array_unique($productId);
                $query->where('P.product_id','in',$productId);
            })
            ->when(isset($where['mer_status']) && $where['mer_status'] !== '',function($query)use($where){
                $query->where('mer_status',$where['mer_status']);
            })
            ->when(isset($where['spu_status']) && $where['spu_status'] !== '',function($query)use($where){
                $query->where('S.status',$where['spu_status']);
            })
            ->when(isset($where['sys_labels']) && $where['sys_labels'] !== '',function($query)use($where){
                $query->whereLike('S.sys_labels',"%,{$where['sys_labels']},%");
            })
            ->when(isset($where['mer_labels']) && $where['mer_labels'] !== '',function($query)use($where){
                $query->whereLike('S.mer_labels',"%,{$where['mer_labels']},%");
            })
            ->when(isset($where['pid']) && $where['pid'] !== '', function ($query) use ($where) {
                $query->join('StoreCategory CT','P.cate_id = CT.store_category_id')->where('CT.pid',$where['pid']);
            })
            ->when(isset($where['delivery_way']) && $where['delivery_way'] !== '', function ($query) use ($where) {
                $query->whereLike('P.delivery_way',"%{$where['delivery_way']}%");
            })
            ->when(isset($where['store_type_id']) && $where['store_type_id'] !== '', function ($query) use ($where) {
                $types = explode(',', $where['store_type_id']);
                foreach ($types as &$item) {
                    $item--;
                }
                $query->whereIn('P.type', $types);
            })
            ->when(isset($where['store_label_id']) && $where['store_label_id'] !== '',function($query)use($where){
                $lables = explode(',', $where['store_label_id']);
                foreach ($lables as $item) {
                    $query->whereFindInSet('S.sys_labels', $item);
                }
            })
            ->when(isset($where['mer_store_label_id']) && $where['mer_store_label_id'] !== '',function($query)use($where){
                $lables = explode(',', $where['mer_store_label_id']);
                foreach ($lables as $item) {
                    $query->whereFindInSet('S.mer_labels', $item);
                }
            })
            ->when(isset($where['scope']) && $where['scope'] !== '', function ($query) use ($where) {
                $scope = explode(',', $where['scope']);
                if ($scope[1] <= 0) {
                    $query->where('S.ot_price','<',$where['scope']);
                } else {
                    $query->where('S.ot_price','between',$scope);
                }
            })
            ->when(isset($where['hot_type']) && $where['hot_type'] !== '', function ($query) use ($where) {
                if ($where['hot_type'] == 'new') $query->where('P.is_new', 1);
                else if ($where['hot_type'] == 'hot') $query->where('P.is_hot', 1);
                else if ($where['hot_type'] == 'best') $query->where('P.is_best', 1);
                else if ($where['hot_type'] == 'good') $query->where('P.is_benefit', 1);
            })
            ->when(isset($where['svip']) && $where['svip'] !== '',function($query)use($where){
                $query->where('P.svip_price_type','>',0)->where('P.mer_svip_status',1);
            })
            ->when(isset($where['delivery_type']) && $where['delivery_type'] !== '',function($query)use($where){
                $productWay = [];
                $reservationWay = [];
                foreach(explode(',', $where['delivery_type']) as $item){
                    if($item <= 3) {
                        $productWay[] = $item;
                    }else{
                        $reservationWay[] = $item;
                    }
                }
                $reservationWhere = [];
                if(in_array('4', $reservationWay)){
                    $reservationWhere = ['R.reservation_type' => 2]; // 上门
                }
                if(in_array('5', $reservationWay)){
                    $reservationWhere = ['R.reservation_type' => 1]; // 到店
                }
                if(in_array('4', $reservationWay) && in_array('5', $reservationWay)){ //上门+到店
                    $reservationWhere = ['R.reservation_type' => 3];
                }
                if($productWay && !$reservationWhere){
                    $query->where('P.type','<>',4);
                    foreach($productWay as $item){
                        if($item <= 3) {
                            $query->whereFindInSet('P.delivery_way', $item);
                        }
                    }
                }
                if(!$productWay && $reservationWhere){
                    $query->where(function($query)use($reservationWhere){
                        $query->where('P.type',4);
                        $query->where($reservationWhere);
                    });
                }
                if($productWay && $reservationWhere){
                    $query->where(function($query)use($productWay, $reservationWhere){
                        $query->where(function($query)use($productWay){
                            $query->where('P.type','<>',4);
                            foreach($productWay as $item){
                                if($item <= 3) {
                                    $query->whereFindInSet('P.delivery_way', $item);
                                }
                            }
                        });
                        $query->whereOr(function($query)use($reservationWhere){
                                $query->where('P.type',4);
                                $query->where($reservationWhere);
                        });
                    });
                }
            })
        ;
        return $query->order($this->buildOrderSql($where));
    }

    /**
     * 根据给定的条件查找或创建数据。
     * 该方法遍历一个条件数组，并对每个条件尝试查找对应的数据记录。
     * 如果找不到，则根据条件创建新记录。
     * @param array $where 包含多个查询条件的数组，每个条件可能包含product_id, product_type, activity_id。
     */
    public function findOrCreateAll(array $where)
    {
        // 遍历条件数组
        foreach ($where as $item) {
            // 确保activity_id有值，如果未指定，则默认为0
            $item['activity_id'] = $item['activity_id'] ?? 0;

            // 根据条件查询数据库，尝试找到匹配的记录
            $data = $this->getModel()::getDB()->where('product_id', $item['product_id'])
                ->where('product_type', $item['product_type'])
                ->where('activity_id', $item['activity_id'])
                ->find();

            // 如果查询结果为空，则意味着该条件下的记录不存在，需要创建新记录
            if (!$data) {
                $this->create($item);
            }
        }
    }


    /**
     * 删除产品记录
     *
     * 本函数用于更新数据库中指定产品ID的记录的is_del字段，标记该产品为已删除。
     * 默认情况下，is_del字段被设置为1，表示产品被逻辑删除。可以通过传入不同的参数来修改这个行为。
     *
     * @param int $id 产品的唯一标识ID
     * @param int $isDel 标记删除状态的字段值，默认为1，表示已删除。可以根据需要传入其他值。
     */
    public function delProduct($id, $isDel = 1)
    {
        // 通过getModel方法获取数据库操作对象，并根据产品ID更新is_del字段
        $this->getModel()::getDb()->where('product_id', $id)->update(['is_del' => $isDel]);
    }

    /**
     * 批量更新产品的删除状态
     * @param array $ids 产品ID数组
     * @param int $isDel 删除状态值，默认为1
     */
    public function batchDelProduct(array $ids, $isDel = 1)
    {
        $this->getModel()::getDb()->whereIn('product_id', $ids)->update(['is_del' => $isDel]);
    }


    /**
     * 获取指定类型下激活状态的商品分类路径
     *
     * 本函数用于查询特定类型的商品所属于的激活状态分类的路径。
     * 通过JOIN操作关联了Spu、StoreProduct和StoreCategory三个表，
     * 以获取商品ID和分类ID之间的关联，并筛选出状态为激活且显示状态为是的分类。
     *
     * @param string $type 商品类型标识
     * @return array 返回一个包含分类路径的数组
     */
    public function getActivecategory($type)
    {
        // 初始化查询，设置表别名为S，并JOIN产品和分类表
        $query = Spu::getDB()->alias('S')->join('StoreProduct P','S.product_id = P.product_id')
            ->join('StoreCategory C','C.store_category_id = P.cate_id');

        // 筛选条件：商品状态为激活，商品类型为参数类型，分类显示状态为是
        $query->where('S.status',1)->where('S.product_type',$type)->where('C.is_show',1);

        // 分组查询以确保每个产品ID只出现一次，并返回分类路径列
        return $query->group('S.product_id')->column('C.path');
    }

    /**
     * 清理指定商户的产品数据
     *
     * 本函数用于将指定商户的所有产品标记为删除状态。它通过修改产品表中相应记录的is_del字段来实现。
     * 这里选择不直接物理删除记录，以防止数据误删导致的不可恢复后果。标记删除允许后续有条件地恢复数据，
     * 同时也保持了数据的完整性。
     *
     * @param int $merId 商户ID，用于指定要清理的产品所属的商户。
     */
    public function clearProduct($merId)
    {
        // 根据$merId更新产品表中is_del字段为1，标记这些产品为删除状态
        $this->getModel()::getDb()->where('mer_id', $merId)->update(['is_del' => 1]);
    }


    /**
     * 清除特定字段中具有指定ID的记录。
     *
     * 此方法通过提供的ID和字段名称，从数据库中删除符合条件的记录。
     * 它首先获取模型对应的数据库实例，然后使用提供的字段和ID构建删除条件，
     * 最后执行删除操作。
     *
     * @param int $id 主键ID，用于指定要删除的记录。
     * @param string $field 要用于删除条件的字段名称。
     */
    public function clear(int $id, string $field)
    {
        $this->getModel()::getDB()->where($field, $id)->delete();
    }


    /**
     * 更新商品价格
     * 本函数用于特定商家ID和商品ID的情况下，更新商品的价格。
     * 这是通过查询数据库中匹配给定商家ID和商品ID的商品，并将其价格更新为新的价格来实现的。
     *
     * @param int $mer_id 商家ID，用于限定查询的商家范围。
     * @param int $product_id 商品ID，用于指定需要更新价格的具体商品。
     * @param float $price 新的商品价格，这是需要更新到数据库的值。
     * @return bool 返回更新操作的结果，成功为true，失败为false。
     */
    public function updatePrice($mer_id, $product_id, $price)
    {
        // 使用模型获取数据库实例，并构建更新查询条件，特定商家ID、商品ID和商品类型为0，然后更新商品价格
        return $this->getModel()::getDB()->where('mer_id', $mer_id)->where('product_id', $product_id)->where('product_type', 0)->update(['price' => $price]);
    }

}
