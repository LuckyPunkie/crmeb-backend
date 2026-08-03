<?php
// +----------------------------------------------------------------------
// | 平台后台 - AI 点餐费率 / 商家调账
// +----------------------------------------------------------------------

namespace app\controller\admin\system\aiOrder;

use app\common\model\store\aiOrder\AiBalanceLog;
use app\common\repositories\store\aiOrder\AiOrderBillingRepository;
use crmeb\basic\BaseController;
use think\App;

class AiOrderAdmin extends BaseController
{
    protected $billing;

    public function __construct(App $app, AiOrderBillingRepository $billing)
    {
        parent::__construct($app);
        $this->billing = $billing;
    }

    /**
     * GET /sys/ai_order/overview
     */
    public function overview()
    {
        return app('json')->success([
            'platform_open' => $this->billing->platformOpen() ? 1 : 0,
            'rate_per_1k' => $this->billing->ratePer1k(),
            'min_balance' => $this->billing->minBalance(),
            'doubao_configured' => (config('ai_order.doubao_app_id') && config('ai_order.doubao_access_token')) ? 1 : 0,
        ]);
    }

    /**
     * POST /sys/ai_order/adjust
     * amount: 正数充值，负数调减
     */
    public function adjust()
    {
        $merId = (int)$this->request->param('mer_id', 0);
        $amount = (float)$this->request->param('amount', 0);
        $remark = trim((string)$this->request->param('remark', ''));
        $adminId = (int)$this->request->adminId();
        $type = $amount >= 0 ? AiBalanceLog::TYPE_RECHARGE : AiBalanceLog::TYPE_ADJUST;
        try {
            $balance = $this->billing->adjustBalance($merId, $amount, $remark, $adminId, $type);
            return app('json')->success(['ai_balance' => $balance]);
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage() ?: '调账失败');
        }
    }

    /**
     * GET /sys/ai_order/logs?mer_id=
     */
    public function logs()
    {
        $merId = (int)$this->request->param('mer_id', 0);
        if ($merId <= 0) {
            return app('json')->fail('请指定商户');
        }
        $page = max(1, (int)$this->request->param('page', 1));
        $limit = min(50, max(1, (int)$this->request->param('limit', 20)));
        return app('json')->success($this->billing->logs($merId, $page, $limit));
    }
}
