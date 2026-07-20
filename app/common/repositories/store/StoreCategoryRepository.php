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


namespace app\common\repositories\store;

use app\common\dao\store\StoreCategoryDao as dao;
use app\common\repositories\BaseRepository;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Route;
use crmeb\traits\CategoresRepository;

/**
 * 商品分类
 */
class StoreCategoryRepository extends BaseRepository
{

    use CategoresRepository;

    public function __construct(dao $dao)
    {
        $this->dao = $dao;

    }

    /**
     * 创建或编辑商品分类表单
     *
     * 该方法用于生成用于创建或编辑商品分类的表单。根据传入的$merId和$id参数，
     * 来决定是创建平台分类还是商户分类，并且确定是新建还是编辑已有分类。
     * 表单中包含上级分类选择、分类名称输入、分类图片上传、是否显示开关和排序数值输入等字段。
     *
     * @param int $merId 商户ID，用于判断是平台分类还是商户分类
     * @param int|null $id 分类ID，用于判断是编辑操作还是创建操作
     * @param array $formData 表单默认数据，用于填充表单字段
     * @return \think\form\Elm 表单对象，包含生成的表单规则和配置
     */
    public function form(int $merId, ?int $id = null, array $formData = [])
    {
        // 根据$merId判断是平台分类还是商户分类，进而设置表单的提交URL
        if ($merId) {
            $form = Elm::createForm(is_null($id) ? Route::buildUrl('merchantStoreCategoryCreate')->build() : Route::buildUrl('merchantStoreCategoryUpdate', ['id' => $id])->build());
            $msg = '';
        } else {
            $form = Elm::createForm(is_null($id) ? Route::buildUrl('systemStoreCategoryCreate')->build() : Route::buildUrl('systemStoreCategoryUpdate', ['id' => $id])->build());
            $msg = '注：平台商品分类请添加至三级分类，商户后台添加商品时才会展示该分类';
        }

        // 配置分类选择器，根据$merId和$id动态加载可选分类，同时设置必要的表单验证规则和附加信息
        $form->setRule([
            Elm::cascader('pid', '上级分类：')->options(function () use ($id, $merId) {
                $menus = $this->dao->getAllOptions($merId, 1, $this->dao->getMaxLevel($merId) - 1, 0);
                if ($id && isset($menus[$id])) unset($menus[$id]);
                $menus = formatCascaderData($menus, 'cate_name');
                array_unshift($menus, ['label' => '顶级分类', 'value' => 0]);
                return $menus;
            })->placeholder('请选择上级分类')->props(['props' => ['checkStrictly' => true, 'emitPath' => false]])->filterable(true)->appendValidate(Elm::validateInt()->required('请选择上级分类')->message('请选择上级分类'))->appendRule('suffix', [
                'type' => 'div',
                'style' => ['color' => '#999999','font-size' => '12px'],
                'domProps' => [
                    'innerHTML' => $msg,
                ]
            ]),
            Elm::input('cate_name', '分类名称：')->placeholder('请输入分类名称')->required('请输入分类名称'),
            Elm::frameImage('pic', '分类图片：', '/' . config('admin.' . ($merId ? 'merchant' : 'admin') . '_prefix') . '/setting/uploadPicture?field=pic&type=1')->width('1000px')->height('600px')->icon('el-icon-camera')->props(['footer' => false])->modal(['modal' => false, 'custom-class' => 'suibian-modal'])->appendRule('suffix', [
                'type' => 'div',
                'style' => ['color' => '#999999'],
                'domProps' => [
                    'innerHTML' => '建议尺寸：110*110px',
                ],
            ]),
            Elm::switches('is_show', '是否显示：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
        ]);

        // 设置表单标题和默认数据，返回表单对象
        return $form->setTitle(is_null($id) ? '添加分类' : '编辑分类')->formData($formData);
    }

    /**
     * 更新表单数据的方法
     *
     * 本方法用于根据给定的商户ID和表单ID来更新表单数据。它首先通过调用DAO层获取当前表单的数据，
     * 然后使用这些数据来构建一个新的表单实例，最后返回这个新构建的表单实例。
     *
     * @param int $merId 商户ID，用于指定表单所属的商户。
     * @param int $id 表单ID，用于唯一标识待更新的表单。
     * @return array 返回包含更新后表单数据的数组。
     */
    public function updateForm(int $merId, $id)
    {
        // 通过DAO层获取指定ID和商户ID的表单数据，并转换为数组格式
        return $this->form($merId, $id, $this->dao->get($id, $merId)->toArray());
    }

    /**
     * 获取分类列表
     *
     * 本函数用于获取指定条件下的分类列表，列表数据将被格式化为适用于级联选择器的数据格式。
     * 默认情况下，将返回所有级别为3的分类。
     *
     * @param string|null $status 分类的状态过滤条件，可为null表示不过滤。
     * @param int|null $lv 分类的级别过滤条件，可为null表示不过滤，默认为null，即获取级别为3的分类。
     * @return array 格式化后的分类列表数据，适用于级联选择器。
     */
    public function getList($status = null, $lv = null)
    {
        // 从数据源获取所有符合条件的分类选项，参数0表示不筛选父分类。
        $menus = $this->dao->getAllOptions(0, $status, $lv, 0);
        // 将获取到的分类数据格式化为级联选择器需要的格式，'cate_name'指定使用分类名称作为标签。
        $menus = formatCascaderData($menus, 'cate_name', $lv ?: 3);
        // 返回格式化后的分类列表数据。
        return $menus;
    }

    /**
     * 获取品牌列表
     *
     * 本方法通过依赖注入的方式，获取存储品牌仓库的实例，并调用其getAll方法，来获取所有的品牌列表。
     * 这种设计模式的使用，有利于代码的解耦和可维护性。
     *
     * @return \Illuminate\Database\Eloquent\Collection 品牌列表，以Eloquent Collection对象形式返回
     */
    public function getBrandList()
    {
        // 通过app()函数获取依赖注入的StoreBrandRepository实例，并调用其getAll方法获取所有品牌
        return app()->make(StoreBrandRepository::class)->getAll();
    }

    /**
     * 检测是否超过最低等级限制
     * @param int $id
     * @param int $level
     * @return bool
     * @author Qinii
     */
    public function checkLevel(int $id, int $level = 0, $merId = null)
    {
        $check = $this->getLevelById($id);
        if ($level)
            $check = $level;
        return ($check < $this->dao->getMaxLevel($merId)) ? true : false;
    }

    /**
     * 更新实体的状态。
     *
     * 本函数通过调用DAO层的update方法，来更新指定实体的状态或属性。它接收两个参数，
     * $id 用于指定需要更新的实体的唯一标识，$data 则包含了更新所用的新状态或属性数据。
     * 这种设计模式符合常见的业务逻辑处理过程，即通过ID定位特定实体，并应用一组新的数据来更新其状态。
     *
     * @param int $id 实体的唯一标识符。用于定位需要更新的具体实体。
     * @param array $data 包含新状态或属性的数据数组。这些数据将会被应用于指定的实体。
     * @return bool 返回更新操作的结果。通常情况下，成功更新返回true，更新失败返回false。
     */
    public function updateStatus($id, $data)
    {
        return $this->dao->update($id, $data);
    }

    /**
     * 获取热门商品
     *
     * 本函数用于查询指定商家ID下的热门商品信息。它通过查询数据库获取满足条件的商品，并返回这些商品的相关信息。
     * 热门商品是指被标记为热门（is_hot = 1）且属于特定商家（mer_id）的商品。
     *
     * @param int $merId 商家ID，用于限定查询的商品属于哪个商家。
     * @return array 返回一个包含热门商品信息的数组，如果不存在热门商品，则数组中hot键对应的值为空。
     */
    public function getHot($merId)
    {
        // 查询数据库中被标记为热门且属于指定商家ID的商品，隐藏某些字段
        $hot = $this->dao->getSearch(['is_hot' => 1, 'mer_id' => $merId])->hidden(['path', 'level', 'mer_id', 'create_time'])->select();

        // 如果查询结果存在，则将其转换为数组格式
        if ($hot) $hot->toArray();

        // 返回包含查询结果的数组
        return compact('hot');
    }


    /**
     * 创建或编辑积分分类的表单
     *
     * 根据传入的$id$决定是创建新的积分分类还是编辑已有的积分分类。
     * 如果$id$存在，则从数据库中获取相应的分类数据，并创建一个用于更新分类的表单。
     * 如果$id$不存在，则创建一个用于新增分类的表单。
     *
     * @param int|null $id 积分分类的ID，如果为null则表示创建新的分类，否则表示编辑已有的分类。
     * @return \think\form\Form 返回创建好的表单对象，包含相应的表单规则和数据。
     */
    public function pointsForm(?int $id)
    {
        $formData = [];
        // 根据$id$是否存在来决定是获取现有分类的数据还是创建一个新的表单
        if ($id) {
            // 如果$id$存在，从数据库中获取分类数据，并转换为数组格式
            $formData = $this->dao->get($id)->toArray();
            // 创建用于更新分类的表单，表单提交地址为更新分类的路由
            $form = Elm::createForm(Route::buildUrl('pointsCateUpdate', ['id' => $id])->build());
        } else {
            // 如果$id$不存在，创建用于新增分类的表单，表单提交地址为创建分类的路由
            $form = Elm::createForm(Route::buildUrl('pointsCateCreate')->build());
        }
        // 设置表单的规则，包括分类名称、是否显示、排序和类型等字段
        $form->setRule([
            Elm::input('cate_name', '分类名称：')->placeholder('请输入分类名称')->required(),
            Elm::switches('is_show', '是否显示：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
            Elm::number('type', '类型：', 1)->hiddenStatus(true),
        ]);
        // 设置表单的标题，并根据$id$是否存在来决定是显示“添加分类”还是“编辑分类”的标题
        // 同时设置表单的数据为之前获取的分类数据，如果$id$不存在，则为空数组
        return $form->setTitle(is_null($id) ? '添加分类' : '编辑分类')->formData($formData);
    }

    /**
     * 获取指定ID分类的所有父分类名称
     *
     * 递归调用，用于构建一个分类的完整路径名。从给定的分类ID开始，
     * 逐级向上查找其父分类，直到找到根分类为止。
     *
     * @param int $id 分类的ID
     * @return string 返回所有父分类名称的字符串，使用斜杠分隔
     */
    public function getAllFatherName($id)
    {
        // 通过ID获取分类信息
        $info = $this->dao->get($id);

        // 如果分类信息为空，则返回一个空格，避免返回null或false引起后续处理问题
        if (empty($info)) {
            return ' ';
        }

        // 如果当前分类有父分类（pid大于0），则递归调用本方法获取父分类的名称
        if ($info['pid'] > 0) {
            $parentName = $this->getAllFatherName($info['pid']);
            // 构建当前分类及其所有父分类的路径，并返回
            return $parentName . '/' . $info['cate_name'];
        } else {
            // 如果当前分类没有父分类（根分类），则直接返回当前分类的名称
            return $info['cate_name'];
        }
    }


    /**
     * 根据分类ID获取分类名称
     *
     * 本函数通过传入的分类ID，查询分类表中对应ID的分类名称。
     * 如果查询结果存在，则返回分类名称；如果查询结果不存在，则返回空字符串。
     *
     * @param int $cate_id 分类ID，作为查询条件
     * @return string 返回查询到的分类名称，如果未查询到则返回空字符串
     */
    public function getCateName($cate_id)
    {
        // 使用DAO对象查询数据库，根据主键$cate_id获取对应的分类名称
        // 如果查询结果存在，则返回cate_name字段的值；如果不存在，则返回空字符串
        return $this->dao->query([$this->dao->getPk() => $cate_id])->value('cate_name') ?? '';
    }
    /**
     * 获取指定商户ID的一级商品分类列表
     *
     * @param [type] $merId
     * @return void
     */
    public function getCategoryByMerId(int $merId)
    {
        $where = [
            'mer_id' => $merId,
            'is_show' => 1,
            'pid' => 0
        ];
        return $this->dao->selectWhere($where, 'store_category_id,mer_id,cate_name,pid');
    }

}
