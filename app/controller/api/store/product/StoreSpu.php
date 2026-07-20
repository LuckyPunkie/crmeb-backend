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
namespace app\controller\api\store\product;

use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\StoreCategoryRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\user\UserHistoryRepository;
use app\common\repositories\user\UserVisitRepository;
use crmeb\services\CopyCommand;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\product\SpuRepository;

class StoreSpu extends BaseController
{
    protected $userInfo;
    protected $repository;

    public function __construct(App $app, SpuRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->userInfo = $this->request->isLogin() ? $this->request->userInfo() : null;
    }

    /**
     * 商品搜索列表
     * @return mixed
     * @author Qinii
     * @day 12/24/20
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params([
            'keyword',
            'cate_id',
            'cate_pid',
            'order',
            'price_on',
            'price_off',
            'brand_id',
            'pid',
            'mer_cate_id',
            'product_type',
            'action',
            'common',
            ['is_trader',''],
            'product_ids',
            'mer_id',
            'filter_params',
            'mer_type_id',
            'ids',
            'latitude',
            'longitude',
            'store_type_id',
            'store_label_id',
            'delivery_type',
            'cate_id',
            ['region_id', 0]
        ]);
        $where['product_ids'] = $where['ids'];
        if($where['product_ids']){
            $limit = count(explode(',',$where['product_ids']));
        }
        $where['is_gift_bag'] = 0;
//        $where['product_type'] = 0;
        $where['order'] = $where['order'] ?: 'star';

        $data = $this->repository->getApiSearch($where, $page, $limit, $this->userInfo);
        return app('json')->success($data);
    }

    /**
     * 商户的商品搜索列表
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 12/24/20
     */
    public function merProductLst($id)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params([
            'keyword',
            'cate_id',
            'order',
            'price_on',
            'price_off',
            'brand_id',
            'pid',
            'mer_cate_id',
            ['product_type', 0],
            'action',
            'common',
            'ids',
            'product_ids',
            'store_type_id',
            'mer_store_label_id',
            'delivery_type'
        ]);
        $where['product_ids'] = $where['ids'];
        if($where['product_ids']){
            $limit = count(explode(',',$where['product_ids']));
        }
        if ($where['action']) unset($where['product_type']);
        $where['mer_id'] = $id;
        $where['is_gift_bag'] = 0;
        $where['order'] = $where['order'] ? $where['order'] : 'sort';
        $data = $this->repository->getApiSearch($where, $page, $limit, $this->userInfo);
        return app('json')->success($data);
    }

    /**
     * 推荐列表
     * @return mixed
     * @author Qinii
     * @day 12/24/20
     */
    public function recommend()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['common', 'mer_id','latitude','longitude',['region_id', 0]]);
        $where['is_gift_bag'] = 0;
        //1:星级
        //2:用户收藏
        //3:创建时间
        switch (systemConfig('recommend_type')) {
            case '1':
                $where['order'] = 'star';
                break;
            case '2':
                $where['order'] = 'sales';
                if (!is_null($this->userInfo)) {
                    $cateId = app()->make(UserHistoryRepository::class)->getRecommend($this->userInfo->uid);
                    if ($cateId && count($cateId) > 5)
                        $where['cate_id'] = $cateId;
                }
                break;
            case '3':
                $where['order'] = 'create_time';
                break;
            default:
                $where['order'] = 'star';
                break;
        }
        $where['product_type'] = 0;
        $where['is_stock'] = 1;
        $recommend_location = systemConfig('recommend_location') == 0 ? false : true;
        $data = $this->repository->getApiSearch($where, $page, $limit, $this->userInfo,$recommend_location);
        return app('json')->success($data);
    }

    /**
     * 热门列表
     * @return mixed
     * @author Qinii
     * @day 12/24/20
     */
    public function hot($type)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['common', 'mer_id']);
        $where['hot_type'] = $type;
        $where['is_gift_bag'] = 0;
        $where['order'] = 'star';
        $where['product_type'] = 0;
        $data = $this->repository->getApiSearch($where, $page, $limit, null);
        return app('json')->success($data);
    }

    /**
     * 礼包列表
     * @return mixed
     * @author Qinii
     * @day 12/24/20
     */
    public function bag()
    {
        [$page, $limit] = $this->getPage();
        $promoter_type = systemConfig('promoter_type');
        if ($promoter_type == 0) {
            $where['is_gift_bag'] = 1;
            $title = '分销礼包';
            $msg = '购买任意礼包商品，成为分销员';
        } else if($promoter_type == 3) {
            $where['is_gift_bag'] = 0;
            $title = '满额分销';
            $promoter_low_money = systemConfig('promoter_low_money');
            if ($promoter_low_money){
                $msg = '商城消费满' . $promoter_low_money . '元，即可成为分销员';
            } else {
                $msg = '商城消费，即可成为分销员';
            }
        } else {
            return app('json')->success([]);
        }

        $where['order'] = 'rank';
        $where['product_type'] = 0;
        $data = $this->repository->getApiSearch($where, $page, $limit, null);
        $data['promoter_type'] = $promoter_type;
        $data['msg'] = $msg;
        $data['title'] = $title;
        return app('json')->success($data);
    }

    /**
     * 礼包推荐列表
     * @return mixed
     * @author Qinii
     * @day 12/24/20
     */
    public function bagRecommend()
    {
        [$page, $limit] = $this->getPage();
        $where['is_gift_bag'] = 1;
        $where['hot_type'] = 'best';
        $where['product_type'] = 0;
        $data = $this->repository->getApiSearch($where, $page, $limit, null);
        return app('json')->success($data);
    }

    /**
     * 活动分类
     * @param $type
     * @return \think\response\Json
     * @author Qinii
     * @day 1/12/21
     */
    public function activeCategory($type)
    {
        $data = $this->repository->getActiveCategory($type);
        return app('json')->success($data);
    }

    /**
     * 根据标签获取数据
     * @return \think\response\Json
     * @author Qinii
     * @day 8/25/21
     */
    public function labelsLst()
    {
        [$page, $limit] = $this->getPage();
        $where['is_gift_bag'] = 0;
        $merId = $this->request->param('mer_id', 0);
        if ($merId) {
            $where = ['mer_id' => $merId, 'mer_labels' => $this->request->param('labels')];
        } else {
            $where = ['sys_labels' => $this->request->param('labels')];
        }
        $where['product_type'] = 0;
        $where['order'] = 'star';
        $data = $this->repository->getApiSearch($where, $page, $limit, null);
        return app('json')->success($data);
    }

    public function local($id)
    {
        [$page, $limit] = $this->getPage();
        $merchant = app()->make(MerchantRepository::class)->get($id);
        if (!in_array(1, $merchant['delivery_way'])) return app('json')->success(['count' => 0, 'list' => []]);
        $where = [
            'is_del' => 0,
            'mer_id' => $id,
            'delivery_way' => 1,
            'is_gift_bag' => 0,
        ];
        $data = $this->repository->getApiSearch($where, $page, $limit, $this->userInfo);

        return app('json')->success($data);
    }

    /**
     * 获取复制口令
     * @return \think\response\Json
     * @author Qinii
     * @day 9/2/21
     */
    public function copy()
    {
        if(!$this->userInfo) {
            return app('json')->fail('请先登录');
        }
        $id = $this->request->param('id');
        $type = $this->request->param('product_type');
        $str = app()->make(CopyCommand::class)->create($id, $type, $this->userInfo);
        return app('json')->success(['str' => $str]);
    }

    public function get($id)
    {
        return app('json')->success($this->repository->get($id));
    }

    public function getProductByCoupon()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params([
            'keyword',
            'cate_id',
            'cate_pid',
            'order',
            'price_on',
            'price_off',
            'brand_id',
            'pid',
            'mer_cate_id',
            'coupon_id'
        ]);
        $where['is_gift_bag'] = 0;
        $where['order'] = $where['order'] ? $where['order'] : 'star';
        $data = $this->repository->getApiSearchByCoupon($where, $page, $limit, $this->userInfo);
        return app('json')->success($data);
    }

    public function getHotRanking()
    {
        $cateId = $this->request->param('cate_pid', 0);
        $cateId = is_array($cateId) ?: explode(',', $cateId);
        $limit = $this->request->param('limit', 15);
        $regionId = $this->request->param('region_id', 0);
        $data = [];
        foreach ($cateId as $cate_id) {
            $cate = app()->make(StoreCategoryRepository::class)->get($cate_id);
            $list = $this->repository->getHotRanking($cate_id ?: 0, $limit, $regionId);
            if ($list) {
                $data[] = [
                    'cate_id' => $cate['store_category_id'] ?? 0,
                    'cate_name' => $cate['cate_name'] ?? '总榜',
                    'list' => $list,
                ];
            }
        }
        return app('json')->success($data);
    }


}
