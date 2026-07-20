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


namespace app\common\repositories\system\groupData;


use app\common\dao\system\groupData\GroupDao;
use app\common\model\system\groupData\SystemGroup;
use app\common\repositories\BaseRepository;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Route;
use think\Model;

/**
 * Class GroupRepository
 * @package app\common\repositories\system\groupData
 * @mixin GroupDao
 * @author xaboy
 * @day 2020-03-27
 */
class GroupRepository extends BaseRepository
{

    /**
     *
     */
    const TYPES = ['input' => '文本框', 'number' => '数字框', 'textarea' => '多行文本框', 'radio' => '单选框', 'checkbox' => '多选框', 'select' => '下拉框', 'file' => '文件上传', 'image' => '图片上传', 'images' => '多图上传', 'color' => '颜色选择框', 'label' => '标签', 'cate' => '平台分类'];


    /**
     * GroupRepository constructor.
     * @param GroupDao $dao
     */
    public function __construct(GroupDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建新记录
     *
     * 本函数用于根据提供的数据创建新的记录。它首先对数据字段进行整理，然后调用DAO层的创建方法来实际执行插入操作。
     * 这个方法是业务逻辑层与数据访问层之间的桥梁，它抽象了数据插入的过程，使得上层应用不需要直接与数据库操作语句打交道。
     *
     * @param array $data 包含待创建记录的数据数组，其中必须包含字段信息。
     * @return mixed 返回DAO层创建方法的执行结果，通常是一个自增的ID或者影响行数。
     */
    public function create(array $data)
    {
        // 整理字段数据，确保数据格式符合数据库要求
        $data['fields'] = $this->tidyFields($data['fields']);

        // 调用DAO层的创建方法，实际执行数据插入操作
        return $this->dao->create($data);
    }

    /**
     * 更新数据库中特定ID的记录。
     *
     * 本函数接受一个ID和一组数据，用于更新数据库中相应记录的字段值。
     * 首先，它将数据字段进行整理，然后将其转换为JSON格式，这是为了满足数据库字段的特定需求。
     * 最后，它调用DAO（数据访问对象）层的update方法，执行实际的数据库更新操作。
     *
     * @param int $id 要更新的记录的唯一标识符。
     * @param array $data 包含要更新的字段及其新值的数组。
     * @return bool 返回更新操作的结果，通常是TRUE表示成功，FALSE表示失败。
     */
    public function update(int $id, array $data)
    {
        // 整理并序列化数据字段，以满足数据库存储要求
        $data['fields'] = json_encode($this->tidyFields($data['fields']));

        // 调用DAO方法执行数据库更新操作
        return $this->dao->update($id, $data);
    }

    /**
     * 整理字段信息
     *
     * 该方法用于接收一个字段数组，每个字段数组包含字段的类型、键、名称等信息。
     * 方法会验证每个字段的必要属性是否存在，并确保字段的键不重复。
     * 最后，它将整理后的字段信息存储在一个新数组中并返回。
     *
     * @param array $fields 字段数组，包含多个字段的定义
     * @return array 整理后的字段信息数组
     * @throws ValidateException 如果字段数组为空、字段类型或键缺失、字段键重复，则抛出异常
     */
    public function tidyFields(array $fields): array
    {
        // 检查字段数组是否为空，如果为空则抛出异常
        if (!count($fields))
            throw new ValidateException('字段最少设置一个');

        // 初始化用于存储整理后字段信息的数组
        $data = [];
        // 初始化用于存储已处理字段键的数组
        $fieldKey = [];

        // 遍历字段数组，对每个字段进行处理
        foreach ($fields as $field) {
            // 检查字段是否缺少类型定义，如果缺少则抛出异常
            if (!isset($field['type']))
                throw new ValidateException('字段类型不能为空');
            // 检查字段是否缺少键定义，如果缺少则抛出异常
            if (!isset($field['field']))
                throw new ValidateException('字段key不能为空');
            // 检查字段是否缺少名称定义，如果缺少则抛出异常
            if (!isset($field['name']))
                throw new ValidateException('字段名称不能为空');
            // 检查字段键是否重复，如果重复则抛出异常
            if (in_array($field['field'], $fieldKey))
                throw new ValidateException('字段key不能重复');

            // 将字段的键添加到已处理字段键的数组中
            $fieldKey[] = $field['field'];
            // 整理字段信息，并添加到整理后字段信息的数组中
            $data[] = [
                'name' => $field['name'],
                'field' => $field['field'],
                'type' => $field['type'],
                'param' => $field['param'] ?? '', // 如果字段有参数，则使用字段的参数，否则使用空字符串
                'props' => $field['props'] ?? '' // 如果字段有属性，则使用字段的属性，否则使用空字符串
            ];
        }

        // 返回整理后的字段信息数组
        return $data;
    }

    /**
     * 创建或编辑组合数据表单
     *
     * 该方法用于生成一个用于添加或编辑组合数据的表单。表单包含了一系列的输入字段和选择项，
     * 用于收集关于组合数据的各种信息，如后台类型、组合数据名称、键等。
     *
     * @param int|null $id 组合数据的ID，如果为null，则表示创建新组合数据；否则，表示编辑已有的组合数据。
     * @param array $formData 表单的初始数据，用于填充表单字段。
     * @return Form 返回生成的表单对象。
     */
    public function form(?int $id = null, array $formData = []): Form
    {
        // 根据$id的值决定表单提交的URL，如果是新建，则提交到'groupCreate'路由；如果是编辑，则提交到'groupUpdate'路由。
        $formUrl = is_null($id) ? Route::buildUrl('groupCreate')->build() : Route::buildUrl('groupUpdate', ['id' => $id])->build();
        $form = Elm::createForm($formUrl);

        // 定义表单的验证规则和字段。包括选择后台类型、输入组合数据名称、键、说明等字段。
        $form->setRule([
            // 选择后台类型字段，是一个下拉列表，必须选择。
            Elm::select('user_type', '后台类型：', 0)->options([
                ['label' => '总后台配置', 'value' => 0],
                ['label' => '商户后台配置', 'value' => 1],
            ])->placeholder('请选择后台类型')->requiredNum('请选择后台类型'),
            // 输入组合数据名称字段，必须输入。
            Elm::input('group_name', '组合数据名称：')->placeholder('请输入组合数据名称')->required('请输入组合数据名称'),
            // 输入组合数据键字段，必须输入。
            Elm::input('group_key', '组合数据key：')->placeholder('请输入组合数据key')->required('请输入组合数据key'),
            // 输入组合数据说明字段，非必须。
            Elm::input('group_info', '组合数据说明：')->placeholder('请输入组合数据说明'),
            // 输入排序字段，是一个数字输入框，默认为0，最大值为99999。
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
            // 字段组，用于定义一组相关的字段，这里包括了类型、名称、键等字段。
            Elm::group('fields', '字段：')->rules([
                // 选择字段类型，是一个必选的下拉列表，选项根据self::TYPES动态生成。
                Elm::select('type', '类型：')->required('请选择字段类型')->options(function () {
                    $options = [];
                    foreach (self::TYPES as $value => $label) {
                        $options[] = compact('value', 'label');
                    }
                    return $options;
                }),
                // 输入字段名称字段。
                Elm::input('name', '字段名称：')->placeholder('请输入字段名称'),
                // 输入字段键字段。
                Elm::input('field', '字段key：')->placeholder('请输入字段key'),
                // 输入选择项字段，用于配置多选框等需要选择项的字段类型。
                Elm::textarea('param', '选择项：'),
                // 输入配置字段，用于配置字段的额外属性。
                Elm::textarea('props', '配置：'),
            ]),
        ]);

        // 设置表单的标题，并根据$id的值决定是添加还是编辑组合数据。最后设置表单的初始数据。
        return $form->setTitle(is_null($id) ? '添加组合数据' : '编辑组合数据')->formData($formData);
    }

    /**
     * 更新表单数据。
     * 该方法通过指定的ID获取表单数据，并使用这些数据来更新表单。
     * 主要用于在前端展示已存在数据的表单，以便用户可以查看并修改这些数据。
     *
     * @param int $id 表单数据的唯一标识ID。
     * @return array|Form
     */
    public function updateForm(int $id)
    {
        // 通过ID获取表单数据，并转换为数组格式，用于更新表单
        return $this->form($id, $this->dao->get($id)->toArray());
    }

    /**
     * 分页获取数据列表
     *
     * 本函数用于根据指定的页码和每页数据量来获取数据列表，并同时返回数据总数。
     * 这样可以在前端实现分页功能，展示数据时既可以知道总数据量，也可以根据页码获取对应页的数据。
     *
     * @param int $page 当前页码，用于指定要获取哪一页的数据。
     * @param int $limit 每页的数据量，用于指定每页显示多少条数据。
     * @return array 返回包含数据列表和数据总数的数组。
     */
    public function page(int $page, int $limit)
    {
        // 根据页码和每页数据量进行分页查询，并隐藏某些字段，按创建时间降序排序
        $list = $this->dao->page($page, $limit)->hidden(['fields', 'sort'])->order('create_time DESC')->select();

        // 统计总数据量
        $count = $this->dao->count();

        // 将数据总数和数据列表一起返回
        return compact('count', 'list');
    }

    /**
     * 获取指定ID对应字段的键名列表
     *
     * 本函数通过查询特定ID的字段信息，然后提取出这些信息中的键名，最终返回一个包含所有键名的数组。
     * 这样做的目的是为了提供一种方式，以字段键名为基础，进行后续的数据处理或检索操作。
     *
     * @param int $id 需要查询的记录ID
     * @return array 返回包含所有字段键名的数组
     */
    public function keys(int $id): array
    {
        // 使用array_column函数从字段信息数组中提取出所有字段的键名
        return array_column($this->fields($id), 'field');
    }

    /**
     * 删除记录并清理关联数据。
     *
     * 本函数通过开启数据库事务，确保删除操作和关联数据清理操作要么同时成功，
     * 要么同时失败，以维护数据的一致性。具体操作包括：
     * 1. 删除指定ID的记录。
     * 2. 清理与被删除记录相关的分组数据。
     *
     * @param int $id 需要删除的记录的ID。
     */
    public function delete($id)
    {
        // 开启数据库事务处理
        Db::transaction(function () use ($id) {
            // 删除指定ID的记录
            $this->delete($id);

            // 实例化分组数据仓库，用于后续的清理操作
            /** @var GroupDataRepository $make */
            $make = app()->make(GroupDataRepository::class);
            // 清理与被删除记录相关的分组数据
            $make->clearGroup($id);
        });
    }


    /***
     * 处理关联字段显示
     * @param $fields
     * @return array
     *
     * @date 2023/09/09
     * @author yyw
     */
    public function handleFields($fields = [])
    {
        foreach ($fields as &$field) {
            switch ($field['type']) {
                case 'label':
                case 'cate':
                    $field['field'] = $field['type'] . '_name';
                    break;
            }
        }
        return $fields;
    }

}
