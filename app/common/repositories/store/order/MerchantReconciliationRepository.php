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

namespace app\common\repositories\store\order;

use app\common\model\store\order\MerchantReconciliationOrder;
use app\common\model\system\merchant\Merchant;
use app\common\repositories\BaseRepository;
use app\common\dao\store\order\MerchantReconciliationDao as dao;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\order\StoreRefundOrderRepository;
use app\common\repositories\system\admin\AdminRepository;
use app\common\repositories\system\merchant\FinancialRecordRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use crmeb\services\SwooleTaskService;
use think\exception\ValidateException;
use think\facade\Db;
use FormBuilder\Factory\Elm;
use think\facade\Route;

/**
 * 商户对账记录
 */
class MerchantReconciliationRepository extends BaseRepository
{
    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }


    /**
     * 根据ID获取核对记录的数量，以判断是否存在特定状态的核对记录。
     *
     * 此方法用于检查是否存在指定ID的核对记录，并且其状态为2（表示某种特定的状态，具体含义依赖于业务逻辑）。
     * 如果存在至少一条满足条件的记录，则返回true，否则返回false。
     *
     * @param int $id 核对记录的ID，用于查询特定的核对记录。
     * @return bool 如果存在满足条件的核对记录则返回true，否则返回false。
     */
    public function getWhereCountById($id)
    {
        // 定义查询条件，包括核对记录的ID和状态。
        $where = ['reconciliation_id' => $id, 'status' => 2];

        // 调用DAO层的方法查询满足条件的记录数量，并检查数量是否大于0。
        // 如果大于0，说明存在满足条件的记录，返回true；否则，返回false。
        return $this->dao->getWhereCount($where) > 0;
    }

    /**
     * 检查指定ID和商家ID对应的未结算记录是否存在
     *
     * 本函数用于查询特定reconciliation_id和mer_id对应的未结算记录是否存在于数据库中。
     * 通过判断返回的记录数是否大于0来确定是否存在符合条件的未结算记录。
     *
     * @param int $id 对应的reconciliation_id，用于查询记录。
     * @param int $merId 商家ID，用于查询特定商家的记录。
     * @return bool 如果存在符合条件的未结算记录则返回true，否则返回false。
     */
    public function merWhereCountById($id, $merId)
    {
        // 定义查询条件，包括reconciliation_id、mer_id、is_accounts和status
        $where = ['reconciliation_id' => $id, 'mer_id' => $merId, 'is_accounts' => 0, 'status' => 0];

        // 调用dao层的getWhereCount方法查询符合条件的记录数，并检查是否大于0
        return ($this->dao->getWhereCount($where) > 0);
    }

    /**
     *  列表
     * @param $where
     * @param $page
     * @param $limit
     * @return array
     * @author Qinii
     * @day 2020-06-15
     */
    public function getList($where, $page, $limit)
    {
        $query = $this->dao->search($where)->with([
            'merchant' => function ($query) {
                $query->field('mer_id,mer_name');
            },
            'admin' => function ($query) {
                $query->field('admin_id,real_name');
            }]);
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->append(['charge'])->each(function ($item) {
            if ($item->type == 1) return $item->price = '-' . $item->price;
        });

        return compact('count', 'list');
    }


    /**
     *  创建对账单
     * @param $id
     * @param $data
     * @author Qinii
     * @day 2020-06-15
     */
    public function create(int $id, array $data)
    {
        $orderMake = app()->make(StoreOrderRepository::class);
        $refundMake = app()->make(StoreRefundOrderRepository::class);

        $bank = merchantConfig($id, 'bank');
        $bank_name = merchantConfig($id, 'bank_name');
        $bank_number = merchantConfig($id, 'bank_number');
        $bank_address = merchantConfig($id, 'bank_address');
        if (!$bank || !$bank_name || !$bank_number || !$bank_address)
            throw  new ValidateException('商户未填写银行卡信息');

        $order_ids = $data['order_ids'];
        $refund_order_ids = $data['refund_order_ids'];
        if ($data['order_type']) { //全选
            $order_ids = $orderMake->search([
                'date' => $data['date'],
                'mer_id' => $id,
                'paid' => 1,
                'order_id'
            ], null
            )->whereNotIn('order_id', $data['order_out_ids'])->column('order_id');
        }
        if ($data['refund_type']) { //全选
            $refund_order_ids = $refundMake->search([
                'date' => $data['date'],
                'mer_id' => $id,
                'reconciliation_type' => 0,
                'status' => 3
            ])->whereNotIn('refund_order_id', $data['refund_out_ids'])->column('refund_order_id');
        }
        if (is_array($order_ids) && (count($order_ids) < 1) && is_array($refund_order_ids) && (count($refund_order_ids) < 1)) {
            throw new ValidateException('没有数据可对账');
        }
        $compute = $this->compute($id, $order_ids, $refund_order_ids);
        $createData = [
            'status' => 0,
            'mer_id' => $id,
            'is_accounts' => 0,
            'mer_admin_id' => 0,
            'bank' => $bank,
            'bank_name' => $bank_name,
            'bank_number' => $bank_number,
            'bank_address' => $bank_address,
            'admin_id' => $data['adminId'],
            'price' => round($compute['price'], 2),
            'order_price' => round($compute['order_price'], 2),
            'refund_price' => round($compute['refund_price'], 2),
            //'refund_rate'   => round($compute['refund_rate'],2),
            'order_rate' => round($compute['order_rate'], 2),
            'order_extension' => round($compute['order_extension'], 2),
            'refund_extension' => round($compute['refund_extension'], 2),
        ];

        Db::transaction(function () use ($order_ids, $refund_order_ids, $orderMake, $refundMake, $createData) {
            $res = $this->dao->create($createData);
            $orderMake->updates($order_ids, ['reconciliation_id' => $res['reconciliation_id']]);
            $refundMake->updates($refund_order_ids, ['reconciliation_id' => $res['reconciliation_id']]);
            $this->reconciliationOrder($order_ids, $refund_order_ids, $res['reconciliation_id']);

            SwooleTaskService::merchant('notice', [
                'type' => 'accoubts',
                'data' => [
                    'title' => '新对账',
                    'message' => '您有一条新的对账单',
                    'id' => $res['reconciliation_id']
                ]
            ], $createData['mer_id']);
        });
    }

    /**
     *  计算对账单金额
     * @param $merId
     * @param $order_ids
     * @param $refund_order_ids
     * @return array
     * @author Qinii
     * @day 2020-06-23
     */
    public function compute($merId, $order_ids, $refund_order_ids)
    {
        $order_price = $refund_price = $order_extension = $refund_extension = $order_rate = $refund_rate = 0;
        $orderMake = app()->make(StoreOrderRepository::class);
        $refundMake = app()->make(StoreRefundOrderRepository::class);

        foreach ($order_ids as $item) {
            if (!$order = $orderMake->getWhere(['order_id' => $item, 'mer_id' => $merId, 'paid' => 1]))
                throw new ValidateException('订单信息不存在或状态错误');

            if ($order['reconciliation_id']) throw new ValidateException('订单重复提交');

            //(实付金额 - 一级佣金 - 二级佣金) * 抽成
            $commission_rate = ($order['commission_rate'] / 100);
            //佣金
            $_order_extension = bcadd($order['extension_one'], $order['extension_two'], 3);
            $order_extension = bcadd($order_extension, $_order_extension, 3);

            //手续费 =  (实付金额 - 一级佣金 - 二级佣金) * 比例
            $_order_rate = bcmul(bcsub($order['pay_price'], $_order_extension, 3), $commission_rate, 3);
            $order_rate = bcadd($order_rate, $_order_rate, 3);

            //金额
            $_order_price = bcsub(bcsub($order['pay_price'], $_order_extension, 3), $_order_rate, 3);
            $order_price = bcadd($order_price, $_order_price, 3);
        }

        foreach ($refund_order_ids as $item) {
            if (!$refundOrder = $refundMake->getWhere(['refund_order_id' => $item, 'mer_id' => $merId, 'status' => 3], '*', ['order']))
                throw new ValidateException('退款订单信息不存在或状态错误');
            if ($refundOrder['reconciliation_id']) throw new ValidateException('退款订单重复提交');

            //退款金额 + 一级佣金 + 二级佣金
            $refund_commission_rate = ($refundOrder['order']['commission_rate'] / 100);
            //佣金
            $_refund_extension = bcadd($refundOrder['extension_one'], $refundOrder['extension_two'], 3);
            $refund_extension = bcadd($refund_extension, $_refund_extension, 3);

            //手续费
//            $_refund_rate = bcmul(bcsub($refundOrder['refund_price'],$_refund_extension,3),$refund_commission_rate,3);
//            $refund_rate = bcadd($refund_rate,$_refund_rate,3);

            //金额
            $_refund_price = bcadd($refundOrder['refund_price'], $_refund_extension, 3);
            $refund_price = bcadd($refund_price, $_refund_price, 3);
        }

        $price = bcsub($order_price, $refund_price, 3);

        return compact('price', 'refund_price', 'order_extension', 'refund_extension', 'order_price', 'order_rate');
    }

    /**
     * 同步调账订单和退款订单到调账表
     *
     * 本函数用于将订单和退款订单的信息同步到调账表中，以确保调账表中的数据与实际发生的订单和退款一致。
     * 这对于财务对账来说是非常重要的，可以确保所有的交易都能在调账表中找到相应的记录。
     *
     * @param array $order_ids 订单ID列表，包含需要同步到调账表的订单ID。
     * @param array $refund_ids 退款ID列表，包含需要同步到调账表的退款ID。
     * @param int $reconciliation_id 调账ID，用于标识这次调账操作。
     *
     * @return bool 返回操作结果，true表示成功插入所有数据，false表示插入数据失败。
     */
    public function reconciliationOrder($order_ids, $refund_ids, $reconciliation_id)
    {
        // 初始化数据数组，用于存储待插入调账表的订单和退款信息。
        $data = [];

        // 遍历订单ID列表，构建订单类型的调账数据，并加入到数据数组中。
        foreach ($order_ids as $item) {
            $data[] = [
                'order_id' => $item,
                'reconciliation_id' => $reconciliation_id,
                'type' => 0, // 订单类型标识。
            ];
        }

        // 遍历退款ID列表，构建退款类型的调账数据，并加入到数据数组中。
        foreach ($refund_ids as $item) {
            $data[] = [
                'order_id' => $item,
                'reconciliation_id' => $reconciliation_id,
                'type' => 1, // 退款类型标识。
            ];
        }

        // 使用依赖注入的方式，创建调账订单仓库实例，并调用其insertAll方法，批量插入调账数据。
        // 返回操作结果，true表示成功插入所有数据，false表示插入数据失败。
        return app()->make(MerchantReconciliationOrderRepository::class)->insertAll($data);
    }

    /**
     *  修改状态
     * @param $id
     * @param $data
     * @param $type
     * @author Qinii
     * @day 2020-06-15
     */
    public function switchStatus($id, $data)
    {
        Db::transaction(function () use ($id, $data) {
            if (isset($data['status']) && $data['status'] == 1) {
                app()->make(StoreRefundOrderRepository::class)->reconciliationUpdate($id);
                app()->make(StoreOrderRepository::class)->reconciliationUpdate($id);
            }
            $this->dao->update($id, $data);
        });
        $res = $this->dao->get($id);
        $mer = app()->make(MerchantRepository::class)->get($res['mer_id']);
        if (isset($data['is_accounts']) && $data['is_accounts']) {
//            $make = app()->make(FinancialRecordRepository::class);
//
//            $make->dec([
//                'order_id' => $id,
//                'order_sn' => $id,
//                'user_info' => $mer['mer_name'],
//                'user_id' => $res['mer_id'],
//                'financial_type' => 'sys_accoubts',
//                'number' => $res->price,
//            ],0);
//
//            $make->inc([
//                'order_id' => $id,
//                'order_sn' => $id,
//                'user_info' => '总平台',
//                'user_id' => 0,
//                'financial_type' => 'mer_accoubts',
//                'number' => $res->price,
//            ],$res->mer_id);

            SwooleTaskService::merchant('notice', [
                'type' => 'accoubts',
                'data' => [
                    'title' => '新对账打款',
                    'message' => '您有一条新对账打款通知',
                    'id' => $id
                ]
            ], $res['mer_id']);
        }
    }

    /**
     * 标记商户结算记录的表单生成方法
     *
     * 本方法用于生成一个用于修改商户结算记录备注的表单。通过给定的结算记录ID，获取当前备注信息，
     * 并构建一个表单以允许用户输入新的备注。表单提交的URL是根据当前商户结算记录ID动态生成的。
     *
     * @param int $id 商户结算记录的唯一标识ID
     * @return \Phper6\Elm\Form 表单对象，已经配置好表单的URL、表单规则和默认值
     */
    public function markForm($id)
    {
        // 根据$id获取商户结算记录的详细信息
        $data = $this->dao->get($id);

        // 构建表单的提交URL，URL指向处理商户结算记录备注修改的接口
        $form = Elm::createForm(Route::buildUrl('merchantReconciliationMark', ['id' => $id])->build());

        // 配置表单的规则，这里只包含一个文本输入框用于输入备注信息
        $form->setRule([
            // 文本输入框用于输入备注，初始值从获取的商户结算记录中取得
            Elm::text('mark', '备注：', $data['mark'])->placeholder('请输入备注')->required('请输入备注'),
        ]);

        // 设置表单的标题为“修改备注”
        return $form->setTitle('修改备注');
    }

    /**
     * 创建管理员标记表单
     *
     * 该方法用于生成一个用于修改管理员标记的表单。通过给定的商家ID，获取当前的管理员标记，
     * 并构建一个表单以允许管理员输入新的标记。此表单通常在后台管理系统中使用，以提供
     * 对商家进行备注和标记的功能，帮助管理员更好地管理商家信息。
     *
     * @param int $id 商家ID，用于获取当前商家的管理员标记信息。
     * @return string 返回构建好的表单HTML代码。
     */
    public function adminMarkForm($id)
    {
        // 根据商家ID获取当前商家的管理员标记信息
        $data = $this->dao->get($id);

        // 构建表单URL，指向系统中的商家对账标记页面
        $formUrl = Route::buildUrl('systemMerchantReconciliationMark', ['id' => $id])->build();

        // 创建表单对象，并设置表单提交的URL
        $form = Elm::createForm($formUrl);

        // 设置表单的验证规则，包括一个文本输入框用于输入管理员标记
        $form->setRule([
            Elm::text('admin_mark', '备注：', $data['admin_mark'])
                ->placeholder('请输入备注')
                ->required('请输入备注'), // 输入框必须填写
        ]);

        // 设置表单的标题为“修改备注”
        return $form->setTitle('修改备注');
    }
}
