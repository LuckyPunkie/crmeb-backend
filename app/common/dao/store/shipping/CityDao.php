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

namespace app\common\dao\store\shipping;

use app\common\dao\BaseDao;
use app\common\model\store\shipping\City as model;

class CityDao  extends BaseDao
{
    protected function getModel(): string
    {
        return model::class;
    }

    /**
     * 获取所有符合条件的城市信息
     *
     * 本函数用于从数据库中查询并返回所有满足给定条件的城市信息。它通过构建一个查询语句，
     * 包括指定的条件、排序方式和所需字段，来实现数据的获取。
     *
     * @param array $where 查询条件数组，用于指定数据库查询的条件。
     * @return array 返回一个包含城市信息的数组，每个元素是一个城市的信息。
     */
    public function getAll(array $where)
    {
        // 通过模型获取数据库实例，然后构建查询语句，指定查询条件、排序方式和返回字段，最后执行查询。
        return ($this->getModel()::getDB())->where($where)->order('id ASC')->field('id,name,code,parent_id')->select();
    }
}
