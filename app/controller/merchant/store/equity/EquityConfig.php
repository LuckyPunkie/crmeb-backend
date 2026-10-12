<?php

namespace app\controller\merchant\store\equity;

use app\common\repositories\store\equity\EquityConfigRepository;
use crmeb\basic\BaseController;
use think\App;

class EquityConfig extends BaseController
{
    protected $repository;

    public function __construct(App $app, EquityConfigRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function config()
    {
        $merId = (int)$this->request->merId();
        return app('json')->success($this->repository->getConfig($merId));
    }

    public function saveConfig()
    {
        $merId = (int)$this->request->merId();
        $data = $this->request->params([
            ['consume_equity_percent', 0],
            ['target_equity_amount', 0],
        ]);
        // 旧版页面随表单提交 enabled；新版不传，开关走 saveSwitch
        if ($this->request->has('enabled')) {
            $data['enabled'] = $this->request->param('enabled');
        }
        try {
            $res = $this->repository->saveConfig($merId, $data);
            return app('json')->success($res);
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function saveSwitch()
    {
        $merId = (int)$this->request->merId();
        $type = (string)$this->request->param('type', 'consume');
        $value = (int)$this->request->param('value', 0);
        if (!in_array($type, ['consume', 'invest'], true)) {
            return app('json')->fail('参数错误');
        }
        try {
            return app('json')->success($this->repository->saveSwitch($merId, $type, $value));
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function progress()
    {
        $merId = (int)$this->request->merId();
        return app('json')->success($this->repository->getProgress($merId));
    }

    public function transactions()
    {
        $merId = (int)$this->request->merId();
        return app('json')->success($this->repository->recentTransactions($merId, 20));
    }
}
