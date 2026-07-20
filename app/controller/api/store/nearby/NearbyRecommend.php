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
use app\common\repositories\store\nearby\NearbyShopRecommendRepository;

/**
 * 附近好店推荐菜 - C端API控制器
 */
class NearbyRecommend extends BaseController
{
    protected $repository;

    public function __construct(App $app, NearbyShopRecommendRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 推荐菜列表
     * GET /api/nearby/recommend/lst/:mer_id
     */
    public function lst($merId)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getList((int)$merId, $page, $limit));
    }
}
