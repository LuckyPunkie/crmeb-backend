<?php

namespace app\controller\api\store\merchant;

use think\App;
use crmeb\basic\BaseController;
use crmeb\services\PayService;
use app\common\repositories\system\merchant\MerchantLabelRepository;
use think\facade\Cache;
use think\facade\Log;

/**
 * 商家标签 - 小程序用户端（商户登录后操作）
 */
class MerchantLabelApi extends BaseController
{
    protected $repository;

    public function __construct(App $app, MerchantLabelRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    protected function getMerId(): int
    {
        $uid = (int)$this->request->uid();
        if (!$uid) return 0;
        $merId = \think\facade\Db::name('user')
            ->where('uid', $uid)
            ->where('mer_id', '>', 0)
            ->value('mer_id');
        return (int)($merId ?: 0);
    }

    /**
     * 标签列表（含本商户加入状态）
     * GET /api/mer/label/lst
     */
    public function lst()
    {
        $merId = $this->getMerId();
        if (!$merId) return app('json')->fail('您尚未入驻商户');
        return app('json')->success($this->repository->getLabelsWithStatus($merId));
    }

    /**
     * 申请加入标签
     * POST /api/mer/label/join/:id
     */
    public function join($id)
    {
        $merId = $this->getMerId();
        if (!$merId) return app('json')->fail('您尚未入驻商户');
        try {
            $result = $this->repository->joinLabel((int)$id, $merId);
            return app('json')->success($result);
        } catch (\InvalidArgumentException $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    /**
     * 创建保证金支付订单（JSAPI/小程序支付）
     * POST /api/mer/label/pay/:id
     */
    public function pay($id)
    {
        $merId = $this->getMerId();
        if (!$merId) return app('json')->fail('您尚未入驻商户');

        $payType = (string)$this->request->param('pay_type', 'routine');

        $label = \app\common\model\system\merchant\MerchantLabel::getDB()
            ->where('id', (int)$id)->find();
        if (!$label) return app('json')->fail('标签不存在');
        if (!$label['has_deposit']) return app('json')->fail('该标签无需缴纳保证金');

        $store = \app\common\model\system\merchant\MerchantLabelStore::getDB()
            ->where('label_id', (int)$id)->where('mer_id', $merId)->find();
        if (!$store) return app('json')->fail('请先申请加入该标签');
        if ((int)$store['is_margin'] !== 1) return app('json')->fail('无需缴纳或已缴纳');

        $orderSn  = 'LM' . $merId . '_' . $id . '_' . date('YmdHis') . rand(100, 999);
        $payPrice = (float)$label['deposit_amount'];

        // 用 cache 暂存 order_sn → {merId, labelId}，60 分钟有效
        Cache::set('label_margin_order:' . $orderSn, ['mer_id' => $merId, 'label_id' => (int)$id], 3600);

        if ($payType === 'mock') {
            if (!systemConfig('pay_mock_open')) {
                return app('json')->fail('未开启模拟支付');
            }
            $this->doPaySuccess($orderSn);
            return app('json')->success(['order_sn' => $orderSn, 'mock_paid' => true]);
        }

        if ($payType === 'balance') {
            if (!systemConfig('yue_pay_status')) {
                return app('json')->fail('余额支付未开启');
            }
            $uid = (int)$this->request->uid();
            $user = \think\facade\Db::name('user')->where('uid', $uid)->find();
            if (!$user || (float)$user['now_money'] < $payPrice) {
                return app('json')->fail('余额不足');
            }
            \think\facade\Db::name('user')->where('uid', $uid)->dec('now_money', $payPrice)->update();
            $this->doPaySuccess($orderSn);
            return app('json')->success(['order_sn' => $orderSn, 'balance_paid' => true]);
        }

        try {
            $payService = new PayService($payType, [
                'order_sn'  => $orderSn,
                'pay_price' => $payPrice,
                'body'      => '商家标签保证金-' . $label['label_name'],
                'attach'    => 'label_margin',
            ], 'label_margin');

            $user      = $this->request->userInfo();
            $payResult = $payService->pay($user);

            return app('json')->success([
                'order_sn'  => $orderSn,
                'pay_price' => $payPrice,
                'config'    => $payResult['config'] ?? $payResult,
            ]);
        } catch (\Exception $e) {
            Log::error('MerchantLabel pay failed: ' . $e->getMessage());
            return app('json')->fail('支付配置失败: ' . $e->getMessage());
        }
    }

    /**
     * 前端支付成功后主动确认（轮询备用，notify 回调也会触发）
     * POST /api/mer/label/confirm/:order_sn
     */
    public function confirm($orderSn)
    {
        $info = Cache::get('label_margin_order:' . $orderSn);
        if (!$info) return app('json')->fail('订单不存在或已处理');

        $merId   = (int)$info['mer_id'];
        $labelId = (int)$info['label_id'];

        // 验证当前用户就是该商户
        if ($this->getMerId() !== $merId) return app('json')->fail('无权操作');

        $this->doPaySuccess($orderSn);
        return app('json')->success('缴纳成功');
    }

    public function doPaySuccess(string $orderSn): void
    {
        $info = Cache::get('label_margin_order:' . $orderSn);
        if (!$info) return;

        \app\common\model\system\merchant\MerchantLabelStore::getDB()
            ->where('label_id', (int)$info['label_id'])
            ->where('mer_id', (int)$info['mer_id'])
            ->where('is_margin', 1)
            ->update(['is_margin' => 10, 'paid_deposit' => 1]);

        Cache::delete('label_margin_order:' . $orderSn);
    }
}
