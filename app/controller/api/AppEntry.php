<?php

namespace app\controller\api;

use app\common\repositories\system\AppEntryRepository;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

class AppEntry extends BaseController
{
    /** @var AppEntryRepository */
    protected $repository;

    public function __construct(App $app, AppEntryRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function config()
    {
        $appVersion = trim((string)$this->request->param('app_version', ''));
        $platform = trim((string)$this->request->param('client_platform', ''));
        if ($appVersion === '') {
            throw new ValidateException('缺少 app_version');
        }
        if (!in_array($platform, ['ios', 'android', 'routine'], true)) {
            throw new ValidateException('client_platform 须为 ios/android/routine');
        }
        try {
            $data = $this->repository->clientConfig($platform, $appVersion);
        } catch (ValidateException $e) {
            return app('json')->fail($e->getMessage());
        }
        return app('json')->success($data);
    }
}
