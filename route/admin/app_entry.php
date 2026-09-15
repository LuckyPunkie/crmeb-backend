<?php

use think\facade\Route;
use app\common\middleware\AdminAuthMiddleware;
use app\common\middleware\AdminTokenMiddleware;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;

Route::group(function () {

    Route::get('app_entry', 'admin.system.AppEntry/index')
        ->name('adminAppEntryGet')
        ->option(['_alias' => 'App入口配置读取', '_auth' => true, '_path' => '/setting/app_entry']);

    Route::post('app_entry', 'admin.system.AppEntry/save')
        ->name('adminAppEntrySave')
        ->option(['_alias' => 'App入口配置保存', '_auth' => true, '_path' => '/setting/app_entry']);

    Route::post('app_entry/init', 'admin.system.AppEntry/init')
        ->name('adminAppEntryInit')
        ->option(['_alias' => 'App入口一键初始化', '_auth' => true, '_path' => '/setting/app_entry']);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
