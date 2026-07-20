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
use app\common\model\user\UserGroup;
use think\db\BaseQuery;

/**
 * Class UserGroupDao
 * @package app\common\dao\user
 * @author xaboy
 * @day 2020-05-07
 */
class UserGroupDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return UserGroup::class;
    }

    /**
     * 根据条件搜索用户组信息
     *
     * 本函数旨在通过提供的条件数组来搜索用户组数据。它不直接返回搜索结果，而是返回用于数据库操作的对象。
     * 这允许进一步的数据库操作，如筛选、排序或分页，以灵活地获取所需数据。
     *
     * @param array $where 搜索条件数组。默认为空数组，表示不适用任何条件。
     *                     条件数组的键值对形式为字段名 => 值，用于构建SQL查询的WHERE子句。
     * @return UserGroup 返回UserGroup类的数据库操作对象，通过该对象可以执行进一步的数据库操作。
     */
    public function search(array $where = [])
    {
        return UserGroup::getDB();
    }

    /**
     * 获取所有用户组的名称
     *
     * 本函数旨在从用户组数据表中提取所有用户组的名称，以数组形式返回。
     * 这对于需要列出所有用户组名称的场景非常有用，比如在用户管理界面中显示用户组选项。
     *
     * @return array 返回一个包含所有用户组名称的数组
     */
    public function allOptions()
    {
        // 通过UserGroup类的静态方法getDB获取数据库连接对象，然后使用column方法提取'group_name'列的值
        return UserGroup::getDB()->column('group_name', 'group_id');
    }

}
