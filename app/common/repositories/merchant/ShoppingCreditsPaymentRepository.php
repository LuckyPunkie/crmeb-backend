<?php

namespace app\common\repositories\merchant;

use app\common\dao\merchant\MerchantMemberDao;
use app\common\dao\system\merchant\MerchantDao;
use app\common\dao\merchant\ShoppingCreditsOrderDao;
use app\common\dao\merchant\ShoppingCreditsPackageDao;
use app\common\dao\user\UserDao;
use app\common\dao\user\UserShoppingCreditsRecordDao;
use app\common\repositories\BaseRepository;
use crmeb\services\pay\dto\PayOrderDto;
use crmeb\services\pay\PayAttach;
use crmeb\services\pay\PayServices;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 购物金充值订单与支付处理。
 */
class ShoppingCreditsPaymentRepository extends BaseRepository
{
    private ShoppingCreditsPackageDao $packageDao;
    private MerchantMemberDao $memberDao;
    private UserDao $userDao;
    private MerchantDao $merchantDao;
    private UserShoppingCreditsRecordDao $shoppingCreditsRecordDao;

    public function __construct(
        ShoppingCreditsOrderDao $dao,
        ShoppingCreditsPackageDao $packageDao,
        MerchantMemberDao $memberDao,
        UserDao $userDao,
        MerchantDao $merchantDao,
        UserShoppingCreditsRecordDao $shoppingCreditsRecordDao
    ) {
        $this->dao = $dao;
        $this->packageDao = $packageDao;
        $this->memberDao = $memberDao;
        $this->userDao = $userDao;
        $this->merchantDao = $merchantDao;
        $this->shoppingCreditsRecordDao = $shoppingCreditsRecordDao;
    }

    public function createRechargeOrder(int $uid, int $packageId, string $type, string $returnUrl = '', bool $isApp = false): array
    {
        $package = $this->packageDao->search()->where('id', $packageId)->where('show_status', 1)->find();
        if (!$package) {
            throw new ValidateException('购物金套餐不存在或已下架');
        }

        if (!$this->merchantIsAvailable((int)$package->mer_id)) {
            throw new ValidateException('店铺暂不可用');
        }
        if (!$this->memberDao->search()
            ->where('uid', $uid)
            ->where('mer_id', $package->mer_id)
            ->where('is_logoff', 0)
            ->find()) {
            throw new ValidateException('请先加入店铺会员');
        }

        $user = $this->userDao->query([])->where('uid', $uid)->find();
        if (!$user) {
            throw new ValidateException('用户不存在');
        }

        $orderNo = $this->makeOrderNo('SC', $uid);
        $order = $this->dao->create([
            'mer_id' => $package->mer_id,
            'uid' => $uid,
            'order_no' => $orderNo,
            'recharge_amount' => $package->recharge_amount,
            'gift_amount' => $package->gift_amount,
            'pay_type' => $type,
            'pay_channel' => $isApp ? 'app' : $type,
            'paid' => 0,
            'out_trade_no' => $orderNo,
        ]);

        if (in_array($type, ['weixin', 'alipay'], true) && $isApp) {
            $type .= 'App';
        }
        $dto = new PayOrderDto(str_starts_with($type, 'alipay') ? 'alipay' : 'weixin', $type);
        $dto->orderSn = $orderNo;
        $dto->payPrice = (string)$order->recharge_amount;
        $dto->body = '店铺购物金充值';
        $dto->attach = PayAttach::MERCHANT_SHOPPING_CREDITS;
        $dto->returnUrl = str_starts_with($type, 'alipay') ? $returnUrl : '';
        $dto->wechatUserId = (int)$user->wechat_user_id;
        $dto->orderId = (string)$order->id;
        $dto->isApp = $isApp;

        $config = app()->make(PayServices::class)->pay($dto);

        return [
            'status' => $type,
            'result' => array_merge($config, [
                'order_no' => $orderNo,
                'order_id' => $order->id,
                'type' => $dto->payType,
            ]),
        ];
    }

    public function rechargePaySuccess(array $data): void
    {
        $orderNo = (string)$data['order_sn'];
        Db::transaction(function () use ($orderNo, $data) {
            $order = $this->dao->search()->where('order_no', $orderNo)->lock(true)->find();
            if (!$order || (int)$order->paid) {
                return;
            }

            $transactionId = (string)($data['data']['transaction_id'] ?? $data['data']['trade_no'] ?? '');
            $order->paid = 1;
            $order->pay_time = date('Y-m-d H:i:s');
            $order->out_trade_no = $transactionId ?: $order->out_trade_no;
            $order->save();

            $creditsRepository = app()->make(ShoppingCreditsRepository::class);
            $creditsRepository->changeMerchantMoney(
                (int)$order->mer_id,
                (string)$order->recharge_amount,
                $orderNo,
                'recharge',
                '购物金充值本金到账'
            );
            $creditsRepository->changeBalance(
                (int)$order->uid,
                (int)$order->mer_id,
                (string)$order->recharge_amount,
                (string)$order->gift_amount,
                1,
                $orderNo,
                'recharge',
                '购物金充值'
            );
        });
    }

    /**
     * 分页查询用户购物金充值及商户手动调整流水。
     * 实际充值关联充值订单补充支付与退款信息，手动调整流水不可退款。
     * @param string $date 记录时间范围
     */
    public function userRechargeOrders(int $uid, int $merId, int $page, int $limit, string $date = ''): array
    {
        $query = $this->shoppingCreditsRecordDao->search()->alias('r')
            ->leftJoin('shopping_credits_order o', "o.order_no = r.link_no AND r.link_type = 'recharge'")
            ->where('r.uid', $uid)
            ->where('r.mer_id', $merId)
            ->whereIn('r.link_type', ['recharge', 'merchant_adjust'])
            ->when($date !== '', function ($query) use ($date) {
                getModelTime($query, $date, 'r.create_time');
            })
            ->field([
                'r.id',
                'r.mer_id',
                'r.uid',
                'r.link_no order_no',
                Db::raw('CASE WHEN r.type = 2 THEN -r.recharge_amount ELSE r.recharge_amount END AS recharge_amount'),
                Db::raw('CASE WHEN r.type = 2 THEN -r.gift_amount ELSE r.gift_amount END AS gift_amount'),
                'o.pay_type',
                'o.pay_channel',
                Db::raw('1 AS paid'),
                Db::raw('CASE WHEN r.link_type = \'recharge\' THEN o.pay_time ELSE r.create_time END AS pay_time'),
                'o.out_trade_no',
                'r.create_time',
                'r.update_time',
                Db::raw('CASE WHEN r.link_type = \'recharge\' THEN COALESCE(o.refund_status, 0) ELSE -1 END AS refund_status'),
                'r.link_type',
                'r.type',
                'r.title',
                'r.balance_recharge_amount',
                'r.balance_gift_amount',
                'r.remark',
            ])
            ->order('r.create_time DESC,r.id DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();
        return compact('count', 'list');
    }

    private function merchantIsAvailable(int $merId): bool
    {
        return $this->merchantDao->query([])
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->where('status', 1)
            ->where('mer_state', 1)
            ->count() > 0;
    }

    private function makeOrderNo(string $prefix, int $uid): string
    {
        return $prefix . date('YmdHis') . $uid . random_int(1000, 9999);
    }
}
