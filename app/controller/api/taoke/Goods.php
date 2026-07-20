<?php

namespace app\controller\api\taoke;

use app\common\repositories\taoke\CommissionRepository;
use crmeb\basic\BaseController;
use crmeb\services\taoke\DingDanXiaService;
use crmeb\services\taoke\JuTuiKeService;
use think\App;
use think\facade\Log;

/**
 * 淘宝客商品API接口
 */
class Goods extends BaseController
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
    
    /*
    标签分类
    */
    
    public function category() {
        $type =  $this->request->post('type', 'taobao'); 
        $data = [];
        if ($type == 'taobao' || $type == 'wph') {
            $data = [
                ['id' => 1, 'text' => '9.9元包邮'],
                ['id' => 2, 'text' => '19.9元包邮'],
                ['id' => 3, 'text' => '29.9元包邮'],
                ['id' => 4, 'text' => '39.9元包邮'],
            ];
        } elseif ($type == 'pdd') {
            $data = [
                ['id' => 4, 'text' => '秒杀'],
                ['id' => 7, 'text' => '百亿补贴'],
                ['id' => 31, 'text' => '品牌黑标'],
                ['id' => 24, 'text' => '品牌高佣'],
                ['id' => 10564, 'text' => '精选爆品'],
                
            ];
        } elseif ($type == 'jd') {
            $data = [
                ['id' => 2, 'text' => '精选卖场'],
                ['id' => 10, 'text' => '9.9包邮'],
                ['id' => 110, 'text' => '自营'],
                ['id' => 15, 'text' => '京东配送'],
                ['id' => 25, 'text' => '数码家电'],
                
            ];
        }

        return app('json')->success($data);
        
    }

    /**
     * 淘宝商品
     * GET /api/taoke/goods/taobao
     */
    public function taobao()
    {
        $page = $this->request->param('page_no', 1);
        $limit = $this->request->param('page_size', 20);
        try {
           // $result = $this->dingdanxiaService->taobaoOrderQuery($page, $limit,$start_time,$end_time);
            $result = $this->dingdanxiaService->taobaoGoods($page, $limit);

            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('淘宝商品列表获取失败', [
                'page' => $page,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('搜索失败，请稍后重试');
        }
    }
    /**
     * 淘宝商品搜索（GET）
     * GET /api/taoke/goods/taobao_search
     */
    public function taobaoSearch()
    {
        $page = $this->request->param('page', 1);
        $limit = $this->request->param('limit', 10);
        $q = $this->request->param('keyword', '');
        $cat = $this->request->param('cat', 0);
        try {
            $result = $this->dingdanxiaService->taobaoGoodsSearch($page, $limit, $q, $cat);
            $data = [];
            foreach ($result as $k => $val) {
                // 确保 $val 是数组
                if (!is_array($val)) {
                    continue;
                }

                $itemBasic = $val['item_basic_info'] ?? [];
                $priceInfo = $val['price_promotion_info'] ?? [];

                $data[] = [
                    'goods_id' => $val['item_id'] ?? '',
                    'title' => $itemBasic['title'] ?? '',
                    'image' => $itemBasic['pict_url'] ?? '',
                    'sales' => isset($itemBasic['tk_total_sales']) ? (int)$itemBasic['tk_total_sales'] : 0,
                    'price' => $priceInfo['final_promotion_price'] ?? '0.00',
                    'ot_price' => $priceInfo['reserve_price'] ?? '0.00',
                ];
            }

            return app('json')->success(['list' => $data]);

        } catch (\Exception $e) {
            Log::error('淘宝商品搜索失败', [
                'keyword' => $q,
                'page' => $page,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('搜索失败，请稍后重试: ' . $e->getMessage());
        }
    }

    /**
     * 淘宝商品搜索（POST）
     * POST /api/taoke/goods/taobao_goods
     */
    public function taobaoGoods()
    {
        $page = $this->request->post('page', 1);
        $limit = $this->request->post('limit', 10);
        $q = $this->request->post('keyword', '');
        $cat = $this->request->post('cat', 0);
        try {
            $result = $this->dingdanxiaService->taobaoGoodsSearch($page, $limit,$q,$cat);
             $data = [];
             foreach ($result as $k=>$val){
                 // 确保 $val 是数组
                if (!is_array($val)) {
                 continue;
                }
        
                $itemBasic = $val['item_basic_info'] ?? [];
                $priceInfo = $val['price_promotion_info'] ?? [];
        
                $data[] = [
                    'goods_id' => $val['item_id'] ?? '',
                    'title' => $itemBasic['title'] ?? '',
                    'image' => $itemBasic['pict_url'] ?? '',
                    'sales' => isset($itemBasic['tk_total_sales']) ? (int)$itemBasic['tk_total_sales'] : 0,
                    'price' => $priceInfo['final_promotion_price'] ?? '0.00',
                    'ot_price' => $priceInfo['reserve_price'] ?? '0.00',
                ];
            }

            return app('json')->success(['list'=>$data]);

        } catch (\Exception $e) {
            Log::error('淘宝商品列表获取失败', [
                'keyword' => $q,
                'page' => $page,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('搜索失败，请稍后重试'.$e->getMessage());
        }
    }
    
     /**
     * 生成淘宝推广链接
     * POST /api/taoke/goods/create_taobao_link
     */
    public function createTaobaoLink()
    {
        $goodsId = $this->request->post('goods_id', '');

        if (empty($goodsId)) {
            return app('json')->fail('商品ID不能为空');
        }
        $relate_id = '3357576229';
        try {
            // 调用高佣转链API
            $result = $this->dingdanxiaService->taobaoHighCommission($goodsId,$relate_id);

            if (empty($result)) {
                return app('json')->fail('生成推广链接失败');
            }

            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('生成淘宝推广链接失败', [
                'goods_id' => $goodsId,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('生成推广链接失败');
        }
    }



    /**
     * 淘宝商品详情
     * GET /api/taoke/goods/taobao_detail
     */
    public function taobaoDetail()
    {
        $goodsId = $this->request->get('goods_id', '');

        if (empty($goodsId)) {
            return app('json')->fail('商品ID不能为空');
        }

        try {
            // 调用商品详情API
            $result = [
                'goods_id' => $goodsId,
                'goods_title' => '示例商品标题',
                'goods_pic' => 'https://example.com/pic.jpg',
                'price' => 99.00,
                'coupon_price' => 10.00,
                'price_after_quan' => 89.00,
                'commission_rate' => 2.5,
                'commission_money' => 2.23,
                'sales' => 10000,
                'is_tmall' => 1,
                'shop_title' => '示例店铺',
            ];

            // 计算预估收益
            $uid = $this->request->uid() ?? 0;
            if ($uid > 0) {
                $stats = $this->commissionRepository->getUserCommissionStats($uid);
                $userRate = $stats['self_rate'] ?? 50;

                $result['earn_self'] = round($result['price_after_quan'] * $result['commission_rate'] * $userRate / 10000, 2);
                $result['earn_share'] = round($result['price_after_quan'] * $result['commission_rate'] * ($userRate - 20) / 10000, 2);
            }

            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('获取淘宝商品详情失败', [
                'goods_id' => $goodsId,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('获取商品详情失败');
        }
    }

     /**
     * 淘宝订单
     * GET /api/taoke/goods/taobao
     */
    public function taobaoOrders()
    {
        $page = $this->request->post('page_no', 1);
        $limit = $this->request->post('page_size', 20);
        $start_time = $this->request->post('start_time', '2026-06-29 15:00:00');
        $end_time = $this->request->post('end_time', '2026-06-29 16:00:00');
        try {
            $result = $this->dingdanxiaService->taobaoOrderQuery($page, $limit, $start_time, $end_time);

            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('淘宝订单查询失败', [
                'page' => $page,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('订单查询失败，请稍后重试');
        }
    }

    
    /**
     * 联盟活动列表
     * GET /api/taoke/goods/activity_list
     */
    public function activityList()
    {
        $page = $this->request->get('page', 1);
        $limit = $this->request->get('pageSize', 20);
        $cate_name =  $this->request->get('cate_name', '');

        try {
            $result = $this->jutuikeService->getActivityList($cate_name,$page, $limit);
            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('获取活动列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return app('json')->fail('获取活动列表失败: ' . $e->getMessage());
        }
    }

    /**
     * 活动转链
     * POST /api/taoke/goods/activity_union
     */
    public function activityUnion()
    {
        $activityId = $this->request->post('activity_id', '');

        if (empty($activityId)) {
            return app('json')->fail('活动ID不能为空');
        }


        try {

            // 调用活动转链API
            $result = $this->jutuikeService->activityUnion($activityId);

            if (empty($result)) {
                return app('json')->fail('生成活动链接失败');
            }
            
             // 根据we_app_info中的appid查找对应的小程序原始ID
            // if (!empty($result['we_app_info'])) {
            //     $appidMap = [
            //         'wxde8ac0a21135c07d' => 'gh_870576f3c6f9',
            //         'wx6a96c49f29850eb5' => 'gh_e4c5d4d5bc2f',
            //         'wx6ce7b07bf7fe6048' => 'gh_acbfc5484d03',
            //         'wxd98a20e429ce834b' => 'gh_7a5c4141778f',
            //     ];
            //     $result['we_app_info'] = $this->fillOriginalId($result['we_app_info'], $appidMap);
            // }


            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('活动转链失败', [
                'activity_id' => $activityId,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('生成活动链接失败');
        }
    }
    
     /**
     * 根据appid映射表填充小程序原始ID到we_app_info
     * @param array $weAppInfo
     * @param array $appidMap
     * @return array
     */
    private function fillOriginalId(array $weAppInfo, array $appidMap): array
    {
        // 判断是单个对象还是数组
        if (isset($weAppInfo['appid'])) {
            // 单个we_app_info对象
            $appid = $weAppInfo['appid'];
            if (isset($appidMap[$appid])) {
                $weAppInfo['original_id'] = $appidMap[$appid];
            }
        } else {
            // 数组形式
            foreach ($weAppInfo as &$item) {
                if (isset($item['appid']) && isset($appidMap[$item['appid']])) {
                    $item['original_id'] = $appidMap[$item['appid']];
                }
            }
            unset($item);
        }

        return $weAppInfo;
    }




    /**
     * 拼多多商品
     * GET /api/taoke/goods/taobao
     */
    public function pddGoods()
    {
        $page = $this->request->post('page_no', 1);
        $limit = $this->request->post('page_size', 20);
        $cate = $this->request->post('cate', 0);
        try {
            $result = $this->dingdanxiaService->pddGoods($page, $limit,$cate);
            //var_dump($result);
            $data = [];
            foreach ($result['list'] as $k => $val) {
                 $data[] = [
                    'goods_id' => $val['goods_id'] ?? '0',
                    'title' => $val['goods_name'] ?? '',
                    'image' => $val['goods_image_url'] ?? '',
                    'sales' => isset($val['sales_tip']) ? (int)$val['sales_tip'] : 0,
                    'price' => ($val['min_normal_price'] ?? 0) / 100,
                    'ot_price' => '0.00',
                    'goods_sign' =>$val['goods_sign']
                ];
            }

            return app('json')->success(['list'=>$data]);

        } catch (\Exception $e) {
            Log::error('拼多多商品列表获取失败', [
                'page' => $page,
                'cate' => $cate,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('搜索失败，请稍后重试');
        }
    }
    
    /**
     * 拼多多商品详情
     * GET /api/taoke/goods/taobao
     */
    public function pddGoodsDetail()
    {
        $goods_sign = $this->request->post('goods_sign', '');
        try {
            $result = $this->dingdanxiaService->pddGoodsDetail($goods_sign);
            return app('json')->success($result);


        } catch (\Exception $e) {
            Log::error('商品详情获取失败', [
                'itemIds' => $goods_sign,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('搜索失败，请稍后重试');
        }
    }
    
     /**
     * 生成拼多多推广链接
     * POST /api/taoke/goods/create_taobao_link
     */
    public function createPddLink()
    {
        $goods_sign = $this->request->post('goods_sign', '');
        //$uid = $this->request->uid() ?? 0;
        $uid = 1;
        if (!$uid) {
            return app('json')->fail('请先登录');
        }

        if (empty($goods_sign)) {
            return app('json')->fail('商品不能为空');
        }
        // 获取用户
        $user = \app\common\model\user\User::find($uid);
        if (!$user) {
            return app('json')->fail('用户不存在');
        }
        $pid = $user->pdd_pid;
        $pdd_custom_parameters = $user->pdd_custom_params;
        try {
            // 调用高佣转链API
            $result = $this->dingdanxiaService->pddHighCommission($goods_sign,$pid,$pdd_custom_parameters);
            

            if (empty($result)) {
                return app('json')->fail('生成推广链接失败');
            }

            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('生成推广链接失败', [
                'goods_sign' => $goods_sign,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('生成推广链接失败'.$e->getMessage());
        }
    }
      /**
     * 生成拼多多推广位然后授权
     * POST /api/taoke/goods/create_taobao_link
     */
    public function createPddPid()
    {
        $uid = $this->request->uid() ?? 0;
        //$uid = 1;
        if (!$uid) {
            return app('json')->fail('请先登录');
        }
        try {
            // 获取用户
            $user = \app\common\model\user\User::find($uid);
            if (!$user) {
                return app('json')->fail('用户不存在');
            }
            
            $pid = 0;
            $pdd_custom_parameters = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);//随机数

            // 如果已有PID，直接返回
            if (!empty($user->pdd_pid)) {
                $pid = $user->pdd_pid;
            } else {
                // 生成推广位
                $result = $this->dingdanxiaService->createPddPid();
                if (empty($result)) {
                    return app('json')->fail('生成推广位失败');
                }

                $pid = $result[0]['p_id'] ?? '';
                if (empty($pid)) {
                    return app('json')->fail('生成推广位失败，未获取到PID');
                }

                // 保存到数据库
                $user->pdd_pid = $pid;
                $user->pdd_custom_params = $pdd_custom_parameters;
                $user->save();
                
                
            }
            if (empty($pid)) {
                return app('json')->fail('生成推广位失败');
            }
            //有了pid去生成授权链接
            $result = $this->dingdanxiaService->pddPromUrlGenerate($pid,$pdd_custom_parameters);


            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('生成推广位失败', [
                'pdd_pid' => $pid,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('生成推广链接失败'.$e->getMessage());
        }
    }
     /**
     * 域名绑定拼多多推广链接
     * POST /api/taoke/goods/create_taobao_link
     */
    public function createPddUrl()
    {
        $goods_sign = $this->request->post('goods_sign', '');

        if (empty($goods_sign)) {
            return app('json')->fail('商品不能为空');
        }
        try {
            // 调用高佣转链API
            //$result = $this->dingdanxiaService->pddHighCommission($goods_sign);
            $result = $this->dingdanxiaService->pddPromUrlGenerate();
            

            if (empty($result)) {
                return app('json')->fail('生成推广链接失败');
            }

            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('生成推广链接失败', [
                'goods_sign' => $goods_sign,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('生成推广链接失败'.$e->getMessage());
        }
    }
    
    
    /**
     * 京东商品
     * GET /api/taoke/goods/taobao
     */
    public function jdGoods()
    {
        $page = $this->request->post('page_no', 1);
        $limit = $this->request->post('page_size', 20);
        $cate = $this->request->post('cate', 0);
        try {
            $result = $this->dingdanxiaService->jdGoods($page, $limit,$cate);
            $data = [];
            foreach ($result as $k => $val) {
                // var_dump($val);die;
                $priceInfo = $val['priceInfo'];
                 $data[] = [
                    'goods_id' => $val['itemId'] ?? '0',
                    'title' => $val['skuName'] ?? '',
                    'image' => $val['imageInfo']['imageList'][0]['url'] ?? '',
                    'sales' => isset($val['inOrderCount30Days']) ? (int)$val['inOrderCount30Days'] : 0,
                    'price' => $priceInfo['lowestCouponPrice'] ?? '0.00',
                    'ot_price' => $priceInfo['price'] ?? '0.00',
                    'is_hot' => $val['isHot'] ?? '0',
                    'materialUrl' => $val['materialUrl']
                ];
            }

            return app('json')->success(['list'=>$data]);


        } catch (\Exception $e) {
            Log::error('京东商品列表获取失败', [
                 'page' => $page,
                'cate' => $cate,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('搜索失败，请稍后重试');
        }
    }
    
     /**
     * 京东商品详情
     * GET /api/taoke/goods/taobao
     */
    public function jdGoodsDetail()
    {
        $itemIds = $this->request->post('itemIds', 0);
        try {
            $result = $this->dingdanxiaService->jdGoodsDetail($itemIds);
            // var_dump($result);die;
            return app('json')->success($result);


        } catch (\Exception $e) {
            Log::error('京东商品详情获取失败', [
                'itemIds' => $itemIds,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('搜索失败，请稍后重试');
        }
    }
     /**
     * 生成京东推广链接
     * POST /api/taoke/goods/create_taobao_link
     */
    public function createJdLink()
    {
        $uid = $this->request->uid() ?? 0;
        // $uid = 2;
        if (!$uid) {
            return app('json')->fail('请先登录');
        }
        $materialUrl = $this->request->post('materialUrl', '');

        if (empty($materialUrl)) {
            return app('json')->fail('商品物料不能为空');
        }
        try {
            // 获取用户
            $user = \app\common\model\user\User::find($uid);
            if (!$user) {
                return app('json')->fail('用户不存在');
            }
            
            $pid = 0;
            //暂时不需要推广位
            // // 如果已有PID，直接返回
            // if (!empty($user->jd_pid)) {
            //     $pid = $user->jd_pid;
            // } else {
            //     // 生成推广位
            //     $result = $this->dingdanxiaService->createJdPid();
            //     var_dump($result);die;
            //     if (empty($result)) {
            //         return app('json')->fail('生成推广位失败');
            //     }

            //     $pid = $result[0]['p_id'] ?? '';
            //     if (empty($pid)) {
            //         return app('json')->fail('生成推广位失败，未获取到PID');
            //     }

            //     // 保存到数据库
            //     $user->pdd_pid = $pid;
            //     $user->save();
                
                
            // }
            // if (empty($pid)) {
            //     return app('json')->fail('生成推广位失败');
            // }
            //有了pid去生成授权链接
            $result = $this->dingdanxiaService->jdHighCommission($materialUrl,$pid);
            
            if (empty($result)) {
                return app('json')->fail('生成推广链接失败');
            }



            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('生成推广位失败', [
                'materialUrl' => $materialUrl,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('生成推广链接失败'.$e->getMessage());
        }
        
    }
    
    
     
    /**
     * 唯品会商品
     * GET /api/taoke/goods/taobao
     */
    public function wphGoods()
    {
        $page = $this->request->post('page_no', 1);
        $limit = $this->request->post('page_size', 20);
        $keyword = $this->request->post('keyword', '');
        try {
            $result = $this->dingdanxiaService->wphGoods($keyword,$page, $limit);
            $data = [];
            foreach ($result as $k => $val) {
                 $data[] = [
                    'goods_id' => $val['goodsId'] ?? '0',
                    'title' => $val['goodsName'] ?? '',
                    'image' => $val['goodsMainPicture'] ?? '',
                    'sales' => isset($val['inOrderCount30Days']) ? (int)$val['inOrderCount30Days'] : 0,
                    'price' => $val['vipPrice'] ?? '0.00',
                    'ot_price' => $val['marketPrice'] ?? '0.00',
                    'is_hot' =>  '0',
                ];
            }

            return app('json')->success(['list'=>$data]);


        } catch (\Exception $e) {
            Log::error('唯品商品列表获取失败', [
                'keyword' => $keyword,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('搜索失败，请稍后重试');
        }
    }
    
     /**
     * 唯品会商品详情
     * GET /api/taoke/goods/taobao
     */
    public function vipGoodsDetail()
    {
        $goods_id = $this->request->post('id', 0);
        try {
            $result = $this->dingdanxiaService->vipGoodsDetail($goods_id);
            // var_dump($result);die;
            return app('json')->success($result);


        } catch (\Exception $e) {
            Log::error('商品详情获取失败', [
                'itemIds' => $goods_id,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('搜索失败，请稍后重试');
        }
    }
     /**
     * 生成唯品会推广链接
     * POST /api/taoke/goods/create_taobao_link
     */
    public function createvipLink()
    {
        $goods_id = $this->request->post('goods_id', '');

        if (empty($goods_id)) {
            return app('json')->fail('商品ID不能为空');
        }
        try {
            // 调用高佣转链API
            $result = $this->dingdanxiaService->vipHighCommission($goods_id);

            if (empty($result)) {
                return app('json')->fail('生成推广链接失败');
            }

            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('生成推广链接失败', [
                'goods_id' => $goods_id,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('生成推广链接失败');
        }
    }
}
