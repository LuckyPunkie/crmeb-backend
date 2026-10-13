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
use app\common\model\delivery\DeliveryOrder;
use app\common\model\delivery\DeliveryService;
use app\common\repositories\BaseRepository;
use FormBuilder\Factory\Elm;
use think\facade\Route;
use think\exception\ValidateException;

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
     * @param int $type 0平台，1商户
     * @param int $merId
     * @return void
     * @author Qinii
     */
    public function form(?int $id, int $type = 1, int $merId = 0)
    {
        $model = $id ? $this->dao->getWhere([
            'service_id' => $id,
            'type' => $type,
            'mer_id' => $merId,
        ], '*', ['user' => function ($query) {
            $query->field('avatar as src,uid as id,uid');
        }]) : null;
        if ($id && !$model) {
            throw new ValidateException('数据不存在');
        }
        $formData = $model ? $model->toArray() : [];

        $isAdmin = $type === 0;
        $createRoute = $isAdmin ? 'systemDeliveryServiceCreate' : 'merchantDeliveryServiceCreate';
        $updateRoute = $isAdmin ? 'systemDeliveryServiceUpdate' : 'merchantDeliveryServiceUpdate';
        $form = Elm::createForm($id ? Route::buildUrl($updateRoute, ['id' => $id])->build() : Route::buildUrl($createRoute)->build());

        $prefix = $isAdmin ? config('admin.admin_prefix') : config('admin.merchant_prefix');
        if($formData) {
            $formData['uid'] = $formData['user'] ?? '';
            $formData['uid']['src'] = ($formData['user']['src'] ?? '') ?: $formData['avatar'];
        }
        $certificateImages = $formData['certificate_images'] ?? [];
        if (is_string($certificateImages)) {
            $certificateImages = $certificateImages === '' ? [] : explode(',', $certificateImages);
        }
        $formData['certificate_images'] = $certificateImages;

        $rules = [
            Elm::frameImage('uid', '关联用户：', '/' . $prefix . '/setting/userList?field=uid&type=1')->prop('srcKey', 'src')->width('1000px')->height('600px')->appendValidate(Iview::validateObject()->message('请选择用户')->required('请选择用户'))->icon('el-icon-camera')->modal(['modal' => false]),
            Elm::frameImage('avatar', '配送员头像：', '/' . $prefix . '/setting/uploadPicture?field=avatar&type=1', $formData['avatar'] ?? '')->width('1000px')->height('600px')->props(['footer' => false])->icon('el-icon-camera')->modal(['modal' => false])
                ->appendRule('suffix', [
                'type' => 'div',
                'style' => ['color' => '#999999'],
                'domProps' => [
                    'innerHTML' => '用于移动端商家管理工作台展示,建议尺寸：80*80px,不能超过5KB',
                ],
            ]),
            Elm::input('name', '姓名：', $formData['name'] ?? '')->required(true)->placeholder('请输入姓名')->required('请输入姓名'),
            Elm::input('phone', '手机号：', $formData['phone'] ?? '')->required(true)->placeholder('请输入手机号')->required('请输入手机号'),
            Elm::textarea('remark', '提示信息：', $formData['remark'] ?? '')->placeholder('特别提示信息'),
            Elm::number('sort', '排序：', $formData['sort'] ?? 0),
            Elm::switches('status', '接单状态：', $formData['status'] ?? 1)->inactiveValue(0)
                ->activeValue(1)->inactiveText('关闭')->className('switch-width-double')->activeText('开启')->appendRule('suffix', [
                    'type' => 'div',
                    'style' => ['color' => '#999999'],
                    'domProps' => [
                        'innerHTML' => '停止接单之后，此人员在派单时不可选择；此状态会同步至配动员端，配送员也可修改接单状态。',
                    ],
                ]),
        ];
        if ($isAdmin) {
            array_splice($rules, 2, 0, [
                Elm::frameImage('certificate_front', '证件照正面：', '/' . $prefix . '/setting/uploadPicture?field=certificate_front&type=1', $formData['certificate_front'] ?? '')->required('请上传证件照正面')->width('1000px')->height('600px')->props(['footer' => false])->icon('el-icon-camera')->modal(['modal' => false]),
                Elm::frameImage('certificate_back', '证件照反面：', '/' . $prefix . '/setting/uploadPicture?field=certificate_back&type=1', $formData['certificate_back'] ?? '')->required('请上传证件照反面')->width('1000px')->height('600px')->props(['footer' => false])->icon('el-icon-camera')->modal(['modal' => false]),
                Elm::frameImages('certificate_images', '证明证书：', '/' . $prefix . '/setting/uploadPicture?field=certificate_images&type=2', $certificateImages)->required('请上传证明证书')->maxLength(10)->width('1000px')->height('600px')->props(['footer' => false])->icon('el-icon-camera')->modal(['modal' => false])->appendRule('suffix', [
                    'type' => 'div',
                    'style' => ['color' => '#999999'],
                    'domProps' => [
                        'innerHTML' => '请上传健康证,专业证书等,最多上传10张',
                    ],
                ]),
            ]);
        }
        $form->setRule($rules);
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

    /**
     * 切换用户的全部配送员身份状态
     * @param int $uid
     * @return int
     */
    public function toggleUserStatus(int $uid): int
    {
        $where = ['uid' => $uid, 'is_del' => 0];
        $service = $this->dao->query($where)->order('service_id')->find();
        if (!$service) {
            throw new ValidateException('配送员身份不存在');
        }

        $status = (int)$service->status === DeliveryService::STATUS_ENABLED
            ? DeliveryService::STATUS_DISABLED
            : DeliveryService::STATUS_ENABLED;
        $this->dao->query($where)->update(['status' => $status]);
        return $status;
    }

    public function getStatisticsList($where, $page, $limit)
    {
        $query = $this->dao->statisticsSearch($where)
            ->order('ds.sort DESC,ds.create_time DESC');

        // 分组查询的 count() 会改写查询字段，使用克隆避免污染统计列表字段。
        $count = (clone $query)->count();
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
            // 待确认、待到店、待取货和配送中均属于待配送。
            if(in_array($value['status'], [0, 1, 2, 3])) {
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

    /**
     * 获取配送员配送订单数量统计。
     * 待配送包含待确认、待到店、待取货和配送中状态，取消订单不计入全部数量。
     */
    public function getOrderCount(int $serviceId): array
    {
        $row = DeliveryOrder::getDB()
            ->where('service_id', $serviceId)
            ->where('status', '<>', -1)
            ->field([
                'COUNT(delivery_order_id) AS all_order_count',
                'SUM(CASE WHEN status IN (0, 1, 2, 3) THEN 1 ELSE 0 END) AS pending_order_count',
            ])
            ->find();

        return [
            'orderCount' => (int)($row['all_order_count'] ?? 0),
            'pendingNum' => (int)($row['pending_order_count'] ?? 0),
        ];
    }


}
