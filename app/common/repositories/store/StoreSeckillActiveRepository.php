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

use app\common\dao\store\StoreSeckillActiveDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\order\StoreOrderProductRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\product\SpuRepository;
use app\common\repositories\user\UserRepository;
use app\controller\api\store\product\StoreSpu;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Db;

class StoreSeckillActiveRepository extends BaseRepository
{

    /**
     * @var StoreSeckillActiveDao
     */
    protected $dao;

    protected $filed = 'seckill_active_id,name,seckill_time_ids,start_day,end_day,once_pay_count,all_pay_count,product_category_ids,status,active_status,product_count,merchant_count,create_time,update_time';

    /**
     * StoreSeckillActiveDao constructor.
     * @param StoreSeckillActiveDao $dao
     */
    public function __construct(StoreSeckillActiveDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取秒杀活动列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * FerryZhao 2024/4/11
     */
    public function getList(array $where, int $page, int $limit, array $append = [])
    {
        $query = $this->dao->search($where)->append($append, true);
        $count = $query->count();
        $list = $query->page($page, $limit)->setOption('field', [])->field($this->filed)->select();
        return compact('count', 'list');
    }

    /**
     * 返回所有列表
     * @return
     * FerryZhao 2024/4/12
     */
    public function getActiveAll()
    {
        return $this->dao->getSearch([])->where('status', '=', 1)->where('active_status', '<>', -1)->field('name as lable,seckill_active_id as value,active_status')->select();
    }

    /**
     * 创建秒杀活动
     * @param $data
     * @return object
     * FerryZhao 2024/4/11
     */
    public function create($data)
    {
        //场次ID
        if (isset($data['seckill_time_ids']) && !empty($data['seckill_time_ids'])) {
            $data['seckill_time_ids'] = implode(',', $data['seckill_time_ids']);
        }
        //活动平台商品一级分类
        if (isset($data['product_category_ids']) && !empty($data['product_category_ids'])) {
            $data['product_category_ids'] = implode(',', $data['product_category_ids']);
        }
        $activity = app()->make(StoreActivityRepository::class);
        $result = $this->dao->create($data);
        if (!$result) {
            throw new ValidateException('活动添加失败');
        }

        if (isset($data['border_pic']) && !empty($data['border_pic'])) {
            //边框图
            $activity->saveByType([
                'activity_name' => $data['name'],
                'start_time' => $data['start_day'],
                'end_time' => $data['end_day'],
                'pic' => $data['border_pic'],
                'link_id' => $result->seckill_active_id,
                'activity_type' => $activity::ACTIVITY_TYPE_BORDER
            ], 1);
        }
        return $result;
    }


    /**
     * 添加商品修改商家数量和商品数量
     * @return true|void
     * FerryZhao 2024/4/24
     */
    public function updateActiveChart($activeId)
    {
        $productRepository = app()->make(ProductRepository::class);
        $productCount = $productRepository->getSearch([])->where(['active_id' => $activeId, 'is_del' => 0])->count();
        $merchantCount = $productRepository->getSearch([])->where(['active_id' => $activeId, 'is_del' => 0])->group('mer_id')->count();
        $this->dao->update($activeId, ['product_count' => $productCount, 'merchant_count' => $merchantCount]);
    }


    /**
     * 编辑秒杀活动
     * @param int $activeId 活动ID
     * @param $data
     * @return void
     * FerryZhao 2024/4/12
     */
    public function updateActive(int $activeId, $data)
    {
        if (!$activeId) {
            throw new ValidateException('活动ID参数错误');
        }
        $activeInfo = $this->dao->get($activeId);
        if (!$activeInfo) {
            throw new ValidateException('数据不存在');
        }
        //场次ID
        if (isset($data['seckill_time_ids']) && !empty($data['seckill_time_ids'])) {
            $data['seckill_time_ids'] = implode(',', $data['seckill_time_ids']);
        }
        //活动平台商品一级分类
        if (isset($data['product_category_ids']) && !empty($data['product_category_ids'])) {
            $data['product_category_ids'] = implode(',', $data['product_category_ids']);
        }
        $result = $activeInfo->save($data);
        if (!$result) {
            throw new ValidateException('编辑失败');
        }
        $activity = app()->make(StoreActivityRepository::class);

        if (isset($data['border_pic']) && !empty($data['border_pic'])) {
            //边框图
            $activity->saveByType([
                'activity_name' => $data['name'],
                'start_time' => $data['start_day'],
                'end_time' => $data['end_day'],
                'pic' => $data['border_pic'],
                'link_id' => $activeId,
                'activity_type' => $activity::ACTIVITY_TYPE_BORDER
            ], 1);
        } else {
            //删除边框
            $activity->deleteSeckll($activeId);
        }
    }

    /**
     * 编辑秒杀活动状态
     * @param $id
     * @param $status
     * @return void
     * FerryZhao 2024/4/12
     */
    public function updateStatus($id, $status)
    {
        if (!$this->dao->get($id)) {
            throw new ValidateException('数据不存在');
        }
        $productRepository = app()->make(ProductRepository::class);
        $storeSpu = app()->make(SpuRepository::class);

        Db::transaction(function () use ($productRepository, $storeSpu,$id,$status) {
            $result = $this->dao->update($id, ['status' => $status]);
            $updateSpu = $productRepository->getSearch([])->where(['active_id'=>$id])->update(['is_used' => $status]);
            $updateProduct = $storeSpu->getSearch([])->where(['activity_id'=>$id])->update(['status' => $status]);
        });
        return true;
    }

    /**
     * 删除秒杀活动
     * @param $activeId 秒杀活动ID
     * @return void
     * FerryZhao 2024/4/12
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function deleteActive($activeId)
    {
        $activeInfo = $this->dao->get($activeId);
        if (!$activeInfo) {
            throw new ValidateException('数据不存在');
        }
        $deleteActive = $this->dao->update($activeId, ['delete_time' => time()]);
        $deleteProduct = app()->make(ProductRepository::class)->getSearch([])->where('active_id', $activeId)->update(['is_del' => 1]);
        app()->make(StoreActivityRepository::class)->deleteSeckll($activeId);
        return compact('deleteActive', 'deleteProduct');
    }


    /**
     * 平台管理端统计面板
     * @param $id
     * @param $merId
     * @return array[]
     * FerryZhao 2024/4/22
     */
    public function chartPanel($id, $merId = null): array
    {
        $merchantWhere = [
            'paid' => 1,
        ];
        if ($merId) {
            $merchantWhere['mer_id'] = $merId;
        }
        //初始化
        $data = [
            'orders_people_count' => 0,
            'pay_order_money' => 0,
            'pay_order_people_count' => 0,
            'pay_order_count' => 0
        ];

        $storeOrderRepository = app()->make(StoreOrderRepository::class);
        $storeOrderProductRepository = app()->make(StoreOrderProductRepository::class);


        //活动对应的订单商品表
        $orderProductOrderIds = $storeOrderProductRepository->getSearch([])->where([
            'activity_id' => $id,
            'product_type' => 1
        ])->column('order_id');
        if (!empty($orderProductOrderIds)) {
            $data['orders_people_count'] = $storeOrderRepository->getSearch([])->where($merchantWhere)->where('status','>',-1)->whereIn('order_id', $orderProductOrderIds)->group('uid')->count();//下单人数
            $data['pay_order_money'] = $storeOrderRepository->getSearch([])->where($merchantWhere)->where('status','>',-1)->where(['paid' => 1])->whereIn('order_id', $orderProductOrderIds)->sum('pay_price');;//支付订单金额
            $data['pay_order_people_count'] = $storeOrderRepository->getSearch([])->where($merchantWhere)->where('status','>',-1)->where(['paid' => 1])->whereIn('order_id', $orderProductOrderIds)->group('uid')->count();//支付人数
            $data['pay_order_count'] = $storeOrderRepository->getSearch([])->where($merchantWhere)->where('status','>',-1)->where(['paid' => 1])->whereIn('order_id', $orderProductOrderIds)->group('order_id')->count();//支付订单数
        }
        return [
            [
                'className' => 'el-icon-user-solid',
                'count' => $data['orders_people_count'],
                'field' => '人',
                'name' => '下单人数'
            ],
            [
                'className' => 'el-icon-s-order',
                'count' => (float)$data['pay_order_money'],
                'field' => '元',
                'name' => '支付订单额'
            ],
            [
                'className' => 'el-icon-s-check',
                'count' => $data['pay_order_people_count'],
                'field' => '人',
                'name' => '支付人数'
            ],
            [
                'className' => 'el-icon-s-order',
                'count' => $data['pay_order_count'],
                'field' => '笔',
                'name' => '支付订单数'
            ]
        ];
    }


    /**
     * 活动参与人列表统计
     * @param $activeId
     * @param $merId
     * @param $where
     * @param int $page
     * @param int $limit
     * @return array
     * FerryZhao 2024/4/28
     */
    public function chartPeople($activeId, $where, $merId = null, int $page = 1, int $limit = 10): array
    {
        $result = $this->validateProduct($activeId, $merId);
        if (!$result) {
            return ['count' => 0, 'list' => []];
        }
        return $this->dao->chartPeople($activeId, $where, $merId, $page, $limit);
    }

    /**
     * 活动订单统计列表
     * @param $activeId
     * @param $merId
     * @param $where
     * @param int $page
     * @param int $limit
     * @return array|null
     * FerryZhao 2024/4/28
     */
    public function chartOrder($activeId, $where, $merId = null, int $page = 1, int $limit = 10): ?array
    {
        $result = $this->validateProduct($activeId, $merId);
        if (!$result) {
            return ['count' => 0, 'list' => []];
        }
        $statusWhere = app()->make(StoreOrderRepository::class)->getOrderType($where['status']);
        unset($where['status']);
        return $this->dao->chartOrder($activeId, $where, $statusWhere, $merId, $page, $limit);
    }

    /**
     * 活动商品统计列表
     * @param $activeId
     * @param $merId
     * @param $where
     * @param int $page
     * @param int $limit
     * @return array|null
     * FerryZhao 2024/4/28
     */
    public function chartProduct($activeId, $where, $merId = null, int $page = 1, int $limit = 10): ?array
    {
        $result = $this->validateProduct($activeId, $merId);
        if (!$result) {
            return ['count' => 0, 'list' => []];
        }
        return $this->dao->chartProduct($activeId, $where, $merId, $page, $limit);
    }

    /**
     * 公用校验商品
     * @param $activeId
     * @param $merId
     * @return false
     * FerryZhao 2024/4/28
     */
    public function validateProduct($activeId, $merId): bool
    {
        $productWhere = [
            'active_id' => $activeId,
            'product_type' => 1
        ];
        if ($merId) {
            $productWhere['mer_id'] = $merId;
        }
        $productIds = app()->make(ProductRepository::class)->getSearch([])->where($productWhere)->whereNotNull('active_id')->find();
        if (empty($productIds)) {
            return false;
        } else {
            return true;
        }
    }

}
