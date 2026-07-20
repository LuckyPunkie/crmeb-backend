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

namespace app\common\repositories\store\product;

use app\common\repositories\BaseRepository;
use app\common\dao\store\product\ProductAssistUserDao;
use think\exception\ValidateException;

class ProductAssistUserRepository extends BaseRepository
{
    public function __construct(ProductAssistUserDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取用户列表
     *
     * 根据给定的条件数组和分页信息，查询用户列表。此方法主要用于数据检索和分页处理，不涉及具体的业务逻辑。
     *
     * @param array $where 查询条件数组，用于构建SQL的WHERE子句。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页显示的记录数，用于分页查询。
     * @return array 返回包含用户列表和总数的数组。
     */
    public function userList(array $where,int $page ,int $limit)
    {
        // 根据查询条件获取查询对象
        $query = $this->dao->getSearch($where);

        // 统计满足条件的用户总数
        $count = $query->count();

        // 根据当前页码和每页记录数，获取用户列表数据
        $list = $query->page($page,$limit)->select();

        // 返回包含用户总数和列表数据的数组
        return compact('count','list');
    }
}
