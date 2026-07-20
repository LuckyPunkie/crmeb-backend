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

namespace app\common\dao\store;

use app\common\model\store\StoreSeckillActive;
use app\common\dao\BaseDao;
use app\common\repositories\store\order\StoreOrderProductRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\product\ProductAttrValueRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\product\SpuRepository;
use app\common\repositories\store\StoreSeckillActiveRepository;
use think\facade\Log;

class StoreSeckillActiveDao extends BaseDao
{

    /**
     *
     * @return string
     * @author Qinii
     * @day 2020-07-30
     */
    public function getModel(): string
    {
        return StoreSeckillActive::class;
    }


    /**
     * 搜索
     * @param array $where
     * @return mixed
     * FerryZhao 2024/4/12
     */
    public function search(array $where)
    {
        $query = $this->getModel()::getDB()
            ->when(isset($where['name']) && $where['name'] !== '', function ($query) use ($where) {
                $query->where('name', 'like', '%' . $where['name'] . '%');
            })
            ->when(isset($where['active_status']) && $where['active_status'] !== '', function ($query) use ($where) {
                $query->where('active_status', $where['active_status']);
            })
            ->when(isset($where['seckill_active_status']) && $where['seckill_active_status'] !== '', function ($query) use ($where) {
                $query->where('status', $where['seckill_active_status']);
            })
            ->when(isset($where['seckill_active_id']) && $where['seckill_active_id'] !== '', function ($query) use ($where) {
                $query->where('seckill_active_id', $where['seckill_active_id']);
            })
            ->when(isset($where['active_name']) && $where['active_name'] !== '', function ($query) use ($where) {
                $query->whereLike('name', "%{$where['active_name']}%");
            })
            ->when(isset($where['active_name']) && $where['active_name'] !== '', function ($query) use ($where) {
                $query->whereLike('name', "%{$where['active_name']}%");
            })
            ->when(!isset($where['sign']), function ($query) use ($where) {
                $query->where('sign', 1);
            })
            ->when(isset($where['date']) && $where['date'], function ($query) use ($where) {
                $timeWhere = explode('-', $where['date']);
                $query->whereTime('start_day', '>=', $timeWhere[0]);
                $query->whereTime('end_day', '<=', date('Y-m-d', strtotime($timeWhere[1]) + 24 * 60 * 60));
            });

        $query->order('status desc,seckill_active_id desc');
        return $query;
    }

    /**
     * 检测活动状态关闭spu
     * @return void
     * FerryZhao 2024/4/19
     */
    public function valActiveStatus()
    {
        try {
            $storeSeckillActiveRepository = app()->make(StoreSeckillActiveRepository::class);
            $changeStartIds = $this->getModel()::getDB()->where(['active_status' => 0])->whereTime('start_day', '<', date('Y-m-d H:i:s', time()))->whereTime('end_day', '>', time())->column('seckill_active_id');
            if (!empty($changeStartIds)) {
                $storeSeckillActiveRepository->getSearch([])->whereIn('seckill_active_id', $changeStartIds)->update(['active_status' => 1]);
            }
            $changeStartingIds = $this->getModel()::getDB()->where(['active_status' => 1])->whereTime('end_day', '<', time())->column('seckill_active_id');
            if (!empty($changeStartingIds)) {
                $storeSeckillActiveRepository->getSearch([])->whereIn('seckill_active_id', $changeStartingIds)->update(['active_status' => '-1']);
                app()->make(SpuRepository::class)->getSearch([
                    'product_type' => 1,
                    'activity_ids' => $changeStartingIds
                ])->update(['status' => 0]);
            }
        } catch (\Exception $e) {
            Log::error('检测活动状态关闭spu失败，' . $e->getMessage());
        }

    }

    /**
     *  不同状态商品
     * @param $status
     * @return mixed
     * @author Qinii
     * @day 2020-08-19
     */
    public function getStatus($status)
    {
        $day = date('Y-m-d', time());
        $_h = date('H', time());
        $query = $this->getModel()::getDB();
        if ($status == 1) //未开始
            $query->where('status', '<>', -1)->where(function ($query) use ($day, $_h) {
                $query->whereTime('start_day', '>', $day)->whereOr(function ($query) use ($day, $_h) {
                    $query->whereTime('start_day', '<=', $day)->where('start_time', '>', $_h);
                });
            });

        if ($status == 2)//进行中
            $query->where('status', 1)
                ->whereTime('start_day', '<=', $day)->whereTime('end_day', '>', $day)
                ->where('start_time', '<=', $_h)->where('end_time', '>', $_h);

        if ($status == 3) //结束
            $query->where('status', -1)->whereOr(function ($query) use ($day, $_h) {
                $query->whereTime('end_day', '<', $day)
                    ->whereOr(function ($query) use ($day, $_h) {
                        $query->whereTime('start_day', '<=', $day)->whereTime('end_day', '>=', $day)->where('end_time', '<=', $_h);
                    });
            });
        return $query;
    }

