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

use app\common\repositories\system\CacheRepository;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\product\ProductPresellRepository;

class StoreProductPresell extends BaseController
{
    use BindSpreadTrait;

    protected $repository;
    protected $userInfo;

    /**
     * StoreProductPresell constructor.
     * @param App $app
     * @param ProductPresellRepository $repository
     */
    public function __construct(App $app, ProductPresellRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->userInfo = $this->request->isLogin() ? $this->request->userInfo() : null;
    }

    /**
     * 获取列表数据
     *
     * 本函数用于根据请求参数获取对应列表的分页数据。
     * 通过解析请求中的参数，确定查询条件，进而调用仓库层的方法获取数据。
     * 返回的数据格式化为JSON，用于前端展示。
     *
     * @return \think\response\Json
     */
    public function lst()
    {
        // 解析并获取当前请求的分页信息
        [$page, $limit] = $this->getPage();

        // 从请求中获取查询参数，包括类型(type)，星级(star)和商户ID(mer_id)
        $where = $this->request->params([['type',4],'star','mer_id']);
        $regionId = $this->request->param('region_id/d', 0);
        if ($regionId) {
            $merIds = $this->getMerIdsByRegin($regionId);
            if (!$merIds) {
                return app('json')->success([]);
            }
            $where['mer_id'] = $merIds;
        }

        // 调用仓库层的getApiList方法获取数据，并返回格式化后的JSON响应
        return app('json')->success($this->repository->getApiList($where, $page, $limit));
    }


    /**
     * 获取详细信息
     *
     * 通过指定的ID从仓库中获取资源的详细信息。此方法封装了与仓库的交互，
     * 以及成功获取数据后的响应处理。它使用了依赖注入来获取必要的依赖项，
     * 并依赖于应用程序容器来返回JSON响应。
     *
     * @param int $id 资源的唯一标识符。转换为整数类型以确保数据类型安全。
     * @return \Illuminate\Http\JsonResponse 成功获取数据时返回的JSON响应，包含获取的数据。
     */
    public function detail($id)
    {
        $this->bindSpread($this->userInfo);
        // 通过ID和用户信息从仓库中获取资源的详细信息
        $data = $this->repository->apiDetail((int)$id, $this->userInfo);

        // 返回成功的JSON响应，包含获取的资源详细信息
        return app('json')->success($data);
    }

    /**
     * 获取预售协议内容
     *
     * 本方法用于获取系统预设的预售协议内容。通过缓存机制获取协议文本，提高获取速度并减少对数据库的直接访问。
     * 返回的内容是经过成功处理的JSON数据，方便前端直接解析展示。
     *
     * @return \Illuminate\Http\JsonResponse 返回包含预售协议内容的JSON响应
     */
    public function getAgree()
    {
        // 通过依赖注入的方式创建并获取缓存仓库实例
        $make = app()->make(CacheRepository::class);

        // 返回成功状态的JSON响应，其中包含从缓存中获取的预售协议内容
        return app('json')->success($make->getResult('sys_product_presell_agree'));
    }

}
