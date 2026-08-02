<?php
// +----------------------------------------------------------------------
// | 用户端 - 扫码下单提交
// +----------------------------------------------------------------------

namespace app\controller\api\store\scanOrder;

use think\App;
use crmeb\basic\BaseController;
use app\common\model\store\order\StoreCart;
use app\common\model\store\order\StoreOrder;
use app\common\repositories\store\order\StoreOrderCreateRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\scanOrder\ScanOrderConfigRepository;
use app\common\repositories\store\scanOrder\ScanOrderTableRepository;
use app\common\repositories\store\scanOrder\ScanOrderVoiceRepository;
use crmeb\services\LockService;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;

class ScanOrderOrder extends BaseController
{
    const SCENE = 'scan_order';

    protected $tableRepository;
    protected $configRepository;

    public function __construct(
        App $app,
        ScanOrderTableRepository $tableRepository,
        ScanOrderConfigRepository $configRepository
    ) {
        parent::__construct($app);
        $this->tableRepository = $tableRepository;
        $this->configRepository = $configRepository;
    }

    /**
     * POST /api/scan_order/order/submit
     * 参数：mer_id, table_id, sign, pay_type(需付款时), real_name, phone
     */
    public function submit(StoreOrderCreateRepository $orderCreateRepository, StoreOrderRepository $orderRepository)
    {
        $merId = (int)$this->request->param('mer_id', 0);
        $tableId = (int)$this->request->param('table_id', 0);
        $sign = (string)$this->request->param('sign', '');
        $payTypeName = (string)$this->request->param('pay_type', '');
        $user = $this->request->userInfo();
        $uid = (int)$user->uid;

        $table = $this->tableRepository->assertTableAccess($merId, $tableId, $sign, true);
        $config = $this->configRepository->getConfig($merId);
        $needPay = (int)$config['need_pay'] === 1;

        // 提交前合并游客本店车，避免加购在游客态、提交在登录态时为空
        $tourist = trim((string)$this->request->param('tourist_unique_key', ''));
        if ($tourist !== '') {
            try {
                app()->make(ScanOrderCart::class)->mergeTouristCart($uid, $tourist, $merId);
            } catch (\Throwable $e) {
                // 合并失败不阻断，继续查登录用户车
            }
        }

        $cartIds = StoreCart::getDB()
            ->where([
                'uid' => $uid,
                'mer_id' => $merId,
                'cart_scene' => self::SCENE,
                'is_del' => 0,
                'is_new' => 0,
                'is_pay' => 0,
            ])
            ->column('cart_id');
        if (!$cartIds) {
            return app('json')->fail('本店购物车为空');
        }

        if ($needPay) {
            if ($payTypeName === '' || !in_array($payTypeName, StoreOrderRepository::PAY_TYPE, true)) {
                return app('json')->fail('请选择支付方式');
            }
        } else {
            // 免支付：走线下支付类型，创建后直接标记已支付/已提交
            $payTypeName = 'offline';
            if (!in_array($payTypeName, StoreOrderRepository::PAY_TYPE, true)) {
                $payTypeName = StoreOrderRepository::PAY_TYPE[0] ?? 'weixin';
            }
        }

        // 复用商城到店自提下单：会校验收货人姓名/手机号（UserAddressValidate::take）
        // 扫码点餐页不收地址，用账号手机；无效则用合规占位号，避免「收货人电话格式不符」
        $realName = trim((string)$this->request->param('real_name', $user['nickname'] ?? '顾客'));
        $phone = trim((string)$this->request->param('phone', $user['phone'] ?? ''));
        $phone = preg_replace('/\D+/', '', $phone);
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            $phone = '13000000000';
        }
        $post = [
            'real_name' => $realName ?: '顾客',
            'phone' => $phone,
        ];

        // 到店自提，避免收货地址校验
        $takes = [$merId];
        $key = 'scan_' . $uid . '_' . $merId . '_' . time() . '_' . mt_rand(1000, 9999);

