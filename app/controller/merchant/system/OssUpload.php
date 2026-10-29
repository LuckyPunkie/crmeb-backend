<?php

namespace app\controller\merchant\system;

use crmeb\basic\BaseController;
use crmeb\services\oss\OssDirectService;
use think\facade\Log;

/**
 * 商家后台直传 OSS 签名；pid 传素材库分类时，回调会同时写本商户素材库
 */
class OssUpload extends BaseController
{
    public function sign()
    {
        $file = $this->request->params(['kind', 'ext', 'size', 'name', 'pid']);
        try {
            return app('json')->success(OssDirectService::sign(OssDirectService::CLIENT_MER, (int)$this->request->adminId(), (int)$this->request->merId(), $file));
        } catch (\RuntimeException $e) {
            Log::error('OSS 直传签名失败(mer): ' . $e->getMessage());
            return app('json')->fail($e->getMessage());
        }
    }
}
