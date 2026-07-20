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
namespace app\controller\api;

use think\facade\Db;
use app\common\repositories\system\diy\DiyRepository;
use app\common\repositories\community\CommunityRepository;
use app\common\repositories\store\broadcast\BroadcastRoomRepository;
use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\product\ProductAssistRepository;
use app\common\repositories\store\product\ProductGroupRepository;
use app\common\repositories\store\product\ProductPresellRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\product\SpuRepository;
use app\common\repositories\store\StoreCategoryRepository;
use app\common\repositories\store\StoreSeckillActiveRepository;
use app\common\repositories\store\StoreSeckillTimeRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\system\merchant\StoreGroupRepository;
use app\common\repositories\system\RelevanceRepository;
use crmeb\basic\BaseController;
use think\App;
use think\facade\Cache;

class Diy extends BaseController
{
    protected $unique;
    protected $diyId;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->unique = trim((string)$this->request->param('unique'));
        // if ($this->unique) {
            $params = $this->request->get();
            unset($params['diy_id'], $params['unique']);
            $params['_url'] = $this->request->pathinfo();
            ksort($params);
            $this->unique = md5($this->unique . json_encode($params));
        // }
        $this->diyId = ((int)$this->request->param('diy_id')) ?: 0;
    }

    protected function cache($fn)
    {
        if (!$this->unique || !$this->diyId) {
            return $fn();
        }
        if(!env('APP_DEBUG', false)) {
            $res = Cache::get('diy.' . $this->diyId . '.' . $this->unique);
            if ($res) return json_decode($res, true);
        }
        $res = $fn();
        Cache::set('diy.' . $this->diyId . '.' . $this->unique, json_encode($res), $res['ttl'] ?? 1500 + random_int(30, 100));
        return $res;
    }

    protected function getMerIdsByRegin($regionId)
    {
        // 获取区域下的所有门店分组ID
        $stroeGroupIds = app()->make(StoreGroupRepository::class)
            ->search([
                'path' => $regionId,
                'status' => 1,
            ])->column('store_group_id');
        array_push($stroeGroupIds, $regionId);
        // 获取门店分组下关联的店铺id
        $merIds = app()->make(RelevanceRepository::class)->getSearch([])
            ->whereIn('left_id', $stroeGroupIds)
            ->where('type', RelevanceRepository::STORE_GROUP)
            ->column('right_id');
        $merIds = array_unique($merIds);

        return $merIds;
    }
    /**
     * 首页diy需要的秒杀列表
     * @param ProductRepository $productRepository
     * @param StoreSeckillTimeRepository $storeSeckillTimeRepository
     * FerryZhao 2024/4/24
     */
    public function seckill(ProductRepository $productRepository, StoreSeckillTimeRepository $storeSeckillTimeRepository)
    {
        $currentHour = date('H', time());
        $storeSeckillTimeInfo = $storeSeckillTimeRepository->getSearch([])->where([
            // ['start_time','<=',$currentHour],
            ['status','=',1],
            ['end_time','>',$currentHour],
        ])->order('end_time','desc')->select();
        if(empty($storeSeckillTimeInfo)){
            return app('json')->success(['list'=>[]]);
        }

        $stopTime =  date('Y-m-d',time()).' '.($storeSeckillTimeInfo[0]['end_time'].':00:00');
        $ttl = (strtotime($stopTime) - time() - 30) > 0 ?: 5;

        $data = $this->cache(function() use($productRepository, $storeSeckillTimeInfo, $stopTime) {
            $mer_id = $this->request->param('mer_id', '');
            $regionId = $this->request->param('region_id', 0);
            $limit = $this->request->param('limit', 10);


            $storeOrderRepository = app()->make(StoreOrderRepository::class);
            $storeSeckillActiveRepository = app()->make(StoreSeckillActiveRepository::class);

            $storeSeckillTimeIds = array_column($storeSeckillTimeInfo->toArray(),'seckill_time_id');
            $activeIds = $storeSeckillActiveRepository->getSearch([])->where(['active_status'=>1])
            ->when(isset($storeSeckillTimeIds) && $storeSeckillTimeIds !== '',function($query)use($storeSeckillTimeIds){
                $query->where(function($query) use ($storeSeckillTimeIds){
                    foreach ($storeSeckillTimeIds as $item) {
                        $query->whereOr(function($query) use ($item){
                            $query->whereFindInSet('seckill_time_ids', $item);
                        });
                    }
                });
            })->column('seckill_active_id');

            $where['star'] = '';
            if ($regionId) {
                $merIds = $this->getMerIdsByRegin($regionId);
                if (!$merIds) {
                    return [];
                }
                $where['mer_id'] = $merIds;
            }
            if ($mer_id) $where['mer_id'] = $mer_id;
            $query = $productRepository->seckillSearch($where)->with(['seckillActive']);
            $field = 'Product.product_id,active_id,Product.mer_id,is_new,U.keyword,brand_id,U.image,U.product_type,U.store_name,U.sort,U.rank,U.star,rate,reply_count,sales,U.price,cost,Product.ot_price,stock,extension_type,care_count,unit_name,U.create_time,old_product_id,activity_label_ids,delivery_free,give_coupon_ids,delivery_way,type';

            $list = $query->whereIn('active_id',$activeIds)->limit($limit)->setOption('field', [])
            ->field($field)
            ->select()
            ->each(function ($item) use ($storeOrderRepository,$stopTime) {
                $item['stock'] = app()->make(ProductRepository::class)->getSeckillAttrValue($item['attrValue'], $item['old_product_id'])['stock'];
                $item['sales'] = $storeOrderRepository->seckillOrderCounut($item['active_id'],$item['product_id']);
                $item['stop'] = strtotime($stopTime);
                $item['skill_status'] = $item['seckill_status'];
            })->toArray()
            ;

            usort($list, function($a, $b) {
                return $b['skill_status'] <=> $a['skill_status'];
            });

            return $list;
        });

        $list = getThumbWaterImage($data, ['image'], 'mid');

        return app('json')->success(['list'=>$list,'stop'=> strtotime($stopTime),'ttl'=>$ttl]);
    }

    /**
     * DIY 预售商品列表
     * @param ProductPresellRepository $productPresellRepository
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/8/15
     */
    public function presell(ProductPresellRepository $repository)
    {
        $data = $this->cache(function() use($repository) {
            $limit = $this->request->param('limit', 10);
            $regionId = $this->request->param('region_id', 0);
            $mer_id = $this->request->param('mer_id', '');

            $where = $repository->actionShow();
            $where['order'] = '';
            $where['type'] = 4;
            if ($regionId) {
                $merIds = $this->getMerIdsByRegin($regionId);
                if (!$merIds) {
                    return [];
                }
                $where['mer_id'] = $merIds;
            }
            if ($mer_id) $where['mer_id'] = $mer_id;
            $list = $repository->search($where)->with([
                'product' => function($query){
                    $query->with(['merchant','reservation']);
                },
            ])->limit($limit)->select()->append(['coupon']);
            if ($list) $data['list'] = $list->toArray();
            return $data;
        });
        return app('json')->success($data);
    }

    /**
     * DIY助力商品列表
     * @param ProductAssistRepository $repository
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/8/15
     */
    public function assist(ProductAssistRepository $repository)
    {
        $data = $this->cache(function() use($repository) {
            $limit = $this->request->param('limit', 10);
            $regionId = $this->request->param('region_id', 0);
            $mer_id = $this->request->param('mer_id', '');

            $where = $repository->assistShow();
            $where['order'] = '';
            if ($regionId) {
                $merIds = $this->getMerIdsByRegin($regionId);
                if (!$merIds) {
                    return [];
                }
                $where['mer_id'] = $merIds;
            }
            if ($mer_id) $where['mer_id'] = $mer_id;
            $list = $repository->search($where)->with([
                'assistSku',
                'product' => function($query){
                    $query->with(['merchant','reservation']);
                },
            ])->append(['user_count'], true)->limit($limit)->select();
            if ($list) $data['list'] = $list->toArray();
            return $data;
        });
        return app('json')->success($data);
    }

    /**
     * DIY拼团商品列表
     * @param ProductGroupRepository $repository
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/8/15
     */
    public function group(ProductGroupRepository $repository)
    {
        $data = $this->cache(function() use($repository) {
            $limit = $this->request->param('limit', 10);
            $regionId = $this->request->param('region_id', 0);
            $mer_id = $this->request->param('mer_id', '');

            $where = $repository->actionShow();
            $where['order'] = '';
            if ($regionId) {
                $merIds = $this->getMerIdsByRegin($regionId);
                if (!$merIds) {
                    return [];
                }
                $where['mer_id'] = $merIds;
            }
            if ($mer_id) $where['mer_id'] = $mer_id;

            $list = $repository->search($where)->with([
                'product' => function($query){
                    $query->with(['merchant','reservation']);
                },
            ])->limit($limit)->select()->append(['sales','stock'],true);
            if ($list) $data['list'] = $list->toArray();
            return $data;
        });
        return app('json')->success($data);
    }

    /**
     * DIY商品列表
     * @param SpuRepository $repository
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/8/15
     */
    public function spu(SpuRepository $repository)
    {
        $data = $this->cache(function() use($repository) {
            $where = $this->request->params(['cate_pid','product_ids','mer_id','mer_cate_id', 'order','latitude','longitude']);
            $limit = (int)$this->request->param('limit',10);
            $where['spu_status'] = 1;
            $where['mer_status'] = 1;
            $where['not_type'] = [20];
            $where['is_gift_bag'] = 0;
            $where['product_type'] = 0;
            $list = $repository->search($where)->with(['merchant'=> function($query){
                $query->with(['typeName','categoryName'])->field('mer_id,category_id,type_id,mer_avatar,mer_name,is_trader,long,lat');
            },'issetCoupon'])->limit($limit)->select();
            if ($list) $data['list'] = $list->toArray();
            // 计算距离
            if((isset($where['latitude']) && !empty($where['latitude'])) && (isset($where['longitude']) && !empty($where['longitude']))) {
                foreach ($data['list'] as &$item) {
                    [$item['distance'], $item['distanceM']] = $this->distance($where, $item['merchant']['lat'], $item['merchant']['long']);
                }
            }
            // 根据距离排序
            if($where['order'] == 'range_asc') {
                usort($data['list'], function($a, $b) {
                    return $a['distanceM'] <=> $b['distanceM'];
                });
            }

            $data['list'] = getThumbWaterImage($data['list'], ['image'], 'mid');
            return $data;
        });
        return app('json')->success($data);
    }
    /**
     * 计算距离
     *
     * @param array $params
     * @param string $merLat
     * @param string $merLong
     * @return void
     */
    public function distance(array $params, string $merLat, string $merLong)
    {
        if (!$merLat || !$merLong) {
            return false;
        }
        $distance = $distanceM = getDistance($params['latitude'], $params['longitude'], $merLat, $merLong);
        if ($distance < 0.9) {
            $distance = max(bcmul($distance, 1000, 0), 1).'m';
            if ($distance == '1m') {$distance = '100m以内';}
        } else {
            $distance.= 'km';
        }

        return [$distance, $distanceM];
    }

    /**
     * DIY社区文章列表
     * @param CommunityRepository $repository
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/8/15
     */
    public function community(CommunityRepository $repository)
    {
        $limit = $this->request->param('limit',10);
        $data = $this->cache(function() use($repository,$limit) {
            $where = $repository::IS_SHOW_WHERE;
            $list = $repository->search($where)->with([
                'author' => function($query) {
                    $query->field('uid,real_name,status,avatar,nickname,count_start');
                },
            ])->limit($limit)->select();
            if ($list) $data['list'] = $list->toArray();
            return $data;
        });
        return app('json')->success($data);
    }

    /**
     * DIY店铺推荐列表
     * @param MerchantRepository $repository
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/8/15
     */
    public function store(MerchantRepository $repository)
    {
        $data = $this->cache(function() use($repository) {
            $where = $this->request->params(['type_id', 'category_id', 'is_best', 'order','latitude','longitude','sort']);
            $regionId = $this->request->param('region_id', 0);
            $mer_id = $this->request->param('mer_id', '');
            if($where['sort']) {
                $where['order'] = $where['sort'];
            }
            $limit = $this->request->param('limit',10);
            if ($regionId) {
                $merIds = $this->getMerIdsByRegin($regionId);
                if (!$merIds) {
                    return [];
                }
                $where['mer_id'] = $merIds;
            }
            if ($mer_id) {
                $where['mer_id'] = $mer_id;
            }

            $field = 'mer_id,care_count,care_ficti,is_trader,type_id,mer_banner,mini_banner,mer_name, mark,mer_avatar,product_score,service_score,postage_score,sales,status,is_best,create_time,long,lat,is_margin';

            $where['status'] = 1;
            $where['mer_state'] = 1;
            $where['is_del'] = 0;
            $list = $repository->search($where)->with(['type_name'])->setOption('field', [])
                ->field($field)
                ->limit((int)$limit)->select()->append(['all_recommend','mer_type_name']);
            if ($list) $data['list'] = $list->toArray();
            foreach ($data['list'] as &$item) {
                $item['care_count'] = $item['care_count'] + $item['care_ficti'];
                $item['distance'] = 0;
                $item['distanceM'] = 0;
                // 计算距离
                if((isset($where['latitude']) && !empty($where['latitude'])) && (isset($where['longitude']) && !empty($where['longitude']))) {
                    [$item['distance'], $item['distanceM']] = $this->distance($where, $item['lat'], $item['long']);
                }
            }
            // 根据距离排序
            if($where['order'] == 'range_asc') {
                usort($data['list'], function($a, $b) {
                    return $a['distanceM'] <=> $b['distanceM'];
                });
            }
            return $data;
        });
        return app('json')->success($data);
    }

    /**
     * DIY 优惠券列表
     * @param StoreCouponRepository $repository
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/8/15
     */
    public function coupon(StoreCouponRepository $repository)
    {
        $limit = $this->request->param('limit',10);
        $list = $this->cache(function() use($repository,$limit) {
            $uid = 0;
            if ($this->request->isLogin()) $uid = $this->request->uid();
            $where['send_type'] = 0;
            $where['mer_id'] = $this->request->param('mer_id','');
            $regionId = $this->request->param('region_id', 0);
            if ($regionId) {
                $merIds = $this->getMerIdsByRegin($regionId);
                if (!$merIds) {
                    return [];
                }
                $where['mer_id'] = $merIds;
            }
            $with = [];
            if ($uid)
                $with['issue'] = function ($query) use ($uid) {
                    $query->where('uid', $uid);
                };
            $baseQuery = $repository->validCouponQueryWithMerchant($where, $uid)->with($with);
            $list = $baseQuery->setOption('field',[])->field('C.*')->limit($limit)->select()->append(['ProductLst'])->toArray();
            foreach($list as $key => $val) {
                if(empty($val['ProductLst'])) {
                    unset($list[$key]);
                }
            }

            return array_values($list);
        });

        $data['list'] = $list;
        return app('json')->success($data);
    }

    /**
     * DIY二级分类
     * @param StoreCategoryRepository $repository
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/8/16
     */
    public function category(StoreCategoryRepository $repository)
    {
        $data = $this->cache(function() use($repository) {
            $data = app()->make(StoreCategoryRepository::class)->getTwoLevel();
            return $data;
        });
        return app('json')->success($data);
    }

    /**
     * 小程序直播接口
     * @param BroadcastRoomRepository $repository
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/8/16
     */
    public function broadcast(BroadcastRoomRepository $repository)
    {
        $limit = $this->request->param('limit',10);
        $data = $this->cache(function() use($repository,$limit) {
            $where = $this->request->params(['mer_id']);
            $where['show_tag'] = 1;
            $list = $repository->search($where)->where('room_id', '>', 0)
                ->whereNotIn('live_status', [107])->limit($limit)
                ->with([
                    'broadcast' => function($query) {
                        $query->where('on_sale',1);
                        $query->with('goods');
                    }
                ])
                ->order('star DESC, sort DESC, create_time DESC')->select();

            // 对查询结果中的每个用户，格式化其直播开始时间
            foreach ($list as $item) {
                $item->show_time = date('m/d H:i', strtotime($item->start_time));
            }
            if ($list) $data['list'] = $list->toArray();
            return $data;
        });
        return app('json')->success($data);
    }

    /**
     * DIY 热门排行列表
     * @param SpuRepository $repository
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/8/16
     */
    public function hot_top(SpuRepository $repository)
    {
        $data = $this->cache(function() use($repository) {
            $cateId = $this->request->param('cate_pid',0);
            $regionId = $this->request->param('region_id',0);
            $cateId = is_array($cateId) ?:explode(',',$cateId);
            $cateId = array_unique($cateId);
            $count = count($cateId);
            if ($count > 3){
                $cateId = array_slice($cateId,0,3);
            } else if ($count < 3) {
                $limit = 3 - count($cateId);
                $_cateId = app()->make(StoreCategoryRepository::class)->getSearch([
                    'level' => systemConfig('hot_ranking_lv') ?:0,
                    'mer_id' => 0,
                    'is_show' => 1,
                    'type' => 0
                ])->limit($limit)->order('sort DESC,create_time DESC')->column('store_category_id');
                $cateId = array_merge($cateId,$_cateId);
            }
            $data = [];
            $storeCategoryRepository = app()->make(StoreCategoryRepository::class);
            foreach ($cateId as $cate_id) {
                $list = $repository->getHotRanking($cate_id ?: 0,3, $regionId);
                $cate = $storeCategoryRepository->get($cate_id);
                $data[] = [
                    'cate_id' => $cate['store_category_id'] ?? 0,
                    'cate_name' => $cate['cate_name'] ?? '总榜',
                    'list' => $list,
                ];
            }
            return $data;
        });
        return app('json')->success($data);
    }

    public function productDetail(DiyRepository $repository)
    {
        $key = env('APP_KEY').'_sys.get_sys_product_detail';
        $data = Cache::remember($key,function(){
            $res = app()->make(DiyRepository::class)->getProductDetail();
            $data['value'] = $res['product_detail_diy'];
            return $data;
        }, 60);
        if (is_null($data['value'])) return app('json')->fail('暂无数据');
        return app('json')->encode($data);
    }
    /**
     * 悬浮按钮
     *
     * @return void
     */
    public function fab()
    {
        $key = env('APP_KEY').'_sys.get_sys_fab_info';
        $data = Cache::remember($key,function(){
            $res = app()->make(DiyRepository::class)->fabInfo();

            return $res;
        }, 60);
        if (empty($data['value'])) {
            return app('json')->fail('暂无数据');
        }

        return app('json')->success($data);
    }
    /**
     * 商品分类页面
     */
    public function productCategory()
    {
        $merId = $this->request->param('mer_id',0);

        $key = env('APP_KEY').'_sys.get_sys_product_category_'.$merId;
        $data = Cache::remember($key,function() use ($merId){
            $res = app()->make(DiyRepository::class)->productCategoryInfo((int)$merId);

            return $res;
        }, 60);
        if (empty($data['value'])) {
            return app('json')->fail('暂无数据');
        }

        return app('json')->success($data);
    }

    /**
     * DIY 盲盒店铺列表（首页盲盒专区组件）
     */
    public function blindbox()
    {
        $data = $this->cache(function() {
            $limit = $this->request->param('limit', 6);
            $regionId = $this->request->param('region_id', 0);

            $merchantRepository = app()->make(MerchantRepository::class);
            $where = [
                'is_del' => 0,
                'status' => 1,
                'is_blindbox' => 1,
            ];

            if ($regionId) {
                $merIds = $this->getMerIdsByRegin($regionId);
                if (!$merIds) {
                    return ['list' => []];
                }
                $where['mer_id'] = $merIds;
            }

            $query = $merchantRepository->search($where)
                ->field('mer_id,mer_name,mer_avatar,mer_banner,category_id')
                ->order('mer_id DESC')
                ->limit($limit);

            $list = $query->select();

            foreach ($list as $item) {
                $minPrice = Db::name('store_product')
                    ->where('mer_id', $item['mer_id'])
                    ->where('is_del', 0)
                    ->where('is_show', 1)
                    ->where('status', 1)
                    ->min('price');
                $item['min_blindbox_price'] = $minPrice ?: 0;
            }

            return ['list' => $list->toArray()];
        });

        return app('json')->success($data);
    }
}
