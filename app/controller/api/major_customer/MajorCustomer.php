<?php

namespace app\controller\api\major_customer;

use app\common\repositories\major_customer\MajorCustomerRepository;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

class MajorCustomer extends BaseController
{
    /** @var MajorCustomerRepository */
    protected $repository;

    public function __construct(App $app, MajorCustomerRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    protected function requireMerId(): int
    {
        $uid = (int)$this->request->uid();
        $merId = $this->repository->resolveMerIdByUid($uid);
        if ($merId <= 0) {
            throw new ValidateException('您尚未入驻商户');
        }
        return $merId;
    }

    public function config()
    {
        $merId = $this->requireMerId();
        return app('json')->success($this->repository->getConfigPayload($merId));
    }

    public function switch()
    {
        $merId = $this->requireMerId();
        $status = (int)$this->request->post('status', 0);
        return app('json')->success($this->repository->saveSwitch($merId, $status));
    }

    public function shareSwitch()
    {
        $merId = $this->requireMerId();
        $status = (int)$this->request->post('status', 0);
        return app('json')->success($this->repository->saveShareSwitch($merId, $status));
    }

    public function referrer()
    {
        $merId = $this->requireMerId();
        $phone = (string)$this->request->post('phone', '');
        return app('json')->success($this->repository->saveReferrerPhone($merId, $phone));
    }

    public function plans()
    {
        $merId = $this->requireMerId();
        $plans = $this->request->post('plans', []);
        if (!is_array($plans)) {
            return app('json')->fail('方案格式错误');
        }
        return app('json')->success($this->repository->savePlans($merId, $plans));
    }

    public function prestore()
    {
        $merId = $this->requireMerId();
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->listPrestoreForMerchant($merId, $page, $limit));
    }

    public function commissionSummary()
    {
        $uid = (int)$this->request->uid();
        return app('json')->success($this->repository->getCommissionSummary($uid));
    }

    public function commissionList()
    {
        $uid = (int)$this->request->uid();
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getCommissionList($uid, $page, $limit));
    }

    public function rebateList()
    {
        $uid = (int)$this->request->uid();
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getRebateList($uid, $page, $limit));
    }
}
