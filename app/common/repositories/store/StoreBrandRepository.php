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

use app\common\repositories\BaseRepository;
use app\common\dao\store\StoreBrandDao as dao;
use app\common\repositories\store\product\ProductRepository;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\facade\Route;

/**
 * 商品品牌
 */
class StoreBrandRepository extends BaseRepository
{

    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 检查给定ID的品牌分类是否存在父分类。
     *
     * 本函数通过ID查询品牌分类仓库中的记录，以确定给定ID的分类是否有一个存在的父分类。
     * 如果查询结果存在，则返回查询结果，否则返回false。
     *
     * @param int $id 品牌分类的唯一标识ID。
     * @return mixed 返回查询到的分类对象或false（如果不存在父分类）。
     */
    public function parentExists(int $id)
    {
        // 通过依赖注入的方式获取品牌分类仓库实例
        $make = app()->make(StoreBrandCategoryRepository::class);
        // 尝试根据ID获取分类信息，如果不存在则返回false
        return ($make->get($id)) ?? false;
    }

    /**
     * 检查当前实体是否存在
     *
     * 通过调用DAO对象的方法，检查给定的ID在数据库中对应的记录是否存在。
     * 这个方法主要用于确定一个特定的实体是否已经被存储，从而可以避免插入重复的数据。
     *
     * @param mixed $id 要检查的实体的唯一标识符
     * @return bool 如果实体存在返回true，否则返回false
     */
    public function meExists($id)
    {
        // 使用DAO对象的方法来检查给定主键对应的记录是否存在
        return $this->dao->merFieldExists($this->dao->getPk(), $id);
    }

    /**
     * 检查品牌名称是否已存在
     *
     * 本函数通过调用DAO层的方法来查询指定的品牌名称是否在数据库中已存在。
     * 这对于防止重复添加相同品牌的商家来说是必要的。
     *
     * @param string $value 品牌名称
     * @return bool 如果品牌名称已存在则返回true，否则返回false
     */
    public function merExistsBrand(string $value)
    {
        // 调用DAO方法检查品牌名称是否存在
        return $this->dao->merFieldExists('brand_name', $value);
    }

    /**
     * 根据条件获取分类下的品牌搜索结果
     *
     * 本函数用于根据给定的条件查询特定分类下的品牌信息，并包括“其他”选项。
     * 使用ProductRepository类来获取品牌信息和展示配置，并通过dao层执行具体的搜索操作。
     * 返回搜索结果的计数和列表，列表中包括一个特殊的“其他”品牌选项。
     *
     * @param array $where 查询条件
     * @return array 包含品牌数量和品牌的数组
     */
    public function getCategorySearch($where)
    {
        // 实例化ProductRepository类，用于后续获取产品展示配置和品牌信息
        $make = app()->make(ProductRepository::class);
        // 将产品展示配置合并到查询条件中
        $where = array_merge($where, $make->productShow());
        // 根据分类条件获取品牌ID列表
        $brandIds = app()->make(ProductRepository::class)->getBrandByCategory($where);
        // 初始化品牌数量和品牌列表
        $count = 0;
        $list = [];
        // 如果有品牌ID，则进行查询
        if ($brandIds) {
            // 执行品牌搜索查询，条件为品牌ID列表
            $query = $this->dao->search(['ids' => $brandIds]);
            // 计算搜索结果的数量
            $count = $query->count();
            // 获取搜索结果的详细信息
            $list = $query->select()->toArray();
            // 将一个特殊的“其他”品牌选项添加到品牌列表中
            array_push($list, [
                "brand_id" => 0,
                "brand_category_id" => 0,
                "brand_name" => "其他",
                "sort" => 999,
                "pic" => "",
                "is_show" => 1,
                "create_time" => "",
            ]);
        }
        // 返回品牌数量和品牌列表的紧凑表示（数组）
        return compact('count', 'list');
    }

