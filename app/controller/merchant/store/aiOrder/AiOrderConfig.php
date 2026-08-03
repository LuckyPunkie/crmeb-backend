<?php
// +----------------------------------------------------------------------
// | 商户后台 - AI 点餐配置 / 余额
// +----------------------------------------------------------------------

namespace app\controller\merchant\store\aiOrder;

use app\common\repositories\store\aiOrder\AiOrderBillingRepository;
use app\common\repositories\store\aiOrder\AiOrderConfigRepository;
use crmeb\basic\BaseController;
use think\App;

class AiOrderConfig extends BaseController
{
    protected $configRepo;
    protected $billing;

    public function __construct(App $app, AiOrderConfigRepository $configRepo, AiOrderBillingRepository $billing)
    {
        parent::__construct($app);
        $this->configRepo = $configRepo;
        $this->billing = $billing;
    }

    public function get()
    {
        return app('json')->success($this->configRepo->getConfig((int)$this->request->merId()));
    }

    public function save()
    {
        $data = $this->request->params(['enable', 'dialect', 'style', 'avatar']);
        $row = $this->configRepo->saveConfig((int)$this->request->merId(), $data);
        return app('json')->success($row);
    }

    public function balance()
    {
        $merId = (int)$this->request->merId();
        return app('json')->success([
            'ai_balance' => $this->billing->getBalance($merId),
            'rate_per_1k' => $this->billing->ratePer1k(),
            'min_balance' => $this->billing->minBalance(),
        ]);
    }

    public function logs()
    {
        $merId = (int)$this->request->merId();
        $page = max(1, (int)$this->request->param('page', 1));
        $limit = min(50, max(1, (int)$this->request->param('limit', 20)));
        return app('json')->success($this->billing->logs($merId, $page, $limit));
    }
}
