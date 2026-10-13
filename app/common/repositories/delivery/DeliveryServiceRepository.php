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
namespace app\common\repositories\delivery;

use FormBuilder\Factory\Iview;
use app\common\dao\delivery\DeliveryServiceDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Factory\Elm;
use think\facade\Route;

/**
 * 配送员
 */
class DeliveryServiceRepository extends BaseRepository
{
    public function __construct(DeliveryServiceDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     *  添加 / 编辑 表单
     * @param int|null $id
     * @return void
     * @author Qinii
     */
    public function form(?int $id)
    {
        $formData        = $id ? $this->dao->getWith($id, ['user' => function ($query) {
            $query->field('avatar as src,uid as id,uid');
        }])->toArray() : [];

        $form = Elm::createForm($id ? Route::buildUrl('merchantDeliveryServiceUpdate', ['id' => $id])->build() : Route::buildUrl('merchantDeliveryServiceCreate')->build());

        $prefix        = config('admin.merchant_prefix');
        if($formData) {
            $formData['uid'] = $formData['user'] ?? '';
            $formData['uid']['src'] = $formData['user']['src'] ?: $formData['avatar'];
        }

        $form->setRule([
            Elm::frameImage('uid', '关联用户：', '/' . $prefix . '/setting/userList?field=uid&type=1')->prop('srcKey', 'src')->width('1000px')->height('600px')->appendValidate(Iview::validateObject()->message('请选择用户')->required('请选择用户'))->icon('el-icon-camera')->modal(['modal' => false]),
            Elm::frameImage('avatar', '证件照：', '/' . $prefix . '/setting/uploadPicture?field=avatar&type=1', $formData['avatar'] ?? '')->width('1000px')->height('600px')->props(['footer' => false])->icon('el-icon-camera')->modal(['modal' => false]),
            Elm::input('name', '姓名：', $formData['name'] ?? '')->placeholder('请输入姓名')->required('请输入姓名'),
            Elm::input('phone', '手机号：', $formData['phone'] ?? '')->placeholder('请输入手机号')->required('请输入手机号'),
            Elm::textarea('remark', '提示信息：', $formData['remark'] ?? '')->placeholder('特别提示信息'),
            Elm::number('sort', '排序：', $formData['sort'] ?? 0),
            Elm::switches('status', '是否开启：', 1)->inactiveValue(0)
                ->activeValue(1)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
        ]);
        return $form->setTitle($id ? '编辑' : '添加')->formData($formData);
    }

    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件数组$where，从数据库中检索满足条件的数据列表。
     * 它支持分页查询，每页的数据数量由$limit参数指定，查询的页码由$page参数指定。
     *
     * @param array $where 查询条件数组，包含要匹配的数据库字段及其值。
     * @param int $page 查询的页码，用于实现分页查询。
     * @param int $limit 每页显示的数据条数。
     * @return array 返回一个包含两个元素的数组，第一个元素是数据总数$count，第二个元素是当前页的数据列表$list。
     */
    public function getList(array $where, int $page, int $limit)
    {
        // 根据条件构建查询
        $query = $this->dao->search($where)->order('ds.sort desc, ds.create_time desc');

        // 计算满足条件的数据总数
        $count = $query->count();

        // 执行分页查询，并获取当前页的数据列表
        $list = $query->page($page, $limit)->select();

        // 将数据总数和数据列表一起返回
        return compact('count', 'list');
    }

    /**
     * 根据条件获取搜索选项
     *
     * 本函数通过调用DAO层的getSearch方法，根据传入的$where条件查询相关数据，
     * 并返回查询结果中特定列的值。这些特定列包括：name（名称）、phone（电话）、service_id（服务ID）。
     * 主要用于在前端展示搜索选项或过滤条件，例如用户服务列表。
     *
     * @param string|array $where 查询条件，可以是字符串或数组形式的SQL WHERE子句。
     * @return array 返回一个包含name、phone和服务ID的列数组。
     */
    public function getOptions($where)
    {
        // 调用DAO层的getSearch方法查询数据，并指定返回的列
        return $this->dao->getSearch($where)->column('name,phone,service_id');
    }

    public function getStatisticsList($where, $page, $limit)
    {
        $query = $this->dao->statisticsSearch($where)
            ->with(['user' => function ($query) {
                $query->field('nickname,avatar,uid,cancel_time');
            }])
            ->order('ds.sort DESC,ds.create_time DESC');

        $count = $query->count();
        $list = $query->page($page, $limit)->select();

        return compact('count', 'list');
    }

    public function getStatisticsDetail($id)
    {
        $info = $this->dao->getWith($id, ['deliveryOrder', 'user' => function ($query) {
            $query->field('nickname,avatar,uid,cancel_time');
        }])->toArray();
        $deliveryOrder = $info['deliveryOrder'];

        $pendingNum = 0;
        $serviceFee = 0;
        foreach ($deliveryOrder as &$value) {
            $serviceFee = bcadd($serviceFee, $value['service_fee'], 2);
            // 待取货＝2 配送中＝3
            if(in_array($value['status'], [2,3])) {
                $pendingNum ++;
            }

            $orderProduct = [];
            if($value['storeOrder']) {
                foreach ($value['storeOrder']['orderProduct'] as &$item) {
                    $orderProduct[] = [
                        'product_name' => $item['cart_info']['product']['store_name'],
                        'product_image' => $item['cart_info']['product']['image'],
                        'unique' => $item['cart_info']['productAttr']['unique'],
                        'product_num' => $item['product_num'],
                        'product_price' => $item['product_price']
                    ];
                }
                unset($value['storeOrder']['orderProduct']);

                $value['storeOrder']['orderProduct'] = $orderProduct;
            }
        }

        $info['deliveryOrder'] = $deliveryOrder;
        $info['pendingNum'] = $pendingNum;
        $info['serviceFee'] = $serviceFee;
        $info['orderCount'] = count($deliveryOrder);

        return $info;
    }


}
