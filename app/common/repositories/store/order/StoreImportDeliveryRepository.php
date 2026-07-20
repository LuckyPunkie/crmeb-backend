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

namespace app\common\repositories\store\order;

use app\common\repositories\BaseRepository;
use app\common\dao\store\order\StoreImportDeliveryDao;

class StoreImportDeliveryRepository extends BaseRepository
{
    /**
     * StoreGroupOrderRepository constructor.
     * @param StoreImportDeliveryDao $dao
     */
    public function __construct(StoreImportDeliveryDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件查询数据库，并返回满足条件的数据列表以及总条数。
     * 这样做的目的是为了支持分页查询，使得前端可以获取到当前页的数据以及总页数等信息。
     *
     * @param string $where 查询条件，用于构造SQL语句中的WHERE部分。
     * @param int $page 当前页码，用于计算查询的起始位置。
     * @param int $limit 每页显示的数据条数，用于限制查询的结果集大小。
     * @return array 返回包含 'count' 和 'list' 两个元素的数组，'count' 表示满足条件的总数据条数，'list' 表示当前页的数据列表。
     */
    public function getList($where,$page, $limit)
    {
        // 根据条件获取查询对象
        $query = $this->dao->getSearch($where);

        // 计算满足条件的数据总条数
        $count = $query->count();

        // 根据当前页码和每页显示的条数，获取当前页的数据列表
        $list = $query->page($page, $limit)->select();

        // 将数据总条数和当前页的数据列表一起返回
        return compact('count','list');
    }
}
