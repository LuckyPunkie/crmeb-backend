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


namespace app\common\dao\store;


use app\common\dao\BaseDao;
use app\common\model\store\StoreActivity;
use app\common\model\store\StoreActivityRelated;

/**
 *
 * Class StoreActivityDao
 * @package app\common\dao\system\merchant
 */
class StoreActivityRelatedDao extends BaseDao
{
    protected function getModel(): string
    {
        return StoreActivityRelated::class;
    }

    /**
     * 根据条件搜索数据
     *
     * 本函数旨在通过提供的条件数组来执行搜索操作。它封装了搜索逻辑，使调用者可以通过简单地传递条件数组来执行复杂的搜索。
     * 条件数组可以包含多个条件，每个条件由字段名和对应的值组成，可以用于构建SQL查询中的WHERE子句。
     *
     * @param array $where 搜索条件数组，包含一个或多个搜索条件
     * @return mixed 返回搜索结果，具体类型取决于getSearch方法的实现
     */
    public function search(array $where = [])
    {
        return $this->getSearch($where);
    }
}
