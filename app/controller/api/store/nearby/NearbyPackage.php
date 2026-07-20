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

namespace app\controller\api\store\nearby;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\nearby\NearbyShopPackageRepository;

/**
 * 附近好店套餐 - C端API控制器
 */
class NearbyPackage extends BaseController
{
    protected $repository;

    public function __construct(App $app, NearbyShopPackageRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 套餐列表
     * GET /api/nearby/package/lst/:mer_id
     */
    public function lst($merId)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getList((int)$merId, $page, $limit));
    }

    /**
     * 套餐详情
     * GET /api/nearby/package/detail/:id
     */
    public function detail($id)
    {
        $package = $this->repository->get((int)$id);
        if (!$package) {
            return app('json')->fail('套餐不存在');
        }
        return app('json')->success($package);
    }
}
