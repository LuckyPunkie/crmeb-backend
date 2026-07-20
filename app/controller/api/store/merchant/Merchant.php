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

namespace app\controller\api\store\merchant;

use app\controller\api\store\product\BindSpreadTrait;
use app\common\repositories\store\service\StoreServiceRepository;
use app\common\repositories\user\UserMerchantRepository;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantRepository as repository;

class Merchant extends BaseController
{
    use BindSpreadTrait;

    protected $repository;
    protected $userInfo;

    /**
     * ProductCategory constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->userInfo =$this->request->isLogin() ? $this->request->userInfo():null;
    }

    /**
     * 获取列表数据
     * 本函数旨在根据用户请求的参数，获取对应的数据列表。支持通过关键字、排序方式、最佳标识、位置、类别ID、类型ID和商家标识等条件进行筛选。
     * @return json 返回一个包含获取的数据列表的JSON对象，其中列表数据由repository层的getList方法提供。
     */
    public function lst()
    {
        // 解构获取当前页码和每页显示数量
        [$page, $limit] = $this->getPage();

        // 从请求中获取查询参数
        $where = $this->request->params(['keyword', 'order', 'is_best', 'location', 'category_id', 'type_id','is_trader']);

        // 调用getList方法获取数据列表，并返回成功响应的JSON对象
        return app('json')->success($this->repository->getList($where, $page, $limit, $this->userInfo));
    }

    /**
     * 获取店铺详情
     *
     * 本函数通过传入的店铺ID，获取该店铺的详细信息并返回给调用者。
     * 如果店铺已关闭，则返回错误信息；如果用户已登录，更新用户访问店铺的时间。
     * 最后，返回店铺的详细信息。
     *
     * @param int $id 店铺的唯一标识ID
     * @return json 返回包含店铺详情的JSON数据
     */
    public function detail($id)
    {
        $this->bindSpread();
        // 检查店铺是否营业，若不营业则返回错误信息
        if (!$this->repository->apiGetOne($id))
            return app('json')->fail('店铺已打烊');

        // 如果用户已登录，更新用户访问该店铺的最后时间
        if ($this->request->isLogin()) {
            app()->make(UserMerchantRepository::class)->updateLastTime($this->request->uid(), intval($id));
        }

        // 返回店铺的详细信息
        return app('json')->success($this->repository->detail($id, $this->userInfo));
    }

    /**
     * 获取系统详情信息
     *
     * 本函数用于获取系统的详细配置信息，并以特定格式返回给调用者。
     * 主要包括站点的logo、名称以及登录页面的logo等信息。返回的数据结构中，
     * 各项信息被组织成一个便于使用的数组，以便前端或其他调用者能够轻松访问。
     *
     * @return \Illuminate\Http\JsonResponse 系统详情信息的JSON响应
     */
    public function systemDetail()
    {
        // 从系统配置中提取特定的配置项，包括站点logo、站点名称和登录logo
        $config = systemConfig(['site_logo', 'site_name','login_logo']);

        // 构建并返回包含系统详情的JSON响应，其中mer_id固定为0，各项评分默认为5.0
        return app('json')->success([
            'mer_avatar' => $config['site_logo'], // 站点logo
            'mer_name' => $config['site_name'], // 站点名称
            'mer_id' => 0, // 商户ID，此处固定为0
            'postage_score' => '5.0', // 物流评分，默认为5.0
            'product_score' => '5.0', // 产品评分，默认为5.0
            'service_score' => '5.0', // 服务评分，默认为5.0
        ]);
    }



    /**
     * @Author:Qinii
     * @Date: 2020/5/29
     * @param $id
     * @return mixed
     */
    public function productList($id)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword','order','mer_cate_id','cate_id', 'order', 'price_on', 'price_off', 'brand_id', 'pid']);
        if(!$this->repository->apiGetOne($id)) return app('json')->fail(' 店铺已打烊');
        return app('json')->success($this->repository->productList($id,$where, $page, $limit,$this->userInfo));
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/29
     * @param int $id
     * @return mixed
     */
    public function categoryList($id)
    {
        if(!$this->repository->merExists((int)$id))
            return app('json')->fail('店铺已打烊');
        return app('json')->success($this->repository->categoryList($id));
    }

    public function qrcode($id)
    {
        if(!$this->repository->merExists((int)$id))
            return app('json')->fail('店铺已打烊');
        $url = $this->request->param('type/s') == 'routine' ?
            $this->repository->routineQrcode(intval($id)) :
            $this->repository->wxQrcode(intval($id));
        return app('json')->success(compact('url'));
    }

    public function localLst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'order', 'is_best', 'location', 'category_id', 'type_id']);
        $where['delivery_way'] = 1;
        return app('json')->success($this->repository->getList($where, $page, $limit, $this->userInfo));
    }

    public function localDetail(int $id)
    {
        $params = $this->request->params(['latitude', 'longitude']);
        if(!$params['latitude'] || !$params['longitude']){
            return app('json')->fail('缺少经纬度');
        }

        return app('json')->success($this->repository->getDistance($id, $params));
    }

}
