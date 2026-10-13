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

use app\common\repositories\store\product\ProductGroupBuyingRepository;
use app\common\repositories\store\product\ProductGroupUserRepository;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\product\ProductGroupRepository;

class StoreProductGroup extends BaseController
{
    use BindSpreadTrait;

    protected $repository;
    protected $userInfo;

    /**
     * StoreProductGroup constructor.
     * @param App $app
     * @param ProductGroupRepository $repository
     */
    public function __construct(App $app, ProductGroupRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->userInfo = $this->request->isLogin() ? $this->request->userInfo() : null;
    }

    /**
     * 获取列表数据
     *
     * 本函数用于根据请求参数获取对应的商品列表数据。
     * 它通过解析页码和每页数量来确定分页信息，从请求中获取过滤条件，然后调用仓库层的方法来获取数据。
     * 返回的数据格式化为JSON，用于API响应。
     *
     * @return \think\response\Json 成功时返回格式化后的数据。
     */
    public function lst()
    {
        // 解析并获取当前页码和每页数据数量
        [$page, $limit] = $this->getPage();

        // 从请求中获取过滤条件，包括激活类型、店铺分类ID、星级和商家ID
        $where = $this->request->params([['active_type',1],'store_category_id','star','mer_id']);
        $regionId = $this->request->param('region_id/d', 0);
        if ($regionId) {
            $merIds = $this->getMerIdsByRegin($regionId);
            if (!$merIds) {
                return app('json')->success([]);
            }
            $where['mer_id'] = $merIds;
        }

        // 调用仓库层的方法获取数据，并以JSON格式返回
        return app('json')->success($this->repository->getApiList($where,$page, $limit));
    }

    /**
     * 获取资源的详细信息
     *
     * 本函数通过调用repository中的apiDetail方法，根据提供的ID和用户信息来获取特定资源的详细数据。
     * 它封装了数据获取的过程，并通过JSON格式返回成功获取的数据。
     *
     * @param int $id 资源的唯一标识符，用于定位特定资源
     * @return \Illuminate\Http\JsonResponse 成功获取数据时返回的JSON响应，包含资源的详细信息
     */
    public function detail($id)
    {
        $this->bindSpread($this->userInfo);
        // 调用repository的apiDetail方法，传入资源ID和用户信息，获取资源详细数据
        $data = $this->repository->apiDetail($id, $this->userInfo);

        // 使用app助手函数获取JSON响应实例，并传入获取的数据，构造成功返回的JSON响应
        return app('json')->success($data);
    }

    /**
     *  某个团的详情
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function groupBuying($id)
    {
        $make = app()->make(ProductGroupBuyingRepository::class);
        $data = $make->detail($id,$this->userInfo);
        if(!$data) return app('json')->fail('数据丢失');
        return app('json')->success($data);
    }

    /**
     * 获取用户数量
     *
     * 本函数用于查询当前应用中的用户数量。通过分页方式获取数据，以支持数据量较大的情况下的查询效率。
     * 不接受任何参数，返回当前应用用户数量的相关数据。
     *
     * @return \Illuminate\Http\JsonResponse 返回包含用户数量数据的JSON响应。
     */
    public function userCount()
    {
        // 解析并获取当前请求的页码和每页数量
        [$page, $limit] = $this->getPage();

        // 通过依赖注入的方式获取ProductGroupUserRepository实例
        // 并调用其getApiList方法获取用户数据列表
        $data = app()->make(ProductGroupUserRepository::class)->getApiList([], $page, $limit);

        // 使用应用的JSON工具类将获取到的数据封装成JSON响应并返回
        return app('json')->success($data);
    }

    public function category()
    {
        return app('json')->success($this->repository->getCategory());
    }

    /**
     * 取消参团
     * @author Qinii
     * @day 1/13/21
     */
    public function cancel()
    {
        $data = (int)$this->request->param('group_buying_id');

        $make = app()->make(ProductGroupBuyingRepository::class);

        $make->cancelGroup($data,$this->userInfo);

        return app('json')->success('取消成功，订单金额将会原路退回');

    }
}
