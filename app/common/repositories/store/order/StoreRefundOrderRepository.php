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


use think\facade\Log;
use crmeb\services\pay\Pay;
use app\common\repositories\delivery\DeliveryOrderRepository;
use app\common\dao\store\order\StoreRefundOrderDao;
use app\common\model\store\order\StoreOrder;
use app\common\model\store\order\StoreRefundOrder;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\system\merchant\FinancialRecordRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserRepository;
use crmeb\jobs\SendSmsJob;
use crmeb\services\ExpressService;
use crmeb\services\SwooleTaskService;
use Exception;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Queue;
use think\facade\Route;
use think\Model;
use think\response\Json;
use app\common\repositories\circle\CircleFinancialRecordRepository;

/**
 * 退款
 */
class StoreRefundOrderRepository extends BaseRepository
{

    //状态 0:待审核 -1:审核未通过 1:待退货 2:待收货 3:已退款 3:平台介入 -10 取消
    const REFUND_STATUS_WAIT = 0;
    const REFUND_STATUS_BACK = 1;
    const REFUND_STATUS_THEGOODS = 2;
    const REFUND_STATUS_SUCCESS = 1;
    const REFUND_STATUS_REFUSED = -1;
    const REFUND_STATUS_CANCEL = -2;
    const REFUND_PLATFORM_INTERVENE = 4;


    //分账用的是平台收付通
    const TYPE_IS_COMBINE = 21;
    //分账用的是服务商
    const TYPE_IS_PARTNER = 22;


    /**
     * StoreRefundOrderRepository constructor.
     * @param StoreRefundOrderDao $dao
     */
    public function __construct(StoreRefundOrderDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取用户列表，根据特定条件和分页信息查询用户数据。
     *
     * 本函数主要用于处理用户列表的查询工作，包括条件查询、分页处理和数据返回。
     * 通过传递条件数组、页码和每页记录数来实现定制化的用户数据查询。
     *
     * @param array $where 查询条件数组，用于定制查询的具体条件。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页记录数，用于分页查询。
     * @return array 返回包含用户数据列表和总记录数的数组。
     */
    public function userList(array $where, $page, $limit)
    {
        // 根据传入的条件进行查询
        $query = $this->dao->search($where);

        // 统计满足条件的总记录数
        $count = $query->count();

        // 定义查询字段，包括退款订单ID、退款金额、商家ID和状态，并关联商家和退款产品的信息
        $list = $query->setOption('field', [])->field('refund_order_id,refund_price,StoreRefundOrder.mer_id,StoreRefundOrder.status')
            ->with(['merchant' => function ($query) {
                // 关联商家信息，只获取商家名称和ID
                $query->field('mer_name,mer_id');
            }, 'refundProduct.product'])->page($page, $limit)->select();

        // 返回查询结果的列表和总记录数
        return compact('list', 'count');
    }

    /**
     * 根据用户ID和订单ID查询用户详情
     * 此函数用于通过用户ID和特定的订单ID来检索用户的详细信息。
     * 这包括订单相关的退款产品信息、商家信息，以及自动退款时间等附加信息。
     *
     * @param int $id 订单ID
     * @param int $uid 用户ID
     * @return array|null 返回匹配条件的用户详情数据，如果找不到则返回null
     */
    public function userDetail($id, $uid)
    {
        // 使用DAO层的search方法查询符合条件的用户订单信息
        // 查询条件包括订单ID、用户ID，以及订单未被删除（is_del为0）
        // 同时，通过with方法加载关联数据，包括退款产品的产品信息和商家信息
        // 对商家信息的查询进行了进一步的字段筛选，并附加了'services_type'信息
        // 最后，通过append方法加载订单的自动退款时间信息
        return $this->dao->search([
            'id'     => $id,
            'uid'    => $uid,
            'is_del' => 0,
        ])->with(['refundProduct.product', 'platform', 'merchant' => function ($query) {
            $query->field('mer_id,mer_name,service_phone')->append(['services_type'], true);
        }])->append(['auto_refund_time'], true)->find();
    }

    /**
     * 用户删除操作
     *
     * 本函数用于处理用户的删除操作，涉及到数据库事务的使用，以确保数据的一致性。
     * 在执行过程中，会首先尝试获取指定ID的相关数据，然后通过事务处理方式，同时更新用户删除状态和订单状态。
     *
     * @param int $id 用户ID，用于指定要删除的用户。
     * @param int $uid 删除操作的执行者ID，用于记录操作人。
     */
    public function userDel($id, $uid)
    {
        // 尝试获取指定ID的用户数据
        $ret = $this->dao->get($id);

        // 初始化订单状态数组，用于记录订单状态变更信息
        // 退款订单记录
        $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
        $orderStatus                = [
            'order_id'       => $ret->refund_order_id,
            'order_sn'       => $ret->refund_order_sn,
            'type'           => $storeOrderStatusRepository::TYPE_REFUND,
            'change_message' => '创建退款单',
            'change_type'    => $storeOrderStatusRepository::ORDER_STATUS_DELETE,
        ];

        // 使用数据库事务来确保操作的原子性
        Db::transaction(function () use ($uid, $id, $storeOrderStatusRepository, $orderStatus) {
            // 更新用户删除状态
            $this->dao->userDel($uid, $id);
            // 记录订单状态变更日志
            $storeOrderStatusRepository->createUserLog($uid,$orderStatus);
        });
    }

    /**
     * 创建退款订单
     *
     * @param StoreOrder $order 退款的订单
     * @param int $refund_type 退款类型，默认为1（普通退款）
     * @param string $refund_message 退款原因，默认为'自动发起退款'
     * @param bool $refund_postage 是否退款邮费，默认为true
     * @return mixed 退款订单信息
     */
    public function createRefund(StoreOrder $order, $refund_type = 1, $refund_message = '自动发起退款', $refund_postage = true)
    {
        $products            = $order->orderProduct;
        $ids                 = array_column($products->toArray(), 'order_product_id');
        $productRefundPrices = app()->make(StoreRefundProductRepository::class)->userRefundPrice($ids);

        $totalRefundPrice         = 0;
        $totalRefundNum           = 0;
        $total_extension_one      = 0;
        $total_extension_two      = 0;
        $totalIntegral            = 0;
        $totalPlatformRefundPrice = 0;
        $totalPostage             = 0;
        $refundProduct            = [];
        $refund_order_id          = 0;
        foreach ($products as $product) {
            $productRefundPrice = $productRefundPrices[$product['order_product_id']] ?? [];
            if ($product['extension_one'] > 0)
                $total_extension_one = bcadd($total_extension_one, bcmul($product['refund_num'], $product['extension_one'], 2), 2);
            if ($product['extension_two'] > 0)
                $total_extension_two = bcadd($total_extension_two, bcmul($product['refund_num'], $product['extension_two'], 2), 2);
            $postagePrice   = ($refund_postage || !$order->status || $order->status == 9) ? bcsub($product['postage_price'], $productRefundPrice['refund_postage'] ?? 0, 2) : 0;
            $totalRefundNum += $product['refund_num'];
            $refundPrice    = 0;
            //计算可退金额
            if ($product['product_price'] > 0) {
                $refundPrice = bcsub($product['product_price'], bcsub($productRefundPrice['refund_price'] ?? 0, $productRefundPrice['refund_postage'] ?? 0, 2), 2);
            }
            $platform_refund_price = 0;
            //计算退的平台优惠券金额
            if ($product['platform_coupon_price'] > 0) {
                $platform_refund_price = bcsub($product['platform_coupon_price'], $productRefundPrice['platform_refund_price'] ?? 0, 2);
            }
            $integral = 0;
            if ($product['integral'] > 0) {
                $integral = bcsub($product['integral_total'], $productRefundPrice['refund_integral'] ?? 0, 0);
            }

            $totalPostage             = bcadd($totalPostage, $postagePrice, 2);
            $totalRefundPrice         = bcadd($totalRefundPrice, $refundPrice, 2);
            $totalPlatformRefundPrice = bcadd($totalPlatformRefundPrice, $platform_refund_price, 2);
            $totalIntegral            = bcadd($totalIntegral, $integral, 2);

            $refundProduct[] = [
                'refund_order_id'       => &$refund_order_id,
                'refund_num'            => $product['refund_num'],
                'order_product_id'      => $product['order_product_id'],
                'platform_refund_price' => $platform_refund_price,
                'refund_integral'       => $integral,
                'refund_price'          => $refundPrice,
                'refund_postage'        => $postagePrice,
            ];
        }
        $data                          = compact('refund_message', 'refund_type');
        $data['order_id']              = $products[0]['order_id'];
        $data['uid']                   = $products[0]['uid'];
        $data['mer_id']                = $order['mer_id'];
        $data['refund_order_sn']       = app()->make(StoreOrderRepository::class)->getNewOrderId(StoreOrderRepository::TYPE_SN_REFUND);
        $data['refund_num']            = $totalRefundNum;
        $data['extension_one']         = $total_extension_one;
        $data['extension_two']         = $total_extension_two;
        $data['refund_price']          = bcadd($totalPostage, $totalRefundPrice, 2);
        $data['integral']              = $totalIntegral;
        $data['platform_refund_price'] = $totalPlatformRefundPrice;
        $data['refund_postage']        = $totalPostage;
        //退款订单记录
        $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);

        return Db::transaction(function () use ($refundProduct, $data, $products, $order, &$refund_order_id, $storeOrderStatusRepository, $refund_message) {
            event('refund.creates.before', compact('data'));
            $refund          = $this->dao->create($data);
            $refund_order_id = $refund->refund_order_id;
            foreach ($products as $product) {
                $product->refund_num = 0;
                $product->is_refund  = 1;
                $product->save();
            }
            $orderStatus = [
                'order_id'       => $refund->refund_order_id,
                'order_sn'       => $order->order_sn,
                'type'           => $storeOrderStatusRepository::TYPE_REFUND,
                'change_message' => $refund_message,
                'change_type'    => $storeOrderStatusRepository::ORDER_STATUS_CREATE,
            ];
            $storeOrderStatusRepository->createSysLog($orderStatus);
            app()->make(StoreRefundProductRepository::class)->insertAll($refundProduct);
            return $refund;
        });
    }

    /**
     * 计算订单的退款总金额
     *
     * 本函数用于根据订单中的商品及其退款情况，计算出订单的总退款金额，包括商品退款金额和邮费退款金额。
     * 在计算过程中，会考虑到商品是否支持退款、订单状态以及具体的退款金额和邮费。
     *
     * @param object $order 订单对象，包含订单的相关信息。
     * @param Collection $products 商品集合，包含需要退款的商品详细信息。
     * @param int $refund_switch 退款开关，默认为1，表示支持退款。如果不支持退款，则会抛出异常。
     * @return float 订单的总退款金额，包括商品退款金额和邮费退款金额。
     * @throws ValidateException 如果部分商品不支持退款，则抛出此异常。
     */
    public function getRefundsTotalPrice($order, $products, $refund_switch = 1)
    {
        // 通过依赖注入获取退款产品仓库，用于查询商品的退款价格信息。
        $productRefundPrices = app()->make(StoreRefundProductRepository::class)->userRefundPrice($products->column('order_product_id'));

        // 初始化邮费退款总额和商品退款总额
        $totalPostage     = 0;
        $totalRefundPrice = 0;

        // 遍历商品集合，计算每个商品的退款金额和邮费退款金额
        foreach ($products as $product) {
            // 如果商品不支持退款，但全局退款开关打开，则抛出异常
            if (!$product['refund_switch'] && $refund_switch) throw new ValidateException('部分商品不支持退款');

            // 获取商品的退款价格信息
            $productRefundPrice = $productRefundPrices[$product['order_product_id']] ?? [];

            // 计算商品的邮费退款金额，根据订单状态和退款邮费来确定
            $postagePrice = (!$order->status || $order->status == 9) ? bcsub($product['postage_price'], $productRefundPrice['refund_postage'] ?? 0, 2) : 0;

            // 计算商品的退款金额，考虑商品价格、商品退款价格和邮费退款金额
            $refundPrice = 0;
            if ($product['product_price'] > 0) {
                $refundPrice = bcsub($product['product_price'], bcsub($productRefundPrice['refund_price'] ?? 0, $productRefundPrice['refund_postage'] ?? 0, 2), 2);
            }

            // 累加邮费退款总额和商品退款总额
            $totalPostage     = bcadd($totalPostage, $postagePrice, 2);
            $totalRefundPrice = bcadd($totalRefundPrice, $refundPrice, 2);
        }

        // 返回邮费退款总额和商品退款总额的总和
        return bcadd($totalPostage, $totalRefundPrice, 2);
    }

    /**
     * 计算订单中商品的退款总金额和退款邮费。
     * 此函数用于处理用户发起的退款请求，根据订单和商品信息，计算出应退给用户的总金额和邮费。
     *
     * @param object $order 订单对象，包含订单的相关信息。
     * @param collection $products 商品集合，包含用户申请退款的商品详情。
     *
     * @return array 返回一个包含总退款金额和退款邮费的数组。
     * @throws ValidateException 如果商品不支持退款，则抛出验证异常。
     */
    public function getRefundTotalPrice($order, $products)
    {
        // 获取所有申请退款商品的退款价格信息
        $productRefundPrices = app()->make(StoreRefundProductRepository::class)->userRefundPrice($products->column('order_product_id'));

        // 取出第一个商品用于后续判断和计算
        $product = $products[0];

        // 如果商品不支持退款，则抛出异常
        if (!$product['refund_switch']) throw new ValidateException('商品不支持退款');

        // 获取当前商品的具体退款价格信息
        $productRefundPrice = $productRefundPrices[$product['order_product_id']] ?? [];

        // 计算商品的总退款金额，减去已退的金额和邮费
        $total_refund_price = bcsub($product['product_price'], bcsub($productRefundPrice['refund_price'] ?? 0, $productRefundPrice['refund_postage'] ?? 0, 2), 2);

        // 计算退款邮费，订单状态为未发货或已取消时才计算邮费退款
        $postage_price = (!$order->status || $order->status == 9) ? bcsub($product['postage_price'], $productRefundPrice['refund_postage'] ?? 0, 2) : 0;

        // 返回总退款金额和退款邮费
        return compact('total_refund_price', 'postage_price');
    }

