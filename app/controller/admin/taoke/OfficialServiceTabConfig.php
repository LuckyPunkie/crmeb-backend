<?php

namespace app\controller\admin\taoke;

use app\common\model\system\config\SystemConfigValue;
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

    /** 逛网店入口：读取当前开关值（与 official Tab 表 shop_street 行同步） */
    public function getShopStreetSwitch()
    {
        $this->repository->ensureShopStreetTab(ServiceTabConfigRepository::CHANNEL_OFFICIAL);
        $tab = $this->repository->listAll(ServiceTabConfigRepository::CHANNEL_OFFICIAL);
        foreach ($tab as $row) {
            if (($row['tab_key'] ?? '') === ServiceTabConfigRepository::TAB_KEY_SHOP_STREET) {
                return app('json')->success([
                    'shop_street_switch' => (int) ($row['status'] ?? 0),
                ]);
            }
        }
        $row = SystemConfigValue::where('config_key', 'shop_street_switch')
            ->where('mer_id', 0)
            ->find();
        return app('json')->success([
            'shop_street_switch' => (int) ($row ? $row->value : 0),
        ]);
    }

    /** 逛网店入口：保存开关值（写入 Tab 表并同步 /api/config） */
    public function saveShopStreetSwitch()
    {
        $value = (int) $this->request->param('shop_street_switch', 0) ? 1 : 0;
        $this->repository->setShopStreetEnabled($value);
        return app('json')->success([
            'shop_street_switch' => $value,
        ]);
    }
}
