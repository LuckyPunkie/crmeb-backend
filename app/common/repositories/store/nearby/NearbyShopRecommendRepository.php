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

use app\common\dao\store\nearby\NearbyShopRecommendDao;
use app\common\model\store\nearby\NearbyShopRecommend;
use app\common\repositories\BaseRepository;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

class NearbyShopRecommendRepository extends BaseRepository
{
    public function __construct(NearbyShopRecommendDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取商家推荐菜列表
     */
    public function getList(int $merId, int $page, int $limit)
    {
        $limit = min($limit, 100); // 分页上限保护
        $query = NearbyShopRecommend::getDB()
            ->where('mer_id', $merId)
            ->where('status', 1)
            ->where('is_show', 1)
            ->where('is_del', 0)
            ->order('sort DESC, id DESC');

        $count = $query->count();
        $list = $query->page($page, $limit)->select();

        return compact('count', 'list');
    }

    /**
     * 获取商家推荐菜（限制数量，用于详情页展示）
     */
    public function getTopList(int $merId, int $limit = 6)
    {
        return NearbyShopRecommend::getDB()
            ->where('mer_id', $merId)
            ->where('status', 1)
            ->where('is_show', 1)
            ->where('is_del', 0)
            ->order('sort DESC, id DESC')
            ->limit($limit)
            ->select();
    }
}
