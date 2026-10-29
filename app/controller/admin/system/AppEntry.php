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

    /**
     * 聚推客活动列表（每日好券添加入口）
     * GET /sys/app_entry/jtk_activities
     */
    public function jtkActivities()
    {
        $keyword = (string)$this->request->param('keyword', '');
        $data = $this->repository->listJutuikeActivities($keyword);
        // with_modes=1：附带微信小程序 / App 打开方式（首次需逐个转链，较慢）
        if ((int)$this->request->param('with_modes/d', 0) === 1 && !empty($data['list'])) {
            $modes = $this->repository->jutuikeActivityOpenModes(array_column($data['list'], 'act_id'));
            foreach ($data['list'] as &$it) {
                $it = array_merge($it, $modes[$it['act_id']] ?? []);
            }
            unset($it);
        }
        return app('json')->success($data);
    }
}
