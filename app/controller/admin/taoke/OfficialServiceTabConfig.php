<?php

namespace app\controller\admin\taoke;

use app\common\repositories\taoke\ServiceTabConfigRepository;
use crmeb\basic\BaseController;
use think\App;

/**
 * 平台直连（official）专用服务页 Tab，与现网 legacy Tab 互不影响。
 */
class OfficialServiceTabConfig extends BaseController
{
    protected ServiceTabConfigRepository $repository;

    public function __construct(App $app, ServiceTabConfigRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function index()
    {
        return app('json')->success([
            'list' => $this->repository->listAll(ServiceTabConfigRepository::CHANNEL_OFFICIAL),
        ]);
    }

    public function save()
    {
        $data = [
            'id'       => (int) $this->request->param('id', 0),
            'tab_key'  => (string) $this->request->param('tab_key', ''),
            'tab_type' => (int) $this->request->param('tab_type', ServiceTabConfigRepository::TYPE_CUSTOM),
            'name'     => (string) $this->request->param('name', ''),
            'brands'   => $this->request->param('brands', []),
            'status'   => (int) $this->request->param('status', 1),
            'sort'     => (int) $this->request->param('sort', 0),
        ];
        if (!is_array($data['brands'])) {
            $data['brands'] = [];
        }
        $row = $this->repository->saveConfig($data, ServiceTabConfigRepository::CHANNEL_OFFICIAL);
        return app('json')->success($row);
    }

    public function delete()
    {
        $id = (int) $this->request->param('id', 0);
        $this->repository->deleteConfig($id, ServiceTabConfigRepository::CHANNEL_OFFICIAL);
        return app('json')->success('删除成功');
    }
}
