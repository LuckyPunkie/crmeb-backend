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


use crmeb\services\pay\Pay;
use app\common\dao\store\order\StoreOrderProfitsharingDao;
use app\common\model\store\order\StoreOrder;
use app\common\model\store\order\StoreOrderProfitsharing;
use app\common\model\store\order\StoreRefundOrder;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;

/**
 * 分账
 */
class StoreOrderProfitsharingRepository extends BaseRepository
{
    const PROFITSHARING_TYPE_ORDER = 'order';
    const PROFITSHARING_TYPE_PRESELL = 'presell';

    /**
     * @var StoreOrderProfitsharingDao
     */
    protected $dao;

    public function __construct(StoreOrderProfitsharingDao $storeOrderProfitsharingDao)
    {
        $this->dao = $storeOrderProfitsharingDao;
    }

    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件查询数据库，并返回分页后的数据列表以及总条数。
     * 主要用于后台管理界面的数据展示，支持条件查询、分页和数据附加字段。
     *
     * @param array $where 查询条件，以数组形式传递，用于构建查询条件。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页数据条数，用于分页查询。
     * @param bool $merchant 是否为商家查询，商家查询不返回某些字段。
     * @return array 返回包含数据列表和总条数的数组。
     */
    public function getList(array $where, $page, $limit, $merchant = false)
    {
        // 构建查询条件，根据$where参数进行搜索，并关联查询订单信息，只获取订单ID和订单号。
        $query = $this->dao->search($where)
            ->with(['order' => function ($query) {
                $query->field('order_id,order_sn');
            }])
            ->order('create_time DESC');

        // 计算满足条件的数据总条数。
        $count = $query->count();

        // 定义需要附加到数据列表中的字段，用于丰富返回的数据。
        $append = ['statusName', 'typeName'];

        // 如果不是商家查询，追加'merchant'字段到附加字段列表。
        if (!$merchant) {
            $append[] = 'merchant';
        }

        // 执行分页查询，并附加指定的字段，返回查询结果。
        $list = $query->page($page, $limit)->append($append, true)->select();

        // 返回数据列表和总条数的组合。
        return compact('list', 'count');
    }

    /**
     * 计算并处理退款金额
     *
     * 此方法用于根据退款订单、原价格和商家退款金额，执行具体的退款金额计算和处理逻辑。
     * 它调用了另一个方法`refundPresallPrice`来实现这一功能。
     *
     * @param StoreRefundOrder $refundOrder 退款订单对象，包含退款订单的相关信息。
     * @param float $price 原价格，即商品的原始购买价格。
     * @param float $refundMerPrice 商家退款金额，即商家实际退还给用户的金额。
     */
    public function refundPrice(StoreRefundOrder $refundOrder, $price, $refundMerPrice)
    {
        // 调用退款预计算方法，进行退款金额的计算和处理。
        $this->refundPresallPrice($refundOrder, $price, $refundMerPrice, true);
    }

    /**
     * 根据预售订单或普通订单类型，进行退款分账处理。
     * 此函数主要用于处理订单退款时的分账逻辑，根据订单类型的不同，退款金额将被相应地添加到分账退款金额中，
     * 并相应地更新商户的分账金额。如果退款金额超过了原始的分账金额，将标记分账订单状态为无效。
     *
     * @param StoreRefundOrder $refundOrder 退款订单对象，包含相关的订单信息。
     * @param float $price 用户退款的金额，用于更新分账退款金额。
     * @param float $refundMerPrice 商户退款的金额，用于更新商户的分账金额。
     * @param bool $order 指示当前处理的是普通订单还是预售订单，默认为false表示预售订单。
     * @throws ValidateException 如果找不到相应的分账订单，则抛出异常。
     */
    public function refundPresallPrice(StoreRefundOrder $refundOrder, $price, $refundMerPrice, $order = false)
    {
        // 根据订单类型选择正确的分账订单模型
        $model = $order ? $refundOrder->order->firstProfitsharing : $refundOrder->order->presellProfitsharing;

        // 如果找不到对应的分账订单模型，则抛出异常
        if (!$model)
            throw new ValidateException('分账订单不存在');

        // 更新分账订单的退款金额和商户退款金额
        $model->profitsharing_refund = bcadd($model->profitsharing_refund, $price, 2);
        $model->profitsharing_mer_price = bcsub($model->profitsharing_mer_price, $refundMerPrice, 2);

        // 如果分账退款金额大于等于原始分账金额，则将分账订单状态设置为无效
        if ($model->profitsharing_refund >= $model->profitsharing_price) {
            $model->status = -1;
        }

        // 保存更新后的分账订单信息
        $model->save();
    }

