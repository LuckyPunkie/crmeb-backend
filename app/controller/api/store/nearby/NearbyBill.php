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

namespace app\controller\api\store\nearby;

use think\App;
use crmeb\basic\BaseController;
use crmeb\services\PayService;
use app\common\model\user\User;
use app\common\model\store\product\Product;
use app\common\repositories\store\nearby\NearbyShopBillOrderRepository;
use app\common\repositories\store\nearby\WelfareFreeOrderRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\validate\api\nearby\NearbyBillValidate;
use think\facade\Db;

/**
 * 附近好店买单 - C端API控制器
 * 支持游客扫码买单（uid=0）；余额 / 小程序 JSAPI 等仍需登录
 */
class NearbyBill extends BaseController
{
    protected $repository;
    protected $merchantRepository;

    /** 游客可用的支付方式（无需账号） */
    protected $guestPayTypes = ['mock', 'weixinApp', 'alipayApp'];

    /** 必须登录的支付方式 */
    protected $loginRequiredPayTypes = ['balance', 'routine', 'weixin', 'alipay'];

    public function __construct(App $app, NearbyShopBillOrderRepository $repository, MerchantRepository $merchantRepository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->merchantRepository = $merchantRepository;
    }

    /**
     * 创建买单订单
     * POST /api/nearby/bill/create
     */
    public function create(NearbyBillValidate $validate)
    {
        $data = $this->request->params([
            'mer_id',
            'pay_price',
            'pay_type',
            'coupon_id',
        ]);

        $validate->check($data);

        $uid = $this->currentUid();
        $user = $this->currentUser();

        if (in_array($data['pay_type'], $this->loginRequiredPayTypes, true) && $uid <= 0) {
            return app('json')->fail('请先登录后再使用该支付方式');
        }

        // 校验商户是否存在且在附近好店展示
        $merchant = $this->merchantRepository->get((int)$data['mer_id']);
        if (!$merchant || $merchant['is_del'] || $merchant['status'] != 1) {
            return app('json')->fail('商家不存在或已下架');
        }

        // 创建订单（游客 uid=0）
        $data['uid'] = $uid;
        $order = $this->repository->createBillOrder($data);

        // 模拟支付：需后台开启 pay_mock_open
        if ($data['pay_type'] === 'mock') {
            if (!systemConfig('pay_mock_open')) {
                return app('json')->fail('未开启模拟支付');
            }
            $this->repository->paySuccess($order['order_sn'], 'mock');
            return app('json')->success([
                'order_sn'  => $order['order_sn'],
                'pay_price' => $order['pay_price'],
                'mock_paid' => true,
                'guest'     => $uid <= 0,
            ]);
        }

        if ($data['pay_type'] === 'balance') {
            return app('json')->fail('余额支付暂未开通，请使用微信支付');
        }

        // 调起支付
        try {
            $payType = $data['pay_type'];
            $payService = new PayService($payType, [
                'order_sn' => $order['order_sn'],
                'pay_price' => (float)$data['pay_price'],
                'body'      => '到店买单',
                'attach'    => 'bill_pay',
            ], 'bill');

            $payResult = $payService->pay($user);

            return app('json')->success([
                'order_sn' => $order['order_sn'],
                'pay_price' => $order['pay_price'],
                'config' => $payResult['config'] ?? $payResult,
                'guest' => $uid <= 0,
            ]);
        } catch (\Exception $e) {
            return app('json')->fail('支付配置失败: ' . $e->getMessage());
        }
    }