    /**
     * 处理退款后的操作
     * 此函数在退款申请后执行，用于记录退款状态变更、发送通知，并根据不同的用户类型执行相应的操作。
     * @param object $refund 退款信息对象，包含退款订单ID等信息
     * @param object $order 原订单信息对象，包含订单ID等信息
     */
    public function applyRefundAfter($refund, $order)
    {
        // 通过退款订单ID获取退款详情
        $refund = $this->dao->get($refund->refund_order_id);
        // 触发退款创建事件，携带退款和订单信息
        event('refund.create', compact('refund', 'order'));
        // 取消同城配送的订单记录
        if($order->order_type == 2 && isset($order->deliveryOrder) && $order->deliveryOrder->status != -1) {
            $deliveryOrderId = $order->deliveryOrder->delivery_order_id;
            $reason = $refund->mark ?? '';
            app()->make(DeliveryOrderRepository::class)->cancel($deliveryOrderId, $order->mer_id, $reason);
        }

        // 实例化订单状态仓库
        $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);

        // 构建退款状态日志数组
        $refundStatus = [
            'order_id'       => $refund->refund_order_id,
            'order_sn'       => $refund->refund_order_sn,
            'type'           => $storeOrderStatusRepository::TYPE_REFUND,
            'change_message' => '创建退款单',
            'change_type'    => $storeOrderStatusRepository::ORDER_STATUS_CREATE,
        ];

        // 构建订单状态日志数组
        $orderStatus = [
            'order_id'       => $order->order_id,
            'order_sn'       => $order->order_sn,
            'type'           => $storeOrderStatusRepository::TYPE_ORDER,
            'change_message' => '申请退款',
            'change_type'    => $storeOrderStatusRepository::CHANGE_REFUND_CREATGE,
        ];

        // 根据用户类型执行不同的操作
        switch ($refund['user_type']) {
            case 1:
                // 用户类型为1时，记录用户日志，并推送退款创建短信通知
                $storeOrderStatusRepository->createUserLog($order->uid, $refundStatus);
                $storeOrderStatusRepository->createUserLog($order->uid,$orderStatus);
                Queue::push(SendSmsJob::class, ['tempId' => 'ADMIN_RETURN_GOODS_CODE', 'id' => $refund->refund_order_id]);
                break;
            case 3:
                // 用户类型为3时，记录管理员日志
                $refundStatus['change_message'] = '商户创建退款';
                $orderStatus['change_message']  = '商户发起退款';
                $storeOrderStatusRepository->createAdminLog($refundStatus);
                $storeOrderStatusRepository->createAdminLog($orderStatus);
                break;
            case 4:
                // 用户类型为4时，记录客服日志
                $refundStatus['change_message'] = '客服创建退款';
                $orderStatus['change_message']  = '客服发起退款';
                $storeOrderStatusRepository->createServiceLog($refund->admin_id, $refundStatus);
                $storeOrderStatusRepository->createServiceLog($refund->admin_id, $orderStatus);
                break;
        }

        if ($order->is_stock_up === 1)
            app()->make(StoreOrderRepository::class)->cancelShipment($order, $order->mer_id,'订单申请退款');

