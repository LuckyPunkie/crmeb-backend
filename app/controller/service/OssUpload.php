<?php

namespace app\controller\service;

use crmeb\basic\BaseController;
use crmeb\services\oss\OssDirectService;
use think\facade\Log;

/**
 * 客服端直传 OSS 签名（聊天发图）
 */
class OssUpload extends BaseController
{
    public function sign()
    {
        $file = $this->request->params(['kind', 'ext', 'size', 'name']);
        try {
            return app('json')->success(OssDirectService::sign(OssDirectService::CLIENT_SER, (int)$this->request->adminId(), (int)$this->request->merchantId(), $file));
        } catch (\RuntimeException $e) {
            Log::error('OSS 直传签名失败(ser): ' . $e->getMessage());
            return app('json')->fail($e->getMessage());
        }
    }
}