    /**
     * 根据条件获取品牌列表
     *
     * 本函数用于根据给定的条件数组$where，以及分页信息$page和$limit，从数据库中检索品牌列表。
     * 它首先构造一个查询，然后计算符合条件的品牌总数，最后根据分页信息获取特定页码的品牌列表。
     * 返回一个包含品牌总数和品牌列表的数组。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页的品牌数量
     * @return array 包含品牌总数和品牌列表的数组
     */
    public function getList(array $where, $page, $limit)
    {
        // 构造查询条件，这里特别地，使用with加载品牌分类的相关信息，但只选择特定的字段
        $query = $this->dao->search($where)
            ->with('brandCategory', function ($query) {
                $query->field('store_brand_category_id,cate_name,path');
            });

        // 计算符合条件的品牌总数
        $count = $query->count();

        // 根据当前页码和每页的品牌数量，获取品牌列表
        $list = $query->page($page, $limit)->select();

        // 将品牌总数和品牌列表打包成数组返回
        return compact('count', 'list');
    }

    /**
     * 更新表单数据。
     * 该方法用于根据给定的ID获取数据库中的记录，并使用这些数据来构建一个表单，以便用户可以查看或编辑这些数据。
     *
     * @param int $id 表单记录的唯一标识符。
     * @return array 返回一个包含表单字段和值的数组。
     */
    public function updateForm($id)
    {
        // 通过ID从数据库获取记录，并转换为数组格式，用于填充表单
        return $this->form($id, $this->dao->get($id)->toArray());
    }

    /**
     * 创建或编辑品牌表单
     *
     * 本函数用于生成一个品牌创建或编辑的表单。表单根据$id$的值决定是创建新品牌还是编辑已有的品牌。
     * 表单中包含了上级分类选择、品牌名称输入、是否显示的开关以及排序数值输入等字段。
     *
     * @param int|null $id 品牌的ID，如果为null，则表示创建新品牌；否则，表示编辑已有的品牌。
     * @param array $formData 表单的初始数据，用于填充表单字段。
     * @return mixed 返回生成的表单对象。
     */
    public function form(?int $id = null, array $formData = [])
    {
        // 根据$id$的值构建表单的提交URL，如果是创建新品牌则指向系统存储品牌创建路由，否则指向系统存储品牌更新路由。
        $form = Elm::createForm(is_null($id) ? Route::buildUrl('systemStoreBrandCreate')->build() : Route::buildUrl('systemStoreBrandUpdate', ['id' => $id])->build());

        // 设置表单的验证规则，包括上级分类选择、品牌名称输入、是否显示的开关和排序数值输入等字段的验证。
        $form->setRule([
            // 上级分类选择字段，使用级联选择器，动态获取分类选项，并设置验证规则。
            Elm::cascader('brand_category_id', '上级分类：')->options(function () use ($id) {
                $menus = app()->make(StoreBrandCategoryRepository::class)->getAncestorsChildList();
                return $menus;
            })->placeholder('请选择上级分类')->props(['props' => ['emitPath' => false]])->appendValidate(Elm::validateInt()->required('请选择上级分类')->message('请选择上级分类')),
            // 品牌名称输入字段，设置占位符和验证规则。
            Elm::input('brand_name', '品牌名称：')->placeholder('请输入品牌名称')->required('请输入品牌名称'),
            // 是否显示开关字段，设置活性和非活性值以及文字说明。
            Elm::switches('is_show', '是否显示：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
            // 排序数值输入字段，设置默认值、精度和最大值。
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
        ]);

        // 设置表单标题，并根据$id$的值决定是创建还是编辑品牌，最后设置表单的初始数据。
        return $form->setTitle(is_null($id) ? '添加品牌' : '编辑品牌')->formData($formData);
    }

    /**
     *  品牌下是否存在商品
     * @param int $id
     * @return bool
     * @author Qinii
     * @day 12/15/20
     */
    public function getBrandHasProduct(int $id)
    {
        $make = app()->make(ProductRepository::class);
        $count = $make->getSearch(['brand_id' => [$id]])->where('is_del', 0)->count();
        return $count ? true : false;
    }
}
