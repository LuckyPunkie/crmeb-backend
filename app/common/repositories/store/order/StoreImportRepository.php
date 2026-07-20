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
use app\common\dao\store\order\StoreImportDao;

class StoreImportRepository extends BaseRepository
{
    /**
     * StoreGroupOrderRepository constructor.
     * @param StoreImportDeliveryDao $dao
     */
    public function __construct(StoreImportDao $dao)
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
    public function getList($where,$page,$limit)
    {
        // 根据查询条件获取查询对象
        $query = $this->dao->getSearch($where);

        // 计算满足条件的数据总条数
        $count = $query->count();

        // 执行分页查询，并按照创建时间降序排序
        $list = $query->page($page,$limit)->order('create_time DESC')->select();

        // 返回包含数据总条数和当前页数据列表的数组
        return compact('count','list');
    }

    /**
     * 创建数据条目
     *
     * 本函数用于在数据库中创建一个新的数据条目。它通过接收特定的参数来构建数据对象，
     * 然后调用DAO层的创建方法来执行实际的数据库操作。
     *
     * @param int $merId 商户ID，用于标识数据所属的商户。
     * @param string $import 数据导入类型，指定数据的导入来源或方式。
     * @param int $type 数据类型，默认为1，用于区分不同种类的数据。
     * @return mixed 返回DAO层创建操作的结果，可能是影响的行数或布尔值。
     */
    public function create(int $merId, string $import, int $type = 1)
    {
        // 初始化数据数组，包含所有必要的字段和默认值。
        $data =  [
            'import_type' => $import, // 数据导入类型。
            'count' => 0, // 初始化计数器，表示当前数据条目的数量。
            'success' => 0, // 初始化成功计数，表示成功处理的数据条目数量。
            'mer_id' => $merId, // 商户ID。
            'status' => 0, // 数据状态，默认为0，表示未处理。
            'type' => $type // 数据类型。
        ];

        // 调用DAO层的创建方法，传入构建好的数据数组，尝试在数据库中创建新条目。
        return $this->dao->create($data);
    }
}
