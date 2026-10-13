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


namespace app\common\repositories\system\auth;


//附件
use app\common\dao\system\menu\RoleDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\facade\Route;


/**
 * 角色身份
 */
class RoleRepository extends BaseRepository
{
    public function __construct(RoleDao $dao)
    {
        /**
         * @var RoleDao
         */
        $this->dao = $dao;
    }

    /**
     * 商户查询功能
     * 根据给定的商户ID和查询条件，分页查询符合条件的数据，并返回总数和查询结果列表。
     *
     * @param int $merId 商户ID，用于指定查询的商户。
     * @param array $where 查询条件数组，用于指定查询的过滤条件。
     * @param int $page 当前页码，用于指定查询的页码。
     * @param int $limit 每页数据条数，用于指定每页返回的数据数量。
     * @return array 返回包含总数和列表的数据数组，列表中的每个角色都包含了规则名称。
     */
    public function search(int $merId, array $where, $page, $limit)
    {
        // 根据商户ID和查询条件进行查询
        $query = $this->dao->search($merId, $where);
        // 统计查询结果的总数
        $count = $query->count();
        // 进行分页查询，并获取查询结果列表
        $list = $query->page($page, $limit)->select();

        // 遍历查询结果列表，为每个角色添加规则名称
        foreach ($list as $k => $role) {
            $list[$k]['rule_name'] = $role->ruleNames();
        }

        // 返回查询结果的总数和列表
        return compact('count', 'list');
    }

    /**
     * 更新数据条目。
     *
     * 本函数用于根据提供的ID和数据数组更新数据库中的相应条目。特别地，如果数据数组中包含'rules'字段，
     * 该字段将被处理为以逗号分隔的字符串，以适应数据库的存储格式。这一步处理是必要的，因为数据库中
     * 'rules'字段的类型通常为字符串，而不是数组。
     *
     * @param int $id 数据条目的唯一标识符。此ID用于在数据库中定位特定的条目。
     * @param array $data 包含要更新的数据的数组。数组的键是数据库字段名，值是要更新的字段值。
     *                    如果数组中包含'rules'键，其值将被转换为逗号分隔的字符串。
     * @return mixed 返回更新操作的结果。具体类型取决于DAO层实现的返回类型。
     */
    public function update(int $id, array $data)
    {
        // 如果$data数组中包含'rules'键，将其值转换为逗号分隔的字符串
        if (isset($data['rules'])) {
            $data['rules'] = implode(',', $data['rules']);
        }
        // 调用DAO层的update方法执行更新操作，并返回操作结果
        return $this->dao->update($id, $data);
    }

    /**
     * 创建或编辑角色的表单
     *
     * 根据传入的$merType和$id参数，决定是创建还是编辑商家角色或系统角色，并构造相应的表单。
     * $merType用于区分商家角色和系统角色，$id用于指定要编辑的角色ID。如果$id为null，则表示创建新角色。
     * 表单包含角色名称输入框、权限选择树和状态开关。
     *
     * @param int $merType 角色类型，0表示系统角色，非0表示商家角色。
     * @param int|null $id 角色ID，用于编辑已有角色。
     * @param array $formData 表单默认数据，用于填充表单。
     * @param array $params 
     * @return Form 返回构造好的表单对象。
     */
    public function form($merType = 0, ?int $id = null, array $formData = [], $params = []): Form
    {
        // 根据$merType和$id构建表单的提交URL
        if ($merType) {
            $form = Elm::createForm(is_null($id) ? Route::buildUrl('merchantRoleCreate')->build() : Route::buildUrl('merchantRoleUpdate', ['id' => $id])->build());
        } else {
            $form = Elm::createForm(is_null($id) ? Route::buildUrl('systemRoleCreate')->build() : Route::buildUrl('systemRoleUpdate', ['id' => $id])->build());
        }
        // 获取权限选项树，用于填充权限选择控件
        $options = app()->make(MenuRepository::class)->getTree($merType, $params['is_agent']??0, $params['circle_id']??0);
        // 设置表单的验证规则
        $form->setRule([
            Elm::input('role_name', '身份名称：')->placeholder('请输入身份名称')->required('请输入身份名称'),
            Elm::tree('rules', '权限：')->data($options)->showCheckbox(true),
            Elm::switches('status', '是否开启：', 1)->inactiveValue(0)->activeValue(1)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
            Elm::hidden('is_agent', '组织类型')->value($params['is_agent']??0),
            Elm::hidden('circle_id', '组织id')->value($params['circle_id']??0)
        ]);

        // 设置表单标题并返回表单对象
        return $form->setTitle(is_null($id) ? '添加身份' : '编辑身份')->formData($formData);
    }

    /**
     * 更新表单数据的方法
     *
     * 本方法用于根据给定的ID获取现有数据，并构建一个更新表单的实例。
     * 主要用于在前端展示现有的数据以便用户进行编辑更新。
     *
     * @param bool $is_mer 是否为商家端，用于区分不同的表单展示逻辑。
     * @param int $id 需要更新的数据的ID。
     * @return Form 返回一个表单实例，该实例包含了需要更新的数据。
     */
    public function updateForm($is_mer, int $id, $params = [])
    {
        // 通过ID获取数据库中的数据，并转换为数组格式
        // 这里的$this->dao->get($id)用于从数据库中获取指定ID的数据
        // ->toArray()则是将获取到的数据对象转换为数组，以便后续处理
        return $this->form($is_mer, $id, $this->dao->get($id)->toArray(), $params);
    }

    /**
     * 检查角色是否匹配
     * 该方法用于验证给定的角色数组是否与商家在数据库中的角色完全匹配。
     * 这是一个业务逻辑层的方法，依赖于数据访问对象（DAO）来执行数据库查询。
     *
     * @param array $role 包含需要检查的角色ID的数组。
     * @param int $merId 商家的唯一标识ID。
     * @return bool 返回true如果角色完全匹配，否则返回false。
     */
    public function checkRole(array $role, $merId)
    {
        // 通过DAO查询商家角色ID，并筛选出状态为1的记录。
        $rest = $this->dao->search($merId, ['role_ids' => $role,'status' => 1])->column('role_id');

        // 对传入的角色数组和查询结果进行排序，以便后续比较。
        sort($role);
        sort($rest);

        // 比较排序后的角色数组和查询结果数组是否完全相同。
        // 使用sort函数的返回值进行比较，如果相同则返回true，否则返回false。
        return (sort($role) == sort($rest)) ?  true: false;
    }
}
