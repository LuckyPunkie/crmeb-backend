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

use app\common\dao\delivery\DeliveryOrderDao;
use app\common\model\delivery\DeliveryStation;
use app\common\model\store\order\StoreOrder;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\order\StoreOrderStatusRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\system\serve\ServeOrderRepository;
use app\common\repositories\user\UserRepository;
use crmeb\services\DeliverySevices;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use think\facade\Route;

/**
 * 同城配送订单
 */
class DeliveryOrderRepository extends BaseRepository
{

    protected $statusData = [
        2 => '待取货',
        3 => '配送中',
        4 => '已完成',
        -1 => '已取消',
        9 => '物品返回中',
        10 => '物品返回完成',
        100 => '骑士到店',
    ];

    protected $message = [
        2 => StoreOrderStatusRepository::ORDER_DELIVERY_CITY_WAITING,
        3 => StoreOrderStatusRepository::ORDER_DELIVERY_CITY_ING,
        4 => StoreOrderStatusRepository::ORDER_DELIVERY_CITY_OVER,
        -1 => StoreOrderStatusRepository::ORDER_DELIVERY_CITY_CANCEL,
        9 => StoreOrderStatusRepository::ORDER_DELIVERY_CITY_REFUNDING,
        10 => StoreOrderStatusRepository::ORDER_DELIVERY_CITY_REFUND,
        100 => StoreOrderStatusRepository::ORDER_DELIVERY_CITY_ARRIVE,
    ];

