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
use app\common\model\system\merchant\MerchantCategory;
use app\common\repositories\BaseRepository;

class NearbyShopCategoryRepository extends BaseRepository
{
    public function __construct(NearbyShopCategoryDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取分类树（统一走 eb_merchant_category，带缓存 1 小时）
     * 盲盒是店铺创建时的属性，不在附近好店筛选里出现，故剔除该分类及其子分类
     */
    public function getTree()
    {
        return app('cache')->remember('nearby_category_tree', function () {
            $excludeIds = MerchantCategory::getDB()
                ->where('category_name', '盲盒')
                ->column('merchant_category_id');

            $query = MerchantCategory::getDB()
                ->field('merchant_category_id as id, pid, category_name as name')
                ->order('sort ASC, merchant_category_id ASC');

            if (!empty($excludeIds)) {
                $query->whereNotIn('merchant_category_id', $excludeIds)
                      ->whereNotIn('pid', $excludeIds);
            }

            $list = $query->select()->toArray();

            $tree = [];
            $map  = [];
            foreach ($list as &$item) {
                if ((int)$item['pid'] === 0) {
                    $item['children'] = [];
                    $tree[] = &$item;
                    $map[$item['id']] = &$item;
                }
            }
            unset($item);
            foreach ($list as $item) {
                if ((int)$item['pid'] > 0 && isset($map[$item['pid']])) {
                    $map[$item['pid']]['children'][] = $item;
                }
            }
            return $tree;
        }, 3600);
    }
}
