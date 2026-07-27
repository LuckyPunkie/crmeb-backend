<?php

namespace app\controller\admin\commission;

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

    public function index(): \think\response\Json
    {
        $data = $this->repository->getConfig();
        return app('json')->success($data);
    }

    public function save(): \think\response\Json
    {
        $redRate  = (float)$this->request->param('red_rate', 0);
        $paidRate = (float)$this->request->param('paid_rate', 0);
        $remark   = (string)$this->request->param('remark', '');

        $adminInfo = $this->request->adminInfo();
        $operator  = $adminInfo['real_name'] ?? ($adminInfo['account'] ?? '管理员');

        $this->repository->saveConfig($redRate, $paidRate, $operator, $remark);

        return app('json')->success('保存成功');
    }
}
