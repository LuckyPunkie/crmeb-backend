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

namespace app\common\repositories\store\product;

use app\common\dao\store\product\CdkeyLibraryDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Route;


class CdkeyLibraryRepository extends BaseRepository
{

    public function __construct(CdkeyLibraryDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取列表数据
     * 根据给定的条件数组$where，页码$page和每页数据量$limt，查询符合条件的数据列表。
     * 同时查询每个商品的主图、产品ID和店铺名称，以及属性值的ID和SKU。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limt 每页数据量
     * @return array 返回包含总数和列表数据的数组
     */
    public function getList(array $where, int $page, int $limt)
    {
        // 根据$where条件搜索数据，并指定关联查询product和attrValue时需要的字段
        $query = $this->dao->search($where)->with([
            'product' =>function($query) {
                // 查询商品的主图、产品ID和店铺名称
                $query->field('image,product_id,store_name');
            },
            'attrValue' =>function($query) {
                // 查询属性值的ID和SKU
                $query->field('value_id,sku');
            }
        ])->order('create_time DESC');

        // 计算符合条件的数据总数
        $count = $query->count();

        // 分页查询数据列表
        $list = $query->page($page, $limt)->select();

        // 返回包含数据总数和列表数据的数组
        return compact('count', 'list');
    }



    /**
     * 创建或编辑商品卡密库的表单
     *
     * 本函数用于生成添加或编辑商品卡密库的表单界面。根据传入的$id$参数决定是创建新的卡密库还是编辑已有的卡密库。
     * 如果$id$存在，则会先从数据库中获取该卡密库的信息，用于表单中显示已有数据。
     *
     * @param int|null $id 卡密库的ID，如果为null则表示创建新的卡密库，否则表示编辑已有的卡密库。
     * @return \think\form\Form 生成的表单对象，包含了表单的规则、动作和预填充数据。
     */
    public function form(?int $id = null)
    {
        // 如果$id$存在，从数据库中获取卡密库的信息
        //生成表单 Elm
        if($id) {$formData = $this->dao->get($id)->toArray();}

        // 根据$id$的存在与否决定表单的动作，即创建还是更新卡密库
        $form = Elm::createForm(is_null($id) ?
            Route::buildUrl('merchantStoreProductCdkeyLibraryCreate')->build() :
            Route::buildUrl('merchantStoreProductCdkeyLibraryUpdate', ['id' => $id])->build()
        );

        // 设置表单的规则和预填充数据，如果.formData['name']或.formData['remark']不存在，则使用默认值
        $form->setRule([
            Elm::input('name', '卡密库名称：', $formData['name'] ?? '')->required('请输入卡密库名称'),
            Elm::input('remark', '备注：',$formData['remark'] ?? ''),
        ]);

        // 根据$id$的存在与否设置表单的标题，并返回表单对象
        return $form->setTitle($id ? '编辑卡密库' : '添加卡密库')->formData($formData ?? []);
    }

    /**
     *  获取可关联的卡密库
     * @param int $mer_id
     * @return array
     * @author Qinii
     */
    public function getOptions(int $mer_id)
    {
        $where = ['is_del' => 0,'status' => 1,'mer_id' => $mer_id];
        $data = $this->dao->search($where)->field('id,name,total_num,used_num,product_id')->select()->each(function
        ($item){
            $item['checkout'] = ($item['product_id'] > 0 ) ? false : true;
        });
        return compact('data');
    }

    /**
     *  取消商品关联的所有卡密库
     * @param int $productId
     * @return void
     * @author Qinii
     */
    public function cancel(int $productId)
    {
        $this->dao->getSearch([])->where('product_id',$productId)
            ->update(['product_id' => 0, 'product_attr_value_id'=>0]);
    }

    /**
     *  批量取消商品关联的所有卡密库
     * @param array $productIds
     * @return void
     */
    public function batchCancel(array $productIds)
    {
        $this->dao->getSearch([])->whereIn('product_id', $productIds)
            ->update(['product_id' => 0, 'product_attr_value_id' => 0]);
    }

    public function destory($id, $merId)
    {
        $res = $this->dao->get($id);
        if (!$res || $res['mer_id'] != $merId) throw new ValidateException('数据不存在');
        $num = $res['total_num'] - $res['used_num'];
        Db::transaction(function() use ($res,$num) {
            if(isset($res->product)){
                $res->product->stock = $res->product->stock  - $num;
                $res->product->save();
            }
            if (isset($res->attrValue)){
                $res->attrValue->stock = $res->attrValue->stock - $num;
                $res->attrValue->save();
            }
            $res->is_del = 1;
            $res->save();
        });
    }
}
