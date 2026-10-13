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


use app\common\dao\user\FeedbackCateoryDao as dao;
use app\common\repositories\BaseRepository;
use crmeb\traits\CategoresRepository;
use FormBuilder\Form;
use think\facade\Route;
use FormBuilder\Factory\Elm;

class FeedBackCategoryRepository extends BaseRepository
{
    use CategoresRepository;
    /**
     * @param FeedbackDao $dao
     */
    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建或编辑反馈分类表单
     *
     * 本函数用于生成一个用于创建或编辑反馈分类的表单。表单包含必要的字段和规则，
     * 以及根据当前操作是创建还是编辑来设置表单的行为和标题。
     *
     * @param int $merId 商户ID，当前操作的商户标识
     * @param int|null $id 分类ID，如果为null则表示创建新分类，否则表示编辑已存在的分类
     * @param array $formData 表单数据数组，用于预填充表单字段的值
     * @return Form|\think\form\Form
     */
    public function form(int $merId, $id = null, array $formData = [])
    {
        // 根据$id的值决定创建还是编辑表单的提交URL
        $url = is_null($id) ? Route::buildUrl('systemUserFeedBackCategoryCreate')->build() : Route::buildUrl('systemUserFeedBackCategoryUpdate', ['id' => $id])->build();
        $form = Elm::createForm($url);

        // 设置表单验证规则
        $form->setRule([
            // 选择上级分类，使用级联选择器，动态获取分类选项并排除当前分类
            Elm::cascader('pid','上级分类：')->placeholder('请选择上级分类')->options(function()use($id,$merId){
                $menus = $this->dao->getAllOptions(null);
                if ($id && isset($menus[$id])) unset($menus[$id]);
                $menus = formatCascaderData($menus, 'cate_name');
                array_unshift($menus, ['label' => '顶级分类', 'value' => 0]);
                return $menus;
            })->props(['props' => ['checkStrictly' => true, 'emitPath' => false]]),
            // 输入分类名称，必填字段
            Elm::input('cate_name', '分类名称：')->placeholder('请输入分类名称')->required('请输入分类名称'),
            // 开关切换，用于设置分类是否显示
            Elm::switches('is_show','是否显示：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
            // 数字输入框，用于设置分类的排序值
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
        ]);

        // 设置表单标题，并返回表单对象
        return $form->setTitle(is_null($id) ? '添加分类' : '编辑分类')->formData($formData);
    }

    /**
     * 更新表单数据的方法
     *
     * 本方法用于根据给定的商户ID和表单ID来更新表单数据。它首先通过ID获取现有的表单数据，
     * 然后使用这些数据来构建一个新的表单实例，最后返回这个新实例。
     * 这种做法允许用户在前端界面直接编辑并更新表单数据，而无需直接操作数据库。
     *
     * @param int $merId 商户ID，用于确定表单所属的商户。
     * @param int $id 表单ID，用于唯一标识待更新的表单。
     * @return mixed 返回更新后的表单数据实例，具体类型取决于表单数据的实现。
     */
    public function updateForm(int $merId,int $id)
    {
        // 通过ID获取当前表单的数据，并转换为数组格式
        // 这里的$this->dao->get($id)用于从数据层获取指定ID的表单数据
        // $this->form($merId, $id, ...)则用于构建并返回一个新的表单实例
        return $this->form($merId,$id,$this->dao->get($id)->toArray());
    }

    /**
     * 切换实体状态
     *
     * 本函数用于通过指定的ID和新状态来更新实体的显示状态。它调用DAO层的方法来执行实际的数据库更新操作。
     * 主要用于在应用程序中切换各种实体（如文章、产品等）的可见性或激活状态。
     *
     * @param int $id 实体的唯一标识符。此ID用于在数据库中定位特定的实体记录。
     * @param int $status 新的状态值。这通常是一个二进制值（0或1），表示实体的显示状态（如：0表示隐藏，1表示显示）。
     */
    public function switchStatus(int $id,int $status)
    {
        // 调用DAO对象的update方法，更新指定ID的实体记录的状态字段
        $this->dao->update($id,['is_show' => $status]);
    }
}
