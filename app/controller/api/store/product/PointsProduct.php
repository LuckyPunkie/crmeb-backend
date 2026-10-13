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

use app\common\repositories\store\pionts\PointsProductRepository;
use app\common\repositories\store\product\SpuRepository;
use app\common\repositories\store\product\StoreDiscountProductRepository;
use app\common\repositories\store\product\StoreDiscountRepository;
use app\common\repositories\system\groupData\GroupDataRepository;
use crmeb\basic\BaseController;
use think\App;

class PointsProduct extends BaseController
{

    protected $repository;

    /**
     * Product constructor.
     * @param App $app
     * @param StoreDiscountRepository $repository
     */
    public function __construct(App $app, PointsProductRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 控制器的首页方法
     *
     * 本方法用于处理首页的展示逻辑，具体包括获取首页轮播图和区域推荐信息。
     * 它通过依赖注入的方式，调用了GroupDataRepository类来获取数据，
     * 并将这些数据封装成一个包含banner和district两个元素的数组，
     * 最后以成功响应的形式返回给前端。
     *
     * @return \Illuminate\Http\JsonResponse 成功响应，包含轮播图和区域推荐信息。
     */
    public function home()
    {
        // 通过依赖注入获取GroupDataRepository实例，并调用groupData方法获取首页轮播图数据
        $banner = app()->make(GroupDataRepository::class)->groupData('points_mall_banner', 0, 1, 20);

        // 同样通过依赖注入获取GroupDataRepository实例，并调用groupData方法获取首页区域推荐数据
        $district = app()->make(GroupDataRepository::class)->groupData('points_mall_district', 0, 1, 40);

        // 使用app的json助手方法封装数据并返回成功响应
        return app('json')->success(compact('banner', 'district'));
    }

    /**
     * 查询积分商城范围配置信息
     *
     * 本函数用于获取积分商城的积分范围配置，这些配置决定了积分商城中不同积分区间的展示规则。
     * 通过分页方式获取数据，以优化数据加载性能，提高用户体验。
     *
     * @return \Illuminate\Http\JsonResponse 返回一个包含积分范围配置信息的JSON响应。
     */
    public function points_mall_scope()
    {
        // 获取当前页码和每页显示数量
        [$page, $limit] = $this->getPage();

        // 通过依赖注入的方式获取GroupDataRepository实例，并调用groupData方法获取积分商城范围配置数据
        $scope = app()->make(GroupDataRepository::class)->groupData('points_mall_scope', 0, $page, $limit);

        // TODO: 注释掉的代码块是用于格式化积分范围显示的，当前代码未实现这部分逻辑。

        // 返回处理后的积分范围配置数据
        return app('json')->success($scope);
    }


    /**
     * 列出搜索结果的接口方法
     *
     * 本方法旨在提供一个接口，用于根据用户请求的参数进行商品或内容的搜索，并返回搜索结果的分页列表。
     * 请求参数包括分页信息、排序方式、价格范围、销量、关键字和分类ID等，用于细化搜索条件。
     * 支持搜索热门商品的特殊条件，当请求中包含is_hot参数时，会标记搜索结果为热门类型。
     *
     * @return \think\response\Json 返回搜索结果的JSON响应，包含数据列表和分页信息。
     */
    public function lst()
    {
        // 解析并获取当前请求的分页信息
        [$page, $limit] = $this->getPage();

        // 从请求中获取搜索参数，包括范围、排序、价格、销量、关键字和分类ID等
        $where = $this->request->params(['scope', ['order', 'sort'], 'price', 'sales', 'keyword', 'cate_id']);

        // 如果请求中包含is_hot参数，表示需要搜索热门内容，设置搜索条件中的hot_type字段
        if ($this->request->param('is_hot', 0)) $where['hot_type'] = 'hot';

        // 调用仓库层的API搜索方法，根据条件进行搜索，并返回分页结果
        $data = $this->repository->getApiSearch($where, $page, $limit);

        // 返回成功的JSON响应，包含搜索结果数据
        return app('json')->success($data);
    }

    /**
     * 获取资源的详细信息
     *
     * 本函数旨在通过指定的ID从仓库中检索特定资源的详细信息，并以JSON格式返回。
     * 它首先调用仓库类的show方法来获取数据，然后使用应用的JSON助手方法将数据封装成成功的JSON响应。
     *
     * @param int $id 要检索的资源的唯一标识符
     * @return \Illuminate\Http\JsonResponse 成功获取数据时的JSON响应，包含请求的数据
     */
    public function detail($id)
    {
        // 从仓库中获取指定ID的资源数据
        $data = $this->repository->show($id);

        // 返回成功的JSON响应，包含获取的数据
        return app('json')->success($data);
    }

}