        // 发送商户通知，告知有新的退款单
        SwooleTaskService::merchant('notice', [
            'type' => 'new_refund_order',
            'data' => [
                'title'   => '新退款单',
                'message' => '您有一个新的退款单',
                'id'      => $refund->refund_order_id
            ]
        ], $order->mer_id);
        // 售后自动审核
        $refundAutoApprove = merchantConfig($order['mer_id'], 'refund_auto_approve');
        if ($refundAutoApprove && $order['status'] == 0) {
            $this->agree($refund->refund_order_id);
        }
    }

    /**
     *  根据商品ID和退款数量计算可退金额
     * @param StoreOrder $order
     * @param array $refund
     * @return int|string
     * @author Qinii
     * @day 2023/8/7
     */
    public function compute(StoreOrder $order, array $refund)
    {
        $productIds = array_keys($refund);
        $orderId    = $order->order_id;
        $uid        = $order->uid;
        if (empty($productIds)) {
            throw new ValidateException('请选择正确的退款商品');
        }
        $products = app()->make(StoreOrderProductRepository::class)->userRefundProducts($productIds, $uid, $orderId, 0);
        if (empty($products->toArray())) throw new ValidateException('请选择正确的退款商品');

        $productRefundPriceData = app()->make(StoreRefundProductRepository::class)->userRefundPrice($productIds);
        $data['refund_price']   = 0;
        $totalRefundPrice       = 0;
        foreach ($products as $product) {
            $num       = $refund[$product['order_product_id']];
            $productId = $product['order_product_id'];
            if ($product['refund_num'] < $num) throw new ValidateException('可退款商品不足' . floatval($num) . '件');
            $productRefundPrice = $productRefundPriceData[$productId] ?? [];
            [$refundOrder, $refundProduct, $total] = $this->getRefundData($order, $product, $data, $num, $productRefundPrice);
            $totalRefundPrice = bcadd($totalRefundPrice, $total, TOP_PRECISION);
        }
        $isDisplayPostage = false;
        if($order->order_type == 2) {
            // 已经申请退款的总数量
            $refundTotalNum = 0;
            $refundInfo = $this->getSearch(['order_id' => $order->order_id])->whereIn('status',[0,1,2,3])->with('refundProduct')->select()->toArray();
            foreach($refundInfo as $item) {
                $refundTotalNum += array_sum(array_column($item['refundProduct'],'refund_num'));
            }
            // 订单总数量
            $orderGroupTotalNum = app()->make(StoreGroupOrderRepository::class)->getSearch(['group_order_id' => $order->group_order_id])->sum('total_num');
            // 如果申请退款的总数量加上当前要退款的数量等于订单分组总数量，说明是全退，同城配送全退才包含运费
            if(bcadd($refundTotalNum, array_sum(array_values($refund))) == $orderGroupTotalNum) {
                $totalRefundPrice += $order->total_postage;
                $isDisplayPostage = true;
            }
        }
        return [$totalRefundPrice, $isDisplayPostage];
    }

    /**
     *  商户发起退款操作
     * @param StoreOrder $order
     * @param array $refund
     * @param array $data
     * @param int $isCreate
     * @param int $userType
     * @return \think\response\Json|void
     * @author Qinii
     * @day 2023/7/11
     */
    public function merRefund(StoreOrder $order, array $refund, array $data)
    {
        $productIds = array_keys($refund);
        $nums       = array_values($refund);
        $orderId    = $order->order_id;
        $uid        = $order->uid;

        if (count($productIds) == 1) {
            $res = $this->refund($order, $productIds[0], $nums[0], $uid, $data, 0);
        } else {
            $products = app()->make(StoreOrderProductRepository::class)->userRefundProducts($productIds, $uid, $orderId, 0);
            if (empty($products->toArray())) throw new ValidateException('请选择正确的退款商品');
            $refund_status = true;
            foreach ($products as $product) {
                if ($product['refund_num'] !== $refund[$product['order_product_id']]) {
                    $refund_status = false;
                    break;
                }
            }
            if ($refund_status) {
                $res = $this->refunds($order, $productIds, $uid, $data, 1, $nums);
            } else {
                $res = $this->refundEach($order, $refund, $data, $products);
            }
        }
        $refundAutoApprove = merchantConfig($order['mer_id'], 'refund_auto_approve');
        if ($res && !($refundAutoApprove && $order['status'] == 0)) {
            $this->agree($res->refund_order_id, ['status' => 1]);
        }

        return $res;
    }

    /**
     *  多个商品 指定数量申请退款操作
     * @param StoreOrder $order
     * @param array $refund
     * @param array $data
     * @param $products
     * @param int $isCreate
     * @param int $userType
     * @return mixed
     * @author Qinii
     * @day 2023/7/13
     */
    public function refundEach(StoreOrder $order, array $refund, array $data, $products)
    {
        $productIds              = array_keys($refund);
        $nums                    = array_values($refund);
        $orderId                 = $order->order_id;
        $uid                     = $order->uid;
        $productRefundPriceData  = app()->make(StoreRefundProductRepository::class)->userRefundPrice($productIds);
        $data['order_id']        = $orderId;
        $data['uid']             = $uid;
        $data['mer_id']          = $order->mer_id;
        $data['refund_order_sn'] = app()->make(StoreOrderRepository::class)->getNewOrderId(StoreOrderRepository::TYPE_SN_REFUND);
        $refund_order_id         = 0;
        $data['refund_num'] = 0;
        foreach ($products as $product) {
            $num       = $refund[$product['order_product_id']];
            $productId = $product['order_product_id'];
            if ($product['refund_num'] < $num) throw new ValidateException('可退款商品不足' . floatval($num) . '件');
            $productRefundPrice = $productRefundPriceData[$productId] ?? [];
            [$refundOrder, $refundProduct, $total] = $this->getRefundData($order, $product, $data, $num, $productRefundPrice);
            $data['refund_postage']           = bcadd($data['refund_postage'] ?? 0, $refundOrder['refund_postage'], TOP_PRECISION);
            $data['platform_refund_price']    = bcadd($data['platform_refund_price'] ?? 0, $refundOrder['platform_refund_price'], TOP_PRECISION);
            $data['integral']                 = $data['integral'] ?? 0 + $refundOrder['integral'];
            $data['refund_num']               += $refundOrder['refund_num'];
            $data['extension_one']            = bcadd($data['extension_one'] ?? 0, $refundOrder['extension_one'], TOP_PRECISION);
            $data['extension_two']            = bcadd($data['extension_two'] ?? 0, $refundOrder['extension_two'], TOP_PRECISION);
            $totalRefundPrice                 = bcadd($totalRefundPrice ?? 0, $total, TOP_PRECISION);
            $refundProduct['refund_order_id'] = &$refund_order_id;
            $refundProducts[]                 = $refundProduct;
        }

        if($order->order_type == 2) {
            $data['refund_postage'] = 0;
            // 已经申请退款的总数量
            $refundTotalNum = 0;
            $refundInfo = $this->getSearch(['order_id' => $order->order_id])->whereIn('status',[0,1,2,3])->with('refundProduct')->select()->toArray();
            foreach($refundInfo as $item) {
                $refundTotalNum += array_sum(array_column($item['refundProduct'],'refund_num'));
            }
            // 订单总数量
            $orderGroupTotalNum = app()->make(StoreGroupOrderRepository::class)->getSearch(['group_order_id' => $order->group_order_id])->sum('total_num');
            // 如果申请退款的总数量加上当前要退款的数量等于订单分组总数量，说明是全退，同城配送全退才包含运费
            if(bcadd($refundTotalNum, array_sum($nums)) == $orderGroupTotalNum) {
                $data['refund_postage'] = $order->total_postage;
            }
        }

        if ($totalRefundPrice < $data['refund_price'])
            throw new ValidateException('最高可退款' . floatval($totalRefundPrice) . '元');
        $storeRefundProductRepository = app()->make(StoreRefundProductRepository::class);
        return Db::transaction(function () use ($order, $data, $products, $refundProducts, &$refund_order_id, $storeRefundProductRepository, $refund) {
            event('refund.create.before', compact('data'));
            $refundCreate    = $this->dao->create($data);
            $refund_order_id = $refundCreate->refund_order_id;
            $storeRefundProductRepository->insertAll($refundProducts);
            // 是否退款 0:未退款 1:退款中 2:部分退款 3=全退
            foreach ($products as $product) {
                $num                 = $refund[$product['order_product_id']];
                $product->refund_num -= $num;
                $product->is_refund  = 1;
                $product->save();
            }
            $this->applyRefundAfter($refundCreate, $order);
            return $refundCreate;
        });
    }

    /**
     *  订单商品循环处理获取可退款金额
     * @param $order
     * @param $product
     * @param $data
     * @param $num
     * @param $productRefundPrice
     * @return array
     * @author Qinii
     * @day 2023/7/13
     */
    public function getRefundData($order, $product, $data, $num, $productRefundPrice)
    {
        //计算可退运费
        $postagePrice = (!$order->status || $order->status == 9) ? bcsub($product['postage_price'], $productRefundPrice['refund_postage'] ?? 0, 2) : 0;
        $refundPrice  = 0;
        //计算可退金额
        if ($product['product_price'] > 0) {
            if ($product['refund_num'] == $num) {
                $refundPrice = bcsub($product['product_price'], bcsub($productRefundPrice['refund_price'] ?? 0, $productRefundPrice['refund_postage'] ?? 0, 2), 2);
            } else {
                $refundPrice = bcmul(bcdiv($product['product_price'], $product['product_num'], 2), $num, 2);
            }
        }
        $totalRefundPrice = bcadd($refundPrice, $postagePrice, 2);
//        if ($totalRefundPrice < $data['refund_price'])
//            throw new ValidateException('最高可退款' . floatval($totalRefundPrice) . '元');

        $data['refund_postage'] = 0;

        if($order->order_type !== 2 && $data['refund_price'] > $refundPrice) {
            $data['refund_postage'] = bcsub($data['refund_price'], $refundPrice, 2);
        }

        $data['order_id'] = $product['order_id'];

        $platform_refund_price = 0;
        //计算退的平台优惠券金额
        if ($product['platform_coupon_price'] > 0) {
            if ($product['refund_num'] == $num) {
                $platform_refund_price = bcsub($product['platform_coupon_price'], $productRefundPrice['platform_refund_price'] ?? 0, 2);
            } else {
                $platform_refund_price = bcmul(bcdiv($product['platform_coupon_price'], $product['product_num'], 2), $num, 2);
            }
        }

        $data['platform_refund_price'] = $platform_refund_price;

        $integral = 0;
        if ($product['integral'] > 0) {
            if ($product['refund_num'] == $num) {
                $integral = bcsub($product['integral_total'], $productRefundPrice['refund_integral'] ?? 0, 0);
            } else {
                $integral = bcmul($product['integral'], $num, 0);
            }
        }

        $data['integral'] = $integral;

        $total_extension_one = 0;
        $total_extension_two = 0;
        if ($product['extension_one'] > 0)
            $total_extension_one = bcmul($num, $product['extension_one'], 2);
        if ($product['extension_two'] > 0)
            $total_extension_two = bcmul($num, $product['extension_two'], 2);

        $data['uid']             = $product['uid'];
        $data['mer_id']          = $order['mer_id'];
        $data['refund_order_sn'] = app()->make(StoreOrderRepository::class)->getNewOrderId(StoreOrderRepository::TYPE_SN_REFUND);
        $data['refund_num']      = $num;
        $data['extension_one']   = $total_extension_one;
        $data['extension_two']   = $total_extension_two;
        $refundProduct           = [
            'refund_num'            => $num,
            'order_product_id'      => $product['order_product_id'],
            'platform_refund_price' => $data['platform_refund_price'],
            'refund_price'          => bcmul(bcdiv($product['product_price'], $product['product_num'], 2), $num, 2),
            'refund_integral'       => $data['integral'],
            'refund_postage'        => $data['refund_postage'],
        ];
        return [$data, $refundProduct, $totalRefundPrice];
    }

    /**
     *  订单多个商品，全部退款操作
     * @param StoreOrder $order
     * @param array $ids
     * @param $uid
     * @param array $data
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/17
     */
    public function refunds(StoreOrder $order, array $ids, $uid, array $data, $refund_switch = 1, $nums = [])
    {
        $orderId  = $order->order_id;
        $products = app()->make(StoreOrderProductRepository::class)->userRefundProducts($ids, $uid, $orderId, $refund_switch);
        if (!$products || count($ids) != count($products))
            throw new ValidateException('请选择正确的退款商品');
        $productRefundPrices      = app()->make(StoreRefundProductRepository::class)->userRefundPrice($ids);
        $totalRefundPrice         = 0;
        $totalRefundNum           = 0;
        $total_extension_one      = 0;
        $total_extension_two      = 0;
        $totalIntegral            = 0;
        $totalPlatformRefundPrice = 0;
        $totalPostage             = 0;
        $refundProduct            = [];
        $refund_order_id          = 0;
        foreach ($products as $product) {
            $productRefundPrice = $productRefundPrices[$product['order_product_id']] ?? [];
            if ($product['extension_one'] > 0)
                $total_extension_one = bcadd($total_extension_one, bcmul($product['refund_num'], $product['extension_one'], 2), 2);
            if ($product['extension_two'] > 0)
                $total_extension_two = bcadd($total_extension_two, bcmul($product['refund_num'], $product['extension_two'], 2), 2);
            $postagePrice   = (!$order->status || $order->status == 9) ? bcsub($product['postage_price'], $productRefundPrice['refund_postage'] ?? 0, 2) : 0;
            $totalRefundNum += $product['refund_num'];
            $refundPrice    = 0;
            //计算可退金额
            if ($product['product_price'] > 0) {
                $refundPrice = bcsub($product['product_price'], bcsub($productRefundPrice['refund_price'] ?? 0, $productRefundPrice['refund_postage'] ?? 0, 2), 2);
            }
            $platform_refund_price = 0;
            //计算退的平台优惠券金额
            if ($product['platform_coupon_price'] > 0) {
                $platform_refund_price = bcsub($product['platform_coupon_price'], $productRefundPrice['platform_refund_price'] ?? 0, 2);
            }
            $integral = 0;
            if ($product['integral'] > 0) {
                $integral = bcsub($product['integral_total'], $productRefundPrice['refund_integral'] ?? 0, 0);
            }

            $totalPostage             = bcadd($totalPostage, $postagePrice, 2);
            $totalRefundPrice         = bcadd($totalRefundPrice, $refundPrice, 2);
            $totalPlatformRefundPrice = bcadd($totalPlatformRefundPrice, $platform_refund_price, 2);
            $totalIntegral            = bcadd($totalIntegral, $integral, 2);

            $refundProduct[] = [
                'refund_order_id'       => &$refund_order_id,
                'refund_num'            => $product['refund_num'],
                'order_product_id'      => $product['order_product_id'],
                'platform_refund_price' => $platform_refund_price,
                'refund_integral'       => $integral,
                'refund_price'          => $refundPrice,
                'refund_postage'        => $postagePrice,
            ];
        }
        if($order->order_type == 2) {
            // 已经申请退款的总数量
            $refundTotalNum = 0;
            $refundInfo = $this->getSearch(['order_id' => $order->order_id])->whereIn('status',[0,1,2,3])->with('refundProduct')->select()->toArray();
            foreach($refundInfo as $item) {
                $refundTotalNum += array_sum(array_column($item['refundProduct'],'refund_num'));
            }
            // 订单总数量
            $orderGroupTotalNum = app()->make(StoreGroupOrderRepository::class)->getSearch(['group_order_id' => $order->group_order_id])->sum('total_num');
            // 如果申请退款的总数量加上当前要退款的数量等于订单分组总数量，说明是全退，同城配送全退才包含运费
            if(bcadd($refundTotalNum, array_sum($nums)) == $orderGroupTotalNum) {
                $totalPostage = $order->total_postage;
            }
        }
        $data['order_id']              = $products[0]['order_id'];
        $data['uid']                   = $products[0]['uid'];
        $data['mer_id']                = $order['mer_id'];
        $data['refund_order_sn']       = app()->make(StoreOrderRepository::class)->getNewOrderId(StoreOrderRepository::TYPE_SN_REFUND);
        $data['refund_num']            = $totalRefundNum;
        $data['extension_one']         = $total_extension_one;
        $data['extension_two']         = $total_extension_two;
        $data['refund_price']          = bcadd($totalPostage, $totalRefundPrice, 2);
        $data['integral']              = $totalIntegral;
        $data['platform_refund_price'] = $totalPlatformRefundPrice;
        $data['refund_postage']        = $totalPostage;

        return Db::transaction(function () use ($refundProduct, $data, $products, $order, &$refund_order_id) {
            event('refund.creates.before', compact('data'));
            $refund          = $this->dao->create($data);
            $refund_order_id = $refund->refund_order_id;
            foreach ($products as $product) {
                $product->refund_num = 0;
                $product->is_refund  = 1;
                $product->save();
            }
            app()->make(StoreRefundProductRepository::class)->insertAll($refundProduct);
            $this->applyRefundAfter($refund, $order);
            return $refund;
        });
    }

    /**
     *  单个商品指定数量退款操作
     * @param StoreOrder $order
     * @param $productId
     * @param $num
     * @param $uid
     * @param array $data
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/17
     */
    public function refund(StoreOrder $order, $productId, $num, $uid, array $data, $refund_switch = 1)
    {
        $orderId = $order->order_id;
        // 订单状态生成佣金
        $products = app()->make(StoreOrderProductRepository::class)->userRefundProducts([$productId], $uid, $orderId, $refund_switch);
        if (empty($products->toArray()))
            throw new ValidateException('请选择正确的退款商品');
        $product = $products[0];
        if ($product['refund_num'] < $num)
            throw new ValidateException('可退款商品不足' . floatval($num) . '件');
        $productRefundPrice = app()->make(StoreRefundProductRepository::class)->userRefundPrice([$productId])[$productId] ?? [];

        //计算可退运费
        $postagePrice = (!$order->status || $order->status == 9) ? bcsub($product['postage_price'], $productRefundPrice['refund_postage'] ?? 0, 2) : 0;
        if($order->order_type == 2) {
            // 已经申请退款的总数量
            $refundTotalNum = 0;
            $refundInfo = $this->getSearch(['order_id' => $order->order_id])->whereIn('status',[0,1,2,3])->with('refundProduct')->select()->toArray();
            foreach($refundInfo as $item) {
                $refundTotalNum += array_sum(array_column($item['refundProduct'],'refund_num'));
            }
            // 订单总数量
            $orderGroupTotalNum = app()->make(StoreGroupOrderRepository::class)->getSearch(['group_order_id' => $order->group_order_id])->sum('total_num');
            // 如果申请退款的总数量加上当前要退款的数量等于订单分组总数量，说明是全退，同城配送全退才包含运费
            if(bcadd($refundTotalNum, $num) == $orderGroupTotalNum) {
                $postagePrice = $order->total_postage;
            }
        }

        $refundPrice = 0;
        //计算可退金额
        if ($product['product_price'] > 0) {
            if ($product['refund_num'] == $num) {
                $refundPrice = bcsub($product['product_price'], bcsub($productRefundPrice['refund_price'] ?? 0, $productRefundPrice['refund_postage'] ?? 0, 2), 2);
            } else {
                $refundPrice = bcmul(bcdiv($product['product_price'], $product['product_num'], 2), $num, 2);
            }
        }
        $totalRefundPrice = bcadd($refundPrice, $postagePrice, 2);
        if ($totalRefundPrice < $data['refund_price'])
            throw new ValidateException('最高可退款' . floatval($totalRefundPrice) . '元');

        $data['refund_postage'] = 0;

        if ($data['refund_price'] > $refundPrice) {
            $data['refund_postage'] = bcsub($data['refund_price'], $refundPrice, 2);
        }

        $data['order_id'] = $product['order_id'];

        $platform_refund_price = 0;
        //计算退的平台优惠券金额
        if ($product['platform_coupon_price'] > 0) {
            if ($product['refund_num'] == $num) {
                $platform_refund_price = bcsub($product['platform_coupon_price'], $productRefundPrice['platform_refund_price'] ?? 0, 2);
            } else {
                $platform_refund_price = bcmul(bcdiv($product['platform_coupon_price'], $product['product_num'], 2), $num, 2);
            }
        }

        $data['platform_refund_price'] = $platform_refund_price;

        $integral = 0;
        if ($product['integral'] > 0) {
            if ($product['refund_num'] == $num) {
                $integral = bcsub($product['integral_total'], $productRefundPrice['refund_integral'] ?? 0, 0);
            } else {
                $integral = bcmul($product['integral'], $num, 0);
            }
        }

        $data['integral'] = $integral;

        $total_extension_one = 0;
        $total_extension_two = 0;
        if ($product['extension_one'] > 0)
            $total_extension_one = bcmul($num, $product['extension_one'], 2);
        if ($product['extension_two'] > 0)
            $total_extension_two = bcmul($num, $product['extension_two'], 2);

        $data['uid']             = $product['uid'];
        $data['mer_id']          = $order['mer_id'];
        $data['refund_order_sn'] = app()->make(StoreOrderRepository::class)->getNewOrderId(StoreOrderRepository::TYPE_SN_REFUND);
        $data['refund_num']      = $num;
        $data['extension_one']   = $total_extension_one;
        $data['extension_two']   = $total_extension_two;

        return Db::transaction(function () use ($order, $data, $product, $productId, $num) {
            event('refund.create.before', compact('data'));
            $refund = $this->dao->create($data);
            app()->make(StoreRefundProductRepository::class)->create([
                'refund_num'            => $num,
                'refund_order_id'       => $refund->refund_order_id,
                'order_product_id'      => $productId,
                'platform_refund_price' => $data['platform_refund_price'],
                'refund_price'          => $data['refund_price'],
                'refund_integral'       => $data['integral'],
                'refund_postage'        => $data['refund_postage'],
            ]);
            $product->refund_num -= $num;
            $product->is_refund  = 1;
            $product->save();
            $this->applyRefundAfter($refund, $order);
            return $refund;
        });
    }

    /**
     * 获取列表数据
     *
     * 根据给定的条件、分页和限制获取列表数据，同时包含相关联的数据。
     * 这里使用了懒加载模式，只在需要时加载关联数据，以提高查询效率。
     *
     * @param array $where 查询条件
     * @param int $page 当前页码
     * @param int $limit 每页数据数量
     * @return array 返回包含总数、数据列表和状态统计的信息
     */
    public function getList(array $where, int $page, int $limit)
    {
        // 构建查询条件，排除已删除的数据和状态为-2的数据，并按创建时间降序排序
        $query = $this->dao->search($where)
            ->where('is_system_del', 0)
            //->where('StoreRefundOrder.status', '<>', -2)
            ->with([
                // 关联订单，只获取指定字段
                'order' => function ($query) {
                    $query->field('order_id,order_sn,activity_type,real_name,user_address,status,order_type,is_del');
                },
                // 关联退款产品和产品的关联信息
                'refundProduct.product',
                // 关联用户信息，只获取指定字段
                'user'  => function ($query) {
                    $query->field('uid,nickname,phone');
                }])
            ->order('create_time DESC');

        // 计算总数据量
        $count = $query->count();

        // 进行分页查询，并追加创建用户的信息
        $list = $query->page($page, $limit)->select()->append(['create_user']);

        // 统计不同状态的数据数量
        $stat = [
            'count'    => $this->dao->search(array_merge($where, ['is_system_del' => 0, 'mer_id' => $where['mer_id']]))->count() ?: 0,
            'audit'    => $this->dao->search(array_merge($where, ['is_system_del' => 0, 'mer_id' => $where['mer_id'], 'status' => 0]))->count() ?: 0,
            'refuse'   => $this->dao->search(array_merge($where, ['is_system_del' => 0, 'mer_id' => $where['mer_id'], 'status' => -1]))->count() ?: 0,
            'agree'    => $this->dao->search(array_merge($where, ['is_system_del' => 0, 'mer_id' => $where['mer_id'], 'status' => 1]))->count() ?: 0,
            'backgood' => $this->dao->search(array_merge($where, ['is_system_del' => 0, 'mer_id' => $where['mer_id'], 'status' => 2]))->count() ?: 0,
            'end'      => $this->dao->search(array_merge($where, ['is_system_del' => 0, 'mer_id' => $where['mer_id'], 'status' => 3]))->count() ?: 0,
            'platform' => $this->dao->search(array_merge($where, ['is_system_del' => 0, 'mer_id' => $where['mer_id'], 'status' => 4]))->count() ?: 0,
            'cancelled'=> $this->dao->search(array_merge($where, ['is_system_del' => 0, 'mer_id' => $where['mer_id'], 'status' => -2]))->count() ?: 0,
        ];

        // 返回总数、数据列表和状态统计
        return compact('count', 'list', 'stat');
    }

    /**
     * 根据条件获取服务列表
     *
     * 本函数用于根据提供的条件数组($where)、页码($page)和每页数量($limit)来查询特定服务的数据列表。
     * 它首先构造一个查询语句，包括条件查询、关联查询和排序方式。
     * 然后，它计算满足条件的数据总数($count)，并根据页码和每页数量获取相应的数据列表($list)。
     * 最后，它将总数和列表一起以数组形式返回。
     *
     * @param array $where 查询条件数组
     * @param int $page 查询的页码
     * @param int $limit 每页的数据数量
     * @return array 返回包含总数和数据列表的数组
     */
    public function getListByService(array $where, int $page, int $limit)
    {
        // 构造查询语句，包括条件查询、关联查询和排序
        $query = $this->dao->search($where)->where('is_system_del', 0)
            ->with([
                'order' => function ($query) {
                    // 关联查询订单信息，只获取指定字段
                    $query->field('order_id,order_sn,activity_type,real_name,user_address');
                },
                'refundProduct.product', // 关联查询退款产品信息
            ])
            ->order('status ASC'); // 按照创建时间降序和状态升序排序

        // 计算满足条件的数据总数
        $count = $query->count();

        // 根据页码和每页数量获取数据列表
        $list = $query->page($page, $limit)->select();

        // 返回总数和数据列表的数组
        return compact('count', 'list');
    }


    /**
     * 获取退款订单列表
     *
     * 根据给定的条件、分页和限制获取退款订单列表，并统计各种状态的退款订单数量。
     * 主要用于后台管理界面的分页显示和状态统计。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页显示的数量
     * @return array 返回包含退款订单总数、列表和状态统计的数据数组
     */
    public function getAdminList(array $where, int $page, int $limit)
    {
        // 构建查询条件，搜索退款订单，并排除状态为-2的退款订单
        // 同时加载关联的订单、退款产品、用户信息，只选择特定的字段以提高查询效率
        $query = $this->dao->search($where)
            //->where('status', '<>', -2)
            ->with([
                'order' => function ($query) {
                    $query->field('order_id,order_sn,activity_type');
                },
                'refundProduct.product',
                'user'  => function ($query) {
                    $query->field('uid,nickname,phone');
                },
            ]);

        // 计算满足条件的退款订单总数
        $count = $query->count();

        // 根据当前页码和每页限制的数量，获取退款订单列表
        $list = $query->page($page, $limit)->select();

        // 统计不同状态的退款订单数量
        $stat = [
            'count'    => $this->dao->search(array_merge($where, ['is_system_del' => 0, 'mer_id' => $where['mer_id']]))->count(),
            'audit'    => $this->dao->search(array_merge($where, ['is_system_del' => 0, 'mer_id' => $where['mer_id'], 'status' => 0]))->count(),
            'refuse'   => $this->dao->search(array_merge($where, ['is_system_del' => 0, 'mer_id' => $where['mer_id'], 'status' => -1]))->count(),
            'agree'    => $this->dao->search(array_merge($where, ['is_system_del' => 0, 'mer_id' => $where['mer_id'], 'status' => 1]))->count(),
            'backgood' => $this->dao->search(array_merge($where, ['is_system_del' => 0, 'mer_id' => $where['mer_id'], 'status' => 2]))->count(),
            'end'      => $this->dao->search(array_merge($where, ['is_system_del' => 0, 'mer_id' => $where['mer_id'], 'status' => 3]))->count(),
            'platform' => $this->dao->search(array_merge($where, ['is_system_del' => 0, 'mer_id' => $where['mer_id'], 'status' => 4]))->count(),
        ];

        // 返回退款订单总数、列表和状态统计的数组
        return compact('count', 'list', 'stat');
    }


    /**
     *  总后台所有订单
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     * @day 2020-06-25
     */
    public function getAllList(array $where, int $page, int $limit)
    {
        $query = $this->dao->search($where)->with(['order' => function ($query) {
            $query->field('order_id,order_sn,activity_type');
        }, 'merchant'                                      => function ($query) {
            $query->field('mer_id,mer_name,is_trader');
        }, 'refundProduct.product', 'user'                 => function ($query) {
            $query->field('uid,nickname,phone');
        }]);
        $count = $query->count();
        $list  = $query->page($page, $limit)->select();
        $stat  = [
            'all'      => $this->dao->search($where)->count(),
            'audit'    => $this->dao->search(array_merge($where, ['status' => 0]))->count(),
            'refuse'   => $this->dao->search(array_merge($where, ['status' => -1]))->count(),
            'agree'    => $this->dao->search(array_merge($where, ['status' => 1]))->count(),
            'backgood' => $this->dao->search(array_merge($where, ['status' => 2]))->count(),
            'end'      => $this->dao->search(array_merge($where, ['status' => 3]))->count(),
            'platform' => $this->dao->search(array_merge($where, ['status' => 4]))->count(),
        ];
        return compact('count', 'list', 'stat');
    }

    /**
     * 根据条件获取商户结算单列表
     *
     * 本函数用于根据给定的条件和分页信息，从数据库中检索商户的结算单列表。
     * 它首先根据条件获取结算单ID列表，然后基于这些ID查询相关的结算单详情，包括订单、退款产品和用户信息。
     * 最后，它计算记录总数，并根据分页参数返回指定页码的记录列表。
     *
     * @param array $where 查询条件
     * @param int $page 当前页码
     * @param int $limit 每页记录数
     * @return array 包含总数和列表的数据数组
     */
    public function reconList($where, $page, $limit)
    {
        // 根据条件获取商户结算单的ID列表
        $ids = app()->make(MerchantReconciliationOrderRepository::class)->getIds($where);

        // 构建查询，查询符合条件的结算单，包括关联的订单、退款产品和用户信息
        $query = $this->dao->search([])->whereIn('refund_order_id', $ids)->with([
            'order' => function ($query) {
                // 限制订单信息的查询字段
                $query->field('order_id,order_sn,activity_type');
            },
            'refundProduct.product', // 包含退款产品的详细信息
            'user'  => function ($query) {
                // 限制用户信息的查询字段
                $query->field('uid,nickname,phone');
            }
        ]);

        // 计算符合条件的记录总数
        $count = $query->count();

        // 根据当前页码和每页记录数，获取结算单列表
        $list = $query->page($page, $limit)->select();

        // 返回包含记录总数和列表的数据数组
        return compact('count', 'list');
    }

    /**
     * 检查退款订单状态是否存在
     *
     * 本函数用于查询特定商家（merId）下的特定退款订单（id）是否处于未处理状态（status为0）。
     * 主要用于退款订单处理逻辑中，确定是否需要对订单进行进一步的操作。
     *
     * @param int $merId 商家ID，用于限定查询的商家范围。
     * @param int $id 退款订单ID，用于指定查询的具体退款订单。
     * @return bool 返回true表示该商家的该退款订单存在且状态为未处理，返回false表示不存在或已处理。
     */
    public function getStatusExists(int $merId, int $id)
    {
        // 定义查询条件，包括商家ID、退款订单ID和订单状态
        $where = [
            'mer_id'          => $merId,
            'refund_order_id' => $id,
            'status'          => 0,
        ];

        // 调用DAO层方法，根据查询条件检查订单状态是否存在
        return $this->dao->getFieldExists($where);
    }

    /**
     *  是否为收到退货状态
     * @param int $merId
     * @param int $id
     * @return bool
     * @author Qinii
     * @day 2020-06-13
     */
    public function getRefundPriceExists(int $merId, int $id)
    {
        $where = [
            'mer_id'          => $merId,
            'refund_order_id' => $id,
            'status'          => 2,
        ];
        return $this->dao->getFieldExists($where);
    }

    /**
     * 检查是否已删除特定的用户退款订单
     *
     * 此方法用于查询是否存在特定条件的用户退款订单已被删除。
     * 它通过比较商家ID（merId）和退款订单ID（id）来确定是否存在满足条件的已删除订单。
     *
     * @param int $merId 商家ID，用于指定特定商家的数据。
     * @param int $id 退款订单ID，用于指定特定的退款订单。
     * @return bool 返回一个布尔值，表示是否找到已删除的订单。如果找到，则返回true；否则返回false。
     */
    public function getUserDelExists(int $merId, int $id)
    {
        // 定义查询条件，包括商家ID、退款订单ID和删除状态（1表示已删除）
        $where = [
            'mer_id'          => $merId,
            'refund_order_id' => $id,
            'is_del'          => 1,
        ];

        // 调用dao层的方法，检查满足条件的记录是否存在，并返回检查结果
        return $this->dao->getFieldExists($where);
    }

    /**
     * 根据商家ID和退款订单ID检查退款订单是否存在。
     *
     * 此方法用于验证特定商家的特定退款订单是否存在于数据库中。
     * 它通过传递商家ID和退款订单ID来查询退款订单是否存在，
     * 这对于处理商家的退款请求和确保数据一致性非常重要。
     *
     * @param int $merId 商家ID，用于指定查询的商家。
     * @param int $id 退款订单ID，用于指定查询的退款订单。
     * @return bool 返回true表示退款订单存在，返回false表示不存在。
     */
    public function getExistsById(int $merId, int $id)
    {
        // 构建查询条件
        $where = [
            'mer_id'          => $merId,
            'refund_order_id' => $id,
        ];

        // 调用DAO层方法查询退款订单是否存在，并返回查询结果
        return $this->dao->getFieldExists($where);
    }

    /**
     * 创建标记表单
     *
     * 本函数用于生成一个用于标记操作的表单，例如在商家退款处理中进行备注标记。
     * 表单的提交目标是通过路由生成的URL，确保了表单提交的安全性和合规性。
     *
     * @param int $id 商家订单ID，用于获取当前订单的备注信息，并在表单中进行展示。
     * @return object 返回生成的表单对象，该对象包含了表单的HTML代码和相关属性，可以直接渲染到前端。
     */
    public function markForm($id)
    {
        // 通过ID获取订单信息，以便在表单中显示当前的备注信息。
        $data = $this->dao->get($id);

        // 创建表单对象，并设置表单的提交URL。
        $form = Elm::createForm(Route::buildUrl('merchantStoreRefundMark', ['id' => $id])->build());

        // 设置表单的验证规则，这里仅包含一个文本输入框用于填写备注信息。
        $form->setRule([
            // 文本输入框用于商家输入备注信息，预填充已有备注。
            Elm::text('mer_mark', '备注：', $data['mer_mark'])->placeholder('请输入备注')
        ]);

        // 设置表单的标题。
        return $form->setTitle('备注信息');
    }

    /**
     * 创建管理员标记表单
     *
     * 该方法用于生成一个表单，允许管理员对特定ID的记录进行备注。表单提交后，备注信息将被保存。
     *
     * @param int $id 记录的唯一标识符，用于获取当前记录的备注信息。
     * @return string 返回生成的表单HTML代码。
     */
    public function adminMarkForm($id)
    {
        // 通过ID获取记录的详细信息，包括现有的管理员备注。
        $data = $this->dao->get($id);

        // 创建表单实例，并设置表单的提交URL。
        $form = Elm::createForm(Route::buildUrl('systemMerchantRefundOrderMark', ['id' => $id])->build());

        // 设置表单的验证规则，这里仅包含一个文本输入框用于输入管理员备注。
        $form->setRule([
            // 文本输入框用于管理员输入备注信息，预填充现有的备注内容。
            Elm::text('admin_mark', '备注：', $data['admin_mark'])->placeholder('请输入备注')
        ]);

        // 设置表单的标题。
        return $form->setTitle('备注信息');
    }

    /**
     * 退款单已发货
     * @param $id
     * @return Form
     * @author Qinii
     * @day 2020-06-13
     */

    public function backGoods($uid, $id, $data)
    {
        $refund = $this->userDetail($id, $uid);
        if (!$refund)
            throw new ValidateException('退款单不存在');
        if ($refund->status != 1)
            throw new ValidateException('退款单状态有误');
        $refund->status      = 2;
        $refund->status_time = date('Y-m-d H:i:s');

        //退款订单记录
        $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
        $orderStatus                = [
            'order_id'       => $refund->refund_order_id,
            'order_sn'       => $refund->refund_order_sn,
            'type'           => $storeOrderStatusRepository::TYPE_REFUND,
            'change_message' => '退款单退回商品已发货',
            'change_type'    => $storeOrderStatusRepository::CHANGE_BACK_GOODS,
        ];
        Db::transaction(function () use ($refund, $data, $id, $uid, $storeOrderStatusRepository, $orderStatus) {
            $refund->save($data);
            $storeOrderStatusRepository->createUserLog($uid, $orderStatus);
            event('refund.backGoods', compact('uid', 'id', 'data'));
        });
        Queue::push(SendSmsJob::class, [
            'tempId' => 'ADMIN_DELIVERY_CODE',
            'id'     => $id
        ]);
    }

    /**
     * 根据退款订单ID生成退款审核表单
     * @param int $id 退款订单ID
     * @return \think\form\Form 表单对象
     */
    public function statusForm($id)
    {
        // 根据退款订单ID查询退款订单信息
        $res = $this->getWhere(['refund_order_id' => $id]);

        // 创建表单，表单提交地址为商户平台的退款订单状态切换接口URL
        $form = Elm::createForm(Route::buildUrl('merchantStoreRefundOrderSwitchStatus', ['id' => $id])->build());

        // 如果退款类型为1（退款申请），设置表单规则
        if ($res['refund_type'] == 1) {
            // 表单包含一个单选 radio 组件用于选择审核结果（同意或拒绝）
            // 当选择拒绝时，需要输入拒绝原因
            $form->setRule([
                Elm::radio('status', '审核：', -1)->setOptions([
                    ['value' => 1, 'label' => '同意'],
                    ['value' => -1, 'label' => '拒绝'],
                ])->control([
                    [
                        'value' => -1,
                        'rule'  => [
                            Elm::input('fail_message', '拒绝原因')->placeholder('请输入拒绝原因')->required('请输入拒绝原因'),
                        ]
                    ],
                ]),
            ]);
        }

        // 如果退款类型为2（换货申请），设置表单规则
        if ($res['refund_type'] == 2) {
            // 表单包含一个单选 radio 组件用于选择审核结果（同意或拒绝）
            // 当选择同意时，需要填写收货人信息；当选择拒绝时，需要输入拒绝原因
            $form->setRule([
                Elm::radio('status', '审核：', -1)->setOptions([
                    ['value' => 1, 'label' => '同意'],
                    ['value' => -1, 'label' => '拒绝'],
                ])->control([
                    [
                        'value' => 1,
                        'rule'  => [
                            Elm::input('mer_delivery_user', '收货人', merchantConfig($res['mer_id'], 'mer_refund_user'))->placeholder('请输入收货人')->required('请输入收货人'),
                            Elm::input('mer_delivery_address', '收货地址', merchantConfig($res['mer_id'], 'mer_refund_address'))->placeholder('请输入收货地址')->required('请输入收货地址'),
                            Elm::input('phone', '手机号', merchantConfig($res['mer_id'], 'set_phone'))->placeholder('请输入手机号')->required('请输入手机号'),
                        ]
                    ],
                    [
                        'value' => -1,
                        'rule'  => [
                            Elm::input('fail_message', '拒绝原因')->placeholder('请输入拒绝原因')->required('请输入拒绝原因'),
                        ]
                    ],
                ]),

            ]);
        }

        // 设置表单标题为“退款审核”
        $form->setTitle('退款审核');

        // 返回表单对象
        return $form;
    }

    /**
     * 拒绝退款
     * @param $id
     * @param $data
     * @author Qinii
     * @day 2020-06-13
     */
    public function refuse($id, $data, $service_id = 0, bool $isPlatform = false)
    {
        $refund = $this->getWhere(['refund_order_id' => $id], '*', ['refundProduct.product']);
        //退款订单记录
        $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
        $platformMsg                = isset($data['platform_mark']) && $data['platform_mark'] ? $data['platform_mark'] : '平台拒绝退款申请';
        unset($data['platform_mark']);
        $orderStatus       = [
            'order_id'       => $refund->order_id,
            'order_sn'       => $refund->order->order_sn,
            'type'           => $storeOrderStatusRepository::TYPE_ORDER,
            'change_message' => '订单退款已拒绝:' . $refund->refund_order_sn . ($isPlatform ? ',原因：' . $platformMsg : ''),
            'change_type'    => $storeOrderStatusRepository::CHANGE_REFUND_REFUSE,
        ];
        $refundOrderStatus = [
            'order_id'       => $refund->refund_order_id,
            'order_sn'       => $refund->refund_order_sn,
            'type'           => $storeOrderStatusRepository::TYPE_REFUND,
            'change_message' => $isPlatform ? $platformMsg : '订单退款已拒绝',
            'change_type'    => $isPlatform ? $storeOrderStatusRepository::CHANGE_REFUND_PLATFORM_REFUSE : $storeOrderStatusRepository::CHANGE_REFUND_REFUSE,
        ];

        Db::transaction(function () use ($id, $data, $refund, $service_id, $storeOrderStatusRepository, $orderStatus, $refundOrderStatus) {

            $data['status_time'] = date('Y-m-d H:i:s');
            $this->getProductRefundNumber($refund, -1);
            $this->dao->update($id, $data);

            if ($service_id) {
                $storeOrderStatusRepository->createServiceLog($service_id, $orderStatus);
                $storeOrderStatusRepository->createServiceLog($service_id, $refundOrderStatus);
            } else {
                $storeOrderStatusRepository->createAdminLog($orderStatus);
                $storeOrderStatusRepository->createAdminLog($refundOrderStatus);
            }

            event('refund.refuse', compact('id', 'refund'));
            Queue::push(SendSmsJob::class, ['tempId' => 'REFUND_FAIL_CODE', 'id' => $id]);
        });
    }

    /**
     * 同意退款
     * @param int $id
     * @param array $data
     * @param int $service_id
     * @param bool $isPlatform
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author Qinii
     * @day 2020-06-13
     */
    public function agree(int $id, array $data = [], int $service_id = 0, bool $isPlatform = false)
    {
        $refund = $this->dao->getWhere(['refund_order_id' => $id], '*', ['refundProduct.product']);
        if ($refund['status'] == 1) return ;
        //已退款金额
        $_refund_price = $this->checkRefundPrice($id);

        // 公益免单：退用户金额 = max(0, 实付 - 买单金额)
        try {
            $orderRow = $refund->order ?? null;
            if ($orderRow && !empty($orderRow['is_welfare_order'])) {
                $paid = (float)($orderRow['pay_price'] ?? 0);
                $billAmount = (float)($orderRow['welfare_bill_amount'] ?? 0);
                $_refund_price = max(0, round($paid - $billAmount, 2));
            }
        } catch (\Throwable $e) {
        }

        //退款订单记录
        $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
        $orderStatus                = [
            'order_id' => $refund->refund_order_id,
            'order_sn' => $refund->refund_order_sn,
            'type'     => $storeOrderStatusRepository::TYPE_REFUND,
        ];
        $platformMsg   = isset($data['platform_mark']) && $data['platform_mark'] ? $data['platform_mark'] : '平台通过退款申请';
        unset($data['platform_mark']);
        $refundJobData = null;
        $refund = Db::transaction(function () use ($id, $data, $_refund_price, $refund, $storeOrderStatusRepository,
            $orderStatus, $service_id, $isPlatform, $platformMsg,&$refundJobData) {
            $this->getProductRefundNumber($refund, 1);
            if ($refund['refund_type'] == 1) {
                //TODO 退款单同意退款
                $refund                        = $this->doRefundPrice($id, $_refund_price);
                $data['status']                = 3;
                $orderStatus['change_message'] = '退款成功';
                $orderStatus['change_type']    = $storeOrderStatusRepository::ORDER_STATUS_CREATE;
                $this->refundAfter($refund);
                $refundJobData = ['refund_id' => $id];
            }
            if ($refund['refund_type'] == 2) {
                if ($isPlatform) {
                    $refund                        = $this->doRefundPrice($id, $_refund_price);
                    $data['status']                = 3;
                    $orderStatus['change_message'] = $platformMsg;
                    $orderStatus['change_type']    = $storeOrderStatusRepository::CHANGE_REFUND_PLATFORM_AGREE;
                    $this->refundAfter($refund);
                    $refundJobData = ['refund_id' => $id];
                } else {
                    $data['status']                = 1;
                    $orderStatus['change_message'] = '退款申请已通过，请将商品寄回';
                    $orderStatus['change_type']    = $storeOrderStatusRepository::CHANGE_REFUND_AGREE;
                    Queue::push(SendSmsJob::class, ['tempId' => 'REFUND_SUCCESS_CODE', 'id' => $id]);
                }
            }
            $data['status_time'] = date('Y-m-d H:i:s');
            $this->dao->update($id, $data);
            if ($service_id) {
                $storeOrderStatusRepository->createServiceLog($service_id, $orderStatus);
            } else {
                $storeOrderStatusRepository->createAdminLog($orderStatus);
            }
            return $refund;
        });
        if ($refundJobData) {
            //Queue::push(CheckOrderCompletionAndUnfreezeJob::class, $refundJobData);
            app()->make(StoreOrderRepository::class)->orderRefundAllAfter($refund->order);
        }
        event('refund.agree', compact('id', 'refund'));
    }



    /**
     * 根据订单退款情况更新商品退款状态
     *
     * @param object $res 订单退款信息
     * @param int $status 退款状态：1表示同意，其他表示拒绝
     * @param bool $after 是否为退款后操作，默认为false表示退款前
     * @return bool 更新操作是否成功
     */
    public function getProductRefundNumber($res, $status, $after = false)
    {
        /**
         * 1.同意退款
         *   1.1 仅退款
         *      1.1.1 是 , 如果退款数量 等于 购买数量 is_refund = 3 全退退款 不等于 is_refund = 2 部分退款
         *      1.1.2 否, is_refund = 1 退款中
         *   1.2 退款退货 is_refund = 1
         *
         * 2. 拒绝退款
         *   2.1 如果退款数量 等于 购买数量 返还可退款数 is_refund = 0
         *   2.2 商品总数小于可退数量 返还可退数 以商品数为准
         *   2.3 是否存在其他图款单,是 ,退款中 ,否, 部分退款
         */
        // 获取订单退款数量
        $refundId = $this->getRefundCount($res->order_id, $res['refund_order_id']);
        // 实例化退款产品仓库
        $make = app()->make(StoreRefundProductRepository::class);

        // 遍历退款商品列表，更新每个商品的退款状态
        foreach ($res['refundProduct'] as $item) {
            // 获取商品当前的退款状态
            $is_refund = $item->product->is_refund;

            // 同意退款的处理逻辑
            if ($status == 1) {
                // 退款后操作的逻辑
                if ($after) {
                    // 全部退款或部分退款的判断
                    $is_refund = ($item->refund_num == $item->product->product_num) ? 3 : 2;
                } // 退款前操作的逻辑，仅在退款类型为退款退货时执行
                else if ($res['refund_type'] == 1) {
                    // 全部退款或部分退款的判断
                    $is_refund = ($item->refund_num == $item->product->product_num) ? 3 : 2;
                }
            } // 拒绝退款的处理逻辑
            else {
                // 计算返还可退款数量
                $refund_num = $item->refund_num + $item->product->refund_num;
                // 全部可退时，设置退款状态为0
                if ($item->product->product_num == $refund_num) {
                    $is_refund = 0;
                }
                // 可退数量小于商品总数时，调整可退数量
                if ($item->product->product_num < $refund_num) {
                    $refund_num = $item->product->product_num;
                }
                // 更新商品的可退数量
                $item->product->refund_num = $refund_num;
            }

            // 检查是否有其他退款单中包含当前商品，如果有，则设置退款状态为1（退款中）
            if (!empty($refundId)) {
                $has = $make->getWhereCount([['refund_order_id', 'in', $refundId], ['order_product_id', '=', $item->product->order_product_id]]);
                if ($has) {
                    $is_refund = 1;
                }
            }

            // 更新商品的退款状态
            $item->product->is_refund = $is_refund;
            // 保存更新后的商品退款状态
            $item->product->save();
        }

        // 更新操作成功，返回true
        return true;
    }

    /**
     * 获取订单存在的未处理完成的退款单
     * @Author:Qinii
     * @Date: 2020/9/25
     * @param int $orderId
     * @param int|null $refundOrderId
     * @return array
     */
    public function getRefundCount(int $orderId, ?int $refundOrderId)
    {
        $where = [
            'type'     => 1,
            'order_id' => $orderId,
        ];

        return $this->dao->search($where)->when($refundOrderId, function ($query) use ($refundOrderId) {
            $query->where('refund_order_id', '<>', $refundOrderId);
        })->column('refund_order_id');
    }

    /**
     * 退款时返回锁定的赠送积分。
     *
     * 当订单退款时，如果订单曾经锁定过赠送积分，本函数将根据退款金额计算应返回的赠送积分，并从用户的积分账户中扣除相应数量的锁定积分。
     * 这里的赠送积分是指用户在购买商品时获得的额外积分，不包括通过其他途径获得的积分。
     *
     * @param StoreRefundOrder $refundOrder 退款订单对象，包含订单相关信息及退款金额。
     */
    public function refundGiveIntegral(StoreRefundOrder $refundOrder)
    {
        // 检查退款金额和订单支付金额是否大于0，因为只有支付了金额的订单才会涉及到赠送积分的锁定和返还。
        if ($refundOrder->refund_price > 0 && $refundOrder->order->pay_price > 0) {
            // 获取用户账单仓库实例，用于操作用户的积分账单记录。
            $userBillRepository = app()->make(UserBillRepository::class);

            // 根据订单组ID查询与赠送积分锁定相关的用户账单记录。
            $bill = $userBillRepository->getWhere(['category' => 'integral', 'type' => 'lock', 'link_id' => $refundOrder->order->group_order_id]);

            // 检查账单是否存在且状态不是已返还，因为只有未返还的锁定积分才能在退款时进行操作。
            if ($bill && $bill->status != 1) {
                // 如果订单状态为已取消，则直接返回全部锁定的赠送积分。
                if ($refundOrder->order->status == -1) {
                    $number = bcsub($bill->number, $userBillRepository->refundIntegral($refundOrder->order->group_order_id, $bill->uid), 0);
                } else {
                    // 如果订单未取消，则根据退款金额占支付金额的比例计算应返回的赠送积分。
                    $number = ($refundOrder['refund_price'] / $refundOrder->order->pay_price) * $refundOrder->order->give_integral;
                }

                // 如果计算结果小于等于0，则不进行后续操作。
                if ($number <= 0) return;

                // 从用户的积分账户中扣除相应的赠送积分，并更新积分账单记录为已返还状态。
                $userBillRepository->decBill($bill->uid, 'integral', 'refund_lock', [
                    'link_id' => $refundOrder->order->group_order_id,
                    'status'  => 1,
                    'title'   => '扣除赠送积分',
                    'number'  => $number,
                    'mark'    => '订单退款扣除赠送积分' . intval($number),
                    'balance' => $refundOrder->user->integral
                ]);
            }
        }
    }

    /**
     * 处理订单退款时的佣金返还逻辑。
     *
     * 该方法主要处理一级和二级佣金在订单退款时的返还操作。它首先检查退款订单中的一级和二级佣金金额，
     * 如果佣金大于0，则尝试从用户的佣金账单中扣除该金额。如果账单存在且状态不是已退款，则执行扣除操作，
     * 同时更新用户的佣金余额。如果账单不存在或状态是已退款，则记录一条财务记录，表示佣金的退还。
     * 最后，更新订单中的佣金金额。
     *
     * @param StoreRefundOrder $refundOrder 退款订单对象，包含订单和退款信息。
     */
    public function descBrokerage(StoreRefundOrder $refundOrder)
    {
        // 获取用户账单仓库和用户仓库实例
        $userBillRepository = app()->make(UserBillRepository::class);
        $userRepository     = app()->make(UserRepository::class);

        // 处理一级佣金退款
        if ($refundOrder['extension_one'] > 0) {
            // 根据订单ID查询一级佣金账单
            $bill = $userBillRepository->getWhere(['category' => 'brokerage', 'type' => 'order_one', 'link_id' => $refundOrder->order_id]);
            // 减少订单中的一级佣金金额
            $refundOrder->order->extension_one = bcsub($refundOrder->order->extension_one, $refundOrder['extension_one'], 2);
            // 如果账单存在且状态不是已退款，则执行佣金扣除操作
            $title = $refundOrder->order->activity_type == 4 ? '团员退款' : '用户退款';
            if ($bill && $bill->status != 1) {
                $userRepository->incBrokerage($bill->uid, $refundOrder['extension_one'], '-');
                $userBillRepository->decBill($bill->uid, 'brokerage', 'refund_one', [
                    'link_id' => $refundOrder->order_id,
                    'status'  => 1,
                    'title'   => $title,
                    'number'  => $refundOrder['extension_one'],
                    'mark'    => $title . '扣除推广佣金' . floatval($refundOrder['extension_one']),
                    'balance' => 0
                ]);
            }
            // 如果账单不存在或状态是已退款，则记录一条财务记录，表示一级佣金的退还
            if (!$bill || $bill->status != 1) {
                app()->make(FinancialRecordRepository::class)->inc([
                    'order_id'       => $refundOrder->refund_order_id,
                    'order_sn'       => $refundOrder->refund_order_sn,
                    'user_info'      => $bill ? $userRepository->getUsername($bill->uid) : '-',
                    'user_id'        => $bill ? $bill->uid : 0,
                    'type'           => 1,
                    'financial_type' => 'refund_brokerage_one',
                    'number'         => $refundOrder['extension_one'],
                ], $refundOrder->mer_id);
            }
        }

        // 处理二级佣金退款
        if ($refundOrder['extension_two'] > 0) {
            // 根据订单ID查询二级佣金账单
            $bill = $userBillRepository->getWhere(['category' => 'brokerage', 'type' => 'order_two', 'link_id' => $refundOrder->order_id]);
            // 减少订单中的二级佣金金额
            $refundOrder->order->extension_two = bcsub($refundOrder->order->extension_two, $refundOrder['extension_two'], 2);
            // 如果账单存在且状态不是已退款，则执行佣金扣除操作
            if ($bill && $bill->status != 1) {
                $userRepository->incBrokerage($bill->uid, $refundOrder['extension_two'], '-');
                $userBillRepository->decBill($bill->uid, 'brokerage', 'refund_two', [
                    'link_id' => $refundOrder->order_id,
                    'status'  => 1,
                    'title'   => '用户退款',
                    'number'  => $refundOrder['extension_two'],
                    'mark'    => '用户退款扣除推广佣金' . floatval($refundOrder['extension_two']),
                    'balance' => 0
                ]);
            }
            // 如果账单不存在或状态是已退款，则记录一条财务记录，表示二级佣金的退还
            if (!$bill || $bill->status != 1) {
                app()->make(FinancialRecordRepository::class)->inc([
                    'order_id'       => $refundOrder->refund_order_id,
                    'order_sn'       => $refundOrder->refund_order_sn,
                    'user_info'      => $bill ? $userRepository->getUsername($bill->uid) : '-',
                    'user_id'        => $bill ? $bill->uid : 0,
                    'type'           => 1,
                    'financial_type' => 'refund_brokerage_two',
                    'number'         => $refundOrder['extension_two'],
                ], $refundOrder->mer_id);
            }
        }

        // 保存更新后的订单信息
        $refundOrder->order->save();
    }

    /**
     * //TODO 退款后
     * @param StoreRefundOrder $refundOrder
     * @author xaboy
     * @day 2020/6/17
     */
    public function refundAfter(StoreRefundOrder $refundOrder)
    {
        //返还库存
        $refundOrder->append(['refundProduct.product']);
        $productRepository = app()->make(ProductRepository::class);
        if ($refundOrder->order->is_virtual !== 4 && ($refundOrder['refund_type'] == 2 || $refundOrder->order->status == 0 ||
                $refundOrder->order->status == 9)) {
            foreach ($refundOrder->refundProduct as $item) {
                $productRepository->orderProductIncStock($refundOrder->order, $item->product, $item->refund_num);
            }
        }
        $storeOrderRepository = app()->make(StoreOrderRepository::class);
        $refundAll = $storeOrderRepository->checkRefundStatusById($refundOrder['order_id'], $refundOrder['refund_order_id']);
        if ($refundAll) {
            $refundOrder->order->status = -1;
            //  $storeOrderRepository = app()->make(StoreOrderRepository::class);->orderRefundAllAfter($refundOrder->order);
        }
        Queue::push(SendSmsJob::class, ['tempId' => 'REFUND_CONFORM_CODE', 'id' => $refundOrder->refund_order_id]);
        $this->descBrokerage($refundOrder);

        // 公益免单商品退款：扣扫码商户分销锁定金额（不扣买单金额）
        try {
            $order = $refundOrder->order;
            if ($order && !empty($order['is_welfare_order'])) {
                app()->make(\app\common\repositories\store\nearby\WelfareFreeOrderRepository::class)
                    ->handleRefund($order, (float)$refundOrder->refund_price);
            }
        } catch (\Throwable $e) {
            \think\facade\Log::error('Welfare refund handle failed: ' . $e->getMessage());
        }

        // 消费送股：订单退款按比例扣减股本金
        try {
            $order = $refundOrder->order;
            if ($order && (int)$order['uid'] > 0 && (int)$order['mer_id'] > 0) {
                $originPay = (float)($order['pay_price'] ?? 0);
                app()->make(\app\common\repositories\store\equity\EquityGrantRepository::class)
                    ->clawbackOnOrderRefund(
                        (string)($order['order_id'] ?? $order['order_sn'] ?? ''),
                        'order',
                        (float)$refundOrder->refund_price,
                        $originPay
                    );
            }
        } catch (\Throwable $e) {
            \think\facade\Log::error('Equity refund clawback failed: ' . $e->getMessage());
        }

        if ($refundOrder->platform_refund_price > 0) {
            if ($refundOrder->order->firstProfitsharing) {
                $model                          = $refundOrder->order->firstProfitsharing;
                $model->profitsharing_mer_price = bcsub($model->profitsharing_mer_price, $refundOrder->platform_refund_price, 2);
                $model->save();
            } else {
                app()->make(MerchantRepository::class)->subLockMoney($refundOrder->mer_id, 'order', $refundOrder->order->order_id, $refundOrder->platform_refund_price);
            }
            $isVipCoupon = app()->make(StoreGroupOrderRepository::class)->isVipCoupon($refundOrder->order->groupOrder);
            app()->make(FinancialRecordRepository::class)->dec([
                'order_id'       => $refundOrder->refund_order_id,
                'order_sn'       => $refundOrder->refund_order_sn,
                'user_info'      => $refundOrder->user->nickname ?? '用户已被删除',
                'user_id'        => $refundOrder->uid,
                'financial_type' => $isVipCoupon ? 'refund_svip_coupon' : 'refund_platform_coupon',
                'type'           => 1,
                'number'         => $refundOrder->platform_refund_price,
                'pay_type'      => $refundOrder->order->pay_type
            ], $refundOrder->mer_id);
        }

        //退回积分
        if ($refundOrder->integral > 0) {
            $make = app()->make(UserRepository::class);
            $make->update($refundOrder->uid, ['integral' => Db::raw('integral+' . $refundOrder->integral)]);
            $userIntegral = $make->get($refundOrder->uid)->integral;
            $make1        = app()->make(UserBillRepository::class);
            $make1->incBill($refundOrder->uid, 'integral', 'refund', [
                'link_id' => $refundOrder->order_id,
                'status'  => 1,
                'title'   => '订单退款',
                'number'  => $refundOrder->integral,
                'mark'    => '订单退款,返还' . intval($refundOrder->integral) . '积分',
                'balance' => $userIntegral
            ]);
            $make1->incBill($refundOrder->uid, 'mer_integral', 'refund', [
                'link_id' => $refundOrder->order_id,
                'status'  => 1,
                'title'   => '订单退款',
                'number'  => $refundOrder->integral,
                'mark'    => '订单退款,返还' . intval($refundOrder->integral) . '积分',
                'balance' => $userIntegral,
                'mer_id'  => $refundOrder->mer_id
            ]);
        }

        //退还赠送积分
        $this->refundGiveIntegral($refundOrder);

        app()->make(FinancialRecordRepository::class)->dec([
            'order_id'       => $refundOrder->refund_order_id,
            'order_sn'       => $refundOrder->refund_order_sn,
            'user_info'      => $refundOrder->user->nickname ?? '用户已被删除',
            'user_id'        => $refundOrder->uid,
            'financial_type' => 'refund_order',
            'type'           => 1,
            'number'         => $refundOrder->refund_price,
            'pay_type'       => $refundOrder->order->pay_type
        ], $refundOrder->mer_id);
        // 退款后商圈代理冻结中的提成失效
        app()->make(CircleFinancialRecordRepository::class)->invalidFinancial($refundOrder->order);
    }

    /**
     * 计算退款金额中商家实际应退金额。
     * 此函数主要用于处理订单退款时，根据退款金额和订单中的额外费用（如手续费）计算商家实际应退的金额。
     * 考虑到订单可能有佣金的情况，还会计算并扣除相应的佣金部分。
     *
     * @param StoreRefundOrder $refundOrder 退款订单对象，包含订单退款的相关信息。
     * @param null $refundPrice 退款金额，如果为null，则使用退款订单中的退款金额。
     * @return string 商家实际应退金额，以字符串形式返回，保留两位小数。
     */
    public function getRefundMerPrice(StoreRefundOrder $refundOrder, $refundPrice = null)
    {
        // 如果未指定退款金额，则使用退款订单中的退款金额。
        if ($refundPrice === null) {
            $refundPrice = $refundOrder->refund_price;
            // 初始化扩展字段一和扩展字段二，用于后续计算。
            $extension_one = $refundOrder['extension_one'];
            $extension_two = $refundOrder['extension_two'];
        } else {
            // 计算退款比例，用于按比例计算扩展字段的退款金额。
            $rate = bcdiv($refundPrice, $refundOrder->refund_price, 3);
            // 根据退款比例计算扩展字段一和扩展字段二的退款金额。
            $extension_one = $refundOrder['extension_one'] > 0 ? bcmul($rate, $refundOrder['extension_one'], 2) : 0;
            $extension_two = $refundOrder['extension_two'] > 0 ? bcmul($rate, $refundOrder['extension_two'], 2) : 0;
        }
        // 计算扩展字段的总退款金额。
        $extension = bcadd($extension_one, $extension_two, 3);
        // 获取订单的佣金比例，用于计算佣金退款金额。
        $commission_rate = bcdiv($refundOrder->order->commission_rate, '100', 6);
        // 初始化佣金退款金额。
        $_refundRate = 0;
        // 如果订单有佣金，则计算佣金退款金额。
        if ($refundOrder->order->commission_rate > 0) {
            $_refundRate = bcmul($commission_rate, bcsub($refundPrice, $extension, 2), 2);
        }
        // 计算商家实际应退金额，即总退款金额减去扩展字段退款金额和佣金退款金额。
        return bcsub(bcsub($refundPrice, $extension, 2), $_refundRate, 2);
    }


    /**
     * 退款单同意退款退货
     * @param $id
     * @param $admin
     * @author Qinii
     * @day 2020-06-13
     */
    public function adminRefund($id, $data, $service_id = 0)
    {
        $refund = $this->dao->getWhere(['refund_order_id' => $id], '*', ['refundProduct.product']);
        //退款订单记录
        $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
        $orderStatus                = [
            'order_id'       => $refund->refund_order_id,
            'order_sn'       => $refund->refund_order_sn,
            'type'           => StoreOrderStatusRepository::TYPE_REFUND,
            'change_message' => $data['status'] == 3 ? '退款成功' : '商家拒绝收货，退款失败',
            'change_type'    => $data['status'] == 3 ? StoreOrderStatusRepository::CHANGE_REFUND_PRICE : StoreOrderStatusRepository::CHANGE_REFUND_REFUSE,
        ];
        Db::transaction(function () use ($service_id, $id, $refund, $storeOrderStatusRepository, $orderStatus, $data) {
            $data['status_time'] = date('Y-m-d H:i:s');
            $this->dao->update($id, $data);
            if ($service_id) {
                $storeOrderStatusRepository->createServiceLog($service_id, $orderStatus);
            } else {
                $storeOrderStatusRepository->createAdminLog($orderStatus);
            }
            $this->getProductRefundNumber($refund, 1, true);
            if ($data['status'] == 3) {
                $refund = $this->doRefundPrice($id, 0);
                if ($refund) $this->refundAfter($refund);
            }
        });
    }


    /**
     * 余额退款
     * @param $data
     * @param $uid
     * @param $id
     * @author Qinii
     * @day 2020-11-03
     */
    public function refundBill($data, $uid, $id)
    {
        try {
            $user = app()->make(UserRepository::class)->get($uid);
            if (empty($user)) {
                throw new ValidateException('用户数据异常，余额退款失败');
            }
            $balance = bcadd($user->now_money, $data['data']['refund_price'], 2);
            $user->save(['now_money' => $balance]);

            app()->make(UserBillRepository::class)
                ->incBill($uid, 'now_money', 'refund', [
                    'link_id' => $id,
                    'status'  => 1,
                    'title'   => '退款增加余额',
                    'number'  => $data['data']['refund_price'],
                    'mark'    => '退款增加' . floatval($data['data']['refund_price']) . '余额，退款订单号:' . $data['sn'],
                    'balance' => $balance
                ]);
        } catch (ValidateException $e) {
            throw new ValidateException($e->getMessage());
        } catch (Exception $e) {
            throw new ValidateException('余额退款失败');
        }
    }

    /**
     * 查询订单物流信息
     *
     * 通过订单ID获取订单详情，并调用物流服务类查询相应的物流信息。
     * 此函数主要用于处理与订单物流相关的查询操作，它封装了与数据库交互的逻辑
     * 和物流信息查询的业务逻辑。
     *
     * @param int $orderId 订单ID，用于查询特定订单的详细信息。
     * @return string 返回查询到的物流信息。
     */
    public function express($orderId)
    {
        // 通过订单ID查询订单详情
        $refundOrder = $this->dao->get($orderId);

        // 调用物流服务类查询物流信息，参数包括配送ID、配送类型和配送电话
        return ExpressService::express($refundOrder->delivery_id, $refundOrder->delivery_type, $refundOrder->delivery_phone);
    }

    /**
     *  退款金额是否超过可退金额
     * @Author:Qinii
     * @Date: 2020/9/2
     * @param int $refundId
     * @return bool
     */
    public function checkRefundPrice(int $refundId)
    {
        $refund = $this->dao->get($refundId);
        if ($refund['refund_price'] < 0) throw new ValidateException('退款金额不能小于0');
        $order     = app()->make(StoreOrderRepository::class)->get($refund['order_id']);
        $pay_price = $order['pay_price'];

        //预售
        if ($order['activity_type'] == 2) {
            $final_price = app()->make(PresellOrderRepository::class)->getSearch(['order_id' => $refund['order_id']])->value('pay_price');
            $pay_price   = bcadd($pay_price, ($final_price ? $final_price : 0), 2);
        }

        //已退金额
        $refund_price = $this->dao->refundPirceByOrder([$refund['order_id']]);

        if (bccomp(bcsub($pay_price, $refund_price, 2), $refund['refund_price'], 2) == -1)
            throw new ValidateException('退款金额超出订单可退金额');

        return $refund_price;
    }


    /**
     * 订单自动退款
     * @param $id
     * @param int $refund_type
     * @param string $message
     * @author Qinii
     * @day 1/15/21
     */
    public function autoRefundOrder($id, $refund_type = 1, $message = '')
    {
        $order = app()->make(StoreOrderRepository::class)->get($id);
        if (!$order) return;
        if ($order->status == -1) return;
        if ($order['paid'] == 1) {
            //已支付
            $refund_make = app()->make(StoreRefundOrderRepository::class);
            $refund      = $refund_make->createRefund($order, $refund_type, $message);
            $refund_make->agree($refund[$refund_make->getPk()], [], 0);
        } else {
            if (!$order->is_del) {
                app()->make(StoreOrderRepository::class)->delOrder($order, $message);
            }
        }
    }


    /**
     * 移动端客服退款信息
     * @param int $id
     * @param int $merId
     * @return array
     * @author Qinii
     * @day 6/2/22
     */
    public function serverRefundDetail(int $id, int $merId)
    {
        if (!$this->dao->merHas($merId, $id)) {
            throw new ValidateException('数据不存在');
        }
        $data        = $this->dao->getWhere(['mer_id' => $merId, $this->dao->getPk() => $id], '*', ['refundProduct.product', 'order']);
        $total_price = $total_postage = 0.00;

        foreach ($data['refundProduct'] as $itme) {
            $total_price   = bcadd($total_price, $itme['refund_price'], 2);
            $total_postage = bcadd($total_postage, $itme['refund_postage'], 2);
        }
        $total_price       = bcsub($total_price, $total_postage, 2);
        $data['total_num'] = $data['order']['total_num'];
        unset($data['refundProduct'], $data['order']);
        $data['total_price']   = $total_price;
        $data['total_postage'] = $total_postage;
        $refund_info           = null;
        if ($data['refund_type'] == 2) {
            $refund_info['mer_delivery_user']    = merchantConfig($merId, 'mer_refund_user');
            $refund_info['mer_delivery_address'] = merchantConfig($merId, 'mer_refund_address');
            $refund_info['phone']                = merchantConfig($merId, 'set_phone');
        }
        $data['refund_info'] = $refund_info;

        return $data;
    }

    /**
     * 用户取消退款单申请
     * @param int $id
     * @param $user
     * @author Qinii
     * @day 2022/11/18
     */
    public function cancel(int $id, $user)
    {
        //状态 0:待审核 -1:审核未通过 1:待退货 2:待收货 3:已退款
        $refund = $this->dao->getWhere(['refund_order_id' => $id, 'uid' => $user->uid], '*', ['refundProduct.product']);
        if (!$refund) throw new ValidateException('数据不存在');
        if (!in_array($refund['status'], [self::REFUND_STATUS_WAIT, self::REFUND_STATUS_BACK]))
            throw new ValidateException('当前状态不可取消');

        //退款订单记录
        $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
        $orderStatus                = [
            'order_id'       => $refund->order_id,
            'order_sn'       => $refund->order->order_sn,
            'type'           => $storeOrderStatusRepository::TYPE_ORDER,
            'change_message' => '用户取消退款',
            'change_type'    => $storeOrderStatusRepository::CHANGE_REFUND_CANCEL,
        ];

        $orderRefundStatus = [
            'order_id'       => $refund->refund_order_id,
            'order_sn'       => $refund->refund_order_sn,
            'type'           => $storeOrderStatusRepository::TYPE_REFUND,
            'change_message' => '用户取消退款',
            'change_type'    => $storeOrderStatusRepository::CHANGE_REFUND_CANCEL,
        ];

        Db::transaction(function () use ($id, $refund, $storeOrderStatusRepository, $orderStatus, $orderRefundStatus) {
            $this->getProductRefundNumber($refund, -1);
            $this->dao->update($id, ['status_time' => date('Y-m-d H:i:s'), 'status' => self::REFUND_STATUS_CANCEL]);
            $storeOrderStatusRepository->createUserLog($refund->uid, $orderRefundStatus);
            $storeOrderStatusRepository->createUserLog($refund->uid,$orderStatus);
        });
    }

    /**
     * 根据ID获取一条信息。
     *
     * 此方法旨在通过提供的ID从数据库中检索单条记录。如果该记录存在但用户信息为空，
     * 则通过UserRepository类来补充用户删除信息。这样设计的目的是为了确保返回的信息尽可能完整，
     * 即便用户信息曾经被删除。
     *
     * @param int $id 需要查询的记录的ID。
     * @return array 包含所需信息的数组。如果信息不存在或查询失败，则返回空数组。
     */
    public function getOne($id)
    {
        // 通过DAO层根据ID获取信息
        $info = $this->dao->getOne($id);
        if (!$info) throw new ValidateException('数据不存在');
        // 检查信息是否存在且用户信息为空，如果满足条件，则补充用户删除信息
        if (empty($info['user'])) {
            $info['user'] = app()->make(UserRepository::class)->getDelUserInfo();
        }

        // 返回获取到的信息
        return $info;
    }

    /**
     * 用户申请平台介入
     * @param $refundOrderId
     * @param $uid
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function applyPlatformIntervene($refundOrderId, $uid)
    {
        if (!systemConfig('platform_intervention')) {
            throw new Exception('已关闭平台介入功能');
        }
        //状态 0:待审核 -1:审核未通过 1:待退货 2:待收货 3:已退款 4:平台介入
        $refund = $this->dao->getWhere(['refund_order_id' => $refundOrderId, 'uid' => $uid], '*', ['refundProduct.product']);
        if (!$refund) {
            throw new Exception('退款单不存在');
        }
        if ($refund->status != self::REFUND_STATUS_REFUSED) {
            throw new Exception('当前状态不允许申请平台介入');
        }
        $daysToAdd = (int)systemConfig('platform_intervention_time');
        if (time() <= strtotime("{$refund->status_time} +{$daysToAdd} days")) {
            throw new Exception('申请平台介入时间已过');
        }

        //退款订单记录
        $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
        $orderStatus                = [
            'order_id'       => $refund->order_id,
            'order_sn'       => $refund->order->order_sn,
            'type'           => $storeOrderStatusRepository::TYPE_ORDER,
            'change_message' => '用户申请平台介入退款',
            'change_type'    => $storeOrderStatusRepository::CHANGE_REFUND_PLATFORM_INTERVENE,
        ];

        $orderRefundStatus = [
            'order_id'       => $refund->refund_order_id,
            'order_sn'       => $refund->refund_order_sn,
            'type'           => $storeOrderStatusRepository::TYPE_REFUND,
            'change_message' => '申请平台介入',
            'change_type'    => $storeOrderStatusRepository::CHANGE_REFUND_PLATFORM_INTERVENE,
        ];

        Db::transaction(function () use ($refundOrderId, $refund, $storeOrderStatusRepository, $orderStatus, $orderRefundStatus) {
            $this->dao->update($refundOrderId, ['status_time' => date('Y-m-d H:i:s'), 'status' => self::REFUND_PLATFORM_INTERVENE]);
            $storeOrderStatusRepository->createUserLog($refund->uid,$orderRefundStatus);
            $storeOrderStatusRepository->createUserLog($refund->uid,$orderStatus);
            foreach($refund['refundProduct'] as $item) {
                $orderProduct = $item['product'];
                $orderProduct->refund_num -= $item['refund_num'];
                $orderProduct->is_refund  = 1;
                $orderProduct->save();
            }
        });
    }

    /**
     * 平台审核退款
     * @param $id
     * @param $data
     * @return void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function approve($id, $data)
    {
        if ($data['status']) {
            $this->agree($id, $data, 0, true);
        } else {
            $data['status'] = -1;
            $this->refuse($id, $data, 0, true);
        }
    }

    /**
     * 退款操作
     *
     * 优化点：
     * 1. 提取退款服务创建逻辑
     * 2. 拆分长方法为多个职责单一的方法
     * 3. 使用异常处理统一错误管理
     * 4. 提取财务记录逻辑
     */
    public function doRefundPrice($id, $refundPrice)
    {
        $refundOrder = $this->dao->getWhere(['refund_order_id' => $id], "*", ['order']);

        //如果订单不存在或者退款金额小于等于0 等无需处理直接返回
        $this->validateRefundOrder($refundOrder);
        if ($refundOrder->refund_price <= 0) {
            return $refundOrder;
        }

        // 获取需要推荐的数据 和 退款需要执行的服务类型
        [$refundData, $serverType] = $this->prepareRefundData($refundOrder, $refundPrice);

        //根据不同的服务类型 找到对应的服务类
        $refundService = $this->createRefundService($serverType);
        //执行退款操作
        $refundResult = $this->processRefund($refundOrder, $refundData, $refundService, $serverType);

        //退款后记录等操作
        $this->recordFinancialTransactions($refundOrder, $refundResult);

        return $refundOrder;
    }

    /**
     * 根据退款情况计算最终订单信息
     */
    public function getFinalOrder(StoreRefundOrder $refundOrder, float $refundPrice): array
    {
        $result = [];
        $order = $refundOrder->order;

        if ($this->shouldRefundFinalOrder($order->pay_price, $refundPrice)) {
            // 情况1：已退款金额大于定金订单，直接退尾款订单
            $result[] = $this->buildFinalOrderData($refundOrder);
        } else {
            // 情况2：已退款金额小于定金订单
            $result = $this->handleDepositRefund($refundOrder, $refundPrice);
        }
        return $result;
    }

    /**
     * 判断是否需要直接退尾款订单
     */
    private function shouldRefundFinalOrder($orderPrice, $refundPrice): bool
    {
        return bccomp($orderPrice, $refundPrice, 2) == -1;
    }

    /**
     * 构建尾款订单退款数据
     */
    private function buildFinalOrderData(StoreRefundOrder $refundOrder): array
    {
        $finalOrderRepo = app()->make(PresellOrderRepository::class);
        $finalOrder = $finalOrderRepo->getSearch(['order_id' => $refundOrder['order_id']])->find();

        $serverType = $this->getServerType($finalOrder);
        $isCombine = $finalOrder->is_combine;

        $baseData = [
            'type' => $serverType,
            'id' => $finalOrder->presell_order_id,
            'sn' => $finalOrder['presell_order_sn'],
        ];

        if ($isCombine) {
            $sub_mchid = $serverType == 10 ? $refundOrder->merchant->sub_mchid : $refundOrder->merchant->applyment_id;
            $baseData['presell'] = 1;
            $baseData['data'] = [
                'sub_mchid' => $sub_mchid,
                'order_sn' => $refundOrder->order->order_sn,
                'refund_order_sn' => $refundOrder->refund_order_sn,
                'pay_price' => $refundOrder->order->pay_price,
                'refund_price' => $refundOrder->refund_price,
            ];
        } else {
            $baseData['data'] = [
                'refund_id' => $refundOrder->refund_order_sn,
                'pay_price' => $refundOrder->order->pay_price,
                'refund_price' => $refundOrder->refund_price
            ];
        }

        return $baseData;
    }

    /**
     * 处理定金订单退款逻辑
     */
    private function handleDepositRefund(StoreRefundOrder $refundOrder, float $refundPrice): array
    {
        $result = [];
        $order = $refundOrder->order;

        // 剩余定金金额
        $remainingDeposit = bcsub($order->pay_price, $refundPrice, 2);
        $refundComparison = bccomp($remainingDeposit, $refundOrder->refund_price, 2);

        $serverType = $this->getServerType($order);

        if ($refundComparison >= 0) {
            // 情况2.2：当前退款金额 <= 剩余定金金额，只退定金
            $result[] = $this->buildDepositOrderData($refundOrder, $serverType);
        } else {
            // 情况2.1：当前退款金额 > 剩余定金金额，需要分两次退款
            $result = $this->buildSplitRefundData($refundOrder, $remainingDeposit, $serverType);
        }

        return $result;
    }

    /**
     * 构建定金订单退款数据
     */
    private function buildDepositOrderData(StoreRefundOrder $refundOrder, int $serverType): array
    {
        $order = $refundOrder->order;
        $groupOrder = $order->groupOrder;

        if ($groupOrder->is_combine) {
            return [
                'type' => $serverType,
                'id' => $order->order_id,
                'sn' => $order->order_sn,
                'data' => $refundOrder->getCombineRefundParams($serverType)
            ];
        }

        return [
            'type' => $serverType,
            'id' => $order->order_id,
            'sn' => $groupOrder->group_order_sn,
            'data' => [
                'refund_id' => $refundOrder->refund_order_sn,
                'pay_price' => $order->pay_price,
                'refund_price' => $refundOrder->refund_price
            ]
        ];
    }

    /**
     * 构建分拆退款数据（定金+尾款）
     */
    private function buildSplitRefundData(StoreRefundOrder $refundOrder, string $remainingDeposit, int $serverType): array
    {
        $result = [];
        $order = $refundOrder->order;

        // 定金部分退款
        if ($order->groupOrder->is_combine) {
            $data = $refundOrder->getCombineRefundParams($serverType);
            $data['refund_price'] = $remainingDeposit;
            $result[] = [
                'type' => $serverType,
                'id' => $order->order_id,
                'sn' => $order->order_sn,
                'data' => $data
            ];
        } else {
            $result[] = [
                'type' => $serverType,
                'sn' => $order->groupOrder->group_order_sn,
                'id' => $order->order_id,
                'data' => [
                    'refund_id' => $refundOrder->refund_order_sn,
                    'pay_price' => $order->pay_price,
                    'refund_price' => $remainingDeposit
                ]
            ];
        }

        // 尾款部分退款
        $result[] = $this->buildFinalPartialRefundData($refundOrder, $remainingDeposit);

        return $result;
    }

    /**
     * 构建尾款部分退款数据
     */
    private function buildFinalPartialRefundData(StoreRefundOrder $refundOrder, string $remainingDeposit): array
    {
        $finalOrderRepo = app()->make(PresellOrderRepository::class);
        $finalOrder = $finalOrderRepo->getSearch(['order_id' => $refundOrder['order_id']])->find();

        $serverType = $this->getServerType($finalOrder);
        $finalRefundAmount = bcsub($refundOrder->refund_price, $remainingDeposit, 2);

        $baseData = [
            'type' => $serverType,
            'id' => $finalOrder->presell_order_id,
            'sn' => $finalOrder['presell_order_sn'],
        ];

        if ($finalOrder->is_combine) {
            $baseData['presell'] = 1;
            $baseData['data'] = [
                'sub_mchid' => $refundOrder->merchant->sub_mchid,
                'order_sn' => $finalOrder['presell_order_sn'],
                'refund_order_sn' => $refundOrder->refund_order_sn . '1',
                'pay_price' => $finalOrder->pay_price,
                'refund_price' => $finalRefundAmount
            ];
        } else {
            $baseData['data'] = [
                'refund_id' => $finalOrder['presell_order_sn'] . '1',
                'pay_price' => $finalOrder->pay_price,
                'refund_price' => $finalRefundAmount
            ];
        }

        return $baseData;
    }

    /**
     * 获取服务类型
     */
    private function getServerType($order): int
    {
        if (isset($order->is_combine) && $order->is_combine) {
            return $order->is_combine == 1 ? self::TYPE_IS_COMBINE : self::TYPE_IS_PARTNER;
        } else if (isset($order->groupOrder) && $order->groupOrder->is_combine) {
            return $order->groupOrder->is_combine == 1 ? self::TYPE_IS_COMBINE : self::TYPE_IS_PARTNER;
        }
        return $order->pay_type ?? 0;
    }



    /**
     * 验证退款订单
     */
    private function validateRefundOrder($refundOrder): void
    {
        if (!$refundOrder->order) {
            $refundOrder->fail_message = '订单信息不全';
            $refundOrder->status = -1;
            $refundOrder->save();
            throw new \Exception('订单信息不全');
        }
    }

    /**
     * 准备退款数据
     */
    private function prepareRefundData($refundOrder, $refundPrice): array
    {
        if ($refundOrder->order->activity_type == 2) {
            $data = $this->getFinalOrder($refundOrder, $refundPrice);
            $serverType = !empty($data) ? $data[0]['type'] : 0;
        } else {
            $data = [$this->buildNormalRefundData($refundOrder)];
            $serverType = $data[0]['type'];
        }

        return [$data, $serverType];
    }

    /**
     * 构建普通订单退款数据
     */
    private function buildNormalRefundData($refundOrder): array
    {
        $order = $refundOrder->order;
        $groupOrder = $order->groupOrder;

        if ($groupOrder->is_combine) {
            $type = $groupOrder->is_combine == 1 ? self::TYPE_IS_COMBINE : self::TYPE_IS_PARTNER;
            return [
                'type' => $type,
                'id' => $order->order_id,
                'sn' => $groupOrder->group_order_sn,
                'data' => $refundOrder->getCombineRefundParams($groupOrder->is_combine)
            ];
        }

        return [
            'type' => $order->pay_type,
            'id' => $order->order_id,
            'sn' => $groupOrder->group_order_sn,
            'data' => [
                'refund_id' => $refundOrder->refund_order_sn,
                'pay_price' => $groupOrder->pay_price,
                'refund_price' => $refundOrder->refund_price,
                'refund_message' => $refundOrder->refund_message,
                'open_id' => $refundOrder->user->wechat->routine_openid ?? null,
                'transaction_id' => $order->transaction_id,
            ]
        ];
    }

    /**
     * 创建退款服务
     */
    private function createRefundService(int $serverType)
    {
        if ($serverType == 0) {
            return null;
        } else if ($serverType == self::TYPE_IS_PARTNER) {
            return new Pay('partner');
        } elseif ($serverType == self::TYPE_IS_COMBINE) {
            return new Pay('combine');
        } elseif ($serverType == 2) {
            return new Pay('weixin');
        } elseif (in_array($serverType, [4, 5, 9])) {
            return new Pay('alipay');
        } else {
            return new Pay('weixin');
        }
    }

    /**
     * 处理退款流程
     */
    private function processRefund($refundOrder, $refundData, $refundService, $serverType): array
    {
        $merchantRepository = app()->make(MerchantRepository::class);
        $totalItems = count($refundData);
        $refundPriceAll = 0;
        $refundRate = 0;
        $totalExtension = bcadd($refundOrder['extension_one'], $refundOrder['extension_two'], 2);
        $processedExtension = 0;

        foreach ($refundData as $index => $item) {
            if ($this->isValidRefundItem($item)) {
                $result = $this->processSingleRefund(
                    $refundOrder,
                    $item,
                    $index,
                    $totalItems,
                    $totalExtension,
                    $processedExtension,
                    $refundService,
                    $merchantRepository,
                    $serverType
                );

                $refundPriceAll = bcadd($refundPriceAll, $result['refundPrice'], 2);
                $refundRate = bcadd($refundRate, $result['refundRate'], 2);
                $processedExtension = bcadd($processedExtension, $result['extension'], 2);
            }
        }

        return [
            'refundPriceAll' => $refundPriceAll,
            'refundRate' => $refundRate
        ];
    }

    /**
     * 处理单笔退款
     */
    private function processSingleRefund($refundOrder, $item, $index, $totalItems, $totalExtension, $processedExtension, $refundService, $merchantRepository, $serverType): array
    {
        $refundPrice = $this->getRefundMerPrice($refundOrder, $item['data']['refund_price']);

        // 计算分佣
        $extension = $this->calculateExtension(
            $refundOrder,
            $item,
            $index,
            $totalItems,
            $totalExtension,
            $processedExtension
        );

        $refundRate = $this->calculateRefundRate($refundOrder, $item, $extension);

        // 执行退款
        if($item['type'] != 7) {
            $this->executeRefund($refundOrder, $item, $refundService, $merchantRepository, $serverType, $refundPrice);
        }

        return [
            'refundPrice' => $refundPrice,
            'refundRate' => $refundRate,
            'extension' => $extension
        ];
    }

    /**
     * 计算分佣金额
     */
    private function calculateExtension($refundOrder, $item, $index, $totalItems, $totalExtension, $processedExtension): string
    {
        if ($refundOrder->order->commission_rate <= 0) {
            return '0.00';
        }

        if ($index == ($totalItems - 1)) {
            return bcsub($totalExtension, $processedExtension, 2);
        }

        $ratio = bcdiv($item['data']['refund_price'], $refundOrder->refund_price, 2);
        return bcmul($ratio, $totalExtension, 2);
    }

    /**
     * 计算退款费率
     */
    private function calculateRefundRate($refundOrder, $item, $extension): string
    {
        if ($refundOrder->order->commission_rate <= 0) {
            return '0.00';
        }

        $commissionRate = bcdiv($refundOrder->order->commission_rate, 100, 6);
        $refundBase = bcsub($item['data']['refund_price'], $extension, 2);

        return bcmul($commissionRate, $refundBase, 2);
    }

    /**
     * 执行退款操作
     */
    private function executeRefund($refundOrder, $item, $refundService, $merchantRepository, $serverType, $refundPrice): void
    {
        try {
            $orderType = isset($item['presell']) && $item['presell'] ? 'presell' : 'order';
            if ($item['type'] == 0) {
                $this->refundBill($item, $refundOrder->uid, $refundOrder->refund_order_id);
                $merchantRepository->subLockMoney($refundOrder->mer_id, $orderType, $item['id'], $refundPrice);
            } else {
                $refundService->payOrderRefund($item['sn'], $item['data']);
                if (in_array($item['type'],[self::TYPE_IS_COMBINE,self::TYPE_IS_PARTNER])) {
                    $this->handleCombineRefund($refundOrder, $item, $orderType, $refundPrice);
                } else {
                    $merchantRepository->subLockMoney($refundOrder->mer_id, $orderType, $item['id'], $refundPrice);
                }
            }
        } catch (Exception $e) {
            throw new ValidateException($e->getMessage());
        }
    }

    /**
     * 处理合并支付退款
     */
    private function handleCombineRefund($refundOrder, $item, $orderType, $refundPrice): void
    {
        $profitsharingRepo = app()->make(StoreOrderProfitsharingRepository::class);

        if ($orderType === 'presell') {
            $profitsharingRepo->refundPresallPrice($refundOrder, $item['data']['refund_price'], $refundPrice);
        } else {
            $profitsharingRepo->refundPrice($refundOrder, $item['data']['refund_price'], $refundPrice);
        }
    }

    /**
     * 记录财务交易
     */
    private function recordFinancialTransactions($refundOrder, $refundResult): void
    {
        $financialRepo = app()->make(FinancialRecordRepository::class);
        $user = $refundOrder->user;

        // 记录退款金额
        $financialRepo->inc([
            'order_id' => $refundOrder->refund_order_id,
            'order_sn' => $refundOrder->refund_order_sn,
            'user_info' => $user->nickname ?? '',
            'user_id' => $refundOrder->uid ?? 0,
            'financial_type' => 'refund_true',
            'number' => $refundResult['refundPriceAll'],
            'type' => 1,
            'pay_type' => $refundOrder->order->pay_type,
        ], $refundOrder->mer_id);

        // 记录退款手续费
        $financialRepo->inc([
            'order_id' => $refundOrder->refund_order_id,
            'order_sn' => $refundOrder->refund_order_sn,
            'user_info' => $user->nickname ?? '',
            'user_id' => $refundOrder->uid ?? 0,
            'type' => 1,
            'financial_type' => 'refund_charge',
            'number' => $refundResult['refundRate'],
            'pay_type' => $refundOrder->order->pay_type
        ], $refundOrder->mer_id);
    }

    /**
     * 检查退款项是否有效
     */
    private function isValidRefundItem(array $item): bool
    {
        return ($item['data']['pay_price'] > 0 && $item['data']['refund_price'] > 0);
    }
}
