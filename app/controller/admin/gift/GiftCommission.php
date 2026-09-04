<?php

namespace app\controller\admin\gift;

use app\common\repositories\gift\GiftRepository;
use crmeb\basic\BaseController;
use think\App;

class GiftCommission extends BaseController
{
    /** @var GiftRepository */
    protected $repository;

    public function __construct(App $app, GiftRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function index()
    {
        return app('json')->success($this->repository->getCommissionConfig());
    }

    public function save()
    {
        $chatRate = (float)$this->request->param('chat_gift_rate', 0);
        $profileRate = (float)$this->request->param('profile_gift_rate', 0);
        $adminId = (int)($this->request->adminId() ?? 0);
        $this->repository->saveCommissionConfig($chatRate, $profileRate, $adminId, (string)$this->request->ip());
        return app('json')->success('保存成功，立即生效');
    }
}