    /**
     * 分享利润订单处理函数
     *
     * 本函数用于处理店铺订单的利润分享。它遍历订单中的每个利润分享项，
     * 并调用利润分享功能来处理这些分享项。
     *
     * @param StoreOrder $storeOrder 包含利润分享信息的店铺订单对象。
     */
    public function profitsharingOrder(StoreOrder $storeOrder)
    {
        // 遍历订单中的每个利润分享项，并进行处理
        foreach ($storeOrder->profitsharing as $profitsharing) {
            $this->profitsharing($profitsharing);
        }
    }

    /**
     * 分享利润处理函数
     * 该函数用于处理店铺订单的利润分享操作。根据利润分享金额和商家分成金额的比较，决定是发起利润分享订单还是结束利润分享订单。
     *
     * @param StoreOrderProfitsharing $profitsharing 利润分享对象，包含利润分享的相关信息。
     * @return bool 返回操作是否成功的标志。成功返回true，失败返回false。
     */
    public function profitsharing(StoreOrderProfitsharing $profitsharing)
    {
        // 初始化状态变量
        $status = 2;
        $error_msg = '';
        $flag = true;

        if ($profitsharing->is_combine == 2) {
            $server = new Pay('partner');
        } else {
            $server = new Pay('combine');
        }
        try {
            // 判断利润分享金额是否大于商家分成金额
            if (bcsub($profitsharing->profitsharing_price, $profitsharing->profitsharing_mer_price, 2) > 0) {
                // 分账并解冻
                $res = $server->profitsharingOrder($profitsharing->getProfitsharingParmas($profitsharing->is_combine), true);
            } else {
                // 直接解冻
                $res = $server->profitsharingFinishOrder($profitsharing->getProfitsharingFinishParmas($profitsharing->is_combine));
            }
            // 更新利润分账时间
            $profitsharing->profitsharing_time = date('Y-m-d H:i:s');
            $status = 1;
        } catch (\Exception $e) {
            // 捕获异常，更新状态变量为失败
            $status = -2;
            $error_msg = $e->getMessage();
            $flag = false;
        }

        // 更新利润分享对象的状态和错误信息
        $profitsharing->status = $status;
        $profitsharing->error_msg = $error_msg;
        // 保存更新后的利润分享对象
        $profitsharing->save();

        return $flag;
    }

    public function profitsharingStatus(StoreOrderProfitsharing $profitsharing)
    {
        if ($profitsharing->is_combine == 1)
            return ;
        $options = [
            'sub_mchid' => $profitsharing->merchant->applyment_id,
            'out_order_no' => $profitsharing->profitsharing_sn,
            'transaction_id' => $profitsharing->transaction_id,
        ];
        $services = new Pay('partner');
        $res = $services->profitsharingStatus($options);
        if (isset($res['state']) && $res['state'] == 'FINISHED') {
            if (isset($res['receivers'][0])) {
                $profitsharing->profitsharing_time = $res['receivers'][0]['finish_time'];
                $profitsharing->profitsharing_mer_price = ($res['receivers'][0]['amount'] / 100);
            }
            $profitsharing->status = 1;
            $profitsharing->save();
        }
    }

}