    /**
     * 按消费金额匹配公益商品（售价升序）
     * GET /api/nearby/bill/welfare_products?pay_price=
     */
    public function welfareProducts()
    {
        $payPrice = round((float)$this->request->param('pay_price', 0), 2);
        if ($payPrice <= 0) {
            return app('json')->success(['list' => []]);
        }

        $merIds = Db::name('merchant')
            ->where('is_welfare_shop', 1)
            ->where('is_del', 0)
            ->where('status', 1)
            ->column('mer_id');
        if (!$merIds) {
            return app('json')->success(['list' => []]);
        }

        $list = Product::getDB()->alias('p')
            ->whereIn('p.mer_id', $merIds)
            ->where('p.is_del', 0)
            ->where('p.is_show', 1)
            ->where('p.status', 1)
            ->where('p.mer_status', 1)
            ->where('p.hit_amount', '>=', $payPrice)
            ->where('p.hit_amount', '>', 0)
            ->whereRaw('p.hit_amount <= p.price')
            ->field('p.product_id,p.mer_id,p.store_name,p.store_info,p.image,p.price,p.hit_amount,p.welfare_commission,p.sales')
            ->order('p.price ASC')
            ->order('p.product_id ASC')
            ->limit(50)
            ->select()
            ->toArray();

        // 附带默认 sku unique，便于立即购买
        if ($list) {
            $productIds = array_column($list, 'product_id');
            $attrs = Db::name('store_product_attr_value')
                ->whereIn('product_id', $productIds)
                ->where('stock', '>', 0)
                ->field('product_id,unique,price,stock')
                ->order('price ASC')
                ->select()
                ->toArray();
            $attrMap = [];
            foreach ($attrs as $attr) {
                $pid = (int)$attr['product_id'];
                if (!isset($attrMap[$pid])) {
                    $attrMap[$pid] = $attr;
                }
            }
            foreach ($list as &$item) {
                $attr = $attrMap[(int)$item['product_id']] ?? null;
                $item['product_attr_unique'] = $attr['unique'] ?? '';
                $item['stock'] = (int)($attr['stock'] ?? 0);
            }
            unset($item);
        }

        return app('json')->success(['list' => $list]);
    }

    /**
     * 网购享免单：校验并缓存上下文（需登录）
     * POST /api/nearby/bill/welfare_prepare
     */
    public function welfarePrepare(WelfareFreeOrderRepository $welfareRepo)
    {
        $uid = $this->currentUid();
        if ($uid <= 0) {
            return app('json')->fail('请先登录');
        }
        $scanMerId = (int)$this->request->param('scan_mer_id', 0);
        $billAmount = round((float)$this->request->param('bill_amount', 0), 2);
        $productId = (int)$this->request->param('product_id', 0);
        try {
            $ctx = $welfareRepo->buildContext($scanMerId, $billAmount, $productId);
            $welfareRepo->saveContext($uid, $ctx);
            return app('json')->success($ctx);
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    /**
     * 商家 APP 拉取待播报收款语音
     * GET /api/nearby/bill/voice_pending
     */
    public function voicePending()
    {
        $uid = 0;
        try {
            // 路由已强制登录；uid 为 Request macro，不能用 method_exists(isLogin)
            $uid = (int)$this->request->uid();
        } catch (\Throwable $e) {
            return app('json')->fail('请先登录');
        }
        if ($uid <= 0) {
            return app('json')->fail('请先登录');
        }
        $list = $this->repository->popVoicePending($uid);
        return app('json')->success($list);
    }

    /**
     * 重新调起支付
     * POST /api/nearby/bill/pay/:order_sn
     */
    public function pay($orderSn)
    {
        $order = $this->repository->getWhere(['order_sn' => $orderSn]);
        if (!$order) {
            return app('json')->fail('订单不存在');
        }

        if ($order['paid'] == 1) {
            return app('json')->fail('订单已支付');
        }

        $uid = $this->currentUid();
        $orderUid = (int)($order['uid'] ?? 0);
        // 登录用户订单：仅本人可付；游客订单：凭订单号即可（订单号足够难猜）
        if ($orderUid > 0 && $orderUid !== $uid) {
            return app('json')->fail('无权操作该订单');
        }

        if (in_array($order['pay_type'], $this->loginRequiredPayTypes, true) && $uid <= 0) {
            return app('json')->fail('请先登录后再支付');
        }

        try {
            $payService = new PayService($order['pay_type'], [
                'order_sn' => $order['order_sn'],
                'pay_price' => (float)$order['pay_price'],
                'body'      => '到店买单',
                'attach'    => 'bill_pay',
            ], 'bill');

            $user = $this->currentUser();
            $payResult = $payService->pay($user);

            return app('json')->success([
                'order_sn' => $order['order_sn'],
                'pay_price' => $order['pay_price'],
                'config' => $payResult['config'] ?? $payResult,
            ]);
        } catch (\Exception $e) {
            return app('json')->fail('支付配置失败: ' . $e->getMessage());
        }
    }

    protected function currentUid(): int
    {
        try {
            // isLogin/uid 是 Request macro，method_exists 检测不到，不能用 method_exists
            if ($this->request->isLogin()) {
                return (int)$this->request->uid();
            }
        } catch (\Throwable $e) {
        }
        return 0;
    }

    /**
     * @return User|null
     */
    protected function currentUser(): ?User
    {
        try {
            if ($this->request->isLogin()) {
                $user = $this->request->userInfo();
                return $user instanceof User ? $user : null;
            }
        } catch (\Throwable $e) {
        }
        return null;
    }
}