    /**
     * 活动参与人列表统计
     * @param $activeId
     * @param $merId
     * @param $where
     * @param int $page
     * @param int $limit
     * @return void
     * FerryZhao 2024/4/28
     */
    public function chartPeople($activeId, $where, $merId = null, int $page = 1, int $limit = 10)
    {
        $storeOrderRepository = app()->make(StoreOrderRepository::class);
        $query = $storeOrderRepository->getSearch([])->alias('ORDERA')
            ->leftJoin('StoreOrderProduct ORDERB', 'ORDERA.order_id = ORDERB.order_id')
            ->leftJoin('User USER', 'USER.uid = ORDERA.uid')
            ->when($merId, function ($query) use ($merId) {
                $query->where('ORDERA.mer_id', '=', $merId);
            })
            ->when(isset($where['keyword']) && $where['keyword'], function ($query) use ($where) {
                $query->whereLike('USER.uid|USER.nickname|USER.phone', "%{$where['keyword']}%");
            })
            ->when(isset($where['date']) && $where['date'], function ($query) use ($where) {
                getModelTime($query, $where['date'], 'ORDERA.create_time');
            })
            ->where([
                'ORDERB.activity_id' => $activeId,
                'ORDERA.paid' => 1,
                'ORDERA.activity_type' => 1,
            ])
            ->where('ORDERA.status','>',-1)
            ->group('ORDERA.uid');
        $count = $query->count();
        $list = $query->page($page, $limit)
            ->field('sum(ORDERA.total_num) as sum_total_num,sum(ORDERA.pay_price) as sum_pay_price,USER.nickname,USER.uid,count(ORDERA.order_id) as order_count,max(ORDERA.create_time) as create_time,ORDERA.is_del,ORDERA.paid,ORDERA.order_type')
            ->select();
        return compact('count', 'list');
    }


    /**
     * 活动订单统计列表
     * @param $activeId
     * @param $merId
     * @param $where
     * @param int $page
     * @param int $limit
     * @return void
     * FerryZhao 2024/4/28
     */
    public function chartOrder($activeId, $where, $statusWhere = [], $merId = null, int $page = 1, int $limit = 10)
    {
        $storeOrderRepository = app()->make(StoreOrderRepository::class);
        $orderWhere = [
            'ORDERB.activity_id' => $activeId,
        ];
        if (isset($where['status']) && $where['status'] != '') {
            $orderWhere['StoreOrder.status'] = $where['status'];
        }
        $query = $storeOrderRepository->getSearch([])->alias('StoreOrder')
            ->leftJoin('User USER', 'StoreOrder.uid = USER.uid')
            ->leftJoin('StoreOrderProduct ORDERB', 'StoreOrder.order_id = ORDERB.order_id')
            ->when(isset($where['keyword']) && $where['keyword'], function ($query) use ($where) {
                $query->whereLike('USER.uid|USER.nickname|USER.phone', "%{$where['keyword']}%");
            })
            ->when($merId, function ($query) use ($merId) {
                $query->where('StoreOrder.mer_id', '=', $merId);
            })
            ->when(isset($where['date']) && $where['date'], function ($query) use ($where) {
                getModelTime($query, $where['date'], 'StoreOrder.create_time');
            })
            ->where($orderWhere)->where($statusWhere);
        $count = $query->count();
        $list = $query->page($page, $limit)
            ->field('StoreOrder.order_sn,USER.nickname,USER.uid,StoreOrder.status,StoreOrder.pay_price,StoreOrder.total_num,StoreOrder.create_time,StoreOrder.pay_time,StoreOrder.is_del,StoreOrder.paid,StoreOrder.order_type')
            ->order('StoreOrder.order_id desc')
            ->select();
        return compact('count', 'list');
    }

    /**
     * 活动商品统计列表
     * @param $activeId
     * @param $merId
     * @param $where
     * @param int $page
     * @param int $limit
     * @return void
     * FerryZhao 2024/4/28
     */
    public function chartProduct($activeId, $where, $merId = null, int $page = 1, int $limit = 10)
    {
        $productRepository = app()->make(ProductRepository::class);
        $storeOrderRepository = app()->make(StoreOrderRepository::class);
        $storeOrderProductRepository = app()->make(StoreOrderProductRepository::class);
        $productAttrValueRepository = app()->make(ProductAttrValueRepository::class);
        $productWhere = [
            'product_type' => 1,
            'seckill_active_id' => $activeId
        ];
        if (isset($where['keyword']) && $where['keyword']) {
            $productWhere['keyword'] = $where['keyword'];
        }
        $with = ['attr', 'attrValue', 'merCateId.category', 'storeCategory', 'content', 'seckillActive',
            'merchant' => function ($query) {
                $query->with(['typeName', 'categoryName'])->field('mer_id,category_id,type_id,mer_avatar,mer_name,is_trader');
            },

        ];
        $query = $productRepository->search($merId, $productWhere)->with($with);
        $count = $query->count();
        $filed = 'Product.product_id,Product.active_id,Product.mer_id,Product.brand_id,Product.unit_name,Product.spec_type,Product.mer_status,Product.rate,Product.reply_count,Product.store_info,Product.cate_id,Product.image,Product.slider_image,Product.store_name,Product.keyword,Product.sort,Product.is_show,Product.sales,Product.price,Product.extension_type,Product.refusal,Product.cost,Product.ot_price,Product.stock,Product.is_gift_bag,Product.care_count,Product.status,Product.is_used,Product.create_time,Product.product_type,Product.old_product_id,Product.integral_total,Product.integral_price_total,Product.is_good,Product.is_del,Product.type,Product.param_temp_id,Product.mer_svip_status,Product.svip_price,Product.svip_price_type';
        $list = $query->page($page, $limit)->setOption('field', [])
        ->where(['Product.is_del' => 0, 'Product.delete' => 0])
        ->field($filed)
        ->select();
        foreach ($list as &$item){
            $item['sales'] = $storeOrderRepository->seckillOrderCounut($item['active_id'], $item['product_id']);
            $item['stock'] = $productAttrValueRepository->getSearch([])->where(['product_id'=>$item['product_id']])->sum('stock');
        }
        return compact('count', 'list');
    }
}
