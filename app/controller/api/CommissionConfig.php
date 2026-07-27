<?php

namespace app\controller\api;

use app\common\repositories\commission\CommissionConfigRepository;
use crmeb\basic\BaseController;
use think\App;

class CommissionConfig extends BaseController
{
    protected CommissionConfigRepository $repository;

    public function __construct(App $app, CommissionConfigRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function rates(): \think\response\Json
    {
        $config = $this->repository->getConfig();
        return app('json')->success([
            'red_rate'  => (float)$config['red']['rate'],
            'paid_rate' => (float)$config['paid']['rate'],
        ]);
    }
}
