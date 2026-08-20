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
            ['enabled', 1],
            ['consume_equity_percent', 0],
            ['target_equity_amount', 0],
        ]);
        try {
            $res = $this->repository->saveConfig($merId, $data);
            return app('json')->success($res);
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
