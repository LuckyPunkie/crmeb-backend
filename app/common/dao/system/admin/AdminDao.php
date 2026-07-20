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


namespace app\common\dao\system\admin;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\admin\Admin;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;

class AdminDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return Admin::class;
    }

    /**
     * 根据条件搜索管理员信息。
     *
     * 方法通过懒加载方式构建查询条件，只有在需要时才添加相应的查询条件，提高了查询的灵活性和性能。
     *
     * @param array $where 查询条件数组，包含各种可能的搜索条件。
     * @param int $is_del 是否已删除的标志，0表示未删除，非0表示已删除。
     * @param bool $level 是否考虑级别条件，true表示只查询级别不为0的记录。
     * @return BaseQuery 查询构建器实例，可用于进一步的查询操作或数据获取。
     */
    public function search(array $where = [], $is_del = 0,$level = true)
    {
        // 获取管理员表的数据库查询构建器
        $query = Admin::getDB();

        // 如果考虑级别条件，添加级别不为0的查询条件
        if($level) $query->where('level', '<>', 0);

        // 如果指定了删除状态，添加相应的查询条件
        $query->when($is_del !== null, function ($query) use ($is_del) {
            $query->where('is_del', $is_del);
        });

        // 如果指定了日期范围，添加日期范围的查询条件
        $query->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
            getModelTime($query, $where['date']);
        });

        // 如果指定了关键字，添加关键字的模糊查询条件
        if (isset($where['keyword']) && $where['keyword'] !== '') {
            $query->whereLike('real_name|account', '%' . $where['keyword'] . '%');
        }

        // 如果指定了状态，添加状态的查询条件
        if (isset($where['status']) && $where['status'] !== '') {
            $query->where('status', intval($where['status']));
        }

        if (isset($where['is_agent']) && $where['is_agent'] !== '') {
            $query->where('is_agent', intval($where['is_agent']))->where('circle_agent_id', 0);
        }

        if (isset($where['region_id']) && $where['region_id'] !== '') {
            $query->whereFindInSet('region_ids', $where['region_id']);
            // $query->whereLike('region_ids', "%,{$where['region_id']},%");
        }

        if(isset($where['circle_agent_id']) && $where['circle_agent_id'] !== '') {
            $query->where('circle_agent_id', intval($where['circle_agent_id']));
        }

        // 返回构建好的查询构建器实例
        return $query;
    }


    /**
     * 检查指定ID的记录是否存在且未被删除。
     *
     * 本函数通过查询数据库来确定给定ID的记录是否存在，并且其删除标志位是否为0（即未被删除）。
     * 主要用于在执行删除、更新等操作前验证记录的有效性，避免错误的操作导致数据丢失或错误。
     *
     * @param int $id 要检查的记录的唯一标识ID。
     * @return bool 如果记录存在且未被删除，则返回true；否则返回false。
     */
    public function exists(int $id)
    {
        // 构建查询条件，查询指定ID且未被删除的记录
        $query = ($this->getModel())::getDB()->where($this->getPk(), $id)->where('is_del', 0);

        // 判断查询结果的数量是否大于0，如果大于0则表示记录存在
        return $query->count() > 0;
    }

    /**
     * 根据ID获取管理员信息
     *
     * 本函数用于通过管理员的ID，从数据库中查询并返回对应的管理员信息。
     * 特别注意，这里只返回未被删除（is_del字段为0）的管理员信息。
     *
     * @param int $id 管理员的唯一标识ID
     * @return array|false 返回管理员的信息数组，如果找不到则返回false。
     */
    public function get( $id)
    {
        // 通过Admin类的单例模式获取管理员实例，然后查询数据库中is_del为0且ID为$id的管理员信息
        return Admin::getInstance()->where('is_del', 0)->find($id);
    }

    /**
     * 检查指定字段是否存在指定值（且未被删除）。
     *
     * 此方法用于查询数据库中是否存在指定字段的值，并且该记录没有被删除。
     * 可以通过传递一个排除ID来排除特定的记录。
     *
     * @param string $field 要检查的字段名。
     * @param mixed $value 字段应该具有的值。
     * @param int|null $except 排除的ID，可选参数，用于排除特定的记录。
     * @return bool 如果找到符合条件的记录，则返回true，否则返回false。
     */
    public function fieldExists($field, $value, ?int $except = null): bool
    {
        // 初始化查询，查询具有指定字段值并且未被删除的记录
        $query = ($this->getModel())::getDB()->where($field, $value)->where('is_del', 0);

        // 如果提供了排除ID，则添加条件以排除该ID的记录
        if (!is_null($except)) {
            $query->where($this->getPk(), '<>', $except);
        }

        // 返回查询结果是否存在，即记录数是否大于0
        return $query->count() > 0;
    }

    /**
     * 通过管理员账号获取管理员信息
     *
     * 本函数用于查询并返回与指定管理员账号相关联的信息。它通过管理员账号查找管理员数据库记录，
     * 并返回包含管理员账号、密码、真实姓名、登录次数、管理员ID和状态等信息的对象。
     * 这样做的目的是为了提供一种方式来验证管理员身份或获取管理员的详细信息。
     *
     * @param string $account 管理员账号
     * @return array|null 包含管理员信息的数组，如果找不到管理员则返回null
     */
    public function accountByAdmin(string $account)
    {
        // 通过管理员类的单例获取管理员实例
        return Admin::getInstance()->where('account', $account)
            // 确保查询的管理员未被删除
            ->where('is_del', 0)
            // 指定查询的字段，以减少不必要的数据返回
            ->field(['account', 'pwd', 'real_name', 'login_count', 'admin_id', 'status', 'is_agent', 'circle_agent_id', 'region_ids'])
            ->append(['region_name'],true)
            // 执行查询并返回结果
            ->find();
    }
}

