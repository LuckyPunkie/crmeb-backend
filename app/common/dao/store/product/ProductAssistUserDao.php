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

namespace app\common\dao\store\product;

use app\common\dao\BaseDao;
use app\common\model\store\product\ProductAssistUser;

class ProductAssistUserDao extends BaseDao
{
    protected function getModel(): string
    {
        return ProductAssistUser::class;
    }


    /**
     * 获取用户数量及列表
     *
     * 本函数用于查询数据库中的用户总数，并返回最近创建的用户列表。
     * 列表默认包含最近创建的3个用户，但可以通过传入参数$limit来调整返回的用户数量。
     *
     * @param int $limit 控制返回的用户数量，默认为3。
     * @return array 包含两个元素的数组，'count'表示用户总数，'list'表示用户列表。
     */
    public function userCount(int $limit = 3)
    {
        // 查询数据库中的用户总数
        $count = $this->getModel()::getDB()->count("*");

        // 查询最近创建的用户列表，限制返回的数量为$limit，并按创建时间降序排序
        $list = $this->getModel()::getDB()->limit($limit)->order('create_time DESC')->select();

        // 返回包含用户总数和用户列表的数组
        return compact('count','list');
    }

}

