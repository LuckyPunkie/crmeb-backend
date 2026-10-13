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

use think\facade\Route;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use app\common\repositories\BaseRepository;
use app\common\dao\store\StoreActivityCateDao;

class StoreActivityCateRepository extends BaseRepository
{
    public function __construct(StoreActivityCateDao $dao)
    {
        $this->dao = $dao;
    }


    public function getList($where, $page, $limit)
    {
        $query = $this->dao->getSearch($where)->order('sort desc, id desc');
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();
        return compact('count', 'list');
    }

    public function form(?int $id = null)
    {
        $formData = [];
        if ($id) {
            $form = Elm::createForm(Route::buildUrl('systemActivityCateUpdate', ['id' => $id])->build())->setTitle('编辑分类');
            $res = $this->dao->get($id);
            if (!$res) throw new ValidateException('数据不存在');
            $formData = $res->toArray();
        } else {
            $form = Elm::createForm(Route::buildUrl('systemActivityCateCreate')->build())->setTitle('添加分类');
        }

        // 配置分类选择器，根据$merId和$id动态加载可选分类，同时设置必要的表单验证规则和附加信息
        $form->setRule([
            Elm::input('name', '分类名称：')->placeholder('请输入分类名称')->required('请输入分类名称'),
            //Elm::frameImage('pic', '分类图片：', '/' . config('admin.' . 'admin_prefix') . '/setting/uploadPicture?field=pic&type=1')->width('1000px')->height('600px')->icon('el-icon-camera')->props(['footer' => false])->modal(['modal' => false, 'custom-class' => 'suibian-modal'])->appendRule('suffix', [
            //    'type' => 'div',
            //    'style' => ['color' => '#999999'],
            //    'domProps' => [
            //        'innerHTML' => '建议尺寸：110*110px',
            //    ],
            //]),
            Elm::switches('status', '是否显示：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
        ]);

        // 设置表单标题和默认数据，返回表单对象
        return $form->formData($formData);
    }

    public function select()
    {
        return $this->dao->getSearch(['status' => 1])->order('sort desc, id desc')->column('id,name');
    }

    public function options()
    {
        $data = $this->dao->getSearch(['status' => 1])->where('id','>',1)->with([
            'children' => function ($query) {
                $query->field('id,label_name,label_cate,style_type,color,bg_color,border_color,icon');
            }
        ])->field('id,name,sort,status')->order('sort desc, id desc')->select()->toArray();

        foreach ($data as $key => &$item) {
            if(empty($item['children'])) {
                unset($data[$key]);
            }
        }

        $data = array_values($data);

        return $data;
    }
 }
