<?php

namespace app\controller\api\taoke;

use app\common\repositories\taoke\CommissionRepository;
use crmeb\basic\BaseController;
use crmeb\services\taoke\DingDanXiaService;
use crmeb\services\taoke\JuTuiKeService;
use think\App;
use think\facade\Log;

/**
 * 淘宝客订单API接口
 */
class Order extends BaseController
{
    /**
     * @var JuTuiKeService
     */
    protected $jutuikeService;

    /**
     * @var DingDanXiaService
     */
    protected $dingdanxiaService;

    /**
     * @var CommissionRepository
     */
    protected $commissionRepository;

    public function __construct(
        App $app,
        JuTuiKeService $jutuikeService,
        DingDanXiaService $dingdanxiaService,
        CommissionRepository $commissionRepository
    ) {
        parent::__construct($app);
        $this->jutuikeService = $jutuikeService;
        $this->dingdanxiaService = $dingdanxiaService;
        $this->commissionRepository = $commissionRepository;
    }
    
    
    //聚推客订单
    public function jutuikeOrder() {
        $page = $this->request->post('page', 1);
        $limit = $this->request->post('pageSize', 20);
        $start_time=  $this->request->post('start_time', '2026-06-05 12:00:00');
        $status=  $this->request->post('status', '');
        $end_time=  $this->request->post('end_time', '2026-06-05 13:00:00');
        $order_sn = $this->request->post('order_sn', '');
        try {
            $result = $this->jutuikeService->getJutuikeOrder($start_time,$end_time,$status,$order_sn,$page,$limit);
            //$result = $this->jutuikeService->getActivityList($page, $limit,$cate_name);
            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('获取活动列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return app('json')->fail('获取活动订单失败: ' . $e->getMessage());
        }
    }
    
    //taobao订单
    public function taobaoOrder() {
        $page = $this->request->post('page', 1);
        $limit = $this->request->post('pageSize', 20);
        $start_time=  $this->request->post('start_time', '');
        $status=  $this->request->post('status', '');
        $end_time=  $this->request->post('end_time', '');
        try {
            $result = $this->dingdanxiaService->taobaoOrder($start_time,$end_time,$status,$page,$limit);
            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('获取订单列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return app('json')->fail('获取订单失败: ' . $e->getMessage());
        }
    }
    
     //pdd订单
    public function pddOrder() {
        $page = $this->request->post('page', 1);
        $limit = $this->request->post('pageSize', 20);
        $start_time=  $this->request->post('start_time', '');
        $end_time=  $this->request->post('end_time', '');
        try {
            $result = $this->dingdanxiaService->pddOrder($start_time,$end_time,$page,$limit);
            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('获取订单列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return app('json')->fail('获取订单失败: ' . $e->getMessage());
        }
    }
     public function pddOrderDetail() {
        $order_sn = $this->request->post('order_sn', '');
        try {
            $result = $this->dingdanxiaService->pddOrderDetail($order_sn);
            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('获取订单详情失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return app('json')->fail('获取订单失败: ' . $e->getMessage());
        }
    }
    
    public function jdOrder() {
        $page = $this->request->post('page', 1);
        $limit = $this->request->post('pageSize', 20);
        $start_time=  $this->request->post('start_time', '2026-06-05 12:00:00');
        $end_time=  $this->request->post('end_time', '2026-06-05 13:00:00');
        $orderId=  $this->request->post('orderId', '');
        try {
            $result = $this->dingdanxiaService->jdOrder($start_time,$end_time,$orderId,$page,$limit);
            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('获取订单列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return app('json')->fail('获取订单失败: ' . $e->getMessage());
        }
    }
    
    public function vipOrder() {
        $page = $this->request->post('page', 1);
        $limit = $this->request->post('pageSize', 20);
        $start_time=  $this->request->post('start_time', '2026-06-05 12:00:00');
        $end_time=  $this->request->post('end_time', '2026-06-05 13:00:00');
        $status=  $this->request->post('status', '');
        try {
            $result = $this->dingdanxiaService->vipOrder($start_time,$end_time,$status,$page,$limit);
            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('获取订单列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return app('json')->fail('获取订单失败: ' . $e->getMessage());
        }
    }
    
    public function vipOrderDetail() {
        $orderSn = $this->request->post('orderSn', '');
        try {
            $result = $this->dingdanxiaService->vipOrderDetail($orderSn);
            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('获取订单详情失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return app('json')->fail('获取详情失败: ' . $e->getMessage());
        }
    }
    

}
