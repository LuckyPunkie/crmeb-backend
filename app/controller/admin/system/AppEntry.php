<?php

namespace app\controller\admin\system;

use app\common\repositories\system\AppEntryRepository;
use crmeb\basic\BaseController;
use think\App;

class AppEntry extends BaseController
{
    /** @var AppEntryRepository */
    protected $repository;

    public function __construct(App $app, AppEntryRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function index()
    {
        return app('json')->success($this->repository->getAdminPayload());
    }

    public function save()
    {
        $payload = $this->request->param();
        $this->repository->saveAdminPayload($payload);
        return app('json')->success('保存成功');
    }

    public function init()
    {
        $force = (bool)$this->request->param('force/d', 0);
        $result = $this->repository->initDefaults($force);
        return app('json')->success($result);
    }
}
