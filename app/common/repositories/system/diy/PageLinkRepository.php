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

use app\common\dao\system\diy\PageLinkDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Route;

/**
 * 页面链接
 */
class PageLinkRepository extends BaseRepository
{
    public function __construct(PageLinkDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取列表数据
     *
     * 根据给定的条件数组、页码和每页数量，从数据库中检索并返回列表数据。
     * 这个方法主要用于处理数据的分页查询，通过where条件筛选数据，同时包含分类信息。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页的数据数量
     * @return array 返回包含总数和列表数据的数组
     */
    public function getList(array $where, int $page, int $limit)
    {
        // 根据条件查询数据，并包含分类信息，按排序降序排列
        $query = $this->dao->getSearch($where)->with(['category'])->order('sort DESC');

        // 计算满足条件的数据总数
        $count = $query->count();

        // 根据当前页码和每页数量进行分页查询，并获取数据列表
        $list = $query->page($page, $limit)->select();

        // 返回包含数据总数和列表数据的数组
        return compact('count', 'list');
    }

    /**
     * 创建或编辑页面链接表单
     *
     * 根据$id$是否存在来决定是创建新页面链接还是编辑已有的页面链接。$isMer$参数用于区分商家和平台，
     * 以提供不同的路由地址和数据选项。
     *
     * @param int|null $id 页面链接的ID，如果为null，则表示创建新链接；否则，表示编辑已有的链接。
     * @param bool $isMer 标识当前操作是否属于商家，用于区分商家和平台的操作。
     * @return \FormBuilder\Form|\think\form\Form
     */
    public function form(?int $id, $isMer)
    {
        // 根据$id$是否存在来决定是加载现有数据还是创建新表单
        if ($id) {
            // 加载现有页面链接的数据
            $formData = $this->dao->get($id)->toArray();
            // 创建编辑页面链接的表单，路由根据$isMer$决定是商家还是平台的编辑路由
            $form = Elm::createForm(Route::buildUrl($isMer ? 'systemDiyPageLinkMerUpdate' : 'systemDiyPageLinkUpdate', ['id' => $id])->build());
        } else {
            // 创建新页面链接的表单，路由根据$isMer$决定是商家还是平台的新建路由
            $form = Elm::createForm(Route::buildUrl($isMer ? 'systemDiyPageLinkMerCreate' : 'systemDiyPageLinkCreate')->build());
            // 新建表单默认数据为空
            $formData = [];
        }

        // 定义表单的规则和字段
        $rule = [
            // 上级分类选择器，使用级联选择器实现，选项根据$isMer$动态获取
            Elm::cascader('cate_id', '上级分类：')->options(function () use ($isMer) {
                $options = app()->make(PageCategoryRepository::class)->getSearch(['status' => 1, 'is_mer' => $isMer, 'type' => 'link', 'level' => 3])->column('id value, name label');
                return $options;
            })->placeholder('请选择上级分类')->props(['props' => ['checkStrictly' => true, 'emitPath' => false]])
                ->filterable(true)
                ->appendValidate(Elm::validateInt()->required('请选择上级分类')->message('请选择上级分类')),
            // 页面名称输入框，必填
            Elm::input('name', '页面名称：')->placeholder('请输入页面名称')->required('请输入页面名称'),
            // 页面链接输入框，必填
            Elm::input('url', '页面链接：')->placeholder('请输入页面链接')->required('请输入页面链接'),
            // 参数输入框，可选
            Elm::text('param', '参数：')->placeholder('请输入参数'),
            // 是否显示开关，默认开启
            Elm::switches('status', '是否显示：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
            // 排序数字输入框，默认为0，最大值99999
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
        ];

        // 设置表单的规则
        $form->setRule($rule);
        // 设置表单标题和数据，标题根据$id$是否为空来决定，数据使用之前加载的或空数组
        return $form->setTitle(is_null($id) ? '添加分类' : '编辑分类')->formData($formData);
    }

    /**
     * 分类下的链接列表
     * @param int $pid
     * @param int $merId
     * @return mixed
     * @author Qinii
     * @day 3/24/22
     */
    public function getLinkList(int $pid, int $merId)
    {
        $where['pid'] = $pid;
        $where['is_mer'] = $merId ? 1 : 0;
        $make = app()->make(PageCategoryRepository::class);
        $list = $make->getSearch($where)->with([
            'pageLink',
        ])->select();
        return $list;
    }
}
