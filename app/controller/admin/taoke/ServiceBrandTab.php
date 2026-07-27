<?php

namespace app\controller\admin\taoke;

use app\common\repositories\taoke\ServiceBrandTabRepository;
use crmeb\basic\BaseController;
use think\App;

class ServiceBrandTab extends BaseController
{
    protected ServiceBrandTabRepository $repository;

    public function __construct(App $app, ServiceBrandTabRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function index()
    {
        return app('json')->success($this->repository->getConfig());
    }

    public function save()
    {
        $name   = (string)$this->request->param('name', '');
        $brands = $this->request->param('brands', []);
        $status = (int)$this->request->param('status', 1);
        if (!is_array($brands)) {
            $brands = [];
        }
        $data = $this->repository->saveConfig($name, $brands, $status);
        return app('json')->success($data);
    }
}
