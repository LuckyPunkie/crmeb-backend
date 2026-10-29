<?php

use think\facade\Route;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;
use app\common\middleware\MerchantAuthMiddleware;
use app\common\middleware\MerchantTokenMiddleware;
use app\common\middleware\MerchantCheckBaseInfoMiddleware;

// 直传 OSS 签名：登录即可用，不挂权限菜单
Route::group(function () {
    Route::post('oss/sign', 'merchant.system.OssUpload/sign')
        ->name('merchantOssDirectSign')
        ->option(['_alias' => '直传OSS签名', '_auth' => false]);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class);