    public function __construct(DeliveryOrderDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     *  商户的同城配送订单列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     */
    public function merList(array $where, int $page, int $limit)
    {
        $query = $this->dao->search($where)->with(
            [
                'station',
                'storeOrder' => function ($query) {
                    $query->with(['take' => function ($query) {
                        $query->field('station_name,station_id');
                    }]);
                }
            ])->order('create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();

        return compact('count', 'list');
    }

    /**
     * 平台同城配送订单列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     */
    public function sysList(array $where, int $page, int $limit)
    {
        $query = $this->dao->getSearch($where)->with([
            'merchant' => function ($query) {
                $query->field('mer_id,mer_name');
            },
            'station',
            'storeOrder' => function ($query) {
                $query->field('order_id,order_sn');
            },
        ])->order('create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();

        return compact('count', 'list');
    }

    /**
     * 需要配置的订单详情
     * @param int $id
     * @param int|null $merId
     * @return \app\common\model\BaseModel|array|mixed|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/5
     */
    public function detail(int $id, ?int $merId)
    {
        $where[$this->dao->getPk()] = $id;
        if ($merId) $where['mer_id'] = $merId;
        $res = $this->dao->getSearch($where)->with([
            'merchant' => function ($query) {
                $query->field('mer_id,mer_name');
            },
            'station',
        ])->find();
         // 先检查结果是否存在
        if (!$res) throw new ValidateException('订单不存在');

         // 然后再处理订单详情
        try {
            $order = DeliverySevices::create($res['station_type'])->getOrderDetail($res);
            $res['data'] = [
                'order_code' => $order['order_code'],
                'to_address' => $order['to_address'],
                'from_address' => $order['from_address'],
                'state' => $order['state'],
                'note' => $order['note'],
                'order_price' => $order['order_price'],
                'distance' => round(($order['distance'] / 1000), 2) . ' km',
            ];
        } catch (\Exception $e) {
            // 记录异常并返回友好提示
            Log::error('获取配送订单详情失败：' . $e->getMessage());
            throw new ValidateException('获取订单详情失败，请稍后再试');
        }
        return $res;
    }

    /**
     * 取消配送订单
     * @param $id
     * @return \FormBuilder\Form
     * @author Qinii
     */
    public function cancelForm($id, $merId)
    {
        $formData = $this->dao->get($id);
        if (!$formData) throw new ValidateException('订单不存在');
        if ($formData['status'] == -1) throw new ValidateException('订单已取消，无法操作');

        $form = Elm::createForm(Route::buildUrl('merchantStoreDeliveryOrderCancel', ['id' => $id])->build());
        $rule = [];
        if ($formData['station_type'] == DeliverySevices::DELIVERY_TYPE_DADA) {
            $options = DeliverySevices::create(DeliverySevices::DELIVERY_TYPE_DADA, $merId)->reasons();
            $rule[] = Elm::select('reason', '取消原因：')->placeholder('请选择取消原因')->options($options);
            $rule[] = Elm::text('cancel_reason', '其他原因说明：')->placeholder('请输入其它原因说明');
        }
        if (in_array($formData['station_type'], [DeliverySevices::DELIVERY_TYPE_UU, 0])) {
            $rule[] = Elm::input('reason', '取消原因：')->placeholder('请输入取消原因')->required('请输入取消原因');
        }
        $form->setRule($rule);
        return $form->setTitle('取消同城配送订单', $formData);
    }

    /**
     *  取消表单
     * @param $id
     * @param $merId
     * @param $reason
     * @return mixed
     * @author Qinii
     */
    public function cancel($id, $merId, $reason)
    {
        $order = $this->dao->getWhere([$this->dao->getPk() => $id, 'mer_id' => $merId]);
        if (!$order) throw new ValidateException('配送订单不存在');
        if ($order['status'] == -1) throw new ValidateException('请勿重复操作');
        // if ($order['status'] == 4) throw new ValidateException('该订单已完成，无法取消');
        $data = [
            'origin_id' => $order['order_sn'],
            'order_code' => $order['order_code'],
            'reason' => is_array($reason) ? $reason['reason'] : $reason,
        ];
        return Db::transaction(function () use ($order, $data, $merId) {
            if($order['station_type']) {
                if ($order['station_type'] == DeliverySevices::DELIVERY_TYPE_DADA) {
                    $options = DeliverySevices::create(DeliverySevices::DELIVERY_TYPE_DADA, $merId)->reasons();
                    $data['reason'] = $options[9]['value'];
                    $data['cancel_reason'] = $options[9]['label'];
                }
                DeliverySevices::create($order['station_type'], $merId)->cancelOrder($data);
            }else{
                $order->status = -1;
                $order->reason = $data['reason'];
                $order->save();
            }
            // 订单取消后操作
            $this->cancelAfter($order);
            //订单记录
            $statusRepository = app()->make(StoreOrderStatusRepository::class);
            $orderStatus = [
                'order_id' => $order->order_id,
                'order_sn' => $order->order_sn,
                'type' => $statusRepository::TYPE_ORDER,
                'change_message' => '同城配送订单已取消',
                'change_type' => $statusRepository::ORDER_DELIVERY_CITY_CANCEL,
            ];
            $statusRepository->createAdminLog($orderStatus);
        });
    }

    /**
     * 订单取消后操作
     * @param $deliveryOrder
     * @param $deductFee
     * @param $mark
     * @return void
     * @author Qinii
     */
    public function cancelAfter($deliveryOrder)
    {
        //修改配送订单
        // $deliveryOrder->status = -1;
        // $deliveryOrder->reason = $mark;
        // $deliveryOrder->deduct_fee = $deductFee;
        // $deliveryOrder->save();

        //修改商城订单
        $res = app()->make(StoreOrderRepository::class)->get($deliveryOrder['order_id']);
        if($res->status == 1) {
            $res->status = 0;
            if ($deliveryOrder['station_type']) {
                $merchantTakeInfo = $res['merchant_take_info'];
                $merchantTakeInfo[$res['mer_id']]['sync_status'] = -1;
                $merchantTakeInfo[$res['mer_id']]['sync_desc'] = '后台手动取消';
                $res['merchant_take_info'] = json_encode($merchantTakeInfo);
            }
            $res->save();
        }

        //修改商户
        // $merchant = app()->make(MerchantRepository::class)->get($deliveryOrder['mer_id']);
        // $balance = bcadd(bcsub($deliveryOrder['fee'], $deductFee, 2), $merchant->delivery_balance, 2);
        // $merchant->delivery_balance = $balance;
        // $merchant->save();
    }


    /**
     *  同城配送订单信息回调
     * @param $data
     * @author Qinii
     * @day 2/17/22
     */
    public function notify($data)
    {
        //达达
        /**
         * 订单状态(待接单＝1,待取货＝2,配送中＝3,已完成＝4,已取消＝5, 指派单=8,妥投异常之物品返回中=9, 妥投异常之物品返回完成=10, 骑士到店=100,创建达达运单失败=1000 可参考文末的状态说明）
         */
        Log::info('同城回调参数：' . var_export(['=======', $data, '======='], 1));
        if (isset($data['data'])) {
            $data = json_decode($data['data'], 1);
        }
        if(isset($data['biz'])) {
            $data = json_decode($data['biz'], 1);
        }

        $reason = '';
        $deductFee = 0;
        $delivery = [];
        if (isset($data['order_status'])) {
            $order_sn = $data['order_id'];
            if ($data['order_status'] == 1) {
                $orderData = $this->dao->getSearch(['sn' => $data['order_id']])->find();
                if (!$orderData['finish_code']) {
                    $orderData->finish_code = $data['finish_code'];
                    $orderData->save();
                }
                return;
            } else if (in_array($data['order_status'], [2, 3, 4, 5, 9, 10, 100])) {
                $status = $data['order_status'];
                if ($data['order_status'] == 5) {
                    $msg = [
                        '取消：',
                        '达达配送员取消：',
                        '商家主动取消：',
                        '系统或客服取消：',
                    ];
                    //1:达达配送员取消；2:商家主动取消；3:系统或客服取消；0:默认值
                    $status = -1;
                    $reason = $msg[$data['cancel_from']] . $data['cancel_reason'];
                }
                $deductFee = $data['deductFee'] ?? 0;
                if (isset($data['dm_name']) && $data['dm_name']) {
                    $delivery = [
                        'delivery_name' => $data['dm_name'],
                        'delivery_id' => $data['dm_mobile'],
                    ];
                }

            }
        } else if (isset($data['state'])) {  //uu
            $deliveryOrder = $this->dao->getWhere(['order_code' => $data['orderCode']]);
            $order_sn = $deliveryOrder['order_sn'];
            //当前状态 1下单成功 2跑男取消（订单状态回退到1） 3跑男抢单 4已到达 5已取件 6到达目的地 10收件人已收货 -1订单取消 -2客服取消（订单状态-1） -3超时无人接单取消（订单状态-1）
            switch ($data['state']) {
                case 2:
                    $deliveryOrder->status = 0;
                    $deliveryOrder->reason = '跑男取消';
                    $deliveryOrder->save();

                    break;
                case 3:
                    $status = 2;
                    break;
                case 4:
                    $status = 100;
                    break;
                case 5:
                    $status = 3;
                    break;
                case 6:
                    $status = 3;
                    break;
                case 10:
                    $status = 4;
                    break;
                case -1:
                    $status = -1;
                    $reason = '商家主动取消';
                    break;
                case -2:
                    $status = -1;
                    $reason = '客服取消';
                    break;
                case -3:
                    $status = -1;
                    $reason = '超时无人接单取消';
                    break;
                default:
                    break;
            }
            if (isset($data['driverName']) && $data['driverName']) {
                $delivery = [
                    'delivery_name' => $data['driverName'],
                    'delivery_id' => $data['driverMobile'],
                ];
            }
        }

        if (isset($order_sn) && isset($status)) {
            $res = $this->dao->getWhere(['order_sn' => $order_sn]);
            if ($res) {
                $this->notifyAfter($status, $reason, $res, $delivery, $deductFee);
            } else {
                Log::info('同城配送回调，未查询到订单：' . $order_sn);
            }
        }
    }

    /**
     * 同城配送回调
     * @param $status
     * @param $reason
     * @param $res
     * @param $data
     * @param $deductFee
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/5
     */
    public function notifyAfter($status, $reason, $res, $data, $deductFee)
    {
        if (!isset($this->statusData[$status])) return;

        $make = app()->make(StoreOrderRepository::class);
        $orderData = $make->get($res['order_id']);

        return Db::transaction(function () use ($res, $data, $status, $reason, $deductFee, $orderData, $make) {
            $res->status = $status;
            $res->reason = $reason;
            $res->deduct_fee = $deductFee;
            $res->save();
            //订单记录
            $statusRepository = app()->make(StoreOrderStatusRepository::class);
            $message = '订单同城配送【' . $this->statusData[$status] . '】';
            $orderStatus = [
                'order_id' => $orderData['order_id'],
                'order_sn' => $orderData['order_sn'],
                'type' => $statusRepository::TYPE_ORDER,
                'change_message' => $message,
                'change_type' => $this->message[$status],
            ];
            $statusRepository->createSysLog($orderStatus);
            if ($status == 2 && !empty($data))
                $make->update($res['order_id'], $data);
            if ($status == 4) {
                $order = $make->get($res['order_id']);
                $user = app()->make(UserRepository::class)->get($order['uid']);
                $make->update($res['order_id'], ['status' => 2]);
                $make->takeAfter($order, $user);
            }
            // if ($status == -1) {
            //     $this->cancelAfter($res, $deductFee, $reason);
            // }
        });
    }

    /**
     * 创建配送订单
     * @param $id
     * @param $merId
     * @param $data
     * @param $order
     * @return bool
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/5
     */
    public function create($id, $merId, $data, $order)
    {
        $type = $order->take->type;
        $where = ['station_id' => $order['merchant_take_id'], 'mer_id' => $merId, 'status' => 1, 'type' => $type];
        $station = app()->make(DeliveryStationRepository::class)->getWhere($where);

        if (!$station) {
            Log::info('同城配送创建订单，门店信息不存在');
            return false;
        }
        if (!$station['city_name']) {
            Log::info('同城配送创建订单，门店缺少所在城市，请重新编辑门店信息');
            return false;
        }
        //地址转经纬度
        $addres = lbs_address([], $order['user_address']);
        $location = $addres['location'];
        // UU坐标转换为百度坐标
        if ($type == DeliverySevices::DELIVERY_TYPE_UU) {
            [$station['lng'], $station['lat']] = gcj02ToBd09($station['lng'], $station['lat']);
            [$location['lng'], $location['lat']] = gcj02ToBd09($addres['location']['lng'], $addres['location']['lat']);
        }

        try {
            $service = DeliverySevices::create($type, $merId);
            $orderSn = $this->getOrderSn();
            $callbackUrl = rtrim(systemConfig('site_url'), '/') . '/api/notice/callback';
            $weight = array_sum(array_column(array_column(array_column($data, 'cart_info'), 'productAttr'), 'weight'));

            $priceParams = $this->getPriceParams($station, $order, $location, $type, $merId);
            $priceParams['cargo_weight'] = max((int)$weight, 1);
            $priceParams['shop_no'] = $station['origin_shop_id'];
            $priceParams['origin_id'] = $orderSn;
            $priceParams['callback_url'] = $callbackUrl;
            $priceParams['receiver_lat'] = $addres['location']['lat'];
            $priceParams['receiver_lng'] = $addres['location']['lng'];
            //计算价格
            $priceData = $service->getOrderPrice($priceParams);
            if ($type == DeliverySevices::DELIVERY_TYPE_UU) {
                $priceData['shop_no'] = $station['origin_shop_id'];
                $priceData['receiver'] = $order['real_name'];
                $priceData['receiver_phone'] = $order['user_phone'];
                $priceData['note'] = $order['mark'];
                $priceData['callback_url'] = $callbackUrl;
                $priceData['push_type'] = "OPEN_ORDER"; // 测试订单:TEST_ORDER 开放订单:OPEN_ORDER
                $priceData['special_type'] = 'NOT_NEED_WARM'; // NEED_WARM
            }
            //发布订单
            $res = $service->addOrder($priceData);

            $ret = [
                'station_id' => $order['merchant_take_id'],
                'order_sn' => $orderSn,
                'city_code' => $station['city_name'],
                'receiver_phone' => $order['user_phone'],
                'user_name' => $order['real_name'],
                'from_address' => $station['station_address'],
                'to_address' => $order['user_address'],
                'order_code' => $type == 2 ? $res['orderCode'] : $priceData['deliveryNo'],
                'order_id' => $id,
                'mer_id' => $merId,
                'status' => $res['status'] ?? 0,
                'station_type' => $type,
                'to_lat' => $addres['location']['lat'],
                'to_lng' => $addres['location']['lng'],
                'from_lat' => $station['lat'],
                'from_lng' => $station['lng'],
                'distance' => $priceData['distance'],
                'fee' => $type == 2 ? bcdiv($priceData['needPayMoney'], 100, 2) : $priceData['fee'],
                'mark' => $order['mark'],
                'uid' => $order['uid'],
                'reason' => ''
            ];
            //入库操作
            $this->dao->create($ret);

            return true;
        } catch (\Exception $e) {
            $merchantTakeInfo = $order['merchant_take_info'] ?? [];
            $merchantTakeInfo[$merId]['sync_status'] = -1;
            $merchantTakeInfo[$merId]['sync_desc'] = $e->getMessage();

            $order['merchant_take_info'] = json_encode($merchantTakeInfo);
            $order->save();

            return false;
        }
    }

    /**
     * 获取订单价格参数
     * @param DeliveryStation $deliveryStation
     * @param StoreOrder $order
     * @param array $addres
     * @param int $type
     * @return array
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/5
     */
    public function getPriceParams(DeliveryStation $deliveryStation, StoreOrder $order, array $addres, int $type, $merId)
    {
        $data = [];
        $type = (int)$type;
        switch ($type) {
            case 1:
                $city = DeliverySevices::create(DeliverySevices::DELIVERY_TYPE_DADA, $merId)->getCity([]);
                $res = [];
                foreach ($city as $item) {
                    $res[$item['label']] = $item['key'];
                }
                $data = [
                    'city_code' => $res[$deliveryStation['city_name']],
                    'cargo_price' => $order['pay_price'],
                    'is_prepay' => 0,
                    'receiver_name' => $order['real_name'],
                    'receiver_address' => $order['user_address'],
                    'cargo_weight' => 0,
                    'receiver_phone' => $order['user_phone'],
                    'is_finish_code_needed' => 1,
                ];
                break;
            case 2:
                $data = [
                    'from_address' => $deliveryStation['station_address'],
                    'to_address' => $order['user_address'],
                    'city_name' => $deliveryStation['city_name'],
                    'goods_type' => $deliveryStation['business']['label'],
                    'send_type' => '0',
                    'to_lat' => $addres['lat'],
                    'to_lng' => $addres['lng'],
                    'from_lat' => $deliveryStation['lat'],
                    'from_lng' => $deliveryStation['lng'],
                ];
                break;
        }
        return $data;
    }

    public function getTitle()
    {
        $query = app()->make(MerchantRepository::class)->getSearch(['is_del' => 0]);
        $merchant = $query->count();
        $price = app()->make(ServeOrderRepository::class)
            ->getSearch(['type' => 10, 'status' => 1])->sum('pay_price');
        $balance = $query->sum('delivery_balance');
        return [
            [
                'className' => 'el-icon-s-order',
                'count' => $merchant,
                'field' => '个',
                'name' => '商户数'
            ],
            [
                'className' => 'el-icon-s-order',
                'count' => $price,
                'field' => '元',
                'name' => '商户充值总金额'
            ],
            [
                'className' => 'el-icon-s-order',
                'count' => $balance,
                'field' => '元',
                'name' => '商户当前余额'
            ],
        ];
    }

    /**
     * 销毁订单。
     * 本函数用于根据给定的订单ID和商家ID删除订单。首先，它会验证订单是否存在，如果不存在，则抛出一个异常；
     * 如果存在，则执行删除操作。
     *
     * @param int $id 订单ID。这是主键，用于唯一标识一个订单。
     * @param int $merId 商家ID。用于确定订单所属的商家。
     * @return bool 返回删除操作的结果。如果删除成功，返回true；否则，返回false。
     * @throws ValidateException 如果订单不存在，则抛出此异常。
     */
    public function destory($id, $merId)
    {
        // 根据订单ID和商家ID构建查询条件
        $where = [
            $this->dao->getPk() => $id, // 使用主键作为查询条件之一
            'mer_id' => $merId, // 商家ID作为查询条件之一
        ];

        // 根据查询条件查找订单
        $res = $this->dao->getSearch($where)->find();

        // 如果查询结果为空，即订单不存在，则抛出异常
        if (!$res) throw new ValidateException('订单不存在');

        // 删除订单，并返回删除结果
        return $this->dao->delete($id);
    }

    /**
     * 生成订单编号
     *
     * 本函数用于生成唯一的订单编号。编号由时间戳和随机数组成，确保了编号的唯一性和可追踪性。
     * 时间戳部分精确到毫秒，增加了编号的唯一性，随机数部分进一步确保了即使在毫秒级别内有多个订单生成，也能得到不同的编号。
     *
     * @return string 返回生成的订单编号
     */
    public function getOrderSn()
    {
        // 获取当前时间的微秒和秒部分
        list($msec, $sec) = explode(' ', microtime());

        // 将微秒和秒转换为毫秒，并格式化为整数，用于订单编号的时间戳部分
        $msectime = number_format((floatval($msec) + floatval($sec)) * 1000, 0, '', '');

        // 生成订单编号：前缀 + 毫秒时间戳 + 随机数
        // 使用'dc'作为前缀，表示订单编号的来源或类型
        // 随机数生成范围确保大于毫秒时间戳转换的整数，避免重复，并且预设了一个最小值98369，以保持编号的一定长度和特征
        $orderId = 'dc' . $msectime . random_int(10000, max(intval($msec * 10000) + 10000, 98369));

        return $orderId;
    }


    /**
     * 根据订单ID和用户ID查询订单信息
     *
     * 本函数用于通过给定的订单ID和用户ID，从数据库中检索相应的订单信息。
     * 如果找不到匹配的订单，将抛出一个验证异常，指出订单不存在。
     * 这是对前端接口的封装，确保了只有合法的订单才能被查询和访问。
     *
     * @param int $id 订单ID
     * @param int $uid 用户ID
     * @return array 查询到的订单信息
     * @throws ValidateException 如果订单不存在则抛出异常
     */
    public function show(int $id, int $uid)
    {
        // 构建查询条件
        $where['order_id'] = $id;
        $where['uid'] = $uid;

        // 执行查询，同时加载订单状态和订单详情
        $res = $this->dao->getSearch($where)->with(['storeOrderStatus', 'storeOrder'])->find();

        // 如果查询结果为空，则抛出异常提示订单不存在
        if (!$res) throw new ValidateException('订单不存在');

        // 返回查询结果
        return $res;
    }

    const ENABLE_ASSIGNED = [
        'RECEIVE' => 0, // 领取
        'DISPATCH' => 1, // 指派
    ];
    const DELIVERY_TYPE = [
        0 => '商家配送',
        1 => '达达配送',
        2 => 'UU配送',
    ];
    /**
     * 配送员领取
     *
     * @param int $merId
     * @param int $orderId
     * @param int $serviceId
     * @return void
     */
    public function selfReceive(int $merId, int $orderId, int $serviceId)
    {
        if(!merchantConfig($merId, 'mer_delivery_order_status')) {
            throw new ValidateException('商家未开启配送员抢单，无法接单');
        }

        // 检查订单是否存在
        $order = $this->orderInfo($orderId, $merId, 0);
        $params['service_id'] = $serviceId;
        return $this->dispatch($order, $merId, $params, self::ENABLE_ASSIGNED['RECEIVE']);
    }
    /**
     * 商家指派/管理端指派
     *
     * @param integer $orderId
     * @param integer $merId
     * @param array $params
     * @param integer $serviceLogId
     * @return void
     */
    public function merDispatch(int $orderId, int $merId, array $params, int $serviceLogId = 0)
    {
        // 检查订单是否存在
        $order = $this->orderInfo($orderId, $merId, 0);
        return $this->dispatch($order, $merId, $params, self::ENABLE_ASSIGNED['DISPATCH'], $serviceLogId);
    }
    /**
     * 同城配送派单
     *
     * @param $order
     * @param int $merId 商家ID
     * @param array $params 派单参数
     * @return bool 派单结果
     */
    public function dispatch($order, int $merId, array $params, int $enableAssigned, int $serviceLogId = 0)
    {
        // 检查配送站是否存在
        $station = app()->make(DeliveryStationRepository::class)->deliveryStationInfo($order['merchant_take_id'], $merId);
        if($station['type']) {
            throw new ValidateException("用户所选自提点不支持商家派单，请返回列表同步第三方配送订单。当前配送方式为【{$this::DELIVERY_TYPE[$station['type']]}】");
        }
        // 数据处理
        $ret = [
            'station_id' => $order['merchant_take_id'],
            'order_sn' => $this->getOrderSn(),
            'city_code' => $station['city_name'],
            'receiver_phone' => $order['user_phone'],
            'user_name' => $order['real_name'],
            'from_address' => $station['station_address'],
            'to_address' => $order['user_address'],
            'order_code' => '',
            'order_id' => $order['order_id'],
            'mer_id' => $merId,
            'status' => 3,
            'station_type' => 0,
            'distance' => '',
            'fee' => $order['pay_postage'],
            'mark' => $order['mark'],
            'uid' => $order['uid'],
            'reason' => '',
            'service_id' => $params['service_id'],
            'service_fee' => $this->getServiceFee($station, $order),
        ];
        //入库操作
        try {
            Db::transaction(function () use ($ret, $order, $enableAssigned, $serviceLogId) {
                // 创建配送订单
                $this->dao->create($ret);
                // 更新订单状态
                $order->enable_assigned = $enableAssigned;
                $order->delivery_type = 5;
                $order->status = 1;
                $order->save();
                // 构建订单状态变更信息
                $orderStatus = [
                    'order_id' => $order->order_id,
                    'order_sn' => $order->order_sn,
                    'type' => StoreOrderStatusRepository::TYPE_ORDER,
                    'change_message' => '订单已派单',
                    'change_type' => StoreOrderStatusRepository::DELIVERY_ORDER_DISPATCH,
                ];
                // 根据服务人员ID是否存在，分别记录服务人员日志或管理员日志
                $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
                if ($serviceLogId) {
                    $storeOrderStatusRepository->createServiceLog($serviceLogId, $orderStatus);
                } else {
                    $storeOrderStatusRepository->createAdminLog($orderStatus);
                }
            });

            return true;
        } catch (\Exception $e) {
            throw new ValidateException('失败：' . $e->getMessage());
        }
    }
    /**
     * 商家改派/管理端改派
     *
     * @param integer $orderId
     * @param integer $merId
     * @param array $params
     * @param integer $serviceLogId
     * @return void
     */
    public function merUpdateDispatch(int $orderId, int $merId, array $params, int $serviceLogId = 0)
    {
        // 检查订单是否存在， 1 表示配送中
        $order = $this->orderInfo($orderId, $merId, 1);
        // 检查配送站是否存在
        $station = app()->make(DeliveryStationRepository::class)->deliveryStationInfo($order['merchant_take_id'], $merId);
        if($station['type']) {
            throw new ValidateException('配送方式错误无法派单,请检查配送设置。当前设置配送方式为'.self::DELIVERY_TYPE[$station['type']]);
        }
        //入库操作
        try {
            Db::transaction(function () use ($params, $order, $serviceLogId) {
                // 更新配送员id
                $order->deliveryOrder->service_id = $params['service_id'];
                $order->deliveryOrder->save();
                // 构建订单状态变更信息
                $orderStatus = [
                    'order_id' => $order->order_id,
                    'order_sn' => $order->order_sn,
                    'type' => StoreOrderStatusRepository::TYPE_ORDER,
                    'change_message' => '订单已改派',
                    'change_type' => StoreOrderStatusRepository::DELIVERY_ORDER_DISPATCH,
                ];
                // 根据服务人员ID是否存在，分别记录服务人员日志或管理员日志
                $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
                if ($serviceLogId) {
                    $storeOrderStatusRepository->createServiceLog($serviceLogId, $orderStatus);
                } else {
                    $storeOrderStatusRepository->createAdminLog($orderStatus);
                }
            });

            return true;
        } catch (\Exception $e) {
            throw new ValidateException('失败：' . $e->getMessage());
        }
    }
    /**
     * 订单信息
     * @param int $orderId
     * @param int $merId
     * @param string $status
     * @return mixed
     */
    protected function orderInfo(int $orderId, int $merId, $status = '')
    {
        $where = ['order_id' => $orderId, 'mer_id' => $merId, 'order_type' => 2, 'paid' => 1, 'is_del' => 0];
        if ($status !== '') {
            $where['status'] = $status;
        }
        $order = app()->make(StoreOrderRepository::class)->getWhere($where);
        if (!$order) {
            throw new ValidateException('订单状态异常，请检查');
        }

        return $order;
    }
    /**
     * 配送订单确认
     * @param int $id
     * @param int $merId
     * @return bool
     */
    public function confirm(int $orderId, int $merId, int $serviceLogId = 0)
    {
        $order = $this->orderInfo($orderId, $merId);
        if($order->status == 2) {
            throw new ValidateException('订单已确认！');
        }
        try {
            Db::transaction(function () use ($order, $serviceLogId) {
                // 配送完成
                if($order->deliveryOrder) {
                    $order->deliveryOrder->status = 4;
                    $order->deliveryOrder->save();
                }
                // 更新订单状态：待评价
                $order->status = 2;
                $order->verify_time = date('Y-m-d H:i:s');
                $order->save();
                // 构建订单状态变更信息
                $orderStatus = [
                    'order_id' => $order->order_id,
                    'order_sn' => $order->order_sn,
                    'type' => StoreOrderStatusRepository::TYPE_ORDER,
                    'change_message' => '订单已确认',
                    'change_type' => StoreOrderStatusRepository::ORDER_STATUS_TAKE,
                ];
                //订单记录
                $user = app()->make(UserRepository::class)->get($order['uid']);
                app()->make(StoreOrderRepository::class)->takeAfter($order, $user);
                // 根据服务人员ID是否存在，分别记录服务人员日志或管理员日志
                $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
                if ($serviceLogId) {
                    $storeOrderStatusRepository->createServiceLog($serviceLogId, $orderStatus);
                } else {
                    $storeOrderStatusRepository->createAdminLog($orderStatus);
                }
            });

            return true;
        } catch (\Exception $e) {
            throw new ValidateException('失败：' . $e->getMessage());
        }
    }
    /**
     * 获取配送订单ID
     * @param array $where
     * @return array
     */
    public function getOrderIds(array $where)
    {
        $orderIds = $this->dao->search($where)->column('order_id');
        return $orderIds;
    }
    /**
     * 配送提成计算
     *
     * @param object $station
     * @param object $order
     * @return void
     */
    protected function getServiceFee($station, $order)
    {
        $deliveryConfigRepository = app()->make(DeliveryConfigRepository::class);
        $deliverySettings = $deliveryConfigRepository->getDeliveryConfig($order['mer_id']);
        if (!$deliverySettings) {
            throw new ValidateException('商户：【' . $deliverySettings['merchant']['mer_name'] . '】，未设置配送费用信息，无法计算配送费');
        }
        // 如果不包邮，直接计算
        if($order['pay_postage'] > 0) {
            return bcmul(bcdiv($deliverySettings['commission_rate'], 100, 2), $order['pay_postage'], 2);
        }
        // 如果包邮，需要先获取到配送费再计算
        // 计算重量
        $totalWeight = 0;
        foreach($order['orderProduct'] as $item) {
            $totalWeight += $item['cart_info']['productAttr']['weight'] * $item['product_num'];
        }

        $fee = $deliveryConfigRepository->deliveryFee(
            $station['station_id'],
            $totalWeight,
            $deliverySettings->toArray(),
            $order['user_address']
        );

        return bcmul(bcdiv($deliverySettings['commission_rate'], 100, 2), $fee, 2);
    }

    public function getDeliveryTrack($deliveryOrder)
    {
        try {
            $data['order_code'] = $deliveryOrder['order_code'];
            $data['origin_id'] = $deliveryOrder['order_sn'];

            return DeliverySevices::create($deliveryOrder['station_type'], $deliveryOrder['mer_id'])->getDeliveryTrack($data);
        } catch (\Exception $e) {
            return [];
        }
    }
}
