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

use FormBuilder\Form;
use think\facade\Route;
use FormBuilder\Factory\Elm;
use think\db\exception\DbException;
use app\common\repositories\BaseRepository;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use FormBuilder\Exception\FormBuilderException;

use crmeb\traits\CategoresRepository;
use app\common\dao\store\StoreBrandCategoryDao as dao;

/**
 * 商品品牌分类
 */
class StoreBrandCategoryRepository extends BaseRepository
{

    use CategoresRepository;

    public function __construct(dao $dao)
    {
        $this->dao = $dao;

    }

    /**
     * 创建或编辑商品分类表单
     *
     * 本函数用于生成一个用于创建或编辑商品分类的表单。表单中包含了上级分类选择、分类名称输入、是否显示开关和排序数字输入等字段。
     * 根据$id$的值来判断是创建新分类还是编辑已有的分类，表单的动作URL也会相应地生成为创建或更新的URL。
     *
     * @param int $merId 商户ID，当前分类所属的商户ID。
     * @param int|null $id 分类ID，如果为null则表示创建新分类，否则表示编辑已有的分类。
     * @param array $formData 表单默认数据，用于填充表单字段的初始值。
     * @return \think\form\Form 返回生成的表单对象，可以进一步设置表单属性或直接渲染表单。
     */
    public function form(int $merId, ?int $id = null, array $formData = [])
    {
        // 根据$id$的值生成表单的动作URL，如果是新建分类则指向创建URL，否则指向更新URL。
        $form = Elm::createForm(is_null($id) ? Route::buildUrl('systemStoreBrandCategoryCreate')->build() : Route::buildUrl('systemStoreBrandCategoryUpdate', ['id' => $id])->build());

        // 设置表单的验证规则。
        $form->setRule([
            // 上级分类选择器，使用级联选择器实现，选项从数据库获取并格式化为级联选择器需要的格式。
            Elm::cascader('pid', '上级分类：')->options(function () use ($id, $merId) {
                $menus = $this->dao->getAllOptions(null);
                // 如果是编辑分类且当前分类有子分类，则不显示当前分类作为选项。
                if ($id && isset($menus[$id])) unset($menus[$id]);
                // 格式化分类数据为级联选择器需要的格式。
                $menus = formatCascaderData($menus, 'cate_name');
                // 添加“顶级分类”选项。
                array_unshift($menus, ['label' => '顶级分类', 'value' => 0]);
                return $menus;
            })->placeholder('请选择上级分类')->props(['props' => ['checkStrictly' => true, 'emitPath' => false]]),
            // 分类名称输入框，必填。
            Elm::input('cate_name', '分类名称：')->placeholder('请输入分类名称')->required('请输入分类名称'),
            // 是否显示开关，默认开启。
            Elm::switches('is_show', '是否显示：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
            // 排序数字输入框，默认值为0，最大值为99999。
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
        ]);

        // 设置表单标题，根据$id$的值确定是“添加分类”还是“编辑分类”。
        // 并设置表单的默认数据。
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
     * @return array|\think\form\Form
     */
    public function updateForm(int $merId, $id)
    {
        // 通过DAO层获取指定ID和商户ID的表单数据，并转换为数组格式
        return $this->form($merId, $id, $this->dao->get($id, $merId)->toArray());
    }

    /**
     * 获取祖先及子类列表
     *
     * 该方法通过查询数据并格式化为级联选择器的数据格式，然后过滤掉没有子类的项，
     * 最后返回一个仅包含有子类的类目列表。这个方法主要用于提供给前端展示类目树结构，
     * 用于级联选择器的场景，方便用户选择类目时能够以树状结构展示，直观地看到类目的层级关系。
     *
     * @return array 返回格式化后的类目列表，每个类目包含名称和子类目列表。
     */
    public function getAncestorsChildList()
    {
        // 从数据访问对象中获取所有类目的选项
        $res = $this->dao->options();
        // 格式化数据为级联选择器需要的格式，指定'cate_name'作为标签名称
        $res = formatCascaderData($res, 'cate_name');
        // 遍历格式化后的数据，移除没有子类的类目
        foreach ($res as $k => $v) {
            // 如果当前类目没有子类或者子类为空，则移除这个类目
            if (!isset($v['children']) || !count($v['children'])) {
                unset($res[$k]);
            }
        }
        // 返回重新索引后的类目列表，确保每个类目都有一个唯一的键
        return array_values($res);
    }


}
