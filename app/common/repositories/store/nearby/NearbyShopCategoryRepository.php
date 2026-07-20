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

namespace app\common\repositories\store\nearby;

use app\common\dao\store\nearby\NearbyShopCategoryDao;
use app\common\repositories\BaseRepository;

class NearbyShopCategoryRepository extends BaseRepository
{
    public function __construct(NearbyShopCategoryDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取分类树（带缓存，TTL 1 小时）
     */
    public function getTree()
    {
        return app('cache')->remember('nearby_category_tree', function () {
            return $this->dao->getTree();
        }, 3600);
    }
}
