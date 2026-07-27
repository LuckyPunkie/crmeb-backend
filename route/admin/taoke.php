<?php

use think\facade\Route;
use app\common\middleware\AdminAuthMiddleware;
use app\common\middleware\AdminTokenMiddleware;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;

Route::group(function () {

    Route::get('service_brand_tab/config', 'admin.taoke.ServiceBrandTab/index')
        ->name('serviceBrandTabIndex')
        ->option(['_alias' => '服务页品牌类别配置', '_auth' => false, '_path' => '/serviceBrandTab/index']);

    Route::post('service_brand_tab/config/save', 'admin.taoke.ServiceBrandTab/save')
        ->name('serviceBrandTabSave')
        ->option(['_alias' => '保存服务页品牌类别', '_auth' => true, '_path' => '/serviceBrandTab/index']);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
