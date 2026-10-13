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


namespace app\common\repositories\system\attachment;


//附件
use app\common\dao\BaseDao;
use app\common\dao\system\attachment\AttachmentCategoryDao;
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
 * 附件分类
 */
class AttachmentCategoryRepository extends BaseRepository
{
    /**
     * AttachmentCategoryRepository constructor.
     * @param AttachmentCategoryDao $dao
     */
    public function __construct(AttachmentCategoryDao $dao)
    {
        /**
         * @var AttachmentCategoryDao
         */
        $this->dao = $dao;
    }

    /**
     * 创建或编辑附件分类表单
     *
     * 根据传入的merchantId和id，决定是创建新的分类还是编辑已有的分类。
     * 对于商家分类，提供了创建和更新的操作；对于系统分类，同样提供了创建和更新的操作。
     * 表单字段包括上级分类选择、分类名称、分类目录和排序。
     *
     * @param int $merId 商家ID，用于区分商家分类和系统分类
     * @param int|null $id 分类ID，如果为null，则表示创建新分类；否则表示编辑已有的分类
     * @param array $formData 表单数据，用于预填充表单字段
     * @return Form 返回创建好的表单对象
     */
    public function form(int $merId, ?int $id = null, array $formData = []): Form
    {
        if ($merId) {
            $action = is_null($id) ? 'merchantAttachmentCategoryCreate' : 'merchantAttachmentCategoryUpdate';
        } else {
            $action = is_null($id) ? 'systemAttachmentCategoryCreate' : 'systemAttachmentCategoryUpdate';
        }

        $form = Elm::createForm(Route::buildUrl($action, is_null($id) ? [] : ['id' => $id])->build());
        $form->setRule([
            Elm::cascader('pid', '上级分类：')->options(function () use ($id, $merId) {
                $menus = $this->dao->getAllOptions($merId);
                if ($id && isset($menus[$id])) unset($menus[$id]);
                $menus = formatCascaderData($menus, 'attachment_category_name');
                array_unshift($menus, ['label' => '顶级分类：', 'value' => 0]);
                return $menus;
            })->placeholder('请选择上级分类')->props(['props' => ['checkStrictly' => true, 'emitPath' => false]])->appendValidate(Elm::validateInt()->required('请选择上级分类')->message('请选择上级分类')),
            Elm::input('attachment_category_name', '分类名称：')->placeholder('请输入分类名称')->required('请输入分类名称'),
            Elm::input('attachment_category_enname', '分类目录：', 'def')->placeholder('请输入分类目录')->required('请输入分类目录'),
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
        ]);

        return $form->setTitle(is_null($id) ? '添加配置' : '编辑配置')->formData($formData);
    }

    /**
     * 更新表单数据的方法
     *
     * 本方法用于根据给定的商户ID和表单ID来更新表单数据。它首先通过调用DAO层获取当前表单的数据，
     * 然后使用这些数据来构建一个新的表单实例，最后返回这个新构建的表单实例。
     *
     * @param int $merId 商户ID，用于指定表单所属的商户。
     * @param int $id 表单ID，用于唯一标识待更新的表单。
     * @return Form 返回更新后的表单实例。
     */
    public function updateForm(int $merId, int $id)
    {
        // 通过DAO层获取指定ID和商户ID的表单数据，并转换为数组格式
        // 这里使用链式调用，先调用get方法获取表单数据对象，然后调用toArray方法将其转换为数组
        return $this->form($merId, $id, $this->dao->get($id, $merId)->toArray());
    }

    /**
     * 获取格式化后的分类列表
     *
     * 本函数旨在根据商家ID获取所有的分类信息，并对这些信息进行格式化处理。通过调用DAO层的方法获取所有分类数据，
     * 然后使用formatCategory函数对数据进行格式化，以便于前端展示或进一步处理。
     *
     * @param int $merId 商家ID，用于获取该商家的分类信息。
     * @return array 格式化后的分类列表数组。
     */
    public function getFormatList(int $merId)
    {
        // 调用DAO层的getAll方法获取所有分类数据，并转换为数组格式
        return formatCategory($this->dao->getAll($merId)->toArray(), 'attachment_category_id');
    }

    /**
     * 更新素材分类信息。
     *
     * 此方法用于根据提供的ID和商家ID更新素材分类的数据。如果分类的父ID（pid）发生了变化，
     * 则需要更新分类的路径，并在数据库中执行事务性更新以确保数据的一致性。如果父ID没有变化，
     * 则直接更新分类的其他信息。
     *
     * @param int $id 分类的ID。
     * @param int $merId 商家的ID。
     * @param array $data 待更新的分类数据，包括pid和其他可能需要更新的字段。
     * @throws ValidateException 如果分类路径超过三级，将抛出验证异常。
     */
    public function update(int $id, $merId, array $data)
    {
        // 根据ID和商家ID获取现有的分类模型
        $model = $this->dao->get($id, $merId);

        // 检查如果父ID发生变化
        if ($model->pid != $data['pid']) {
            // 使用事务处理来确保数据一致性
            Db::transaction(function () use ($data, $model) {
                // 根据新的父ID获取分类路径
                $data['path'] = $this->getPathById($data['pid']);

                // 确保分类路径不超过三级，否则抛出异常
                if (substr_count(trim($data['path'], '/'), '/') > 1) {
                    throw new ValidateException('素材分类最多添加三级');
                }

                // 更新现有的分类路径，为即将进行的保存操作做准备
                $this->dao->updatePath($model->path . $model->attachment_category_id, $data['path'] . $model->attachment_category_id);

                // 保存更新后的模型数据
                $model->save($data);
            });
        } else {
            // 如果父ID没有变化，直接更新分类数据，不需要修改路径
            unset($data['path']);
            $this->dao->update($id, $data);
        }
    }


    /**
     * 添加
     * @param array $data 添加的数据
     * @return BaseDao|int|Model
     * @author 张先生
     * @date 2020-03-30
     */
    public function create(array $data)
    {
        $data['path'] = $this->getPathById($data['pid']);
        if (substr_count(trim($data['path'], '/'), '/') > 1) throw new ValidateException('素材分类最多添加三级');
        return $this->dao->create($data);
    }


    /**
     * 获取path
     * @param int $id 主键id
     * @return mixed
     * @author 张先生
     * @date 2020-03-30
     */
    private function getPathById(int $id = 0)
    {
        $result = '/';
        if ($id) {
            $result = $this->dao->getPathById($id) . $id . '/';
        }
        return $result;
    }

}
