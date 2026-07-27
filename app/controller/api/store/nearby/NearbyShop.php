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
use app\common\repositories\store\nearby\NearbyShopRepository;

/**
 * 附近好店 - C端API控制器
 */
class NearbyShop extends BaseController
{
    protected $repository;

    public function __construct(App $app, NearbyShopRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 附近好店商家列表（瀑布流）
     * GET /api/nearby/shop/lst
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params([
            'keyword',
            'category_id',
            'order',
            'latitude',
            'longitude',
            'tags',
            'is_open',
            ['store_type', 'offline'], // offline=实体店/线下店，online=线上店
        ]);

        // 前端传 category_id，仓库统一用 nearby_category_id
        if (!empty($where['category_id'])) {
            $where['nearby_category_id'] = (int)$where['category_id'];
        }

        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * 商家详情
     * GET /api/nearby/shop/detail/:mer_id
     */
    public function detail($merId)
    {
        $where = $this->request->params(['latitude', 'longitude']);
        $detail = $this->repository->getDetail((int)$merId, $where);

        if (!$detail) {
            return app('json')->fail('商家不存在或已下架');
        }

        return app('json')->success($detail);
    }
}
