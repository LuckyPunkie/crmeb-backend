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


namespace app\common\repositories\system\diy;

use app\common\dao\system\diy\PageCategoryDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Route;

/**
 * 页面链接分类
 */
class PageCategoryRepository extends BaseRepository
{
    public function __construct(PageCategoryDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取格式化后的分类列表
     *
     * 本函数通过查询数据对象，并对查询结果进行排序和格式化，返回符合条件的分类列表。
     * 主要用于在前端展示分类信息时，提供一个格式化好的数据数组。
     *
     * @param string|array $where 查询条件，可以是字符串或者数组，用于构造SQL查询的WHERE子句。
     * @return array 返回一个经过格式化后的分类列表数组，每个元素包含分类的相关信息。
     */
    public function getFormatList($where)
    {
        // 执行查询，根据'sort'和'add_time'字段降序排序，然后将查询结果转换为数组
        $data = $this->dao->getSearch($where)
            ->order('sort DESC,add_time DESC')
            ->select()
            ->toArray();

        // 调用formatCategory函数对查询结果进行格式化，然后返回
        // formatCategory函数的具体作用是根据数据对象的主键对数据进行特定的格式化处理，以便更方便地在前端使用
        return formatCategory($data, $this->dao->getPk());
    }

    /**
     * 获取列表数据
     * 根据给定的条件和分页信息，从数据库中检索并返回列表数据。
     *
     * @param array $where 查询条件，以键值对形式指定。
     * @param int $page 当前页码。
     * @param int $limit 每页显示的数据条数。
     * @return array 包含 'count' 和 'list' 两个元素的数组，'count' 为数据总条数，'list' 为当前页的数据列表。
     */
    public function getList(array $where, int $page, int $limit)
    {
        // 根据查询条件构造查询对象，并按添加时间降序排序
        $query = $this->dao->getSearch($where)->order('add_time DESC');

        // 计算满足条件的数据总条数
        $count = $query->count();

        // 获取当前页码对应的分页数据列表
        $list = $query->page($page, $limit)->select();

        // 返回包含数据总条数和当前页数据列表的数组
        return compact('count', 'list');
    }


    /**
     * 创建或编辑分类表单
     *
     * 此函数用于生成一个用于创建或编辑分类的表单。根据$id$是否存在来判断是创建新分类还是编辑已有的分类。
     * $isMer$参数用于区分是平台分类还是商户分类。
     *
     * @param int|null $id 分类的ID，如果存在，则表示编辑现有分类；如果不存在，则表示创建新分类。
     * @param int $isMer 标志位，表示是否为商户分类。0表示平台分类，1表示商户分类。
     * @return \think\form\Form 表单对象，已经配置好相关的表单字段和规则。
     */
    public function form(?int $id, int $isMer = 0)
    {
        // 如果$id$存在，表示编辑现有分类
        if ($id) {
            // 根据$id$获取分类数据，并转换为数组格式
            $formData = $this->dao->get($id)->toArray();
            // 如果当前分类的类型不是'link'，抛出异常，不允许修改
            if ($formData['type'] != 'link') throw new ValidateException('此类型不能修改');
            // 根据$isMer$生成相应的更新分类的URL，并创建表单对象
            $form = Elm::createForm(Route::buildUrl($isMer ? 'systemDiyPageMerCategroyUpdate' : 'systemDiyPageCategroyUpdate', ['id' => $id])->build());
            // 保存formData中的is_mer值
            $isMer = $formData['is_mer'];
        } else {
            // 如果$id$不存在，表示创建新分类
            // 根据$isMer$生成相应的创建分类的URL，并创建表单对象
            $form = Elm::createForm(Route::buildUrl($isMer ? 'systemDiyPageMerCategroyCreate' : 'systemDiyPageCategroyCreate')->build());
            // 初始化.formData为空数组
            $formData = [];
        }

        // 配置表单的规则和字段
        $form->setRule([
            // 级联选择器，用于选择上级分类，动态获取可选分类
            Elm::cascader('pid', '上级分类：')->options(function () use ($isMer) {
                // 根据$isMer$查询可用的上级分类，并格式化为级联选择器需要的数据格式
                $options = $this->dao->getSearch(['status' => 1, 'is_mer' => $isMer, 'type' => 'link'])->where('level', '<', 3)->column('pid,name', 'id');
                $options = formatCascaderData($options, 'name');
                return $options;
            })->placeholder('请选择上级分类')->props(['props' => ['checkStrictly' => true, 'emitPath' => false]])->filterable(true)->appendValidate(Elm::validateInt()->required('请选择上级分类')->message('请选择上级分类')),
            // 输入框，用于输入分类名称
            Elm::input('name', '分类名称：')->placeholder('请输入分类名称')->required('请输入分类名称'),
            // 输入框，用于输入类型，固定为'link'
            Elm::input('type', '类型：', 'link')->placeholder('请输入类型'),
            // 开关，用于控制分类的显示状态
            Elm::switches('status', '是否显示：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
            // 数字输入框，用于输入排序值
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
            // 隐藏字段，用于保存$isMer$的值
            Elm::hidden('is_mer', $isMer),
        ]);
        // 设置表单的标题，并根据$id$的存在与否设置标题文本
        return $form->setTitle(is_null($id) ? '添加' . ($isMer ? '商户分类' : '平台分类') : '编辑' . ($isMer ? '商户分类' : '平台分类'))->formData($formData);
    }

    /**
     * 获取子分类列表
     * 根据类型和父ID获取特定分类的子分类列表，用于显示分类树结构。
     *
     * @param int $type 分类类型标识，用于区分不同的分类类型。
     * @param int $pid 父分类ID，默认为0，表示获取顶级分类的子分类。
     * @return array 返回一个包含子分类信息的数组，每个子分类可能包含其子分类。
     */
    public function getSonCategoryList($type, $pid = 0)
    {
        // 定义查询条件，筛选父ID为$pid且类型为$type的分类。
        $where['pid'] = $pid;
        $where['is_mer'] = $type;
        // 如果类型为1，则进一步筛选不为'product_category'的分类。
        if ($type == 1) {
            $where['not_type'] = 'product_category';
        }
        // 执行查询，获取级别小于3的分类信息，按排序和添加时间降序排列。
        $list = $this->dao->getSearch($where)->where('level', '<', 3)
            ->field('id,pid,type,name label,status')->order('sort DESC,add_time DESC')->select();
        // 初始化返回数组。
        $arr = [];
        // 遍历查询结果，构建分类树结构。
        if ($list) {
            foreach ($list as $item) {
                // 如果分类状态为启用，则进一步处理并添加到返回数组。
                if ($item['status'] == 1) {
                    // 设置分类标题为名称。
                    $item['title'] = $item['label'];
                    // 默认展开分类。
                    $item['expand'] = true;
                    // 递归获取当前分类的子分类。
                    $item['children'] = $this->getSonCategoryList($type, $item['id']);
                    // 将当前分类添加到返回数组。
                    $arr [] = $item;
                }
            }
        }
        // 返回构建好的分类树结构数组。
        return $arr;
    }

}
