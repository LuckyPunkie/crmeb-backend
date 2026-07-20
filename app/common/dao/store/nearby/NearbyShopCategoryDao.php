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

namespace app\common\dao\store\nearby;

use app\common\dao\BaseDao;
use app\common\model\store\nearby\NearbyShopCategory;

class NearbyShopCategoryDao extends BaseDao
{
    protected function getModel(): string
    {
        return NearbyShopCategory::class;
    }

    /**
     * 获取分类树（带层级结构）
     */
    public function getTree()
    {
        $list = NearbyShopCategory::getDB()
            ->where('is_show', 1)
            ->where('status', 1)
            ->where('is_del', 0)
            ->order('sort ASC, id ASC')
            ->select()
            ->toArray();

        $tree = [];
        $parentList = [];
        foreach ($list as $item) {
            if ($item['pid'] == 0) {
                $item['children'] = [];
                $tree[] = $item;
                $parentList[$item['id']] = &$tree[count($tree) - 1];
            }
        }
        foreach ($list as $item) {
            if ($item['pid'] > 0 && isset($parentList[$item['pid']])) {
                $parentList[$item['pid']]['children'][] = $item;
            }
        }

        return $tree;
    }

    /**
     * 获取所有选项（一级和二级平铺）
     */
    public function allOptions()
    {
        $list = NearbyShopCategory::getDB()
            ->where('is_show', 1)
            ->where('status', 1)
            ->where('is_del', 0)
            ->order('sort ASC, id ASC')
            ->select()
            ->toArray();

        $result = [];
        foreach ($list as $item) {
            $result[] = [
                'id' => $item['id'],
                'pid' => $item['pid'],
                'name' => $item['name'],
                'icon' => $item['icon'],
            ];
        }
        return $result;
    }
}
