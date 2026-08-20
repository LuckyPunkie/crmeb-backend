<?php

namespace app\controller\api\store\equity;

use app\common\repositories\store\equity\EquityGrantRepository;
use app\common\repositories\store\equity\EquityProjectRepository;
use crmeb\basic\BaseController;
use crmeb\services\PayService;
use app\common\model\user\User;
use think\App;

class EquityApi extends BaseController
{
    protected $repository;
    protected $grant;

    public function __construct(App $app, EquityProjectRepository $repository, EquityGrantRepository $grant)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->grant = $grant;
    }

    protected function currentUid(): int
    {
        try {
            if ($this->request->isLogin()) {
                return (int)$this->request->uid();
            }
        } catch (\Throwable $e) {
        }
        return 0;
    }

    public function myProjects()
    {
        $uid = $this->currentUid();
        if ($uid <= 0) {
            return app('json')->fail('请登录');
        }
        $params = $this->request->params([
            ['tab', 'all'],
            ['sort', 'consume_time'],
            ['order', 'desc'],
        ]);
        return app('json')->success($this->repository->myProjects($uid, $params));
    }

    public function detail($id)
    {
        $uid = $this->currentUid();
        try {
            return app('json')->success($this->repository->projectDetail((int)$id, $uid));
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function shopModule($merId)
    {
        $uid = $this->currentUid();
        return app('json')->success($this->repository->shopInvestModule((int)$merId, $uid));
    }

    public function myTransactions($id)
    {
        $uid = $this->currentUid();
        if ($uid <= 0) {
            return app('json')->fail('请登录');
        }
        return app('json')->success($this->repository->myTransactions((int)$id, $uid));
    }

    public function invest($id)
    {
        $uid = $this->currentUid();
        if ($uid <= 0) {
            return app('json')->fail('请先登录');
        }
        $amount = $this->request->param('amount/f', 0);
        $agree = (int)$this->request->param('agree', 0);
        $payType = (string)$this->request->param('pay_type', 'weixin');
        if (!$agree) {
            return app('json')->fail('请勾选消费送股/入股协议');
        }
        try {
            $order = $this->grant->createInvestOrder((int)$id, $uid, $amount);
            if ($payType === 'mock') {
                if (!systemConfig('pay_mock_open')) {
                    return app('json')->fail('未开启模拟支付');
                }
                $this->grant->investPaySuccess($order['order_sn'], 'mock');
                return app('json')->success([
                    'order_sn' => $order['order_sn'],
                    'amount' => $order['amount'],
                    'mock_paid' => true,
                ]);
            }
            if ($payType === 'balance') {
                return app('json')->fail('余额支付暂未开通，请使用微信支付');
            }
            $user = User::getDB()->where('uid', $uid)->find();
            $payService = new PayService($payType, [
                'order_sn' => $order['order_sn'],
                'pay_price' => (float)$order['amount'],
                'body' => '充值入股',
                'attach' => 'equity_invest',
            ], 'equity_invest');
            $payResult = $payService->pay($user);
            return app('json')->success([
                'order_sn' => $order['order_sn'],
                'amount' => $order['amount'],
                'config' => $payResult['config'] ?? $payResult,
            ]);
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function investRefund($id)
    {
        $uid = $this->currentUid();
        if ($uid <= 0) {
            return app('json')->fail('请登录');
        }
        $reason = (string)$this->request->param('reason', '');
        try {
            $res = $this->grant->applyInvestRefund((int)$id, $uid, $reason);
            return app('json')->success($res);
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function dividendNotices($id)
    {
        return app('json')->success($this->repository->dividendNotices((int)$id));
    }

    public function financialReport($id)
    {
        $uid = $this->currentUid();
        if ($uid <= 0) {
            return app('json')->fail('请登录');
        }
        $start = (string)$this->request->param('start', date('Y-m-d', strtotime('-30 days')));
        $end = (string)$this->request->param('end', date('Y-m-d'));
        try {
            return app('json')->success($this->repository->financialReport((int)$id, $uid, $start, $end));
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function myDividends()
    {
        $uid = $this->currentUid();
        if ($uid <= 0) {
            return app('json')->fail('请登录');
        }
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->myDividends($uid, $page, $limit));
    }
}
