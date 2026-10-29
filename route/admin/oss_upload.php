<?php

use think\facade\Route;
use app\common\middleware\AdminAuthMiddleware;
use app\common\middleware\AdminTokenMiddleware;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;

// 直传 OSS 签名：登录即可用，不挂权限菜单
Route::group(function () {
    Route::post('oss/sign', 'admin.system.OssUpload/sign')
        ->name('systemOssDirectSign')
        ->option(['_alias' => '直传OSS签名', '_auth' => false]);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
