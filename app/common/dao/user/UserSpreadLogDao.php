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


namespace app\common\dao\user;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\user\UserSpreadLog;

class UserSpreadLogDao extends BaseDao
{

    protected function getModel(): string
    {
        return UserSpreadLog::class;
    }

    /**
     * 添加推广关系记录
     *
     * 该方法用于创建一个新的推广关系记录。通过传入用户ID、推广用户ID、旧的推广用户ID以及可选的管理员ID，
     * 来建立或更新用户的推广关系。特别地，管理员ID用于标识该操作是否由管理员触发，默认为0表示非管理员操作。
     *
     * @param int $uid 用户ID，表示被推广的用户
     * @param int $spread_uid 推广用户ID，表示进行推广的用户
     * @param int $old_spread_uid 旧的推广用户ID，用于在更新推广关系时记录原先的推广人
     * @param int $admin_id 管理员ID，可选参数，表示该操作是否由管理员触发，默认为0
     */
    public function add($uid, $spread_uid, $old_spread_uid, $admin_id = 0)
    {
        // 使用compact函数创建一个新的推广关系记录，同时包含uid、spread_uid、admin_id和old_spread_uid
        $this->create(compact('uid', 'spread_uid', 'admin_id', 'old_spread_uid'));
    }


    /**
     * 根据条件搜索用户传播日志
     *
     * 本函数用于根据提供的条件查询用户传播日志。特别地，它允许通过用户ID来筛选结果，并且总是按照创建时间降序排序。
     *
     * @param array $where 查询条件，其中可能包含用户ID（uid）作为筛选条件。
     * @return \think\db\Query 用户传播日志的查询结果对象，尚未执行查询。
     */
    public function search($where)
    {
        // 获取数据库操作对象
        return UserSpreadLog::getDB()->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
            // 如果条件中包含有效的用户ID，则添加用户ID的查询条件
            $query->where('uid', $where['uid']);
        })->order('create_time DESC'); // 按照创建时间降序排序查询结果
    }
}
