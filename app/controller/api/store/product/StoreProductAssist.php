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

use app\common\model\store\product\ProductAssistUser;
use app\common\repositories\store\product\ProductAssistSetRepository;
use app\common\repositories\store\product\ProductAssistUserRepository;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\product\ProductAssistRepository;

class StoreProductAssist extends BaseController
{
    use BindSpreadTrait;

    protected $repository;
    protected $userInfo;

    /**
     * StoreProductAssist constructor.
     * @param App $app
     * @param ProductAssistRepository $repository
     */
    public function __construct(App $app, ProductAssistRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->userInfo = $this->request->isLogin() ? $this->request->userInfo() : null;
    }

    /**
     * 获取资源列表
     *
     * 本函数用于根据请求参数获取特定资源的列表。支持通过类型、星级和商家ID来过滤资源。
     * 使用分页机制返回资源列表，以提高接口的性能和响应速度。
     *
     * @return json 返回格式化的资源列表数据
     */
    public function lst()
    {
        // 解析并获取当前请求的分页信息
        [$page, $limit] = $this->getPage();

        // 从请求中获取过滤条件，包括类型、星级和商家ID
        $where = $this->request->params(['type','star','mer_id']);
        $regionId = $this->request->param('region_id/d', 0);
        if ($regionId) {
            $merIds = $this->getMerIdsByRegin($regionId);
            if (!$merIds) {
                return app('json')->success([]);
            }
            $where['mer_id'] = $merIds;
        }

        // 调用仓库层的方法获取满足条件的资源列表，并返回给前端
        return app('json')->success($this->repository->getApiList($where,$page, $limit));
    }

    /**
     * 获取用户数量
     *
     * 本方法用于查询并返回当前应用程序中的用户总数。
     * 它通过依赖注入获取repository对象，并调用其getUserCount方法来获取用户数。
     * 最后，它使用JSON响应助手将用户数量包装在一个成功的JSON响应中返回。
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function userCount()
    {
        // 使用JSON响应助手构造一个成功的响应，包含用户数量
        return app('json')->success($this->repository->getUserCount());
    }
}
