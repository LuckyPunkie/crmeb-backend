<?php

namespace app\common\repositories\store\staff;

use app\common\dao\store\staff\StaffsDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Factory\Iview;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Route;

/**
 * 企业员工
 * @mixin StaffsDao
 */
class StaffsRepository extends BaseRepository
{
    public function __construct(StaffsDao $dao)
    {
        $this->dao = $dao;
    }

    public function getList($where, $page, $limit)
    {
        // 构建查询语句，根据 $where 条件进行搜索，并包含 'user' 关联数据，但只选取特定字段。
        $query = $this->dao->search($where)
            ->with(['user' => function ($query) {
                // 在 'user' 关联数据中，只选取 'nickname', 'avatar', 'uid', 'cancel_time' 四个字段。
                $query->field('nickname,avatar,uid,cancel_time');
            }])
            ->order('sort DESC,create_time DESC'); // 按 'sort' 和 'create_time' 降序排序。

        // 计算满足条件的数据总数。
        $count = $query->count();

        // 进行分页查询，获取当前页的数据列表。
        $list = $query->page($page, $limit)->select();

        // 将数据总数和数据列表一起返回。
        return compact('count', 'list');
    }

    public function create($data)
    {
        $info = $this->dao->getOnlyTrashed(['mer_id' => $data['mer_id'], 'uid' => $data['uid']])->find();
        if ($info) {
            $info->restore();
            $info->save($data);
        }else if($this->dao->getWhere(['mer_id' => $data['mer_id'], 'uid' => $data['uid']])){
            throw new ValidateException('该员工已存在');
        }else{
            $this->dao->create($data);
        }
    }

    /**
     * 员工表单
     * @param int|null $id
     * @return Form
     * @throws FormBuilderException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function form(?int $id = 0): Form
    {
        $detail        = $id ? $this->dao->getWith($id, ['user' => function ($query) {
            $query->field('avatar as src,uid as id,uid');
        }])->toArray() : [];
        $form          = Elm::createForm(Route::buildUrl($id ? 'merchantStaffsUpdate' : 'merchantStaffsCreate', ['id' => $id])->build());
        $prefix        = config('admin.merchant_prefix');
        if($detail) {
            $detail['uid'] = $detail['user'] ?? '';
            $detail['uid']['src'] = $detail['uid']['src'] ?: $detail['photo'];
        }
        // 设置表单的验证规则和默认值。
        $form->setRule([
            Elm::frameImage('uid', '关联用户：', '/' . $prefix . '/setting/userList?field=uid&type=1')->prop('srcKey', 'src')->width('1000px')->height('600px')->appendValidate(Iview::validateObject()->message('请选择用户')->required('请选择用户'))->icon('el-icon-camera')->modal(['modal' => false]),
            Elm::frameImage('photo', '证件照：', '/' . $prefix . '/setting/uploadPicture?field=photo&type=1', $detail['photo'] ?? '')->width('1000px')->height('600px')->props(['footer' => false])->icon('el-icon-camera')->modal(['modal' => false]),
            Elm::input('name', '姓名：', $detail['name'] ?? '')->placeholder('请输入姓名')->required('请输入姓名'),
            Elm::input('phone', '电话：', $detail['phone'] ?? '')->placeholder('请输入联系电话')->required('请输入联系电话'),
            Elm::text('remark', '备注：', $detail['remark'] ?? '')->placeholder('请输入备注'),
            Elm::number('sort', '排序：', $detail['sort'] ?? '')->precision(0)->min(0)->max(99999),
            Elm::switches('status', '员工状态：', $detail['status'] ?? '')->activeValue(1)->width(60)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启')->col(12),
        ])->formData($detail);
        // 设置表单的标题，并返回表单对象。
        return $form->setTitle($id ? '编辑服务人员' : '添加服务人员');
    }

    public function fetchMerAll(int $merId, $staffId)
    {
        $field = 'staffs_id,mer_id,phone,name';
        $where = ['mer_id' => $merId, 'status' => 1];
        if($staffId){
            $where['staff_id'] = $staffId;
        }

        return $this->dao->search($where)->field($field)->order('sort DESC')->select();
    }

    public function getStatisticsList($where, $page, $limit)
    {
        $query = $this->dao->statisticsSearch($where)
            ->with(['user' => function ($query) {
                $query->field('nickname,avatar,uid,cancel_time');
            }])
            ->order('s.sort DESC,s.create_time DESC');

        $count = $query->count();
        $list = $query->page($page, $limit)->select();

        return compact('count', 'list');
    }

    public function getStatisticsDetail($id)
    {
        $info = $this->dao->getWith($id, ['order', 'user' => function ($query) {
            $query->field('nickname,avatar,uid,cancel_time');
        }])->toArray();
        $order = $info['order'];

        $pendingNum = 0;
        $serviceFee = 0;
        $productCount = 0;
        foreach ($order as &$value) {
            $value['reservation_date'] = $value['orderProduct'][0]['reservation_date'];
            $value['settlement_price'] = array_sum(array_column($value['orderProduct'], 'settlement_price'));
            $serviceFee = bcadd($serviceFee, $value['settlement_price'], 2);
            $productCount += array_sum(array_column($value['orderProduct'], 'product_num'));

            if($value['status'] == 1) {
                $pendingNum ++;
            }

            $orderProduct = [];
            foreach ($value['orderProduct'] as &$item) {
                $orderProduct[] = [
                    'product_name' => $item['cart_info']['product']['store_name'],
                    'product_image' => $item['cart_info']['product']['image'],
                    'unique' => $item['cart_info']['productAttr']['unique'],
                    'product_num' => $item['product_num'],
                    'product_price' => $item['product_price'],
                    'settlement_price' => $item['settlement_price']
                ];
            }
            unset($value['orderProduct']);

            $value['orderProduct'] = $orderProduct;
        }

        $info['order'] = $order;
        $info['pendingNum'] = $pendingNum;
        $info['serviceFee'] = $serviceFee;
        $info['orderCount'] = count($order);
        $info['productCount'] = $productCount;

        return $info;
    }
}
