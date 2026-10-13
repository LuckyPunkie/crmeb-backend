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


namespace app\common\repositories\system\merchant;


use app\common\dao\system\merchant\MerchantCategoryDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Route;

/**
 * 商户分类
 */
class MerchantCategoryRepository extends BaseRepository
{
    /**
     * @var MerchantCategoryDao
     */
    protected $dao;

    /**
     * MerchantCategoryRepository constructor.
     * @param MerchantCategoryDao $dao
     */
    public function __construct(MerchantCategoryDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取列表数据，支持根据条件查询和分页
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页数据条数
     * @return array 返回包含总数和列表数据的数组
     */
    public function getList(array $where, $page, $limit)
    {
        // 根据条件进行查询
        $query = $this->search($where);

        // 计算满足条件的数据总条数
        $count = $query->count($this->dao->getPk());

        // 获取当前页码的数据列表
        $list = $query->page($page, $limit)->select()->toArray();

        // 对列表中的每一项，计算并转换佣金比例为百分比形式
        foreach ($list as $k => $v) {
            $list[$k]['commission_rate'] = ($v['commission_rate'] > 0 ? bcmul($v['commission_rate'], 100, 2) : 0) . '%';
        }

        // 返回包含总数和列表的数组
        return compact('count', 'list');
    }

    /**
     * 创建或编辑商户分类表单
     *
     * 本函数用于生成一个商户分类的创建或编辑表单。根据传入的$id$参数决定是创建新分类还是编辑已有的分类。
     * 表单包含分类名称和手续费率两个字段，都为必填字段。手续费率字段是一个数值字段，限制在0到100之间，精度为2位小数。
     *
     * @param int|null $id 商户分类的ID，如果为null则表示创建新分类，否则表示编辑已有的分类。
     * @param array $formData 表单的初始数据，用于填充表单字段的值。
     * @return \EasyWeChat\Kernel\Messages\Form|Form
     */
    public function form(?int $id = null, array $formData = [])
    {
        // 根据$id$的值决定构建创建还是更新分类的URL
        $action = Route::buildUrl(is_null($id) ? 'systemMerchantCategoryCreate' : 'systemMerchantCategoryUpdate', is_null($id) ? [] : compact('id'))->build();

        // 创建表单，包含分类名称和手续费率两个字段
        $form = Elm::createForm($action, [
            Elm::input('category_name', '分类名称：')->placeholder('请输入分类名称')->required('请输入分类名称'),
            Elm::number('commission_rate', '手续费(%)：', 0)->required('请输入手续费')->max(100)->precision(2)
        ]);

        // 设置表单数据和标题，根据$id$的值决定是添加还是编辑分类
        return $form->formData($formData)->setTitle(is_null($id) ? '添加店铺分类' : '编辑店铺分类');
    }

    /**
     * 更新表单数据。
     * 此方法用于根据给定的ID获取表单数据，并对其进行更新，特别是将佣金率转换为百分比格式。
     *
     * @param int $id 数据库中记录的ID，用于定位特定的表单数据。
     * @return array|\EasyWeChat\Kernel\Messages\Form|Form
     */
    public function updateForm($id)
    {
        // 通过ID获取数据库中的记录，并转换为数组格式。
        $res = $this->dao->get($id)->toArray();

        // 如果佣金率大于0，则将其转换为百分比格式，保留两位小数；否则设置为0。
        $res['commission_rate'] = $res['commission_rate'] > 0 ? bcmul($res['commission_rate'], 100, 2) : 0;

        // 调用form方法，传入ID和更新后的数据，返回准备好的表单数据。
        return $this->form($id, $res);
    }


    /**
     * 筛选分类
     * @Author:Qinii
     * @Date: 2020/9/15
     * @return array
     */
    public function getSelect()
    {
        $query = $this->search([])->field('merchant_category_id,category_name');
        $list = $query->select()->toArray();
        return $list;
    }
}