        try {
            $orderInfo = $orderCreateRepository->v2CartIdByOrderInfo(
                $user,
                $cartIds,
                $takes,
                [],
                false,
                0,
                []
            );
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage() ?: '订单预览失败');
        }

        $cacheKey = 'order_create_cache' . $uid . '_' . $key;
        Cache::set($cacheKey, $orderInfo, 600);

        $payType = array_search($payTypeName, StoreOrderRepository::PAY_TYPE, true);
        if ($payType === false) {
            return app('json')->fail('支付方式无效');
        }

        $userRemark = trim((string)$this->request->param('remark', ''));
        if (mb_strlen($userRemark) > 200) {
            $userRemark = mb_substr($userRemark, 0, 200);
        }
        $markText = '扫码下单:' . ($table['table_label'] ?? '') . '(台号ID:' . $tableId . ')';
        if ($userRemark !== '') {
            $markText .= '；备注:' . $userRemark;
        }
        $mark = [
            $merId => $markText,
        ];

        try {
            $groupOrder = app()->make(LockService::class)->exec('order.create', function () use (
                $key, $orderCreateRepository, $payType, $user, $cartIds, $mark, $takes, $post
            ) {
                return $orderCreateRepository->v2CreateOrder(
                    $key,
                    (int)$payType,
                    $user,
                    $cartIds,
                    [],
                    $mark,
                    [],
                    $takes,
                    [],
                    false,
                    0,
                    $post
                );
            });
        } catch (\Throwable $e) {
            Cache::delete($cacheKey);
            return app('json')->fail($e->getMessage() ?: '下单失败');
        }

        $orders = StoreOrder::getDB()
            ->where('group_order_id', $groupOrder->group_order_id)
            ->select();

        $orderRemark = $userRemark !== '' ? ('扫码下单；' . $userRemark) : '扫码下单';
        foreach ($orders as $order) {
            StoreOrder::getDB()->where('order_id', $order['order_id'])->update([
                'is_scan_order' => 1,
                'scan_table_id' => $tableId,
                'scan_table_label' => (string)$table['table_label'],
                'remark' => $orderRemark,
            ]);
        }

        // 免支付：直接置为已支付，进入待服务/待发货
        if (!$needPay) {
            try {
                $orderRepository->paySuccess($groupOrder);
            } catch (\Throwable $e) {
                StoreOrder::getDB()->where('group_order_id', $groupOrder->group_order_id)->update([
                    'paid' => 1,
                    'pay_time' => date('Y-m-d H:i:s'),
                    'status' => 0,
                ]);
                Db::name('store_group_order')->where('group_order_id', $groupOrder->group_order_id)->update([
                    'paid' => 1,
                    'pay_time' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        foreach ($orders as $order) {
            // 自动打印（下单后）
            if ((int)$config['auto_print'] === 1) {
                try {
                    $orderRepository->autoPrinter((int)$order['order_id'], $merId, 2);
                } catch (\Throwable $e) {
                }
            }
        }

        // 语音播报（商家后台 WebSocket + APP 轮询队列 / UniPush）
        // 需：扫码下单设置里「手机端语音播报」开启；商家后台保持打开或商家 APP 在线
        if ((int)$config['voice_enable'] === 1) {
            try {
                $firstOrderId = 0;
                foreach ($orders as $o) {
                    $firstOrderId = (int)$o['order_id'];
                    break;
                }
                app()->make(ScanOrderVoiceRepository::class)->notify(
                    $merId,
                    (string)($groupOrder['group_order_sn'] ?? $groupOrder->group_order_id),
                    $firstOrderId,
                    (string)($table['table_label'] ?? '')
                );
            } catch (\Throwable $e) {
                \think\facade\Log::error('ScanOrder voice notify: ' . $e->getMessage());
            }
        }

        $resp = [
            'group_order_id' => (int)$groupOrder->group_order_id,
            'need_pay' => $needPay ? 1 : 0,
            'table_label' => (string)$table['table_label'],
            'pay_type' => $payTypeName,
        ];

        // 需付款：返回支付拉起信息（兼容 json->status 响应）
        if ($needPay) {
            try {
                $pay = $orderRepository->pay(
                    $payTypeName,
                    $user,
                    $groupOrder,
                    $this->request->param('return_url'),
                    (bool)$this->request->isApp()
                );
                $payData = $this->extractPayPayload($pay);
                // 兼容 {status,result:{config}} 或 data 嵌套
                if (isset($payData['data']) && is_array($payData['data'])) {
                    $payData = $payData['data'];
                }
                if ($payData) {
                    $resp['pay_status'] = (string)($payData['status'] ?? '');
                    $result = $payData['result'] ?? [];
                    if (is_array($result)) {
                        $resp['config'] = $result['config'] ?? $result;
                        if (isset($result['order_id'])) {
                            $resp['group_order_id'] = (int)$result['order_id'];
                        }
                    } elseif (isset($payData['config'])) {
                        $resp['config'] = $payData['config'];
                    }
                }
            } catch (\Throwable $e) {
                $resp['pay_error'] = $e->getMessage();
            }
        }

        return app('json')->success($resp);
    }

    protected function extractPayPayload($pay): array
    {
        if (is_array($pay)) {
            return $pay;
        }
        if (is_object($pay) && method_exists($pay, 'getData')) {
            $data = $pay->getData();
            return is_array($data) ? $data : [];
        }
        if (is_object($pay) && method_exists($pay, 'getContent')) {
            $raw = $pay->getContent();
            $arr = json_decode((string)$raw, true);
            return is_array($arr) ? $arr : [];
        }
        return [];
    }

}
