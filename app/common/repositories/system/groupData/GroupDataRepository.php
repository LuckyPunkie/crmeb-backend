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


use app\common\dao\BaseDao;
use app\common\dao\system\groupData\GroupDataDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\product\ProductLabelRepository;
use app\common\repositories\store\StoreCategoryRepository;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Route;
use think\Model;

/**
 * Class GroupDataRepository
 * @package app\common\repositories\system\groupData
 * @mixin GroupDataDao
 * @author xaboy
 * @day 2020-03-30
 */
class GroupDataRepository extends BaseRepository
{

    /**
     * GroupDataRepository constructor.
     * @param GroupDataDao $dao
     */
    public function __construct(GroupDataDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建数据条目
     *
     * 本函数用于在数据库中创建一个新的数据条目。它首先验证传入数据的有效性，然后将商家ID添加到数据数组中，
     * 最后调用DAO层的创建方法来实际执行数据插入操作。
     *
     * @param int $merId 商家ID，用于标识数据所属的商家。
     * @param array $data 数据数组，包含要创建的数据条目的各个字段及其值。
     * @param array $fieldRule 字段规则数组，用于验证数据数组中的字段值是否符合要求。
     * @return mixed 返回DAO层创建方法的执行结果，通常是新创建数据的ID或其他标识符。
     */
    public function create(int $merId, array $data, array $fieldRule)
    {
        // 验证传入的数据是否符合预设的规则
        $this->checkData($data['value'], $fieldRule);
        // 将商家ID添加到数据数组中
        $data['mer_id'] = $merId;
        // 调用DAO层的创建方法，实际执行数据插入操作
        return $this->dao->create($data);
    }

    /**
     * 更新商家信息。
     *
     * 本函数用于更新特定商家的特定信息。在执行更新之前，会通过检查数据的合法性来确保数据安全。
     * 主要涉及的步骤包括：
     * 1. 根据传入的字段规则，验证数据的合法性。
     * 2. 调用DAO层的方法，执行实际的更新操作。
     *
     * @param int $merId 商家ID，用于确定要更新的商家。
     * @param int $id 需要更新的具体信息的ID，用于定位要更新的具体信息。
     * @param array $data 包含要更新的数据的信息，其中'value'键包含实际的更新值。
     * @param array $fieldRule 字段规则，用于验证$data中'value'的合法性。
     * @return bool 更新操作的结果，true表示成功，false表示失败。
     */
    public function merUpdate($merId, $id, $data, $fieldRule)
    {
        // 根据字段规则检查数据的合法性
        $this->checkData($data['value'], $fieldRule);

        // 调用DAO层的方法执行商家信息的更新操作
        return $this->dao->merUpdate($merId, $id, $data);
    }

    /**
     * 检查数据是否符合指定的规则
     *
     * 本函数用于遍历字段规则数组，并针对每个规则检查相应的数据字段是否满足条件。
     * 如果数据字段不满足规则条件，则抛出一个验证异常。
     *
     * @param array $data 要检查的数据数组，其中包含各个字段的值。
     * @param array $fieldRule 字段规则数组，每个元素包含字段的名称、类型及其他验证条件。
     * @throws ValidateException 如果数据字段不满足规则条件，则抛出此异常。
     */
    public function checkData(array $data, array $fieldRule)
    {
        foreach ($fieldRule as $rule) {
            // 检查规则类型为数字的字段是否小于0
            // 如果字段类型为数字且值小于0，则抛出验证异常，指出该字段不能小于0。
            if ($rule['type'] === 'number' && $data[$rule['field']] < 0) {
                throw new ValidateException($rule['name'] . '不能小于0');
            }
        }
    }

    /**
     * 获取分组数据列表
     *
     * 本函数用于根据商家ID、分组ID，获取特定分组的数据列表。支持分页和限制返回数据的数量。
     * 主要用于后台管理界面的数据展示，例如商品分类的管理。
     *
     * @param int $merId 商家ID，用于限定查询的商家范围。
     * @param int $groupId 分组ID，指定要查询的分组。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页数据条数，用于控制返回的数据量。
     * @return array 返回包含数据总数和数据列表的数组。
     */
    public function getGroupDataLst(int $merId, int $groupId, int $page, int $limit): array
    {
        // 根据商家ID和分组ID构建查询条件，并按排序字段排序
        $query = $this->dao->getGroupDataWhere($merId, $groupId)->order('sort DESC,group_data_id ASC');

        // 计算满足条件的数据总数
        $count = $query->count();

        // 根据当前页码和每页数据条数，查询满足条件的数据列表
        // 返回的数据包含字段：group_data_id, value, sort, status, create_time
        $list = $query->field('group_data_id,value,sort,status,create_time')->page($page, $limit)->select()->toArray();

        // 遍历数据列表，将value字段合并到主数据数组中，以丰富数据内容
        foreach ($list as $k => $data) {
            $value = $data['value'];
            unset($data['value']);
            $data += $value;
            $list[$k] = $data;
        }

        // 返回包含数据总数和数据列表的数组
        return compact('count', 'list');
    }

    /**
     * 根据组ID和可选的ID、商家ID以及表单数据生成表单对象。
     * 此方法主要用于创建或更新数据组的表单，根据不同的场景（是否存在商家ID）和操作类型（创建或更新）构建不同的表单URL。
     * 表单字段根据组的字段定义动态生成，支持多种字段类型，包括图像、多图像、分类、标签、选择、复选框、单选框、文件等。
     *
     * @param int $groupId 组ID，用于确定表单的字段定义。
     * @param int|null $id 可选的ID，用于确定是进行更新操作还是创建操作。
     * @param int|null $merId 商家ID，用于确定表单的URL路径和某些字段的选项。
     * @param array $formData 可选的表单数据数组，用于预填充表单。
     * @return Form 返回生成的表单对象，该对象包含了表单的规则、标题和初始数据。
     */
    public function form(int $groupId, ?int $id = null, ?int $merId = null, array $formData = []): Form
    {
        // 根据组ID获取组的字段定义
        $fields = app()->make(GroupRepository::class)->fields($groupId);

        // 根据是否存在商家ID和ID，构建不同的表单URL
        if (is_null($merId)) {
            $url = is_null($id)
                ? Route::buildUrl('groupDataCreate', compact('groupId'))->build()
                : Route::buildUrl('groupDataUpdate', compact('groupId', 'id'))->build();
        } else {
            $url = is_null($id)
                ? Route::buildUrl('merchantGroupDataCreate', compact('groupId'))->build()
                : Route::buildUrl('merchantGroupDataUpdate', compact('groupId', 'id'))->build();
        }

        // 创建表单对象并设置表单URL
        $form = Elm::createForm($url);

        // 初始化表单规则数组
        $rules = [];

        // 遍历组的字段定义，根据字段类型生成相应的表单规则
        foreach ($fields as $field) {
            $rule = null;
            if ($field['type'] == 'image') {
                // 图像字段生成框架图像组件
                $rule = Elm::frameImage($field['field'], $field['name'], '/' . config('admin.' . ($merId ? 'merchant' : 'admin') . '_prefix') . '/setting/uploadPicture?field=' . $field['field'] . '&type=1')->icon('el-icon-camera')->modal(['modal' => false])->width('1000px')->height('600px')->props(['footer' => false]);
            } else if ($field['type'] == 'images') {
                // 多图像字段生成框架图像组件，支持多图
                $rule = Elm::frameImage($field['field'], $field['name'], '/' . config('admin.' . ($merId ? 'merchant' : 'admin') . '_prefix') . '/setting/uploadPicture?field=' . $field['field'] . '&type=2')->maxLength(5)->icon('el-icon-camera')->modal(['modal' => false])->width('1000px')->height('600px')->props(['footer' => false]);
            } else if ($field['type'] == 'cate') {
                // 分类字段生成级联选择器组件，并动态加载分类选项
                $rule = Elm::cascader($field['field'], $field['name'])->options(function () use ($id) {
                    $storeCategoryRepository = app()->make(StoreCategoryRepository::class);
                    $menus = $storeCategoryRepository->getAllOptions(0, 1, null, 0);
                    if ($id && isset($menus[$id])) unset($menus[$id]);
                    $menus = formatCascaderData($menus, 'cate_name');
                    return $menus;
                })->props(['props' => ['checkStrictly' => true, 'emitPath' => false]])->filterable(true)->appendValidate(Elm::validateInt()->required('请选择分类')->message('请选择分类'));
            } else if ($field['type'] == 'label') {
                // 标签字段生成选择器组件，并动态加载标签选项
                $rule = Elm::select($field['field'], $field['name'])->options(function () {
                    return app()->make(ProductLabelRepository::class)->getSearch(['mer_id' => request()->merId(), 'status' => 1])->column('label_name as label,product_label_id as value');
                })->appendValidate(Elm::validateNum()->required('请选择标签')->message('请选择标签'));
            } else if (in_array($field['type'], ['select', 'checkbox', 'radio'])) {
                // 选择、复选框和单选框字段生成相应的组件，并动态加载选项
                $options = array_map(function ($val) {
                    [$value, $label] = explode(':', $val, 2);
                    return compact('value', 'label');
                }, explode("\n", $field['param']));
                $rule = Elm::{$field['type']}($field['field'], $field['name'])->options($options);
                if ($field['type'] == 'select') {
                    $rule->filterable(true)->prop('allow-create', true);
                }
            } else if ($field['type'] == 'file') {
                // 文件字段生成上传组件，并设置上传地址
                $rule = Elm::uploadFile($field['field'], $field['name'], rtrim(systemConfig('site_url'), '/') . Route::buildUrl('configUpload', ['field' => 'file'])->build())->headers(['X-Token' => request()->token()]);
            } else {
                // 其他字段类型直接生成相应的组件
                $rule = Elm::{$field['type']}($field['field'], $field['name'], '');
            }

            // 根据字段的属性设置组件的属性、验证规则和默认值
            if ($field['props'] ?? '') {
                $props = @parse_ini_string($field['props'], false, INI_SCANNER_TYPED);
                if (is_array($props)) {
                    $rule->props($props);
                    if (isset($props['required']) && $props['required']) {
                        $rule->required();
                    }
                    if (isset($props['defaultValue'])) {
                        $rule->value($props['defaultValue']);
                    }
                }
            }

            // 将生成的表单规则添加到规则数组
            $rules[] = $rule;
        }

        // 添加排序和状态字段的规则
        $rules[] = Elm::number('sort', '排序：', 0)->precision(0)->max(99999);
        $rules[] = Elm::switches('status', '是否显示：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启');

        // 设置表单的规则
        $form->setRule($rules);

        // 设置表单的标题和初始数据
        return $form->setTitle(is_null($id) ? '添加数据' : '编辑数据')->formData(array_filter($formData, function ($item) {
            return $item !== '' && !is_null($item);
        }));
    }

    /**
     * 更新表单数据。
     *
     * 本函数用于根据给定的组ID、商家ID和表单ID，更新表单的详细数据。
     * 它首先通过查询数据库获取当前表单的数据，然后结合新的值更新表单数据，最后调用form方法来处理更新后的表单数据。
     *
     * @param int $groupId 表单所属的组ID。
     * @param int $merId 商家ID，用于确定表单所属的商家。
     * @param int $id 表单的唯一ID，用于标识要更新的具体表单。
     * @return mixed 返回更新后的表单数据。
     */
    public function updateForm(int $groupId, int $merId, int $id)
    {
        // 通过商家ID和组ID查询数据库，获取指定表单的数据，其中group_data_id为表单的唯一标识。
        $data = $this->dao->getGroupDataWhere($merId, $groupId)->where('group_data_id', $id)->find()->toArray();

        // 从查询结果中提取出表单的值部分。
        $value = $data['value'];
        // 移除查询结果中的值部分，为后续合并新值做准备。
        unset($data['value']);
        // 将提取出的值部分合并到查询结果中，以更新表单的数据。
        $data += $value;

        // 调用form方法，传入更新后的数据，完成表单的更新。
        return $this->form($groupId, $id, $merId, $data);
    }

    /**
     * 根据键值和商家ID分组数据
     *
     * 本函数旨在根据提供的键值和商家ID，从数据库中检索并返回分组的数据。
     * 它支持分页查询，每页的数据量可以自定义，默认为10条。
     * 如果提供的键值对应的分组不存在，则函数将返回空数组。
     *
     * @param string $key 分组的键值，用于查找特定的分组ID。
     * @param int $merId 商家的ID，用于限定查询的商家范围。
     * @param int|null $page 查询的页码，用于分页查询。如果未提供，则默认查询第一页。
     * @param int|null $limit 每页显示的数据条数。如果未提供，则默认为10条。
     * @return array 返回查询结果，如果找不到对应的分组，则返回空数组。
     */
    public function groupData(string $key, int $merId, ?int $page = null, ?int $limit = 10)
    {
        // 通过键值获取分组ID
        $make = app()->make(GroupRepository::class);
        $groupId = $make->keyById($key);

        // 如果找不到对应的分组ID，则直接返回空数组
        if (!$groupId) return [];

        // 调用DAO层方法，根据商家ID和分组ID进行查询，并支持分页
        return $this->dao->getGroupData($merId, $groupId, $page, $limit);
    }

    /**
     * 获取指定键对应的分组数据数量
     *
     * 本函数通过给定的键值和商家ID，查询并返回对应分组的数据数量。
     * 首先，它会尝试根据键值查找分组ID，如果找不到，则不进行后续操作。
     * 最后，它会调用dao层的方法，传入商家ID和分组ID，获取数据数量。
     *
     * @param string $key 分组的键值
     * @param int $merId 商家ID
     * @return int 分组的数据数量
     */
    public function getGroupDataCount(string $key, int $merId)
    {
        /** @var GroupRepository $make */
        $make = app()->make(GroupRepository::class);
        $groupId = $make->keyById($key);
        if (!$groupId) {
            return 0;
        }
        return $this->dao->groupDataCount($merId, $groupId);
    }

    /**
     * 根据ID和商家ID获取数据并解码
     *
     * 本函数旨在通过给定的ID和商家ID从数据库中检索特定数据，并将该数据的值部分解码为PHP对象。
     * 这对于处理存储在数据库中的JSON格式数据特别有用，允许灵活地检索和操作数据。
     *
     * @param int $id 数据记录的唯一标识符。
     * @param int $merId 商家的唯一标识符，用于区分不同商家的数据。
     * @return mixed 如果找到数据，则返回解码后的PHP对象；如果未找到数据，则返回null。
     */
    public function idByData(int $id, int $merId)
    {
        // 通过ID和商家ID从数据库获取数据
        $data = $this->dao->merGet($id, $merId);

        // 检查数据是否存在，如果不存在则返回null
        if (!$data) return null;

        // 解码数据的值部分并返回解码后的对象
        return json_decode($data['value']);
    }

    /**
     * 根据键值和商家ID分组获取数据ID
     *
     * 本函数旨在通过给定的键值和商家ID，从特定的数据组中检索数据ID。
     * 它首先尝试根据键值获取组ID，如果找不到对应的组ID，则不进行后续操作。
     * 如果找到了组ID，它将利用DAO层来获取对应商家ID和组ID的数据ID列表。
     *
     * @param string $key 键值，用于查找对应的组ID。
     * @param int $merId 商家ID，用于限定数据范围。
     * @param int|null $page 分页页码，可选参数，用于指定返回数据的页码。
     * @param int|null $limit 每页数据数量，可选参数，默认为10，用于控制返回的数据量。
     * @return array 返回数据ID列表，如果找不到组ID，则返回空数组。
     */
    public function groupDataId(string $key, int $merId, ?int $page = null, ?int $limit = 10)
    {
        // 通过依赖注入获取GroupRepository实例
        $make = app()->make(GroupRepository::class);
        // 根据键值查找对应的组ID
        $groupId = $make->keyById($key);
        // 如果找不到组ID，直接返回空数组
        if (!$groupId) return [];
        // 调用DAO层方法，根据商家ID、组ID和分页信息获取数据ID列表
        return $this->dao->getGroupDataId($merId, $groupId, $page, $limit);
    }

    public function setGroupData(string $key, $merId, array $data)
    {
        $groupRepository = app()->make(GroupRepository::class);
        $group = $groupRepository->getWhere(['group_key' => $key]);
        $fields = array_column($groupRepository->fields($group->group_id), 'field');
        $insert = [];
        foreach ($data as $k => $item) {
            unset($item['group_data_id'], $item['group_mer_id']);
            $value = [];
            foreach ($fields as $field) {
                if (isset($item[$field])) {
                    $value[$field] = $item[$field];
                }
            }
            $insert[$k] = [
                'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
                'status' => 1,
                'sort' => 0,
                'group_id' => $group->group_id,
                'mer_id' => $merId,
            ];
        }
        $this->dao->selectWhere(['group_id' => $group->group_id])->delete();
        if (count($insert)) {
            $this->dao->insertAll($insert);
        }
    }

    public function clearGroup(string $key, $merId)
    {
        $groupRepository = app()->make(GroupRepository::class);
        $group = $groupRepository->getWhere(['group_key' => $key]);
        $this->dao->selectWhere(['group_id' => $group->group_id, 'mer_id' => $merId])->delete();
    }

    /**
     * 设置分组数据
     * 该方法用于更新特定分组的数据项。它首先根据分组键获取分组信息，然后根据提供的数据数组更新或插入新的数据项。
     * 如果数据项已存在，旧的数据项将被删除，然后用新的数据项替换。
     *
     * @param string $key 分组键，用于唯一标识分组。
     * @param int $merId 商家ID，用于标识数据所属的商家。
     * @param array $data 数据数组，包含要更新或插入的字段及其值。
     */
    public function reSetDataForm(int $groupId, ?int $id, ?int $merId)
    {
        $formData = [];
        if (is_null($id)) {
            $url = is_null($merId)
                ? Route::buildUrl('groupDataCreate', compact('groupId'))->build()
                : Route::buildUrl('merchantGroupDataCreate', compact('groupId'))->build();

        } else {
            $data = $this->dao->getSearch([])->find($id);
            if (!$data) throw new ValidateException('数据不存在');
            $formData = $data->value;
            $formData['status'] = $data->status;
            $formData['sort'] = $data->sort;
            $url = is_null($merId)
                ? Route::buildUrl('systemUserSvipTypeUpdate', compact('groupId', 'id'))->build()
                : Route::buildUrl('merchantGroupDataUpdate', compact('groupId', 'id'))->build();
        }
        $form = Elm::createForm($url);
        $rules = [
            Elm::input('svip_name', '会员名：')->placeholder('请输入会员名')->required(),
            Elm::radio('svip_type', '会员类别：', '2')
                ->setOptions([
                    ['value' => '1', 'label' => '试用期',],
                    ['value' => '2', 'label' => '有限期',],
                    ['value' => '3', 'label' => '永久期',],
                ])->control([
                    [
                        'value' => '1',
                        'rule' => [
                            Elm::number('svip_number', '有效期（天）：')->required('请输入有效期（天）')->min(0),
                        ]
                    ],
                    [
                        'value' => '2',
                        'rule' => [
                            Elm::number('svip_number', '有效期（天）：')->required('请输入有效期（天）')->min(0),
                        ]
                    ],
                    [
                        'value' => '3',
                        'rule' => [
                            Elm::input('svip_number1', '有效期（天）：', '永久期')->disabled(true)->placeholder('请输入有效期'),
                            Elm::input('svip_number', '有效期（天）：', '永久期')->hiddenStatus(true)->placeholder('请输入有效期'),
                        ]
                    ],
                ])->appendRule('suffix', [
                    'type' => 'div',
                    'style' => ['color' => '#999999'],
                    'domProps' => [
                        'innerHTML' => '试用期每个用户只能购买一次，购买过付费会员之后将不在展示，不可购买',
                    ]
                ]),
            Elm::number('cost_price', '原价：')->required('请输入原价'),
            Elm::number('price', '优惠价：')->required('请输入优惠价'),
            Elm::number('sort', '排序：'),
            Elm::switches('status', '是否开启：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
        ];
        $form->setRule($rules);
        if ($formData && $formData['svip_type'] == 3) $formData['svip_number'] = '永久期';
        return $form->setTitle(is_null($id) ? '添加' : '编辑')->formData($formData);
    }


    /**
     * 查找组合数关联标签名称
     * @param array $fields
     * @param array $data
     * @return array
     *
     * @date 2023/09/09
     * @author yyw
     */
    public function handleDataValue(array $fields = [], array $data = [])
    {
        foreach ($fields as $field) {
            switch ($field['type']) {
                case 'label':   // 标签
                    $data[$field['type'] . '_name'] = app()->make(ProductLabelRepository::class)->getLabelName($data[$field['field']]);
                    break;
                case 'cate':  // 平台分类
                    $data[$field['type'] . '_name'] = app()->make(StoreCategoryRepository::class)->getCateName($data[$field['field']]);
                    break;
            }
        }

        return $data;
    }
}
