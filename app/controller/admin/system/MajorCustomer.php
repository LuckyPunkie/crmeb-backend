<?php

namespace app\controller\admin\system;

use app\common\repositories\major_customer\MajorCustomerRepository;
use crmeb\basic\BaseController;
use think\App;

class MajorCustomer extends BaseController
{
    /** @var MajorCustomerRepository */
    protected $repository;

    public function __construct(App $app, MajorCustomerRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /** GET /sys/major_customer/config */
    public function config()
    {
        return app('json')->success($this->repository->getPlatformConfig());
    }

    /** POST /sys/major_customer/config */
    public function saveConfig()
    {
        $data = $this->request->params([
            'major_customer_commission_rate',
            'major_customer_balance_low_ratio',
            'major_customer_balance_cap_multiple',
        ]);
        return app('json')->success($this->repository->savePlatformConfig($data));
    }

    /** GET /sys/major_customer/virtual_recharge */
    public function virtualRechargeList()
    {
        [$page, $limit] = $this->getPage();
        $merId = (int)$this->request->param('mer_id', 0);
        $where = $merId > 0 ? ['mer_id' => $merId] : [];
        return app('json')->success($this->repository->listAdminVirtualRecharges($page, $limit, $where));
    }
}
