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


namespace app\common\dao\system\menu;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\auth\Role;

/**
 * Class RoleDao
 * @package app\common\dao\system\menu
 * @author xaboy
 * @day 2020-04-18
 */
class RoleDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return Role::class;
    }

    /**
     * 根据条件搜索角色信息。
     *
     * 本函数用于查询特定商家下的角色信息，支持通过角色名、状态和角色ID列表进行过滤。
     *
     * @param string $merId 商家ID，用于限定查询的商家范围。
     * @param array $where 查询条件数组，包含可选的role_name（角色名）、status（状态）和role_ids（角色ID列表）。
     * @return Role 查询结果的Role对象，用于进一步的查询操作。
     */
    public function search($merId, array $where = [])
    {
        // 获取Role模型的实例，用于后续的查询操作。
        $roleModel = Role::getInstance();

        // 如果指定了角色名，则添加角色名的模糊查询条件。
        if (isset($where['role_name'])) {
            $roleModel = $roleModel->whereLike('role_name', '%' . $where['role_name'] . '%');
        }

        // 如果指定了状态，则添加状态的精确查询条件。
        if (isset($where['status'])) {
            $roleModel = $roleModel->where('status', intval($where['status']));
        }

        if(isset($where['is_organization']) && $where['is_organization'] !== '') {
            $roleModel = $roleModel->where('is_agent', '>', 0);
        }

        // 如果指定了角色ID列表，并且列表不为空，则添加角色ID在指定列表中的查询条件。
        if (isset($where['role_ids']) && $where['role_ids'] !== '') {
            $roleModel = $roleModel->whereIn('role_id', $where['role_ids']);
        }
        // 是否为商圈代理角色
        if (isset($where['is_agent']) && $where['is_agent'] !== '') {
            $roleModel = $roleModel->where('is_agent', intval($where['is_agent']));
        }
        // 是否为商圈id
        if (isset($where['circle_id']) && $where['circle_id'] !== '') {
            $roleModel = $roleModel->where('circle_id', intval($where['circle_id']));
        }
        // 角色名称
        if (isset($where['role_name']) && $where['role_name'] !== '') {
            $roleModel = $roleModel->whereLike('role_name', "%{$where['role_name']}%");
        }

        // 最终添加商家ID的查询条件，并返回Role对象。
        return $roleModel->where('mer_id', $merId);
    }

    /**
     * 获取所有角色选项
     *
     * 本函数用于根据商家ID查询所有状态为激活的角色名称及其对应的角色ID。
     * 主要用于在前端展示角色选项，例如在角色选择的下拉列表中。
     *
     * @param int $merId 商家ID，用于限定查询的商家范围。
     * @return array 返回一个数组，其中键为角色ID，值为角色名称。
     */
    public function getAllOptions(int $merId)
    {
        // 使用Role类中的getDB方法获取数据库实例，并链式调用where方法指定查询条件：状态为1且商家ID为$merId，最后调用column方法获取role_name列的值，键为role_id。
        return Role::getDB()->where('status', 1)->where('mer_id', $merId)->where('is_agent', 0)->column('role_name', 'role_id');
    }

    public function getCircleOptions(int $circleId)
    {
        return Role::getDB()->where('status', 1)->where('is_agent', 1)->where('circle_id', $circleId)->column('role_name', 'role_id');
    }

    /**
     * 根据规则ID获取关联的规则列表。
     * 该方法通过查询数据库，获取指定商家ID对应的所有状态为启用的规则，并将这些规则的ID合并为一个数组返回。
     * 这样做的目的是为了方便后续的操作，比如权限检查，可以快速地通过这个方法获取到一个商家的所有有效规则ID。
     *
     * @param int $merId 商家ID，用于查询特定商家的规则。
     * @param array $ids 规则ID数组，用于查询数据库中对应ID的规则。
     * @return array 返回一个包含所有规则ID的唯一数组。
     */
    public function idsByRules($merId, array $ids)
    {
        // 根据$merId和$ids查询数据库，获取所有状态为1的规则的rules字段
        $rules = Role::getDB()->where('status', 1)->where('mer_id', $merId)->whereIn($this->getPk(), $ids)->column('rules');

        // 初始化用于存储所有规则ID的数组
        $data = [];
        // 遍历查询结果，将每个规则的ID以逗号分隔并合并到$data数组中
        foreach ($rules as $rule) {
            $data = array_merge(explode(',', $rule), $data);
        }
        // 去除数组中的重复元素，确保每个规则ID只出现一次，并返回结果数组
        return array_unique($data);
    }

    /**
     * 检查是否存在指定ID的角色与给定的商户ID相关联
     *
     * 本函数通过查询数据库来确定是否存在一个角色，其ID为$id$且关联的商户ID为$merId$。
     * 这对于在多商户系统中验证角色是否属于特定商户非常有用。
     *
     * @param int $merId 商户ID，用于指定要查询的商户范围。
     * @param int $id 角色ID，用于指定要查询的角色。
     * @return bool 如果找到符合条件的角色则返回true，否则返回false。
     */
    public function merExists(int $merId, int $id)
    {
        // 使用Role类的静态方法getDB来获取数据库实例，并构造查询条件，查询指定商户ID和角色ID的角色记录数
        // 如果记录数大于0，则表示存在符合条件的角色，返回true；否则，返回false。
        return Role::getDB()->where($this->getPk(), $id)->where('mer_id', $merId)->count() > 0;
    }

    /**
     * 根据ID数组获取角色列表
     *
     * 本函数通过传入一个ID数组，从数据库中查询并返回对应ID的角色列表。使用了whereIn查询语句来筛选ID属于传入数组的角色。
     * 主要用于在需要根据多个ID批量获取角色信息的场景，例如在权限管理中根据用户ID批量查询其所属角色。
     *
     * @param array $ids 角色ID的数组，默认为空数组，表示查询所有角色
     * @return array 返回查询结果的数组，每个元素为一个角色的信息
     */
    public function getRolesListByIds(array $ids = [])
    {
        // 使用Role模型的getDB方法获取数据库连接，并构造查询语句，查询本模型主键在$ids数组中的记录，然后将结果转换为数组返回
        return Role::getDB()->whereIn($this->getPk(), $ids)->select()->toArray();
    }
}

