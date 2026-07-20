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


use app\common\dao\user\UserLabelDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Route;

/**
 * Class UserLabelRepository
 * @package app\common\repositories\user
 * @author xaboy
 * @day 2020-05-07
 * @mixin UserLabelDao
 */
class UserLabelRepository extends BaseRepository
{
    /**
     * @var UserLabelDao
     */
    protected $dao;

    /**
     * UserGroupRepository constructor.
     * @param UserLabelDao $dao
     */
    public function __construct(UserLabelDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件数组 $where，从数据库中检索满足条件的数据列表。
     * 它支持分页查询，每页的数据数量由 $limit 指定，查询结果将包含当前页码的数据。
     *
     * @param array $where 查询条件，以键值对形式表示，用于构建 SQL 查询的 WHERE 子句。
     * @param int $page 当前页码，用于指定要返回的数据页。
     * @param int $limit 每页的数据数量，用于指定每页返回的记录数。
     * @return array 返回包含 'count' 和 'list' 两个元素的数组，'count' 表示满足条件的总记录数，'list' 表示当前页的数据列表。
     */
    public function getList(array $where, $page, $limit)
    {
        // 根据 $where 条件搜索数据
        $query = $this->dao->search($where);

        // 计算满足条件的总记录数
        $count = $query->count($this->dao->getPk());

        // 按照 'label_id' 降序排序，并根据 $page 和 $limit 获取分页数据
        $list = $query->order('label_id DESC')->page($page, $limit)->select();

        // 返回包含总记录数和数据列表的数组
        return compact('count', 'list');
    }

    /**
     * 创建或编辑用户标签的表单
     *
     * 本函数用于生成添加或编辑用户标签的表单界面。根据$id$是否存在来判断是创建新标签还是编辑已有的标签。
     * 表单的动作（action）根据创建或编辑的状态动态生成相应的URL。返回生成的表单元素。
     *
     * @param int|null $id 用户标签的ID，如果为null，则表示创建新标签；否则，表示编辑已有的标签。
     * @param array $formData 表单的初始数据，用于填充表单字段。
     * @return \EasyWeChat\Kernel\Messages\MiniprogramForm|Form
     */
    public function form($id = null, array $formData = [])
    {
        // 判断当前操作是创建还是编辑
        $isCreate = is_null($id);

        // 根据操作类型生成表单的action URL
        $action = Route::buildUrl($isCreate ? 'systemUserLabelCreate' : 'systemUserLabelUpdate', $isCreate ? [] : compact('id'))->build();

        // 返回生成的表单，设置表单标题和初始数据
        return Elm::createForm($action, [
            Elm::input('label_name', '标签名称：')->placeholder('请输入用户标签名称')->required('请输入用户标签名称'),
        ])->setTitle($isCreate ? '添加用户标签' : '编辑用户标签')->formData($formData);
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
