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


namespace app\common\repositories\store\service;


use app\common\dao\store\service\StoreServiceReplyDao;
use app\common\repositories\BaseRepository;

/**
 * Class StoreServiceRepository
 * @package app\common\repositories\store\service
 * @author xaboy
 * @day 2020/5/29
 * @mixin StoreServiceReplyDao
 */
class StoreServiceReplyRepository extends BaseRepository
{
    /**
     * StoreServiceRepository constructor.
     * @param StoreServiceReplyDao $dao
     */
    public function __construct(StoreServiceReplyDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取列表数据
     *
     * 根据给定的条件($where)、分页($page)和每页数据量($limit)来获取列表数据。
     * 此方法首先根据条件进行查询，然后计算总记录数，最后根据分页和数据量限制获取具体的数据列表。
     *
     * @param string|array $where 查询条件，可以是字符串或数组形式的条件。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页的数据量，用于分页查询。
     * @return array 返回包含 'count' 和 'list' 两个元素的数组，'count' 表示总记录数，'list' 表示当前页的数据列表。
     */
    public function getList($where, $page, $limit)
    {
        // 根据条件进行查询
        $query = $this->search($where);

        // 计算总记录数
        $count = $query->count();

        // 根据当前页码和每页数据量进行分页查询，并获取数据列表
        $list = $query->page($page, $limit)->select();

        // 返回包含总记录数和数据列表的数组
        return compact('count', 'list');
    }

    /**
     * 创建新记录
     *
     * 本函数用于根据传入的数据创建新的记录。在创建之前，如果关键字数据是数组，
     * 则会将它们合并为一个以逗号分隔的字符串，这是为了满足数据库字段的格式要求。
     * 最后，通过调用DAO层的create方法来实际执行创建操作。
     *
     * @param array $data 包含新记录数据的数组。数组中可能包含一个名为'keyword'的字段，
     *                    如果该字段是数组，则需要转换为字符串。
     * @return mixed 返回DAO层创建操作的结果。具体类型取决于DAO层的实现。
     */
    public function create($data)
    {
        // 检查$data中'keyword'字段是否为数组，如果是，则转换为逗号分隔的字符串
        if (is_array($data['keyword'])) {
            $data['keyword'] = implode(',', $data['keyword']);
        }
        // 调用DAO层的create方法，传入处理后的$data，创建新记录
        return $this->dao->create($data);
    }

}
