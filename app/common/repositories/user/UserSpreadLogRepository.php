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


namespace app\common\repositories\user;


use app\common\dao\user\UserSpreadLogDao;
use app\common\repositories\BaseRepository;

/**
 * @mixin UserSpreadLogDao
 */
class UserSpreadLogRepository extends BaseRepository
{
    public function __construct(UserSpreadLogDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件数组和分页信息，从数据库中检索并返回列表数据。
     * 它首先构造一个查询条件，然后计算符合条件的记录总数，最后根据分页信息和条件获取具体的数据列表。
     * 数据列表中还包括了传播者的信息，如uid，nickname和avatar。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页显示的记录数
     * @return array 包含总数和列表数据的数组
     */
    public function getList(array $where, $page, $limit)
    {
        // 根据$where条件搜索数据
        $query = $this->dao->search($where);

        // 计算符合条件的记录总数
        $count = $query->count();

        // 分页获取数据，并且加载传播者（spread）的相关信息，但只获取指定的字段
        $list = $query->page($page, $limit)->with(['spread' => function ($query) {
            $query->field('uid,nickname,avatar');
        }])->select();

        // 返回包含总数和列表数据的数组
        return compact('count', 'list');
    }
}
