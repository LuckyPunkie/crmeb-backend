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


use app\common\dao\user\UserGroupDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Route;

/**
 * Class UserGroupRepository
 * @package app\common\repositories\user
 * @author xaboy
 * @day 2020-05-07
 * @mixin UserGroupDao
 */
class UserGroupRepository extends BaseRepository
{
    /**
     * @var UserGroupDao
     */
    protected $dao;

    /**
     * UserGroupRepository constructor.
     * @param UserGroupDao $dao
     */
    public function __construct(UserGroupDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件数组 $where，从数据库中检索满足条件的数据列表。
     * 它支持分页查询，每页的数据数量由 $limit 指定，查询的页码由 $page 指定。
     * 函数返回一个包含两个元素的数组，第一个元素是数据总数 $count，第二个元素是当前页的数据列表 $list。
     *
     * @param array $where 查询条件数组
     * @param int $page 查询的页码
     * @param int $limit 每页的数据数量
     * @return array 返回包含 'count' 和 'list' 两个元素的数组
     */
    public function getList(array $where, $page, $limit)
    {
        // 根据条件查询数据
        $query = $this->dao->search($where);

        // 统计满足条件的数据总数
        $count = $query->count($this->dao->getPk());

        // 获取当前页的数据列表
        $list = $query->page($page, $limit)->select();

        // 返回数据总数和当前页的数据列表
        return compact('count', 'list');
    }

    /**
     * 创建或编辑用户分组的表单
     *
     * 本函数用于生成添加新用户分组或编辑已存在用户分组的表单。根据$id$参数的值来判断是创建新分组还是编辑已有分组。
     * 表单的动作（action）根据$id$是否有值来决定是向系统请求创建新用户分组还是更新已有用户分组的信息。
     *
     * @param int|null $id 用户分组的ID。如果ID为null，则表示创建新分组；如果ID有值，则表示编辑已存在的分组。
     * @param array $formData 表单的初始数据。用于在编辑分组时填入已有的分组信息。
     * @return \EasyWeChat\Kernel\Messages\MiniprogramForm|Form
     */
    public function form($id = null, array $formData = [])
    {
        // 判断当前操作是创建还是编辑用户分组
        $isCreate = is_null($id);

        // 根据操作类型生成表单的动作URL
        $action = Route::buildUrl($isCreate ? 'systemUserGroupCreate' : 'systemUserGroupUpdate', $isCreate ? [] : compact('id'))->build();

        // 返回生成的表单对象，设置表单的动作、标题和初始数据
        return Elm::createForm($action, [
            Elm::input('group_name', '分组名称：')->placeholder('请输入用户分组名称')->required('请输入用户分组名称')
        ])->setTitle($isCreate ? '添加用户分组' : '编辑用户分组')->formData($formData);
    }

    /**
     * 更新表单数据。
     * 该方法用于根据给定的ID获取数据库中的记录，并使用这些数据来构建一个表单，以便用户可以查看或编辑这些数据。
     *
     * @param int $id 表单记录的唯一标识符。
     * @return array|\EasyWeChat\Kernel\Messages\MiniprogramForm|Form
     */
    public function updateForm($id)
    {
        // 通过ID从数据库获取记录，并转换为数组格式，用于填充表单
        return $this->form($id, $this->dao->get($id)->toArray());
    }

}
