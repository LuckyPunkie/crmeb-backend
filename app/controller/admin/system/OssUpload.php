<?php

namespace app\controller\admin\system;

use crmeb\basic\BaseController;
use crmeb\services\oss\OssDirectService;
use think\facade\Log;

/**
 * 平台后台直传 OSS 签名；pid 传素材库分类时，回调会同时写素材库
 */
class OssUpload extends BaseController
{
    public function sign()
    {
        $file = $this->request->params(['kind', 'ext', 'size', 'name', 'pid']);
        try {
            return app('json')->success(OssDirectService::sign(OssDirectService::CLIENT_SYS, (int)$this->request->adminId(), 0, $file));
        } catch (\RuntimeException $e) {
            Log::error('OSS 直传签名失败(sys): ' . $e->getMessage());
            return app('json')->fail($e->getMessage());
        }
    }
}
